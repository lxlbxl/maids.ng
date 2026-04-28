<?php

namespace App\Http\Controllers;

use App\Models\StandaloneVerification;
use App\Models\User;
use App\Models\Setting;
use App\Services\Agents\GatekeeperAgent;
use App\Mail\VerificationReportMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StandaloneVerificationController extends Controller
{
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'requester_name' => 'required|string|max:255',
            'requester_email' => 'required|email',
            'requester_phone' => 'required|string|max:20',
            'maid_nin' => 'required|string|regex:/^[0-9]{11}$/',
            'maid_first_name' => 'required|string|max:255',
            'maid_last_name' => 'required|string|max:255',
        ]);

        // Register user if not logged in
        $user = Auth::user();
        if (!$user) {
            $user = User::where('email', $validated['requester_email'])->first();
            if (!$user) {
                $user = User::create([
                    'name' => $validated['requester_name'],
                    'email' => $validated['requester_email'],
                    'phone' => $validated['requester_phone'],
                    'password' => Hash::make(Str::random(12)),
                ]);
                $user->assignRole('employer');
            }
            Auth::login($user);
        }

        $amount = (int) Setting::get('standalone_verification_fee', 2000);
        $reference = 'VRF-' . strtoupper(Str::random(12));

        $gateway = Setting::get('default_payment_gateway', 'paystack');

        $verification = StandaloneVerification::create([
            'requester_id' => $user->id,
            'maid_nin' => $validated['maid_nin'],
            'maid_first_name' => $validated['maid_first_name'],
            'maid_last_name' => $validated['maid_last_name'],
            'amount' => $amount,
            'payment_reference' => $reference,
            'gateway' => $gateway,
            'payment_status' => 'pending',
        ]);

        $publicKey = $gateway === 'flutterwave' 
            ? Setting::get('flutterwave_public_key', config('services.flutterwave.public_key'))
            : Setting::get('paystack_public_key', config('services.paystack.public_key'));

        return response()->json([
            'success' => true,
            'reference' => $reference,
            'amount' => $amount, // Base amount in Naira
            'email' => $user->email,
            'verification_id' => $verification->id,
            'gateway' => $gateway,
            'key' => trim($publicKey)
        ]);
    }

    public function verifyPayment(Request $request)
    {
        $reference = $request->query('reference');
        $verification = StandaloneVerification::where('payment_reference', $reference)->firstOrFail();

        // In production, verify with Paystack API
        // For now, assume success if redirected here
        $verification->update([
            'payment_status' => 'paid',
        ]);

        // Trigger verification via GatekeeperAgent
        $gatekeeper = new GatekeeperAgent();
        $result = $gatekeeper->verifyNinStandalone(
            $verification->maid_nin,
            $verification->maid_first_name,
            $verification->maid_last_name
        );

        $verification->update([
            'verification_status' => $result['success'] ? 'success' : 'failed',
            'verification_data' => $result['data'] ?? null,
        ]);

        // Send Email with report
        try {
            Mail::to($verification->requester->email)->send(new VerificationReportMail($verification));
        } catch (\Exception $e) {
            Log::error("Failed to send verification report email: " . $e->getMessage());
        }
        
        return redirect()->route('standalone-verification.report', $reference);
    }

    public function showReport($reference)
    {
        $verification = StandaloneVerification::where('payment_reference', $reference)
            ->with('requester')
            ->firstOrFail();

        if ($verification->payment_status !== 'paid') {
            return redirect()->route('verify-service')->with('error', 'Payment required to view report.');
        }

        return Inertia::render('VerificationReport', [
            'verification' => $verification
        ]);
    }
}
