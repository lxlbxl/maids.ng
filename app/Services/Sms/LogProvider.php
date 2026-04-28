<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Log SMS Provider — for development and testing.
 *
 * Logs SMS messages to storage/logs/sms.log instead of sending them.
 */
class LogProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): array
    {
        $messageId = 'log_' . uniqid();

        Log::channel('single')->info('[SMS LOG] Message sent', [
            'message_id' => $messageId,
            'to'         => $phone,
            'body'       => $message,
            'timestamp'  => now()->toIso8601String(),
        ]);

        return [
            'success'    => true,
            'message_id' => $messageId,
            'response'   => ['provider' => 'log', 'logged' => true],
        ];
    }

    public function getBalance(): array
    {
        return [
            'success'  => true,
            'balance'  => 999999.00,
            'currency' => 'NGN',
        ];
    }

    public function getDeliveryStatus(string $messageId): array
    {
        return [
            'success' => true,
            'status'  => 'delivered',
        ];
    }

    public function name(): string
    {
        return 'Log (Development)';
    }
}
