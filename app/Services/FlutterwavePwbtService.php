<?php

namespace App\Services;

use App\Models\MatchingFeePayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
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
        $this->baseUrl = config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3');
        $this->secretKey = $this->resolveSecretKey();
    }

    /**
     * Resolve the Flutterwave secret key from the layered stores, tolerating the
     * historically corrupted values (DB settings / .env hold Laravel-encrypted
     * blobs, some of them encrypted more than once under APP_KEYs that are now
     * gone). We try, in order: the DB setting, then config/env, and for each we
     * peel up to 2 Crypt layers. A real key looks like "FLWSECK-..." /
     * "FLWSECK_TEST-...". Anything else is treated as "not configured" so the
     * caller fails loudly instead of sending ciphertext to Flutterwave (401).
     */
    private function resolveSecretKey(): string
    {
        $candidates = [
            Setting::get('flutterwave_secret_key', null),
            config('services.flutterwave.secret_key'),
            env('FLUTTERWAVE_SECRET_KEY'),
        ];

        foreach ($candidates as $raw) {
            $key = $this->unwrap((string) $raw);
            if ($this->looksLikeFlwKey($key)) {
                return $key;
            }
        }

        Log::warning('Flutterwave secret key could not be resolved to a real key — '
            . 'PWBT is disabled until FLUTTERWAVE_SECRET_KEY is set to a valid FLWSECK- key '
            . '(the stored value is an unrecoverable multi-encrypted blob).');

        return '';
    }

    /** Peel up to 2 Laravel-Crypt layers off a value; return the first plaintext-ish result. */
    private function unwrap(string $value): string
    {
        $value = trim($value);
        for ($i = 0; $i < 2; $i++) {
            if ($this->looksLikeFlwKey($value) || $value === '') {
                return $value;
            }
            try {
                $value = trim(Crypt::decryptString($value));
            } catch (\Throwable $e) {
                break;
            }
        }
        return $value;
    }

    private function looksLikeFlwKey(string $v): bool
    {
        return $v !== ''
            && ! str_contains($v, 'xxxx')
            && preg_match('/^FLWSECK[_-]/', $v) === 1;
    }

    /**
     * Generate a PWBT virtual account for a user's matching fee payment.
     */
    public function generateForUser(User $user, int $amount = 20000): array
    {
        if (empty($this->secretKey)) {
            throw new \RuntimeException('PWBT_UNAVAILABLE: Flutterwave secret key is not configured. '
                . 'Do not retry — use the manual payment fallback and alert the team.');
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

    /**
     * Pull a pending PWBT payment's status straight from Flutterwave and, if it
     * has been paid for real, settle it (mark paid + preference status +
     * PaymentConfirmed event). Idempotent. This is the source of truth — we do
     * not depend on the Flutterwave webhook because the same Flutterwave account
     * serves several Digital20 brands and its single webhook URL may not point
     * at Maids.ng.
     *
     * Isolation is by tx_ref: only Maids.ng mints "MNG-<userId>-..." refs, so a
     * sibling brand's transaction never resolves to a row here.
     *
     * @return array{status:string, paid:bool, paid_amount?:int, expected_amount?:int}
     *   status ∈ already|paid|pending|amount_mismatch|ref_mismatch|gateway_error
     */
    public function reconcile(MatchingFeePayment $payment): array
    {
        if (in_array($payment->status, ['paid', 'completed'], true)) {
            return ['status' => 'already', 'paid' => true];
        }
        if (empty($payment->tx_ref)) {
            return ['status' => 'gateway_error', 'paid' => false];
        }

        try {
            $tx = $this->verifyTransaction($payment->tx_ref);
        } catch (\Throwable $e) {
            Log::warning('PWBT reconcile: gateway call failed', ['tx_ref' => $payment->tx_ref, 'error' => $e->getMessage()]);
            return ['status' => 'gateway_error', 'paid' => false];
        }

        if (!$tx) {
            return ['status' => 'pending', 'paid' => false];
        }

        $paidAmount = (int) round((float) ($tx['amount'] ?? 0));
        $currency   = strtoupper((string) ($tx['currency'] ?? 'NGN'));
        $txRef      = (string) ($tx['tx_ref'] ?? '');

        if ($txRef !== '' && $txRef !== $payment->tx_ref) {
            Log::warning('PWBT reconcile: tx_ref mismatch', ['expected' => $payment->tx_ref, 'got' => $txRef]);
            return ['status' => 'ref_mismatch', 'paid' => false];
        }
        if ($currency !== 'NGN' || $paidAmount < (int) $payment->amount) {
            Log::warning('PWBT reconcile: amount/currency mismatch', [
                'tx_ref' => $payment->tx_ref, 'expected' => $payment->amount,
                'paid' => $paidAmount, 'currency' => $currency,
            ]);
            return [
                'status' => 'amount_mismatch', 'paid' => false,
                'paid_amount' => $paidAmount, 'expected_amount' => (int) $payment->amount,
            ];
        }

        $payment->update([
            'status'            => 'paid',
            'paid_at'           => now(),
            'gateway_response'  => $tx,
            'flutterwave_tx_id' => (string) ($tx['id'] ?? $payment->flutterwave_tx_id ?? ''),
        ]);

        if ($payment->preference) {
            $payment->preference->update([
                'matching_status' => $payment->payment_type === 'guarantee_match' ? 'guarantee_paid' : 'paid',
            ]);
        }

        if ($payment->employer) {
            \App\Events\PaymentConfirmed::dispatch(
                $payment->employer,
                $payment->reference ?? $payment->tx_ref,
                (int) $payment->amount,
                $payment->payment_type ?? 'matching_fee',
            );
        }

        Log::info('PWBT reconcile: payment confirmed', [
            'payment_id' => $payment->id, 'tx_ref' => $payment->tx_ref, 'user_id' => $payment->employer_id,
        ]);

        return ['status' => 'paid', 'paid' => true];
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
