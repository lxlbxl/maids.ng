<?php

namespace App\Http\Controllers;

use App\Models\EmployerPreference;
use App\Models\MatchingFeePayment;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MatchingFeeController extends Controller
{
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'preference_id' => 'required|exists:employer_preferences,id',
            'payment_type' => 'nullable|string|in:matching_fee,guarantee_match',
        ]);

        $preference = EmployerPreference::findOrFail($validated['preference_id']);
        $paymentType = $validated['payment_type'] ?? 'matching_fee';
        $amount = (int) Setting::get('matching_fee_amount', 5000);
        $reference = 'MNG-' . strtoupper(Str::random(10));

        $gateway = Setting::get('default_payment_gateway', 'paystack');
        $key = Setting::get($gateway . '_public_key');

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
        $reference = $request->query('reference');
        $payment = MatchingFeePayment::where('reference', $reference)->firstOrFail();

        // Mark as paid (in production, verify with Paystack API)
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

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
        $reference = $payload['data']['reference'] ?? null;

        if (!$reference)
            return response()->json(['status' => 'ignored'], 200);

        $payment = MatchingFeePayment::where('reference', $reference)->first();
        if (!$payment)
            return response()->json(['status' => 'not_found'], 200);

        if ($payload['event'] === 'charge.success') {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway_response' => $payload['data'],
            ]);

            $newStatus = $payment->payment_type === 'guarantee_match' ? 'guarantee_paid' : 'paid';
            $payment->preference->update(['matching_status' => $newStatus]);
        }

        return response()->json(['status' => 'success'], 200);
    }
}
