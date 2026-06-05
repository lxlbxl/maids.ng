<?php

namespace App\Services\Sms;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Termii SMS Provider — Nigeria-focused SMS gateway.
 *
 * @see https://developers.termii.com/
 */
class TermiiProvider implements SmsProviderInterface
{
    protected string $apiKey;
    protected string $senderId;
    protected string $baseUrl;

    public function __construct()
    {
        // Read from Admin Settings (database) — falls back to .env
        $this->apiKey = Setting::get('termii_api_key', config('services.termii.api_key', ''));
        $this->senderId = Setting::get('termii_sender_id', config('services.termii.sender_id', 'MaidsNG'));
        $this->baseUrl = Setting::get('termii_url', config('services.termii.url', 'https://api.ng.termii.com/api'));
    }

    public function send(string $phone, string $message): array
    {
        if (!$this->apiKey) {
            return ['success' => false, 'error' => 'Termii API key not configured'];
        }

        $phone = $this->normalizePhone($phone);

        try {
            $response = Http::timeout(15)->post("{$this->baseUrl}/sms/send", [
                'to' => $phone,
                'from' => $this->senderId,
                'sms' => $message,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => $this->apiKey,
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['message_id'])) {
                return [
                    'success' => true,
                    'message_id' => $data['message_id'],
                    'response' => $data,
                ];
            }

            Log::warning('Termii SMS send failed', ['response' => $data, 'phone' => $phone]);

            return [
                'success' => false,
                'error' => $data['message'] ?? 'Termii API error',
            ];
        } catch (\Exception $e) {
            Log::error('Termii SMS exception', ['error' => $e->getMessage(), 'phone' => $phone]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getBalance(): array
    {
        if (!$this->apiKey) {
            return ['success' => false, 'error' => 'Termii API key not configured'];
        }

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/get-balance", [
                'api_key' => $this->apiKey,
            ]);

            $data = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'balance' => (float) ($data['balance'] ?? 0),
                    'currency' => $data['currency'] ?? 'NGN',
                ];
            }

            return ['success' => false, 'error' => $data['message'] ?? 'Failed to fetch balance'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getDeliveryStatus(string $messageId): array
    {
        if (!$this->apiKey) {
            return ['success' => false, 'error' => 'Termii API key not configured'];
        }

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/sms/inbox", [
                'api_key' => $this->apiKey,
                'message_id' => $messageId,
            ]);

            $data = $response->json();

            return [
                'success' => $response->successful(),
                'status' => $data['status'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function name(): string
    {
        return 'Termii';
    }

    /**
     * Normalise Nigerian phone numbers to international format.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Convert 0xxx to 234xxx
        if (str_starts_with($phone, '0') && strlen($phone) === 11) {
            $phone = '234' . substr($phone, 1);
        }

        return $phone;
    }
}
