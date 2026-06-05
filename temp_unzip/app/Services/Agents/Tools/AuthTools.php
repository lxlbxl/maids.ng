<?php

namespace App\Services\Agents\Tools;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\Sms\SmsService;

/**
 * AuthTools — OTP generation, verification, and authentication.
 */
class AuthTools
{
    private const OTP_TTL = 300; // 5 minutes
    private const OTP_LENGTH = 6;

    public function __construct(
        private readonly SmsService $sms,
    ) {
    }

    /**
     * Send OTP to a phone number.
     *
     * @param array{ phone: string } $args
     * @param \App\Models\AgentChannelIdentity $identity
     * @param \App\Services\Agents\DTOs\ChannelMessage $message
     * @return array{ success: bool, message: string, expires_in?: int }
     */
    public function __invoke(array $args, $identity, $message): array
    {
        $phone = $args['phone'];

        // Validate phone format
        if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
            return [
                'success' => false,
                'message' => 'Please provide a valid phone number (e.g., +2348012345678).',
            ];
        }

        // Generate OTP
        $otp = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        // Store OTP in cache with TTL
        $cacheKey = "otp_{$phone}";
        Cache::put($cacheKey, [
            'otp' => $otp,
            'created_at' => now()->timestamp,
            'attempts' => 0,
        ], self::OTP_TTL);

        // Send via SMS
        try {
            $this->sms->send($phone, "Your Maids.ng verification code is: {$otp}. Valid for 5 minutes.");

            Log::info('OTP sent', [
                'phone' => $phone,
                'channel' => $message->channel,
            ]);

            return [
                'success' => true,
                'message' => "I've sent a verification code to {$phone}. Please check your messages and enter the code.",
                'expires_in' => self::OTP_TTL,
            ];
        } catch (\Throwable $e) {
            Log::error('OTP send failed: ' . $e->getMessage(), ['phone' => $phone]);

            return [
                'success' => false,
                'message' => 'I could not send the verification code. Please try again or contact support.',
            ];
        }
    }

    /**
     * Verify an OTP.
     *
     * @param array{ phone: string, otp: string } $args
     * @return array{ success: bool, user_id?: int, message: string }
     */
    public function verify(array $args): array
    {
        $phone = $args['phone'];
        $otp = $args['otp'];

        $cacheKey = "otp_{$phone}";
        $stored = Cache::get($cacheKey);

        if (!$stored) {
            return [
                'success' => false,
                'message' => 'No verification code found. Please request a new one.',
            ];
        }

        // Check attempts
        if ($stored['attempts'] >= 3) {
            Cache::forget($cacheKey);
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Please request a new verification code.',
            ];
        }

        // Increment attempts
        $stored['attempts']++;
        Cache::put($cacheKey, $stored, self::OTP_TTL);

        if ($stored['otp'] !== $otp) {
            return [
                'success' => false,
                'message' => 'Invalid verification code. Please try again.',
            ];
        }

        // OTP verified — clear it
        Cache::forget($cacheKey);

        // Find or create user
        $user = User::where('phone', $phone)->first();

        if ($user) {
            // Update last login
            $user->update(['last_login_at' => now()]);

            return [
                'success' => true,
                'user_id' => $user->id,
                'message' => 'Verification successful! You are now logged in.',
            ];
        }

        return [
            'success' => true,
            'user_id' => null,
            'message' => 'Verification successful! It looks like you don\'t have an account yet. Would you like to create one?',
        ];
    }

    /**
     * Check if a phone has a pending OTP.
     *
     * @param string $phone
     * @return bool
     */
    public function hasPendingOtp(string $phone): bool
    {
        return Cache::has("otp_{$phone}");
    }
}