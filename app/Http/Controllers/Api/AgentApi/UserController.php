<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Models\MaidProfile;
use App\Models\NinVerification;
use App\Models\AgentNote;
use App\Models\EmployerPreference;
use App\Models\AgentConversation;
use App\Models\AgentMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends ApiController
{
    /**
     * Generate all plausible phone format variants for lookup.
     *
     * Nigerian numbers commonly appear as:
     *   - 08012345678  (local, with leading 0)
     *   - 2348012345678 (international, 13 digits)
     *   - +234****5678 (with + prefix)
     *
     * This normalizes the input and returns all variants so we can
     * match regardless of how the user (or WACRM) formatted the number.
     */
    private function phoneVariants(string $raw): array
    {
        $digits = preg_replace('/[^\d]/', '', $raw);
        $variants = [$digits];

        // 13 digits starting with 234 → also try 0-prefixed local format
        if (strlen($digits) === 13 && str_starts_with($digits, '234')) {
            $variants[] = '0' . substr($digits, 3); // e.g. 08104999930
        }

        // 11 digits starting with 0 → also try 234-prefixed international
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $variants[] = '234' . substr($digits, 1); // e.g. 2348104999930
        }

        // 10 digits (no prefix at all) → try both local and international
        if (strlen($digits) === 10 && !str_starts_with($digits, '0')) {
            $variants[] = '0' . $digits;
            $variants[] = '234' . $digits;
        }

        return array_unique($variants);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'  => 'nullable|string',
            'email'  => 'nullable|email',
            'user_id' => 'nullable|integer',
        ]);

        $user = null;

        if ($id = $validated['user_id'] ?? null) {
            $user = User::find($id);
        } elseif ($phone = $validated['phone'] ?? null) {
            $variants = $this->phoneVariants($phone);
            $user = User::where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('phone', 'like', '%' . $variant . '%');
                }
            })->first();
        } elseif ($email = $validated['email'] ?? null) {
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            return $this->success(null, 'No user found');
        }

        return $this->success([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role'  => $user->role,
            'status' => $user->status,
        ]);
    }

    public function summary($id): JsonResponse
    {
        $user = User::with(['maidProfile', 'employerPreferences'])->findOrFail($id);

        $maidProfile    = $user->maidProfile;
        $latestPreference = $user->employerPreferences()->latest()->first();

        $recentMessages = AgentMessage::whereHas('conversation', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->latest()->limit(5)->get(['role', 'content', 'created_at']);

        return $this->success([
            'user' => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'phone'  => $user->phone,
                'role'   => $user->role,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'last_login_at' => $user->last_login_at,
            ],
            'onboarding' => [
                'maid_profile'    => $maidProfile ? [
                    'profile_completeness' => $maidProfile->profile_completeness ?? 0,
                    'is_profile_complete'  => $maidProfile->is_profile_complete,
                    'nin_verified'         => $maidProfile->nin_verified,
                    'background_verified'  => $maidProfile->background_verified,
                    'availability_status'  => $maidProfile->availability_status,
                ] : null,
            ],
            'latest_preference' => $latestPreference ? [
                'id'              => $latestPreference->id,
                'quiz_status'     => $latestPreference->quiz_status,
                'help_types'      => $latestPreference->help_types,
                'location'        => $latestPreference->location,
                'budget_min'      => $latestPreference->budget_min,
                'budget_max'      => $latestPreference->budget_max,
                'matching_status' => $latestPreference->matching_status,
                'matches_shown_at' => $latestPreference->matches_shown_at,
            ] : null,
            'recent_messages' => $recentMessages->toArray(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'role'  => 'required|in:employer,maid',
            'password' => 'nullable|string|min:8',
        ]);

        // Find-or-create by phone first, then by email
        $variants = $this->phoneVariants($validated['phone']);
        $existing = User::where(function ($query) use ($variants) {
            foreach ($variants as $variant) {
                $query->orWhere('phone', 'like', '%' . $variant . '%');
            }
        })->first();

        if ($existing) {
            return $this->success([
                'user_id' => $existing->id,
                'name' => $existing->name,
                'phone' => $existing->phone,
                'role' => $existing->role,
                'status' => $existing->status,
                'existed' => true,
            ], 'User already exists');
        }

        $email = $validated['email'] ?? ($validated['phone'].'@maids.ng');

        if (User::where('email', $email)->exists()) {
            $email = $validated['phone'] . '.' . now()->timestamp . '@maids.ng';
        }

        $user = User::create([
            'name'     => $validated['name'],
            'phone'    => $validated['phone'],
            'email'    => $email,
            'password' => bcrypt($validated['password'] ?? 'maids123'),
            'role'     => $validated['role'],
            'status'   => 'active',
        ]);

        // Sync Spatie role so admin panel and permission checks work
        if ($validated['role']) {
            $user->syncRoles([$validated['role']]);
        }

        // Meta CAPI — a *qualified* Lead: the agent (Peace) created this person from
        // a conversation, so we have a real name + phone + stated role. A bare
        // WhatsApp-CTA tap is NOT a lead; this is.
        app(\App\Services\MetaCapi::class)->lead(
            $user,
            'lead_' . $user->id,
            [
                'content_name'     => $validated['role'] === 'maid' ? 'Helper Lead' : 'Employer Lead',
                'content_category' => 'domestic_staff_matching',
                'lead_type'        => $validated['role'],
            ],
            $request,
            'chat',
        );

        return $this->success(['user_id' => $user->id, 'existed' => false], 'User created', [], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'  => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|in:active,suspended,pending',
            'role'   => 'nullable|in:admin,maid,employer',

            // MaidProfile fields — written to user->maidProfile when present.
            // Verification flags (nin_verified, background_verified) are deliberately
            // excluded; they flip only via the verification flow (POST /nin/verify).
            'nin'             => 'nullable|string|size:11',
            'gender'          => 'nullable|string|max:32',
            'bio'             => 'nullable|string|max:2000',
            'skills'          => 'nullable|array',
            'languages'       => 'nullable|array',
            'experience_years'=> 'nullable|integer|min:0|max:60',
            'help_types'      => 'nullable|array',
            'schedule_preference' => 'nullable|string|max:64',
            'expected_salary' => 'nullable|integer|min:0',
            'location'        => 'nullable|string|max:255',
            'state'           => 'nullable|string|max:64',
            'lga'             => 'nullable|string|max:128',
            'willing_states'  => 'nullable|array',
            'bank_name'       => 'nullable|string|max:128',
            'account_number'  => 'nullable|string|max:32',
            'account_name'    => 'nullable|string|max:255',
            'is_foreigner'    => 'nullable|boolean',
        ]);

        // Fields that go on the User row itself
        $userFields = array_intersect_key($validated, array_flip([
            'name', 'first_name', 'last_name', 'phone', 'email', 'status', 'role',
        ]));
        if (! empty($userFields)) {
            $user->update($userFields);
        }

        // Sync Spatie role if role was changed
        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        // Fields that go on maidProfile (auto-create if missing so this works
        // for maids who never logged in to the web form).
        $profileFields = array_intersect_key($validated, array_flip([
            'first_name', 'last_name', 'nin', 'gender', 'bio', 'skills',
            'languages', 'experience_years', 'help_types', 'schedule_preference',
            'expected_salary', 'location', 'state', 'lga', 'willing_states',
            'bank_name', 'account_number', 'account_name', 'is_foreigner',
        ]));
        $profileResult = null;

        if (! empty($profileFields)) {
            $profile = $user->maidProfile;
            if (! $profile) {
                $profile = $user->maidProfile()->create([
                    'location' => $user->location ?? ($profileFields['location'] ?? ''),
                    'skills' => [],
                    'help_types' => [],
                ]);
            }

            // If NIN is changing, refuse once profile is already verified
            if (array_key_exists('nin', $profileFields) && $profile->nin_verified && $profile->nin !== $profileFields['nin']) {
                return $this->error('Identity already verified. NIN cannot be changed.', 409);
            }

            $profile->update($profileFields);
            $profileResult = $profile->fresh();

            // When NIN is set/changed, queue QoreID via NinVerification tracking row
            if (array_key_exists('nin', $profileFields) && ! empty($profileFields['nin'])) {
                NinVerification::where('user_id', $user->id)
                    ->where('status', 'failed')
                    ->update(['status' => 'pending', 'reviewed_at' => null]);

                NinVerification::firstOrCreate(
                    ['user_id' => $user->id, 'status' => 'pending'],
                    ['submitted_at' => now()]
                );
            }
        }

        return $this->success([
            'user' => $user->fresh(),
            'maid_profile' => $profileResult,
        ], 'User updated');
    }

    /**
     * POST /api/agent-api/v1/users/{id}/nin
     *
     * Dedicated NIN-write endpoint for the agent. Mirrors the semantics of
     * MaidVerificationController::submitNin (web form) so the agent can record
     * NINs collected over WhatsApp without sending the applicant to the web UI.
     *
     * Auto-creates the MaidProfile if it doesn't exist yet (e.g. user was
     * created via store() and never logged in to the web form).
     *
     * After writing NIN, queues QoreID via the NinVerification tracking table
     * (same pattern RegisterController uses). Use POST .../nin/verify to run
     * QoreID immediately, or wait for the artisan ai:verify-pending-nins sweep.
     */
    public function submitNin(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'nin'         => 'required|string|size:11',
            'first_name'  => 'nullable|string|max:255',
            'last_name'   => 'nullable|string|max:255',
            'auto_verify' => 'nullable|boolean',
        ]);

        $user = User::findOrFail($id);

        if ($user->role !== 'maid') {
            return $this->error('NIN submission is only for users with role=maid.', 422);
        }

        $profile = $user->maidProfile;
        if (! $profile) {
            $profile = $user->maidProfile()->create([
                'location'    => $user->location ?? '',
                'skills'      => [],
                'help_types'  => [],
            ]);
        }

        // Prevent NIN change if already verified
        if ($profile->nin_verified && $profile->nin !== $validated['nin']) {
            return $this->error('Identity already verified. NIN cannot be changed.', 409);
        }

        // Apply name updates if provided
        $profileFields = [];
        if (! empty($validated['first_name'])) {
            $profileFields['first_name'] = $validated['first_name'];
        }
        if (! empty($validated['last_name'])) {
            $profileFields['last_name'] = $validated['last_name'];
        }
        $profileFields['nin'] = $validated['nin'];

        // Uniqueness across all maid profiles (NIN is global per NIMC)
        $collision = MaidProfile::where('nin', $validated['nin'])
            ->where('id', '!=', $profile->id)
            ->first();
        if ($collision) {
            return $this->error('This NIN is already on file for another maid profile.', 409);
        }

        $profile->update($profileFields);

        // Reset failed verifications so the sweep retries with the new NIN
        NinVerification::where('user_id', $user->id)
            ->where('status', 'failed')
            ->update(['status' => 'pending', 'reviewed_at' => null]);

        // Create a pending NinVerification so ai:verify-pending-nins picks it up
        $verification = NinVerification::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'pending'],
            ['submitted_at' => now()]
        );

        // Log the action for the operations audit trail
        try {
            AgentNote::create([
                'entity_type'  => 'user',
                'entity_id'    => $user->id,
                'note'         => 'NIN submitted via agent-api',
                'action_taken' => 'submit_nin',
                'outcome'      => 'pending',
                'agent_type'   => optional($request->agent_api_key)->agent_type,
                'agent_user_id'=> null,
                'metadata'     => [
                    'source' => 'agent_api',
                    'endpoint' => 'POST /users/{id}/nin',
                    'nin_last4' => substr($validated['nin'], -4),
                    'auto_verify' => (bool) ($validated['auto_verify'] ?? false),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('AgentNote create failed during submitNin: ' . $e->getMessage());
        }

        $response = [
            'user_id'       => $user->id,
            'maid_profile'  => $profile->fresh(),
            'verification'  => [
                'status'        => $verification->status,
                'submitted_at'  => $verification->submitted_at,
            ],
        ];

        // Optionally kick QoreID right away
        if (! empty($validated['auto_verify'])) {
            try {
                $gatekeeper = app(\App\Services\Agents\GatekeeperAgent::class);
                $result = $gatekeeper->verifyIdentity($profile->fresh(), $validated['nin']);
                $response['verification_result'] = $result;
                $response['verification']['status'] = $result['status'] ?? 'pending';
            } catch (\Throwable $e) {
                Log::warning('submitNin auto_verify failed for user ' . $user->id . ': ' . $e->getMessage());
                $response['verification_result'] = [
                    'success' => false,
                    'status'  => 'pending',
                    'reason'  => 'Auto-verify call failed; sweep will retry. ' . $e->getMessage(),
                ];
            }
        }

        return $this->success($response, 'NIN submitted', [], 200);
    }

    /**
     * POST /api/agent-api/v1/users/{id}/nin/verify
     *
     * Runs the Gatekeeper (QoreID) verification against an already-submitted
     * NIN. Mirrors MaidVerificationController::verifyNin. Useful when an agent
     * wants to trigger QoreID immediately after submitNin instead of waiting
     * for the artisan sweep.
     */
    public function verifyNin(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $profile = $user->maidProfile;

        if (! $profile || ! $profile->nin) {
            return $this->error('Maid profile or NIN missing. Submit NIN first.', 422);
        }

        try {
            $gatekeeper = app(\App\Services\Agents\GatekeeperAgent::class);
            $result = $gatekeeper->verifyIdentity($profile, $profile->nin);

            // Ensure a tracking row exists for the agent's view
            $verification = NinVerification::where('user_id', $user->id)
                ->latest()
                ->first();

            return $this->success([
                'user_id'      => $user->id,
                'result'       => $result,
                'maid_profile' => $profile->fresh(),
                'verification' => $verification ? [
                    'status'          => $verification->status,
                    'confidence_score'=> $verification->confidence_score,
                    'reviewed_at'     => $verification->reviewed_at,
                ] : null,
            ], $result['success'] ? 'Verified' : 'Verification returned non-success');
        } catch (\Throwable $e) {
            Log::warning('verifyNin failed for user ' . $user->id . ': ' . $e->getMessage());
            return $this->error('Verification service error: ' . $e->getMessage(), 500);
        }
    }

    public function scanInactive(): JsonResponse
    {
        $users = User::where('status', 'active')
            ->where('last_login_at', '<', now()->subDays(30))
            ->orWhereNull('last_login_at')
            ->where('created_at', '<', now()->subDays(30))
            ->latest()
            ->limit(50)
            ->get(['id', 'name', 'phone', 'email', 'role', 'last_login_at', 'created_at']);

        return $this->success([
            'count' => $users->count(),
            'users' => $users,
        ]);
    }

    public function scanIncompleteMaids(): JsonResponse
    {
        $profiles = MaidProfile::where('is_profile_complete', false)
            ->where('profile_completeness', '<', 80)
            ->with('user:id,name,phone,email')
            ->latest()
            ->limit(50)
            ->get();

        $results = $profiles->map(fn($p) => [
            'user_id' => $p->user_id,
            'name'    => $p->user?->name,
            'phone'   => $p->user?->phone,
            'profile_completeness' => $p->profile_completeness,
            'is_profile_complete'  => $p->is_profile_complete,
            'nin_verified' => $p->nin_verified,
            'created_at'   => $p->created_at,
        ]);

        return $this->success([
            'count' => $results->count(),
            'users' => $results,
        ]);
    }

    public function conversationHistory($id): JsonResponse
    {
        $conversations = AgentConversation::where('user_id', $id)
            ->with(['messages' => fn($q) => $q->latest()->limit(30)])
            ->latest()
            ->limit(10)
            ->get();

        $timeline = $conversations->flatMap(function ($conversation) {
            return $conversation->messages->map(fn($m) => [
                'conversation_id' => $conversation->id,
                'channel'         => $conversation->channel,
                'role'            => $m->role,
                'content'         => $m->content,
                'created_at'      => $m->created_at,
            ]);
        })->sortByDesc('created_at')->take(50)->values();

        return $this->success($timeline);
    }
}
