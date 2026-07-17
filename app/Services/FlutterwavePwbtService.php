<?php

namespace App\Services;

use App\Models\MatchingFeePayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Flutterwave Pay with Bank Transfer (PWBT) service.
 *
 * Generates single-use dynamic accounts for in-chat matching fee payment.
 * No BVN/NIN required — works for cold leads with just phone + name.
 */
class FlutterwavePwbtService
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.flutterwave.secret_key');
        $this->baseUrl = config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3');

        // Fallback: if config is still returning encrypted legacy data, use raw env
        if (empty($this->secretKey) || strlen($this->secretKey) > 100) {
            $this->secretKey = env('FLUTTERWAVE_SECRET_KEY', '');
        }

        // Detect placeholder / masked keys
        if (str_contains($this->secretKey, 'xxxx') || strlen($this->secretKey) < 20) {
            Log::warning('Flutterwave secret key appears to be a placeholder or invalid', [
                'length' => strlen($this->secretKey),
            ]);
        }

        $this->baseUrl = config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3');
    }

    /**
     * Generate a PWBT virtual account for a user's matching fee payment.
     */
    public function generateForUser(User $user, int $amount = 20000): array
    {
        if (empty($this->secretKey)) {
            throw new \RuntimeException('Flutterwave secret key is not configured.');
        }

        $preference = $user->latestPreference;

        // Auto-create a minimal preference if the user doesn't have one yet
        if (!$preference) {
            $preference = \App\Models\EmployerPreference::create([
                'employer_id' => $user->id,
                'help_types' => json_encode([]),
                'location' => null,
                'quiz_status' => 'in_progress',
                'matching_status' => 'pending',
            ]);
            Log::info('Created minimal preference for user for PWBT payment', [
                'user_id' => $user->id,
                'preference_id' => $preference->id,
            ]);
        }

        $txRef = $this->generateTxRef($user->id);
        $email = $user->email ?: 'user' . $user->id . '@maids.ng';
        $phone = $user->phone ? $this->normalizePhone($user->phone) : null;
        $name = $user->name;

        $payload = [
            'tx_ref' => $txRef,
            'amount' => (string) $amount,
            'currency' => 'NGN',
            'email' => $email,
            'fullname' => $name,
            'is_permanent' => false,
        ];

        if ($phone) {
            $payload['phone_number'] = $phone;
        }

        if ($preference) {
            $payload['meta'] = [
                'preference_id' => (string) ($preference->id ?? ''),
                'employer_id' => (string) $user->id,
                'user_name' => $name,
            ];
        }

        Log::info('Flutterwave PWBT: generating account', [
            'tx_ref' => $txRef,
            'user_id' => $user->id,
            'amount' => $amount,
        ]);

        $response = Http::withToken($this->secretKey)
            ->timeout(30)
            ->post($this->baseUrl . '/charges?type=bank_transfer', $payload);

        if (!$response->successful()) {
            Log::error('Flutterwave PWBT: generation failed', [
                'tx_ref' => $txRef,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Flutterwave PWBT generation failed: ' . $response->body());
        }

        $data = $response->json();

        $meta = $data['meta']['authorization'] ?? [];
        if (empty($meta)) {
            Log::error('Flutterwave PWBT: missing authorization in response', [
                'tx_ref' => $txRef,
                'body' => $data,
            ]);
            throw new \RuntimeException('Flutterwave PWBT response missing bank account details.');
        }

        $payment = MatchingFeePayment::create([
            'preference_id' => $preference?->id,
            'employer_id' => $user->id,
            'amount' => $amount,
            'reference' => $txRef,
            'gateway' => 'flutterwave',
            'payment_type' => 'matching_fee',
            'status' => 'pending',
            'tx_ref' => $txRef,
            'account_number' => $meta['transfer_account'] ?? ($meta['account_number'] ?? null),
            'account_bank' => $meta['transfer_bank'] ?? ($meta['bank_name'] ?? $meta['bank'] ?? null),
            'account_name' => $meta['account_name'] ?? $name,
            'expires_at' => now()->addMinutes(30),
            'flutterwave_tx_id' => (string) ($data['data']['id'] ?? ''),
        ]);

        Log::info('Flutterwave PWBT: account generated', [
            'tx_ref' => $txRef,
            'user_id' => $user->id,
            'account_number' => $meta['transfer_account'] ?? 'missing',
            'payment_id' => $payment->id,
        ]);

        $accountNumber = $meta['transfer_account'] ?? $meta['account_number'] ?? '';
        $bankName = $meta['transfer_bank'] ?? $meta['bank_name'] ?? $meta['bank'] ?? '';

        return [
            'payment_id' => $payment->id,
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => 'NGN',
            'account_number' => $accountNumber,
            'account_bank' => $bankName,
            'account_name' => $meta['account_name'] ?? $name,
            'expires_at' => $payment->expires_at->toIso8601String(),
            'expires_in_minutes' => 30,
            'whatsapp_text' => $this->formatWhatsAppMessage($meta, $amount, $name),
        ];
    }

    /**
     * Format bank details for WhatsApp chat display.
     *
     * IMPORTANT: The virtual account is NOT in the user's name — it's a
     * Flutterwave pooled account. The transfer_note from Flutterwave tells
     * the user what name appears on their banking app (e.g. "Digital20 Limited FLW").
     * We must NOT tell the user the account is in their name.
     */
    private function formatWhatsAppMessage(array $meta, int $amount, string $name): string
    {
        $accountNumber = $meta['transfer_account'] ?? $meta['account_number'] ?? '—';
        $bankName = $meta['transfer_bank'] ?? $meta['bank_name'] ?? $meta['bank'] ?? '—';
        $transferNote = $meta['transfer_note'] ?? '';

        // Extract the business name from the transfer note for clarity
        $businessName = '';
        if (preg_match('/transfer to (.+?) FLW/', $transferNote, $m)) {
            $businessName = $m[1];
        }

        $msg = "*Amount:* ₦" . number_format($amount) . "\n"
            . "*Bank:* {$bankName}\n"
            . "*Account Number:* *{$accountNumber}*\n";

        if ($businessName) {
            $msg .= "*Account Name:* {$businessName}\n";
        }

        $msg .= "\nThis is a temporary account. It expires in 30 minutes.\n"
            . "Once you transfer, reply \"done\" and I'll confirm it right away.\n\n"
            . "Note: Maids.ng is a subsidiary of Digital20 Limited, so that's the name you'll see on your banking app. This is normal.";

        return $msg;
    }

    /**
     * Verify a PWBT transaction. Called from webhook and on-demand polling.
     */
    public function verifyTransaction(string $txRef): ?array
    {
        $response = Http::withToken($this->secretKey)
            ->timeout(30)
            ->get($this->baseUrl . '/transactions/verify_by_reference', [
                'tx_ref' => $txRef,
            ]);

        if (!$response->successful()) {
            Log::warning('Flutterwave PWBT: verification failed', [
                'tx_ref' => $txRef,
                'status' => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();
        $txData = $data['data'] ?? [];

        $txStatus = $txData['status'] ?? '';

        Log::info('Flutterwave PWBT: verification result', [
            'tx_ref' => $txRef,
            'status' => $txStatus,
            'amount' => $txData['amount'] ?? 0,
        ]);

        if ($txStatus === 'successful') {
            return $txData;
        }

        return null;
    }

    private function generateTxRef(int $userId): string
    {
        return 'MNG-' . $userId . '-' . now()->format('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^\d]/', '', $phone);

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '234' . substr($digits, 1);
        }

        return $digits;
    }
}
