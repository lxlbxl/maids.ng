<?php

namespace App\Services\Agents\Tools;

use App\Models\AgentChannelIdentity;
use App\Services\Agents\DTOs\InboundMessage;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Tool: send_otp
 * Generates and sends a 6-digit OTP via SMS for identity verification.
 */
class SendOtpTool
{
    public function __construct(private readonly SmsProviderInterface $sms)
    {
    }

    public function __invoke(array $args, AgentChannelIdentity $identity, InboundMessage $message): string
    {
        $phone = $args['phone'] ?? $message->phone;

        if (!$phone) {
            return json_encode([
                'success' => false,
                'message' => 'No phone number available to send OTP.',
            ]);
        }

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save OTP to identity
        $identity->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'phone' => $phone,
        ]);

        // Send via SMS
        try {
            $this->sms->send($phone, "Your Maids.ng verification code is: {$otp}. Valid for 10 minutes.");
            Log::info('OTP sent', ['phone' => $phone, 'channel' => $identity->channel]);

            return json_encode([
                'success' => true,
                'message' => 'Verification code sent successfully.',
                'expires_in' => '10 minutes',
            ]);
        } catch (\Throwable $e) {
            Log::error('OTP send failed: ' . $e->getMessage(), ['phone' => $phone]);

            return json_encode([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.',
            ]);
        }
    }

    public function description(): string
    {
        return 'Send a 6-digit verification code (OTP) to a phone number via SMS. Used to verify user identity before account creation or login.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'phone' => [
                    'type' => 'string',
                    'description' => 'Phone number in international format (e.g., +234...)',
                ],
            ],
            'required' => ['phone'],
        ];
    }
}