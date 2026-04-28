<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Africa's Talking SMS Provider.
 *
 * @see https://africastalking.com/sms
 */
class AfricasTalkingProvider implements SmsProviderInterface
{
    protected string $username;
    protected string $apiKey;
    protected string $from;

    public function __construct()
    {
        $this->username = config('services.africastalking.username', '');
        $this->apiKey   = config('services.africastalking.api_key', '');
        $this->from     = config('services.africastalking.from', 'MaidsNG');
    }

    public function send(string $phone, string $message): array
    {
        if (! $this->username || ! $this->apiKey) {
            return ['success' => false, 'error' => "Africa's Talking credentials not configured"];
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);

        try {
            $response = Http::timeout(15)
                ->withHeaders(['apiKey' => $this->apiKey, 'Accept' => 'application/json'])
                ->asForm()
                ->post('https://api.africastalking.com/version1/messaging', [
                    'username' => $this->username,
                    'to'       => $phone,
                    'message'  => $message,
                    'from'     => $this->from,
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['SMSMessageData']['Recipients'])) {
                $recipient = $data['SMSMessageData']['Recipients'][0] ?? [];

                return [
                    'success'    => true,
                    'message_id' => $recipient['messageId'] ?? '',
                    'response'   => $data,
                ];
            }

            Log::warning("AfricasTalking SMS send failed", ['response' => $data, 'phone' => $phone]);

            return [
                'success' => false,
                'error'   => $data['SMSMessageData']['Message'] ?? "Africa's Talking API error",
            ];
        } catch (\Exception $e) {
            Log::error("AfricasTalking SMS exception", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getBalance(): array
    {
        if (! $this->username || ! $this->apiKey) {
            return ['success' => false, 'error' => "Africa's Talking credentials not configured"];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['apiKey' => $this->apiKey, 'Accept' => 'application/json'])
                ->get("https://api.africastalking.com/version1/user?username={$this->username}");

            $data = $response->json();

            if ($response->successful() && isset($data['UserData'])) {
                $balance = $data['UserData']['balance'] ?? 'NGN 0';
                preg_match('/[\d.]+/', $balance, $m);

                return [
                    'success'  => true,
                    'balance'  => (float) ($m[0] ?? 0),
                    'currency' => 'NGN',
                ];
            }

            return ['success' => false, 'error' => 'Failed to fetch balance'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getDeliveryStatus(string $messageId): array
    {
        // Africa's Talking uses delivery report callbacks — no polling endpoint
        return [
            'success' => false,
            'error'   => "Africa's Talking does not support polling delivery status. Use delivery report callbacks.",
        ];
    }

    public function name(): string
    {
        return "Africa's Talking";
    }
}
