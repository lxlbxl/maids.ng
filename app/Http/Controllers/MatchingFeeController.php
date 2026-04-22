<?php

namespace App\Http\Controllers;

use App\Models\EmployerPreference;
use App\Models\MatchingFeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MatchingFeeController extends Controller
{
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'preference_id' => 'required|exists:employer_preferences,id',
        ]);

        $preference = EmployerPreference::findOrFail($validated['preference_id']);
        $amount = config('services.fees.matching', 5000);
        $reference = 'MNG-' . strtoupper(Str::random(10));

        $payment = MatchingFeePayment::create([
            'preference_id' => $preference->id,
            'employer_id' => Auth::id(),
            'amount' => $amount,
            'reference' => $reference,
            'gateway' => config('services.defaults.payment_gateway', 'paystack'),
            'status' => 'pending',
        ]);

        return response()->json([
            'reference' => $reference,
            'amount' => $amount * 100, // kobo
            'email' => Auth::user()->email,
            'payment_id' => $payment->id,
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

        $payment->preference->update(['matching_status' => 'paid']);

        return redirect()->route('employer.dashboard')
            ->with('success', 'Payment successful! You can now view your matched maid\'s contact details.');
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

        // Check 10-day refund window
        if ($payment->paid_at && $payment->paid_at->diffInDays(now()) > 10) {
            return back()->with('error', 'Refund window (10 days) has expired.');
        }

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);

        return back()->with('success', 'Refund has been processed.');
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();
        $reference = $payload['data']['reference'] ?? null;

        if (!$reference) return response()->json(['status' => 'ignored'], 200);

        $payment = MatchingFeePayment::where('reference', $reference)->first();
        if (!$payment) return response()->json(['status' => 'not_found'], 200);

        if ($payload['event'] === 'charge.success') {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway_response' => $payload['data'],
            ]);
            $payment->preference->update(['matching_status' => 'paid']);
        }

        return response()->json(['status' => 'success'], 200);
    }
}
