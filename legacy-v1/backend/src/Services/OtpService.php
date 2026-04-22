<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Psr\Log\LoggerInterface;

class OtpService
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private SmsService $smsService;
    private EmailService $emailService;
    private int $otpLength = 6;
    private int $expiryMinutes = 10;
    private int $maxAttempts = 3;

    public function __construct(
        PDO $pdo,
        LoggerInterface $logger,
        SmsService $smsService,
        EmailService $emailService
    ) {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->smsService = $smsService;
        $this->emailService = $emailService;

        $this->ensureTableExists();
    }

    /**
     * Generate and send OTP to phone number
     */
    public function sendToPhone(string $phone, string $purpose = 'verification'): array
    {
        // Normalize phone number
        $phone = $this->normalizePhone($phone);

        // Check rate limiting
        if ($this->isRateLimited($phone, $purpose)) {
            return [
                'success' => false,
                'error' => 'Too many OTP requests. Please wait before trying again.',
                'retry_after' => 60,
            ];
        }

        // Invalidate previous OTPs for this phone/purpose
        $this->invalidatePreviousOtps($phone, $purpose);

        // Try Termii's OTP first if available
        if ($this->smsService->isEnabled()) {
            $termiiResult = $this->smsService->sendOtp($phone, $this->otpLength, $this->expiryMinutes);

            if ($termiiResult) {
                // Store reference in database
                $this->storeOtp($phone, null, $purpose, $termiiResult['pin_id']);

                return [
                    'success' => true,
                    'message' => 'OTP sent to your phone',
                    'expires_in' => $this->expiryMinutes * 60,
                    'phone' => $this->maskPhone($phone),
                ];
            }
        }

        // Fallback: Generate our own OTP and send via SMS
        $otp = $this->generateOtp();
        $this->storeOtp($phone, $otp, $purpose);

        $sent = $this->smsService->send($phone, "Your Maids.ng code is: {$otp}. Valid for {$this->expiryMinutes} minutes.");

        if (!$sent) {
            $this->logger->warning('Failed to send OTP via SMS', ['phone' => $this->maskPhone($phone)]);
            return [
                'success' => false,
                'error' => 'Failed to send OTP. Please try again.',
            ];
        }

        return [
            'success' => true,
            'message' => 'OTP sent to your phone',
            'expires_in' => $this->expiryMinutes * 60,
            'phone' => $this->maskPhone($phone),
        ];
    }

    /**
     * Send OTP to email address
     */
    public function sendToEmail(string $email, string $purpose = 'verification'): array
    {
        $email = strtolower(trim($email));

        // Check rate limiting
        if ($this->isRateLimited($email, $purpose)) {
            return [
                'success' => false,
                'error' => 'Too many OTP requests. Please wait before trying again.',
                'retry_after' => 60,
            ];
        }

        // Invalidate previous OTPs
        $this->invalidatePreviousOtps($email, $purpose);

        $otp = $this->generateOtp();
        $this->storeOtp($email, $otp, $purpose);

        $sent = $this->emailService->sendPinReset($email, $otp, $email);

        if (!$sent) {
            return [
                'success' => false,
                'error' => 'Failed to send OTP. Please try again.',
            ];
        }

        return [
            'success' => true,
            'message' => 'OTP sent to your email',
            'expires_in' => $this->expiryMinutes * 60,
            'email' => $this->maskEmail($email),
        ];
    }

    /**
     * Verify OTP
     */
    public function verify(string $identifier, string $otp, string $purpose = 'verification'): array
    {
        $identifier = $this->normalizeIdentifier($identifier);

        // Get stored OTP
        $stmt = $this->pdo->prepare("
            SELECT * FROM otps
            WHERE identifier = ?
            AND purpose = ?
            AND verified = 0
            AND expires_at > datetime('now')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$identifier, $purpose]);
        $record = $stmt->fetch();

        if (!$record) {
            return [
                'success' => false,
                'error' => 'OTP expired or not found. Please request a new one.',
            ];
        }

        // Check attempts
        if ($record['attempts'] >= $this->maxAttempts) {
            $this->invalidateOtp($record['id']);
            return [
                'success' => false,
                'error' => 'Too many failed attempts. Please request a new OTP.',
            ];
        }

        // If we have a Termii pin_id, verify via Termii
        if (!empty($record['termii_pin_id'])) {
            $verified = $this->smsService->verifyOtp($record['termii_pin_id'], $otp);

            if ($verified) {
                $this->markVerified($record['id']);
                return [
                    'success' => true,
                    'message' => 'OTP verified successfully',
                ];
            }

            $this->incrementAttempts($record['id']);
            return [
                'success' => false,
                'error' => 'Invalid OTP. Please try again.',
                'attempts_remaining' => $this->maxAttempts - $record['attempts'] - 1,
            ];
        }

        // Otherwise, verify against stored OTP
        if ($record['otp'] !== $otp) {
            $this->incrementAttempts($record['id']);
            return [
                'success' => false,
                'error' => 'Invalid OTP. Please try again.',
                'attempts_remaining' => $this->maxAttempts - $record['attempts'] - 1,
            ];
        }

        $this->markVerified($record['id']);

        return [
            'success' => true,
            'message' => 'OTP verified successfully',
        ];
    }

    /**
     * Check if identifier is verified within time window
     */
    public function isVerified(string $identifier, string $purpose = 'verification', int $windowMinutes = 30): bool
    {
        $identifier = $this->normalizeIdentifier($identifier);

        $stmt = $this->pdo->prepare("
            SELECT 1 FROM otps
            WHERE identifier = ?
            AND purpose = ?
            AND verified = 1
            AND verified_at > datetime('now', '-{$windowMinutes} minutes')
            LIMIT 1
        ");
        $stmt->execute([$identifier, $purpose]);

        return $stmt->fetch() !== false;
    }

    /**
     * Generate random OTP
     */
    private function generateOtp(): string
    {
        $otp = '';
        for ($i = 0; $i < $this->otpLength; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }

    /**
     * Store OTP in database
     */
    private function storeOtp(string $identifier, ?string $otp, string $purpose, ?string $termiiPinId = null): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->expiryMinutes * 60));

        $stmt = $this->pdo->prepare("
            INSERT INTO otps (identifier, otp, purpose, termii_pin_id, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([$identifier, $otp, $purpose, $termiiPinId, $expiresAt]);
    }

    /**
     * Invalidate previous OTPs for identifier
     */
    private function invalidatePreviousOtps(string $identifier, string $purpose): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE otps SET verified = -1
            WHERE identifier = ? AND purpose = ? AND verified = 0
        ");
        $stmt->execute([$identifier, $purpose]);
    }

    /**
     * Invalidate specific OTP
     */
    private function invalidateOtp(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE otps SET verified = -1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Mark OTP as verified
     */
    private function markVerified(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE otps SET verified = 1, verified_at = datetime('now') WHERE id = ?
        ");
        $stmt->execute([$id]);
    }

    /**
     * Increment failed attempts
     */
    private function incrementAttempts(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE otps SET attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Check rate limiting (max 3 OTPs per minute)
     */
    private function isRateLimited(string $identifier, string $purpose): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM otps
            WHERE identifier = ?
            AND purpose = ?
            AND created_at > datetime('now', '-1 minute')
        ");
        $stmt->execute([$identifier, $purpose]);

        return $stmt->fetchColumn() >= 3;
    }

    /**
     * Normalize phone number
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+234')) {
            $phone = '0' . substr($phone, 4);
        } elseif (str_starts_with($phone, '234')) {
            $phone = '0' . substr($phone, 3);
        }

        return $phone;
    }

    /**
     * Normalize identifier (phone or email)
     */
    private function normalizeIdentifier(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($identifier));
        }
        return $this->normalizePhone($identifier);
    }

    /**
     * Mask phone for display
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 6) {
            return $phone;
        }
        return substr($phone, 0, 4) . '****' . substr($phone, -2);
    }

    /**
     * Mask email for display
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        if (strlen($name) <= 2) {
            $maskedName = $name[0] . '*';
        } else {
            $maskedName = $name[0] . str_repeat('*', strlen($name) - 2) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    /**
     * Ensure OTPs table exists
     */
    private function ensureTableExists(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS otps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier TEXT NOT NULL,
                otp TEXT,
                purpose TEXT NOT NULL DEFAULT 'verification',
                termii_pin_id TEXT,
                attempts INTEGER DEFAULT 0,
                verified INTEGER DEFAULT 0,
                verified_at TEXT,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_otps_identifier ON otps(identifier)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_otps_expires ON otps(expires_at)");
    }

    /**
     * Cleanup expired OTPs (run via cron)
     */
    public function cleanup(): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM otps WHERE expires_at < datetime('now', '-1 day')
        ");
        $stmt->execute();

        return $stmt->rowCount();
    }
}
