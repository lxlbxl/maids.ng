<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Verify a transaction with the specified gateway.
     *
     * @param string $reference
     * @param string $gateway
     * @return array|null Returns transaction data on success, null on failure.
     */
    public function verifyTransaction(string $reference, string $gateway = 'paystack'): ?array
    {
        try {
            if ($gateway === 'paystack') {
                return $this->verifyPaystack($reference);
            } elseif ($gateway === 'flutterwave') {
                return $this->verifyFlutterwave($reference);
            }
        } catch (\Exception $e) {
            Log::error("Payment verification failed for {$gateway} (Ref: {$reference}): " . $e->getMessage());
        }

        return null;
    }

    /**
     * Verify with Paystack.
     */
    private function verifyPaystack(string $reference): ?array
    {
        $secretKey = Setting::get('paystack_secret_key', config('services.paystack.secret_key'));
        $baseUrl = Setting::get('paystack_base_url', 'https://api.paystack.co');

        if (!$secretKey) {
            Log::error("Paystack secret key not configured.");
            return null;
        }

        $response = Http::withToken(trim($secretKey))
            ->get("{$baseUrl}/transaction/verify/{$reference}");

        if ($response->successful()) {
            $data = $response->json();
            if (($data['status'] ?? false) && ($data['data']['status'] ?? '') === 'success') {
                return $data['data'];
            }
        }

        Log::warning("Paystack verification failed for ref: {$reference}", [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return null;
    }

    /**
     * Verify with Flutterwave.
     */
    private function verifyFlutterwave(string $reference): ?array
    {
        $secretKey = Setting::get('flutterwave_secret_key', config('services.flutterwave.secret_key'));
        $baseUrl = Setting::get('flutterwave_base_url', 'https://api.flutterwave.com/v3');

        if (!$secretKey) {
            Log::error("Flutterwave secret key not configured.");
            return null;
        }

        $response = Http::withToken(trim($secretKey))
            ->get("{$baseUrl}/transactions/verify_by_reference", [
                'tx_ref' => $reference
            ]);

        if ($response->successful()) {
            $data = $response->json();
            if (($data['status'] ?? '') === 'success' && ($data['data']['status'] ?? '') === 'successful') {
                return $data['data'];
            }
        }

        Log::warning("Flutterwave verification failed for ref: {$reference}", [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return null;
    }
}
