<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio SMS Provider.
 *
 * @see https://www.twilio.com/docs/sms
 */
class TwilioProvider implements SmsProviderInterface
{
    protected string $sid;
    protected string $token;
    protected string $from;

    public function __construct()
    {
        $this->sid   = config('services.twilio.sid', '');
        $this->token = config('services.twilio.token', '');
        $this->from  = config('services.twilio.from', '');
    }

    public function send(string $phone, string $message): array
    {
        if (! $this->sid || ! $this->token) {
            return ['success' => false, 'error' => 'Twilio credentials not configured'];
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);

        try {
            $response = Http::withBasicAuth($this->sid, $this->token)
                ->timeout(15)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                    'To'   => $phone,
                    'From' => $this->from,
                    'Body' => $message,
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['sid'])) {
                return [
                    'success'    => true,
                    'message_id' => $data['sid'],
                    'response'   => $data,
                ];
            }

            Log::warning('Twilio SMS send failed', ['response' => $data, 'phone' => $phone]);

            return [
                'success' => false,
                'error'   => $data['message'] ?? 'Twilio API error',
            ];
        } catch (\Exception $e) {
            Log::error('Twilio SMS exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getBalance(): array
    {
        if (! $this->sid || ! $this->token) {
            return ['success' => false, 'error' => 'Twilio credentials not configured'];
        }

        try {
            $response = Http::withBasicAuth($this->sid, $this->token)
                ->timeout(10)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Balance.json");

            $data = $response->json();

            return [
                'success'  => $response->successful(),
                'balance'  => (float) ($data['balance'] ?? 0),
                'currency' => $data['currency'] ?? 'USD',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getDeliveryStatus(string $messageId): array
    {
        if (! $this->sid || ! $this->token) {
            return ['success' => false, 'error' => 'Twilio credentials not configured'];
        }

        try {
            $response = Http::withBasicAuth($this->sid, $this->token)
                ->timeout(10)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages/{$messageId}.json");

            $data = $response->json();

            return [
                'success' => $response->successful(),
                'status'  => $data['status'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function name(): string
    {
        return 'Twilio';
    }
}
