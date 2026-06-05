<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeEmployerMail;
use App\Models\EmployerPreference;
use App\Models\MaidProfile;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MatchingController extends Controller
{
    /**
     * Create an employer account during onboarding (before search runs).
     * Called as soon as the user provides name, email, and phone.
     * Sends a welcome email on first-time creation.
     */
    public function createAccount(Request $request)
    {
        $validated = $request->validate([
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
        ]);

        $email = $validated['contact_email'];
        $name = $validated['contact_name'];
        $phone = $validated['contact_phone'] ?? null;

        // Check if user exists by email OR phone
        $existingUser = User::where('email', $email)
            ->when($phone, function($q) use ($phone) {
                return $q->orWhere('phone', $phone);
            })
            ->first();

        $isNewAccount = !$existingUser;
        $tempPassword = null;

        if ($isNewAccount) {
            $tempPassword = Str::random(10);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($tempPassword),
                'status' => 'active',
            ]);

            $user->assignRole('employer');
        } else {
            $user = $existingUser;

            // Update user details if they have changed or were missing
            $updates = [];
            if (!$user->name || $user->name === 'Guest Employer') {
                $updates['name'] = $name;
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

        // Send welcome email on first-time creation
        if ($isNewAccount) {
            try {
                Mail::to($user->email)->send(new WelcomeEmployerMail($user, $tempPassword));
            } catch (\Throwable $e) {
                Log::warning('Welcome email failed: ' . $e->getMessage());
            }
        }

        // Auto-login the user so subsequent routes (matching, payment) work
        Auth::loginUsingId($user->id);

        return response()->json([
            'user_id' => $user->id,
            'is_new' => $isNewAccount,
            'message' => $isNewAccount
                ? 'Account created! Check your email for login details.'
                : 'Welcome back! We found your existing account.',
        ]);
    }

    /**
     * Find matching maids based on employer preferences.
     * Implements intelligent scoring with location, help type, schedule, and budget alignment.
     */
    public function findMatches(Request $request, \App\Services\Agents\ScoutAgent $scoutAgent)
    {
        $validated = $request->validate([
            'help_types' => 'required|array',
            'schedule' => 'required|string',
            'urgency' => 'required|string',
            'location' => 'required|string',
            'budget_min' => 'nullable|integer',
            'budget_max' => 'nullable|integer',
            'contact_name' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'contact_email' => 'nullable|string|email',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        // Use the user_id from onboarding if provided (account already created),
        // otherwise fall back to Auth or create a new account
        $employerId = $validated['user_id'] ?? Auth::id();

        if (!$employerId) {
            $email = $validated['contact_email'] ?? 'guest_' . uniqid() . '@maids.ng';
            $name = $validated['contact_name'] ?? 'Guest Employer';
            $phone = $validated['contact_phone'] ?? null;
            $tempPassword = Str::random(10);

            // Check if user exists by email OR phone
            $user = User::where('email', $email)
                ->when($phone, function($q) use ($phone) {
                    return $q->orWhere('phone', $phone);
                })
                ->first();

            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => Hash::make($tempPassword),
                    'status' => 'active'
                ]);
                $user->assignRole('employer');
            } else {
                // Update missing details
                $updates = [];
                if (!$user->name || $user->name === 'Guest Employer') $updates['name'] = $name;
                if ($phone && !$user->phone) $updates['phone'] = $phone;
                if ($email && !$user->email) $updates['email'] = $email;
                if (!empty($updates)) $user->update($updates);
            }

            // Send welcome email if this was a new creation
            if ($user->wasRecentlyCreated) {
                try {
                    Mail::to($user->email)->send(new WelcomeEmployerMail($user, $tempPassword));
                } catch (\Throwable $e) {
                    Log::warning('Welcome email failed: ' . $e->getMessage());
                }
            }

            Auth::loginUsingId($user->id);
            $employerId = $user->id;
        }

        // Save preferences with quiz lifecycle tracking
        $preference = EmployerPreference::create([
            'employer_id' => $employerId,
            'help_types' => $validated['help_types'],
            'schedule' => $validated['schedule'],
            'urgency' => $validated['urgency'],
            'location' => $validated['location'],
            'city' => explode(',', $validated['location'])[0] ?? trim($validated['location']),
            'state' => trim(explode(',', $validated['location'])[1] ?? ''),
            'budget_min' => $validated['budget_min'] ?? null,
            'budget_max' => $validated['budget_max'] ?? null,
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'matching_status' => 'pending',
            'quiz_status' => 'completed',
            'quiz_completed_at' => now(),
        ]);

        Log::info('Matching requested for location: ' . $validated['location'] . ' (City: ' . $preference->city . ', State: ' . $preference->state . ')');

        // Track quiz completion event
        UserEvent::recordQuizComplete($employerId, [
            'preference_id' => $preference->id,
            'help_types' => $validated['help_types'],
            'location' => $validated['location'],
            'budget_max' => $validated['budget_max'],
        ]);

        // Get all available maids with profiles
        $maids = User::role('maid')
            ->where('status', 'active')
            ->with('maidProfile')
            ->whereHas('maidProfile', fn($q) => $q->where('availability_status', 'available'))
            ->get();

        Log::info('Found ' . $maids->count() . ' potential maids in database.');

        // Update matches_shown_at timestamp when we're about to return matches
        $preference->update([
            'matches_shown_at' => now(),
        ]);

        // Track matches viewed event
        UserEvent::recordMatchesViewed($employerId, [
            'preference_id' => $preference->id,
            'total_maids_scored' => $maids->count(),
            'matches_returned' => 0, // Will be updated after scoring
        ]);

        // Score each maid
        $scored = $maids->map(function ($maid) use ($preference, $scoutAgent) {
            $profile = $maid->maidProfile;
            if (!$profile)
                return null;

            $scoring = $scoutAgent->scoreMatch($profile, $preference);

            return [
                'id' => $maid->id,
                'name' => $maid->name,
                'role' => $profile->getMaidRole(),
                'location' => $profile->location ?? $maid->location,
                'rating' => round($profile->rating ?? 0, 1),
                'total_reviews' => $profile->total_reviews ?? 0,
                'rate' => $profile->expected_salary,
                'skills' => (array) ($profile->skills ?? []),
                'experience_years' => $profile->experience_years,
                'verified' => $profile->isVerified(),
                'match' => $scoring['score'],
                'confidence' => $scoring['confidence'],
                'bio' => $profile->bio,
                'avatar' => $maid->avatar,
            ];
        })
            ->filter(fn($m) => $m !== null && ($m['match'] ?? 0) >= 40)
            ->sortByDesc('match')
            ->take(15)
            ->values();

        Log::info('Matching results: ' . count($scored) . ' maids passed 40% threshold.');

        // Update the matches_viewed event with actual count
        UserEvent::where('event_type', 'matches_viewed')
            ->where('user_id', $employerId)
            ->latest()
            ->first()?->update([
                    'event_data' => array_merge(
                        (array) (UserEvent::where('event_type', 'matches_viewed')
                            ->where('user_id', $employerId)
                            ->latest()
                            ->first()?->event_data ?? []),
                        ['matches_returned' => count($scored)]
                    ),
                ]);

        // Include guarantee_fee in response so frontend can show it for zero-result upsell
        $guaranteeFee = (int) Setting::get('matching_fee_amount', 5000);

        if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'matches' => $scored,
                'preference_id' => $preference->id,
                'guarantee_fee' => $guaranteeFee,
            ]);
        }

        return back()->with([
            'matches' => $scored,
            'preference_id' => $preference->id,
            'guarantee_fee' => $guaranteeFee,
        ]);
    }

    /**
     * Select a maid from matches.
     */
    public function selectMaid(Request $request)
    {
        $validated = $request->validate([
            'preference_id' => 'required|exists:employer_preferences,id',
            'maid_id' => 'required|exists:users,id',
        ]);

        $preference = EmployerPreference::findOrFail($validated['preference_id']);
        $preference->update([
            'selected_maid_id' => $validated['maid_id'],
            'matching_status' => 'matched',
        ]);

        return redirect()->route('employer.matching.payment', $preference->id);
    }

    /**
     * Activate Guarantee Match service for zero-result searches.
     * Sets the preference to 'guarantee_search' status and redirects to payment.
     */
    public function activateGuaranteeMatch(Request $request)
    {
        $validated = $request->validate([
            'preference_id' => 'required|exists:employer_preferences,id',
        ]);

        $preference = EmployerPreference::findOrFail($validated['preference_id']);
        $preference->update([
            'matching_status' => 'guarantee_search',
        ]);

        if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'redirect' => route('employer.guarantee-match.payment', $preference->id),
                'preference_id' => $preference->id,
            ]);
        }

        return redirect()->route('employer.guarantee-match.payment', $preference->id);
    }

    /**
     * Show payment page for matching fee.
     */
    public function showPayment($preferenceId)
    {
        $preference = EmployerPreference::with('selectedMaid.maidProfile')->findOrFail($preferenceId);

        $maid = $preference->selectedMaid;
        $profile = $maid?->maidProfile;

        // Determine which payment gateway to use
        $defaultGateway = Setting::get('default_payment_gateway', 'paystack');
        $publicKey = Setting::get($defaultGateway . '_public_key', config('services.' . $defaultGateway . '.public_key'));

        return Inertia::render('Employer/MatchingPayment', [
            'preference' => $preference,
            'maid' => $maid ? [
                'id' => $maid->id,
                'name' => $maid->name,
                'role' => $profile?->getMaidRole() ?? 'Helper',
                'location' => $profile?->location ?? $maid->location,
                'rating' => round($profile?->rating ?? 0, 1),
                'rate' => $profile?->expected_salary ?? 0,
                'skills' => $profile?->skills ?? [],
                'verified' => ($profile?->nin_verified ?? false) && ($profile?->background_verified ?? false),
                'avatar' => $maid->avatar,
            ] : null,
            'matchingFee' => (int) Setting::get('matching_fee_amount', 5000),
            'paystackKey' => $publicKey,
            'defaultGateway' => $defaultGateway,
        ]);
    }

    /**
     * Show payment page for Guarantee Match service.
     */
    public function showGuaranteePayment($preferenceId)
    {
        $preference = EmployerPreference::findOrFail($preferenceId);

        $defaultGateway = Setting::get('default_payment_gateway', 'paystack');
        $publicKey = Setting::get($defaultGateway . '_public_key', config('services.' . $defaultGateway . '.public_key'));

        return Inertia::render('Employer/GuaranteeMatchPayment', [
            'preference' => $preference,
            'guaranteeFee' => (int) Setting::get('matching_fee_amount', 5000),
            'paystackKey' => $publicKey,
            'defaultGateway' => $defaultGateway,
        ]);
    }
}
