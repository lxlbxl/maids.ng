<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class VerificationService
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private NotificationService $notificationService;
    private array $qoreidConfig;
    private Client $httpClient;

    public function __construct(
        PDO $pdo,
        LoggerInterface $logger,
        NotificationService $notificationService,
        array $qoreidConfig
    ) {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->notificationService = $notificationService;
        $this->qoreidConfig = $qoreidConfig;
        $this->httpClient = new Client(['timeout' => 30]);
    }

    public function submitVerification(int $helperId, string $documentType, string $documentPath, ?string $ninNumber = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO verifications (helper_id, document_type, document_path, nin_number, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$helperId, $documentType, $documentPath, $ninNumber]);

        $verificationId = (int) $this->pdo->lastInsertId();

        $this->logger->info("Verification submitted", [
            'verification_id' => $verificationId,
            'helper_id' => $helperId,
            'document_type' => $documentType
        ]);

        // If NIN number provided, attempt auto-verification with QoreID
        if ($ninNumber && $documentType === 'nin') {
            $this->autoVerifyNin($verificationId, $helperId, $ninNumber);
        }

        return $verificationId;
    }

    public function autoVerifyNin(int $verificationId, int $helperId, string $ninNumber): array
    {
        // 0. Fetch latest config from DB
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'qoreid_token'");
        $stmt->execute();
        $tokenSetting = $stmt->fetch();
        $token = $tokenSetting ? json_decode($tokenSetting['value'], true) : $this->qoreidConfig['token'];

        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'qoreid_base_url'");
        $stmt->execute();
        $baseSetting = $stmt->fetch();
        $baseUrl = $baseSetting ? json_decode($baseSetting['value'], true) : ($this->qoreidConfig['base_url'] ?? 'https://api.qoreid.com/v1');

        // Get helper details for verification
        $stmt = $this->pdo->prepare("SELECT full_name, first_name, last_name, date_of_birth FROM helpers WHERE id = ?");
        $stmt->execute([$helperId]);
        $helper = $stmt->fetch();

        if (!$helper) {
            return ['success' => false, 'message' => 'Helper not found'];
        }

        // Prepare request for QoreID NIN Premium API
        $requestData = [
            'firstname' => $helper['first_name'] ?? explode(' ', $helper['full_name'])[0],
            'lastname' => $helper['last_name'] ?? (explode(' ', $helper['full_name'])[1] ?? ''),
        ];

        // Add optional fields if available
        if ($helper['date_of_birth']) {
            $requestData['dob'] = $helper['date_of_birth'];
        }

        try {
            // Call QoreID NIN Premium API
            $response = $this->httpClient->post(
                "{$baseUrl}/ng/identities/nin-premium/{$ninNumber}",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $requestData
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            // Store QoreID response
            $this->updateVerification($verificationId, [
                'qoreid_response' => json_encode($data)
            ]);

            // Validate the response matches helper data
            $isValid = $this->validateNinResponse($data, $helper);

            if ($isValid) {
                $this->approveVerification($verificationId, null, 'auto');

                // Update helper with verified NIN data
                $this->updateHelperFromNin($helperId, $data);

                $this->logger->info("NIN auto-verification successful", [
                    'verification_id' => $verificationId,
                    'helper_id' => $helperId
                ]);

                return ['success' => true, 'message' => 'NIN verified successfully', 'data' => $data];
            } else {
                // Queue for manual review
                $this->updateVerification($verificationId, [
                    'status' => 'pending_review',
                    'rejection_reason' => 'Name mismatch - queued for manual review'
                ]);

                $this->logger->info("NIN verification queued for manual review", [
                    'verification_id' => $verificationId,
                    'helper_id' => $helperId
                ]);

                return ['success' => false, 'message' => 'Queued for manual review'];
            }
        } catch (GuzzleException $e) {
            $this->logger->error("QoreID NIN verification failed", [
                'verification_id' => $verificationId,
                'error' => $e->getMessage()
            ]);

            // Queue for manual review on API failure
            $this->updateVerification($verificationId, [
                'status' => 'pending_review',
                'rejection_reason' => 'API error - queued for manual review'
            ]);

            return ['success' => false, 'message' => 'Verification service unavailable, queued for manual review'];
        }
    }

    private function validateNinResponse(array $ninData, array $helper): bool
    {
        // Normalize names for comparison
        $ninFirstName = strtolower(trim($ninData['firstname'] ?? ''));
        $ninLastName = strtolower(trim($ninData['lastname'] ?? ''));
        $helperFirstName = strtolower(trim($helper['first_name'] ?? explode(' ', $helper['full_name'])[0]));
        $helperLastName = strtolower(trim($helper['last_name'] ?? (explode(' ', $helper['full_name'])[1] ?? '')));

        // Check if names match (allowing for minor variations)
        $firstNameMatch = $this->fuzzyMatch($ninFirstName, $helperFirstName);
        $lastNameMatch = $this->fuzzyMatch($ninLastName, $helperLastName);

        return $firstNameMatch && $lastNameMatch;
    }

    private function fuzzyMatch(string $str1, string $str2): bool
    {
        if ($str1 === $str2) {
            return true;
        }

        // Check if one contains the other
        if (str_contains($str1, $str2) || str_contains($str2, $str1)) {
            return true;
        }

        // Check similarity percentage (80% threshold)
        similar_text($str1, $str2, $percent);
        return $percent >= 80;
    }

    private function updateHelperFromNin(int $helperId, array $ninData): void
    {
        $updates = [];
        $params = [];

        if (!empty($ninData['firstname'])) {
            $updates[] = "first_name = ?";
            $params[] = $ninData['firstname'];
        }
        if (!empty($ninData['lastname'])) {
            $updates[] = "last_name = ?";
            $params[] = $ninData['lastname'];
        }
        if (!empty($ninData['birthdate'])) {
            $updates[] = "date_of_birth = ?";
            $params[] = $ninData['birthdate'];
        }
        if (!empty($ninData['gender'])) {
            $updates[] = "gender = ?";
            $params[] = $ninData['gender'];
        }

        if (!empty($updates)) {
            $updates[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $helperId;

            $sql = "UPDATE helpers SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    public function approveVerification(int $verificationId, ?int $adminId = null, string $method = 'manual'): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE verifications SET
                status = 'verified',
                verified_by = ?,
                verified_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $verificationId]);

        // Get helper ID and update helper verification status
        $stmt = $this->pdo->prepare("SELECT v.helper_id, h.* FROM verifications v JOIN helpers h ON v.helper_id = h.id WHERE v.id = ?");
        $stmt->execute([$verificationId]);
        $helper = $stmt->fetch();

        if ($helper) {
            $stmt = $this->pdo->prepare("
                UPDATE helpers SET
                    verification_status = 'verified',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$helper['id']]);

            // Trigger notification
            $this->notificationService->notifyHelperVerified($helper);
        }

        $this->logger->info("Verification approved", [
            'verification_id' => $verificationId,
            'admin_id' => $adminId,
            'method' => $method
        ]);

        return true;
    }

    public function rejectVerification(int $verificationId, int $adminId, string $reason): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE verifications SET
                status = 'rejected',
                verified_by = ?,
                verified_at = CURRENT_TIMESTAMP,
                rejection_reason = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $reason, $verificationId]);

        // Get helper details for notification
        $stmt = $this->pdo->prepare("SELECT v.helper_id, h.* FROM verifications v JOIN helpers h ON v.helper_id = h.id WHERE v.id = ?");
        $stmt->execute([$verificationId]);
        $helper = $stmt->fetch();

        if ($helper) {
            // Trigger notification
            $this->notificationService->notifyVerificationRejected($helper, $reason);
        }

        $this->logger->info("Verification rejected", [
            'verification_id' => $verificationId,
            'admin_id' => $adminId,
            'reason' => $reason
        ]);

        return true;
    }

    public function getVerification(int $verificationId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.*, h.full_name as helper_name, h.profile_photo
            FROM verifications v
            JOIN helpers h ON v.helper_id = h.id
            WHERE v.id = ?
        ");
        $stmt->execute([$verificationId]);
        $verification = $stmt->fetch();

        if ($verification && $verification['qoreid_response']) {
            $verification['qoreid_response'] = json_decode($verification['qoreid_response'], true);
        }

        return $verification ?: null;
    }

    public function getPendingVerifications(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.*, h.full_name as helper_name, h.profile_photo, u.phone
            FROM verifications v
            JOIN helpers h ON v.helper_id = h.id
            JOIN users u ON h.user_id = u.id
            WHERE v.status IN ('pending', 'pending_review')
            ORDER BY v.created_at ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    public function getVerificationStats(): array
    {
        $stats = [];

        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM verifications GROUP BY status");
        while ($row = $stmt->fetch()) {
            $stats[$row['status']] = $row['count'];
        }

        // Today's verifications
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count FROM verifications
            WHERE DATE(created_at) = DATE('now')
        ");
        $stats['today'] = (int) $stmt->fetch()['count'];

        return $stats;
    }

    private function updateVerification(int $verificationId, array $data): void
    {
        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $params[] = $value;
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $verificationId;

        $sql = "UPDATE verifications SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
}
