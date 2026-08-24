<?php

namespace App\Http\Controllers;

use App\Models\EmployerPreference;
use App\Models\MatchingFeePayment;
use App\Models\Setting;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MatchingFeeController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'preference_id' => 'required|exists:employer_preferences,id',
            'payment_type' => 'nullable|string|in:matching_fee,guarantee_match',
        ]);

        $preference = EmployerPreference::findOrFail($validated['preference_id']);
        $paymentType = $validated['payment_type'] ?? 'matching_fee';
        $amount = (int) Setting::get('matching_fee_amount', 20000);
        $reference = 'MNG-' . strtoupper(Str::random(10));

        $gateway = Setting::get('default_payment_gateway', 'paystack');
        $key = Setting::get($gateway . '_public_key', config('services.' . $gateway . '.public_key'));

        if (empty($key)) {
            Log::error("Payment gateway {$gateway} public key not configured.");
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway is not properly configured. Please contact support.',
            ], 500);
        }

        $payment = MatchingFeePayment::create([
            'preference_id' => $preference->id,
            'employer_id' => Auth::id(),
            'amount' => $amount,
            'reference' => $reference,
            'gateway' => $gateway,
            'status' => 'pending',
            'payment_type' => $paymentType,
        ]);

        return response()->json([
            'success' => true,
            'reference' => $reference,
            'amount' => $amount,
            'email' => Auth::user()->email,
            'name' => Auth::user()->name,
            'phone' => Auth::user()->phone ?? '',
            'payment_id' => $payment->id,
            'gateway' => $gateway,
            'key' => trim($key),
        ]);
    }

    public function verify(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');
        
        if (!$reference) {
            return redirect()->route('employer.dashboard')
                ->with('error', 'Payment reference missing.');
        }

        $payment = MatchingFeePayment::where('reference', $reference)->firstOrFail();

        // Already processed
        if ($payment->status === 'paid') {
            return redirect()->route('employer.dashboard')
                ->with('success', 'Payment already verified.');
        }

        // Verify with Gateway
        $gatewayData = $this->paymentService->verifyTransaction($reference, $payment->gateway);

        if (!$gatewayData) {
            Log::warning("Payment verification failed for reference: {$reference}");
            return redirect()->route($payment->payment_type === 'guarantee_match' ? 'employer.guarantee-match.payment' : 'employer.matching.payment', $payment->preference_id)
                ->with('error', 'We could not verify your payment. If you were debited, please contact support.');
        }

        // Mark as paid
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'gateway_response' => $gatewayData,
        ]);

        if ($payment->employer) {
            \App\Events\PaymentConfirmed::dispatch(
                $payment->employer,
                $payment->reference,
                (int) $payment->amount,
                $payment->payment_type ?? 'matching_fee'
            );
        }

        // Set the correct matching status based on payment type
        $newStatus = $payment->payment_type === 'guarantee_match' ? 'guarantee_paid' : 'paid';
        $payment->preference->update(['matching_status' => $newStatus]);

        $successMessage = $payment->payment_type === 'guarantee_match'
            ? 'Guarantee Match activated! Our team will find your perfect helper within 14 days.'
            : 'Payment successful! You can now view your matched maid\'s contact details.';

        return redirect()->route('employer.dashboard')
            ->with('success', $successMessage);
    }

    public function history()
    {
        $payments = MatchingFeePayment::where('employer_id', Auth::id())
            ->with('preference.selectedMaid')
            ->latest()
            ->paginate(10);

        return response()->json($payments);
    }

    public function requestRefund(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:matching_fee_payments,id',
            'reason' => 'required|string|max:500',
        ]);

        $payment = MatchingFeePayment::where('id', $validated['payment_id'])
            ->where('employer_id', Auth::id())
            ->where('status', 'paid')
            ->firstOrFail();

        // Guarantee Match has a 14-day refund window, regular matching has 10-day
        $refundWindowDays = $payment->payment_type === 'guarantee_match' ? 14 : 10;

        if ($payment->paid_at && $payment->paid_at->diffInDays(now()) > $refundWindowDays) {
            return back()->with('error', "Refund window ({$refundWindowDays} days) has expired.");
        }

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);

        // Reset matching status if guarantee match is refunded
        if ($payment->payment_type === 'guarantee_match') {
            $payment->preference->update(['matching_status' => 'pending']);
        }

        return back()->with('success', 'Refund has been processed.');
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();
        $event = $payload['event'] ?? '';
        $reference = $payload['data']['reference'] ?? $payload['tx_ref'] ?? null;

        if (!$reference) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Look up payment by reference OR tx_ref (Flutterwave uses tx_ref)
        $payment = MatchingFeePayment::where('reference', $reference)
            ->orWhere('tx_ref', $reference)
            ->first();

        if (!$payment) {
            return response()->json(['status' => 'not_found'], 200);
        }

        // Already paid — idempotent
        if ($payment->status === 'paid') {
            return response()->json(['status' => 'already_paid'], 200);
        }

        // Validate we're handling a charge success event (Paystack: charge.success, Flutterwave: charge.completed)
        $isSuccessEvent = in_array($event, ['charge.success', 'charge.completed'], true);

        if (!$isSuccessEvent) {
            return response()->json(['status' => 'ignored_event'], 200);
        }

        // For Flutterwave, verify the payload amount matches
        if ($event === 'charge.completed') {
            $fwAmount = (int) ($payload['data']['amount'] ?? 0);
            if ($fwAmount > 0 && $fwAmount !== (int) $payment->amount) {
                Log::warning('Webhook: amount mismatch', [
                    'reference' => $reference,
                    'expected' => $payment->amount,
                    'received' => $fwAmount,
                ]);
                return response()->json(['status' => 'amount_mismatch'], 200);
            }
        }

        // Re-verify on server to be 100% sure it's not a spoofed webhook
        $gatewayData = $this->paymentService->verifyTransaction($reference, $payment->gateway);

        // For Flutterwave PWBT, also try the FlutterwavePwbtService
        if (!$gatewayData && $payment->gateway === 'flutterwave' && $payment->tx_ref) {
            $pwbtService = app(\App\Services\FlutterwavePwbtService::class);
            $gatewayData = $pwbtService->verifyTransaction($payment->tx_ref);
        }

        if (!$gatewayData) {
            Log::warning('Webhook: server-side re-verification failed', [
                'reference' => $reference,
                'gateway' => $payment->gateway,
            ]);
            return response()->json(['status' => 'verification_failed'], 200);
        }

        // Mark payment as paid
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'gateway_response' => $gatewayData,
        ]);

        // Fire PaymentConfirmed event (triggers webhooks, notifications)
        if ($payment->employer) {
            \App\Events\PaymentConfirmed::dispatch(
                $payment->employer,
                $payment->reference,
                (int) $payment->amount,
                $payment->payment_type ?? 'matching_fee'
            );
        }

        // Update preference matching_status
        if ($payment->preference) {
            $newStatus = $payment->payment_type === 'guarantee_match' ? 'guarantee_paid' : 'paid';
            $payment->preference->update(['matching_status' => $newStatus]);
        }

        // Update onboarding journey milestone if applicable
        $this->updateOnboardingMilestone($payment);

        Log::info("Payment verified via webhook", [
            'reference' => $reference,
            'payment_id' => $payment->id,
            'gateway' => $payment->gateway,
            'event' => $event,
        ]);

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Update onboarding journey to payment_confirmed if the user has one.
     */
    private function updateOnboardingMilestone(MatchingFeePayment $payment): void
    {
        try {
            $journey = \App\Models\OnboardingJourney::where('user_id', $payment->employer_id)
                ->where('status', '!=', 'completed')
                ->first();

            if ($journey) {
                $journey->update([
                    'current_step' => 'payment_confirmed',
                    'completion_pct' => max($journey->completion_pct, 60),
                    'last_activity_at' => now(),
                ]);

                Log::info('Onboarding journey updated on payment', [
                    'user_id' => $payment->employer_id,
                    'journey_id' => $journey->id,
                    'step' => 'payment_confirmed',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to update onboarding milestone on payment', [
                'user_id' => $payment->employer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
