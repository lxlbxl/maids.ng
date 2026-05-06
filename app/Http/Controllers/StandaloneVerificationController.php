<?php

namespace App\Http\Controllers;

use App\Models\StandaloneVerification;
use App\Models\User;
use App\Models\Setting;
use App\Services\Agents\GatekeeperAgent;
use App\Services\PaymentService;
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
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'requester_name' => 'required|string|max:255',
            'requester_email' => 'required|email',
            'requester_phone' => 'required|string|max:20',
            'maid_nin' => 'required|string|regex:/^[0-9]{11}$/',
            'maid_first_name' => 'required|string|max:255',
            'maid_last_name' => 'required|string|max:255',
            // Optional fields for better QoreID match accuracy
            'maid_middle_name' => 'nullable|string|max:255',
            'maid_dob' => 'nullable|date|before:today',
            'maid_phone' => 'nullable|string|max:20',
            'maid_email' => 'nullable|email|max:255',
            'maid_gender' => 'nullable|in:male,female,m,f',
        ]);

        // Auto-register as employer if not logged in
        $user = Auth::user();
        if (!$user) {
            $email = $validated['requester_email'];
            $phone = $validated['requester_phone'];

            // Check if user exists by email OR phone
            $user = User::where('email', $email)
                ->when($phone, function($q) use ($phone) {
                    return $q->orWhere('phone', $phone);
                })
                ->first();

            $isNewAccount = !$user;
            $tempPassword = null;

            if ($isNewAccount) {
                $tempPassword = Str::random(10);
                $user = User::create([
                    'name' => $validated['requester_name'],
                    'email' => $email,
                    'phone' => $phone,
                    'password' => Hash::make($tempPassword),
                    'status' => 'active',
                ]);
                $user->assignRole('employer');
            } else {
                // Update missing details on existing user
                $updates = [];
                if (!$user->name || $user->name === 'Guest Employer') {
                    $updates['name'] = $validated['requester_name'];
                }
                if ($phone && !$user->phone) {
                    $updates['phone'] = $phone;
                }
                if ($email && !$user->email) {
                    $updates['email'] = $email;
                }
                if (!empty($updates)) {
                    $user->update($updates);
                }
            }

            // Send welcome email for new accounts
            if ($isNewAccount) {
                try {
                    Mail::to($user->email)->send(new \App\Mail\WelcomeEmployerMail($user, $tempPassword));
                } catch (\Throwable $e) {
                    Log::warning('Welcome email failed for verification user: ' . $e->getMessage());
                }
            }

            Auth::login($user);
        }

        $amount = (int) Setting::get('standalone_verification_fee', 2000);
        $reference = 'VRF-' . strtoupper(Str::random(12));

        $gateway = Setting::get('default_payment_gateway', 'paystack');

        // Normalize gender
        $gender = null;
        if (!empty($validated['maid_gender'])) {
            $gender = in_array(strtolower($validated['maid_gender']), ['m', 'male']) ? 'm' : 'f';
        }

        // Build optional fields array for QoreID
        $optionalFields = [];
        if (!empty($validated['maid_middle_name'])) {
            $optionalFields['middlename'] = $validated['maid_middle_name'];
        }
        if (!empty($validated['maid_dob'])) {
            // Convert to YYYY-MM-DD format
            $optionalFields['dob'] = date('Y-m-d', strtotime($validated['maid_dob']));
        }
        if (!empty($validated['maid_phone'])) {
            $optionalFields['phone'] = $validated['maid_phone'];
        }
        if (!empty($validated['maid_email'])) {
            $optionalFields['email'] = $validated['maid_email'];
        }
        if ($gender) {
            $optionalFields['gender'] = $gender;
        }

        $verification = StandaloneVerification::create([
            'requester_id' => $user->id,
            'requester_name' => $validated['requester_name'],
            'requester_email' => $validated['requester_email'],
            'maid_nin' => $validated['maid_nin'],
            'maid_first_name' => $validated['maid_first_name'],
            'maid_last_name' => $validated['maid_last_name'],
            'maid_middle_name' => $validated['maid_middle_name'] ?? null,
            'maid_dob' => !empty($validated['maid_dob']) ? date('Y-m-d', strtotime($validated['maid_dob'])) : null,
            'maid_phone' => $validated['maid_phone'] ?? null,
            'maid_email' => $validated['maid_email'] ?? null,
            'maid_gender' => $gender,
            'amount' => $amount,
            'payment_reference' => $reference,
            'gateway' => $gateway,
            'payment_status' => 'pending',
            'optional_fields' => !empty($optionalFields) ? json_encode($optionalFields) : null,
        ]);

        if ($gateway === 'flutterwave') {
            // Use Flutterwave redirect-based checkout (more reliable than inline modal)
            $flutterwaveSecret = Setting::get('flutterwave_secret_key', config('services.flutterwave.secret_key'));
            $baseUrl = Setting::get('flutterwave_base_url', 'https://api.flutterwave.com/v3');

            $paymentData = [
                'tx_ref' => $reference,
                'amount' => $amount,
                'currency' => 'NGN',
                'redirect_url' => route('standalone-verification.verify', ['reference' => $reference]),
                'customer' => [
                    'email' => $user->email,
                    'name' => $validated['requester_name'],
                    'phone_number' => $validated['requester_phone'],
                ],
                'meta' => [
                    'consumer_id' => $verification->id,
                    'consumer_mac' => 'maids-ng-verification',
                ],
                'customizations' => [
                    'title' => 'Maids.ng NIN Verification',
                    'description' => 'Payment for NIN Identity Verification',
                    'logo' => url('/maids-logo.png'),
                ],
            ];

            $response = \Illuminate\Support\Facades\Http::withToken(trim($flutterwaveSecret))
                ->post("{$baseUrl}/payments", $paymentData);

            if ($response->successful()) {
                $responseData = $response->json();
                if (($responseData['status'] ?? '') === 'success') {
                    return response()->json([
                        'success' => true,
                        'reference' => $reference,
                        'amount' => $amount,
                        'email' => $user->email,
                        'verification_id' => $verification->id,
                        'gateway' => 'flutterwave',
                        'redirect_url' => $responseData['data']['link'] ?? null,
                    ]);
                }
            }

            // Fallback to inline if API fails
            Log::warning('Flutterwave payment API failed, falling back to inline', [
                'reference' => $reference,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $publicKey = Setting::get('flutterwave_public_key', config('services.flutterwave.public_key'));
            return response()->json([
                'success' => true,
                'reference' => $reference,
                'amount' => $amount,
                'email' => $user->email,
                'verification_id' => $verification->id,
                'gateway' => 'flutterwave',
                'key' => trim($publicKey),
            ]);
        }

        $publicKey = Setting::get('paystack_public_key', config('services.paystack.public_key'));

        return response()->json([
            'success' => true,
            'reference' => $reference,
            'amount' => $amount,
            'email' => $user->email,
            'verification_id' => $verification->id,
            'gateway' => $gateway,
            'key' => trim($publicKey),
        ]);
    }

    public function verifyPayment(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');

        if (!$reference) {
            return redirect()->route('verify-service')->with('error', 'Payment reference missing.');
        }

        $verification = StandaloneVerification::where('payment_reference', $reference)->firstOrFail();

        // Already processed
        if ($verification->payment_status === 'paid') {
            return redirect()->route('standalone-verification.report', $reference);
        }

        // Verify with Gateway
        $gatewayData = $this->paymentService->verifyTransaction($reference, $verification->gateway);

        if (!$gatewayData) {
            Log::warning("Standalone verification payment failed for reference: {$reference}");
            return redirect()->route('verify-service')->with('error', 'We could not verify your payment. If you were debited, please contact support.');
        }

        // Mark as paid
        $verification->update([
            'payment_status' => 'paid',
        ]);

        // Trigger verification via GatekeeperAgent
        $gatekeeper = new GatekeeperAgent();

        // Parse optional fields for QoreID
        $optionalFields = [];
        if ($verification->optional_fields) {
            $decoded = json_decode($verification->optional_fields, true);
            if (is_array($decoded)) {
                $optionalFields = $decoded;
            }
        }

        $result = $gatekeeper->verifyNinStandalone(
            $verification->maid_nin,
            $verification->maid_first_name,
            $verification->maid_last_name,
            $optionalFields
        );

        $verification->update([
            'verification_status' => $result['success'] ? 'success' : 'failed',
            'verification_data' => $result['data'] ?? null,
            'confidence_score' => $result['confidence'] ?? 0,
            'name_matched' => $result['name_match'] ?? false,
            'external_reference' => ($result['data']['qoreid_data']['id'] ?? null) ?
                'QOREID-' . $result['data']['qoreid_data']['id'] : null,
        ]);

        // Send Email with report — use stored email, fallback to requester relationship
        $emailToSend = $verification->requester_email ?? ($verification->requester?->email);
        if ($emailToSend) {
            try {
                Mail::to($emailToSend)->send(new VerificationReportMail($verification));
            } catch (\Exception $e) {
                Log::error("Failed to send verification report email: " . $e->getMessage());
            }
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
