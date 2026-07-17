<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Models\MaidProfile;
use App\Models\EmployerPreference;
use App\Models\AgentConversation;
use App\Models\AgentMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    /**
     * Generate all plausible phone format variants for lookup.
     *
     * Nigerian numbers commonly appear as:
     *   - 08012345678  (local, with leading 0)
     *   - 2348012345678 (international, 13 digits)
     *   - +2348012345678 (with + prefix)
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

        return $this->success(['user_id' => $user->id, 'existed' => false], 'User created', [], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'  => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|in:active,suspended,pending',
        ]);

        $user->update($validated);

        return $this->success($user->fresh(), 'User updated');
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
