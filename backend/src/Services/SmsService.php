<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class SmsService
{
    private Client $client;
    private LoggerInterface $logger;
    private string $apiKey;
    private string $senderId;
    private string $channel;
    private bool $enabled;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->apiKey = $_ENV['TERMII_API_KEY'] ?? '';
        $this->senderId = $_ENV['TERMII_SENDER_ID'] ?? 'Maids.ng';
        $this->channel = $_ENV['TERMII_CHANNEL'] ?? 'generic'; // generic, dnd, whatsapp
        $this->enabled = !empty($this->apiKey);

        $this->client = new Client([
            'base_uri' => 'https://api.ng.termii.com/api/',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Send SMS via Termii
     */
    public function send(string $to, string $message): bool
    {
        if (!$this->enabled) {
            $this->logger->warning('SMS not sent: Termii API key not configured', [
                'to' => $this->maskPhone($to),
            ]);
            return false;
        }

        $to = $this->formatPhoneNumber($to);

        $payload = [
            'to' => $to,
            'from' => $this->senderId,
            'sms' => $message,
            'type' => 'plain',
            'channel' => $this->channel,
            'api_key' => $this->apiKey,
        ];

        try {
            $response = $this->client->post('sms/send', [
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $success = isset($body['message_id']) || ($body['code'] ?? '') === 'ok';

            $this->logger->info('SMS sent', [
                'to' => $this->maskPhone($to),
                'success' => $success,
                'message_id' => $body['message_id'] ?? null,
            ]);

            return $success;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to send SMS', [
                'to' => $this->maskPhone($to),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send OTP via Termii
     */
    public function sendOtp(string $to, int $pinLength = 6, int $expiryMinutes = 10): ?array
    {
        if (!$this->enabled) {
            $this->logger->warning('OTP not sent: Termii API key not configured');
            return null;
        }

        $to = $this->formatPhoneNumber($to);

        $payload = [
            'api_key' => $this->apiKey,
            'message_type' => 'NUMERIC',
            'to' => $to,
            'from' => $this->senderId,
            'channel' => $this->channel,
            'pin_attempts' => 3,
            'pin_time_to_live' => $expiryMinutes,
            'pin_length' => $pinLength,
            'pin_placeholder' => '< 1234 >',
            'message_text' => 'Your Maids.ng verification code is < 1234 >. Valid for ' . $expiryMinutes . ' minutes.',
            'pin_type' => 'NUMERIC',
        ];

        try {
            $response = $this->client->post('sms/otp/send', [
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['pinId'])) {
                $this->logger->info('OTP sent', [
                    'to' => $this->maskPhone($to),
                    'pin_id' => $body['pinId'],
                ]);

                return [
                    'pin_id' => $body['pinId'],
                    'to' => $to,
                    'expires_in' => $expiryMinutes * 60,
                ];
            }

            $this->logger->warning('OTP send failed', [
                'to' => $this->maskPhone($to),
                'response' => $body,
            ]);

            return null;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to send OTP', [
                'to' => $this->maskPhone($to),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify OTP via Termii
     */
    public function verifyOtp(string $pinId, string $pin): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $payload = [
            'api_key' => $this->apiKey,
            'pin_id' => $pinId,
            'pin' => $pin,
        ];

        try {
            $response = $this->client->post('sms/otp/verify', [
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $verified = ($body['verified'] ?? false) === true || ($body['verified'] ?? '') === 'True';

            $this->logger->info('OTP verification', [
                'pin_id' => $pinId,
                'verified' => $verified,
            ]);

            return $verified;
        } catch (GuzzleException $e) {
            $this->logger->error('OTP verification failed', [
                'pin_id' => $pinId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking confirmation SMS
     */
    public function sendBookingConfirmation(string $to, array $booking, array $helper): bool
    {
        $message = "Maids.ng: Your booking is confirmed! Ref: {$booking['reference']}. "
            . "Helper: {$helper['full_name']}. "
            . "We'll connect you shortly.";

        return $this->send($to, $message);
    }

    /**
     * Send payment confirmation SMS
     */
    public function sendPaymentConfirmation(string $to, array $payment): bool
    {
        $amount = number_format($payment['amount'], 0);
        $message = "Maids.ng: Payment of NGN{$amount} received! "
            . "Ref: {$payment['tx_ref']}. Thank you!";

        return $this->send($to, $message);
    }

    /**
     * Send verification status SMS
     */
    public function sendVerificationStatus(string $to, string $name, bool $approved): bool
    {
        if ($approved) {
            $message = "Maids.ng: Congratulations {$name}! Your profile is now verified. "
                . "You have a verified badge. Start receiving job offers!";
        } else {
            $message = "Maids.ng: Hi {$name}, your verification needs attention. "
                . "Please check your email or contact support.";
        }

        return $this->send($to, $message);
    }

    /**
     * Send new booking notification to helper
     */
    public function sendNewBookingToHelper(string $to, string $helperName, string $location): void
    {
        $message = "Maids.ng: Hello {$helperName}, you have a new booking request in {$location}. Check your dashboard for details.";
        $this->send($to, $message);
    }

    /**
     * Send new lead alert via SMS to admin
     */
    public function sendNewLeadAlert(array $lead): void
    {
        $adminPhone = $_ENV['ADMIN_PHONE'] ?? '';
        if (empty($adminPhone))
            return;

        $sms = "Maids.ng Admin: New Lead! Phone: " . ($lead['phone'] ?? 'N/A') . ", Source: " . ($lead['source'] ?? 'N/A');
        $this->send($adminPhone, $sms);
    }

    /**
     * Format phone number to international format for Nigeria
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Convert to international format
        if (str_starts_with($phone, '0')) {
            $phone = '234' . substr($phone, 1);
        } elseif (str_starts_with($phone, '+234')) {
            $phone = substr($phone, 1); // Remove +
        } elseif (!str_starts_with($phone, '234')) {
            $phone = '234' . $phone;
        }

        return $phone;
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 6) {
            return $phone;
        }
        return substr($phone, 0, 4) . '****' . substr($phone, -2);
    }

    /**
     * Check if SMS service is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get account balance (for monitoring)
     */
    public function getBalance(): ?float
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $response = $this->client->get('get-balance', [
                'query' => ['api_key' => $this->apiKey],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            return (float) ($body['balance'] ?? 0);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to get SMS balance', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
