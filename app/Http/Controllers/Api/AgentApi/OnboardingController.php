<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgentNote;
use App\Models\EmployerPreference;
use App\Models\MaidProfile;
use App\Models\OnboardingJourney;
use App\Models\OnboardingTouchpoint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends ApiController
{
    public function show(int $userId): JsonResponse
    {
        try {
            $journey = OnboardingJourney::firstOrCreate(
                ['user_id' => $userId],
                [
                    'status'         => 'in_progress',
                    'current_step'   => 'registered',
                    'completion_pct' => 0,
                    'last_activity_at' => now(),
                ]
            );

            $journey->load('touchpoints');

            return $this->success($journey, 'Onboarding journey retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve onboarding journey: ' . $e->getMessage(), 500);
        }
    }

    public function scanNeedsWelcomeCall(): JsonResponse
    {
        try {
            $users = User::where('created_at', '>=', now()->subHours(3))
                ->where('created_at', '<=', now()->subHour())
                ->whereHas('roles', fn($q) => $q->where('name', 'employer'))
                ->whereDoesntHave('onboardingJourney.touchpoints')
                ->get(['id', 'name', 'phone', 'email', 'created_at']);

            return $this->success([
                'count' => $users->count(),
                'users' => $users,
            ], 'Users needing welcome call');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan for welcome calls: ' . $e->getMessage(), 500);
        }
    }

    public function scanQuizAbandoned(): JsonResponse
    {
        try {
            $preferences = EmployerPreference::where('quiz_status', 'in_progress')
                ->where('quiz_started_at', '<', now()->subHours(2))
                ->with('employer:id,name,phone,email')
                ->get();

            return $this->success([
                'count'       => $preferences->count(),
                'preferences' => $preferences,
            ], 'Abandoned quiz scans');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan quiz abandoned: ' . $e->getMessage(), 500);
        }
    }

    public function scanAwaitingPayment(): JsonResponse
    {
        try {
            $preferences = EmployerPreference::where('matching_status', '!=', 'paid')
                ->where('matches_shown_at', '<', now()->subHour())
                ->whereNotNull('matches_shown_at')
                ->with('employer:id,name,phone,email')
                ->get();

            return $this->success([
                'count'       => $preferences->count(),
                'preferences' => $preferences,
            ], 'Awaiting payment scans');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan awaiting payment: ' . $e->getMessage(), 500);
        }
    }

    public function scanMaidProfileIncomplete(): JsonResponse
    {
        try {
            $profiles = MaidProfile::where('profile_completeness', '<', 80)
                ->where('created_at', '<', now()->subHours(24))
                ->with('user:id,name,phone,email')
                ->get();

            return $this->success([
                'count'    => $profiles->count(),
                'profiles' => $profiles,
            ], 'Incomplete maid profiles');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan incomplete maid profiles: ' . $e->getMessage(), 500);
        }
    }

    public function scanNinPending(): JsonResponse
    {
        try {
            $profiles = MaidProfile::where('nin_verified', false)
                ->where('created_at', '<', now()->subHours(48))
                ->with('user:id,name,phone,email')
                ->get();

            return $this->success([
                'count'    => $profiles->count(),
                'profiles' => $profiles,
            ], 'NIN pending scans');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan NIN pending: ' . $e->getMessage(), 500);
        }
    }

    public function scanAbandoned(): JsonResponse
    {
        try {
            $journeys = OnboardingJourney::where('last_activity_at', '<', now()->subDays(7))
                ->orWhereNull('last_activity_at')
                ->where('created_at', '<', now()->subDays(7))
                ->with('user:id,name,phone,email')
                ->get();

            return $this->success([
                'count'    => $journeys->count(),
                'journeys' => $journeys,
            ], 'Abandoned onboarding journeys');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan abandoned journeys: ' . $e->getMessage(), 500);
        }
    }

    public function touchpoints(int $journeyId): JsonResponse
    {
        try {
            $touchpoints = OnboardingTouchpoint::where('journey_id', $journeyId)->latest()->get();

            return $this->success($touchpoints, 'Touchpoints retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve touchpoints: ' . $e->getMessage(), 500);
        }
    }

    public function storeTouchpoint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'journey_id'      => 'required|integer|exists:onboarding_journeys,id',
            'user_id'         => 'required|integer|exists:users,id',
            'touchpoint_type' => 'required|string|max:100',
            'channel'         => 'nullable|string|max:50',
            'status'          => 'nullable|string|max:50',
            'notes'           => 'nullable|string|max:5000',
            'sent_at'         => 'nullable|date',
        ]);

        try {
            $touchpoint = OnboardingTouchpoint::create(array_merge($validated, [
                'sent_at' => $validated['sent_at'] ?? now(),
            ]));

            OnboardingJourney::where('id', $validated['journey_id'])->update([
                'last_activity_at' => now(),
            ]);

            return $this->success($touchpoint, 'Touchpoint created', [], 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create touchpoint: ' . $e->getMessage(), 500);
        }
    }

    public function updateMilestone(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'milestone' => 'required|string|in:quiz_started,quiz_completed,payment_confirmed,nin_submitted,profile_updated',
            'value'     => 'nullable',
        ]);

        try {
            $milestone = $validated['milestone'];
            $updates = [];

            switch ($milestone) {
                case 'quiz_started':
                    $preference = EmployerPreference::where('employer_id', $userId)->latest()->first();
                    if ($preference) {
                        $preference->update(['quiz_status' => 'in_progress', 'quiz_started_at' => now()]);
                    }
                    $updates = ['current_step' => 'quiz_started', 'completion_pct' => 20];
                    break;
                case 'quiz_completed':
                    $preference = EmployerPreference::where('employer_id', $userId)->latest()->first();
                    if ($preference) {
                        $preference->update(['quiz_status' => 'completed', 'quiz_completed_at' => now()]);
                    }
                    $updates = ['current_step' => 'quiz_completed', 'completion_pct' => 40];
                    break;
                case 'payment_confirmed':
                    $updates = ['current_step' => 'payment_confirmed', 'completion_pct' => 60];
                    break;
                case 'nin_submitted':
                    $updates = ['current_step' => 'nin_submitted', 'completion_pct' => 60];
                    break;
                case 'profile_updated':
                    $updates = ['current_step' => 'profile_updated', 'completion_pct' => 80];
                    break;
            }

            $journey = OnboardingJourney::where('user_id', $userId)->first();
            if ($journey) {
                $journey->update(array_merge($updates, ['last_activity_at' => now()]));
            }

            return $this->success([
                'milestone' => $milestone,
                'journey'   => $journey?->fresh(),
            ], 'Milestone updated');
        } catch (\Throwable $e) {
            return $this->error('Failed to update milestone: ' . $e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request, int $journeyId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:in_progress,completed,abandoned,on_hold',
        ]);

        try {
            $journey = OnboardingJourney::findOrFail($journeyId);
            $journey->update([
                'status'           => $validated['status'],
                'last_activity_at' => now(),
                'converted_at'     => $validated['status'] === 'completed' ? now() : $journey->converted_at,
            ]);

            return $this->success($journey, 'Journey status updated');
        } catch (\Throwable $e) {
            return $this->error('Failed to update journey status: ' . $e->getMessage(), 500);
        }
    }

    public function storeNote(Request $request, int $journeyId): JsonResponse
    {
        $validated = $request->validate([
            'note'         => 'required|string|max:5000',
            'action_taken' => 'nullable|string|max:100',
            'outcome'      => 'nullable|string|max:50',
            'next_action'  => 'nullable|string|max:255',
            'metadata'     => 'nullable|array',
        ]);

        try {
            OnboardingJourney::findOrFail($journeyId);

            $note = AgentNote::create(array_merge($validated, [
                'entity_type'    => 'onboarding_journey',
                'entity_id'      => $journeyId,
                'agent_type'     => request()->agent_api_key->agent_type ?? null,
                'agent_user_id'  => null,
            ]));

            return $this->success($note, 'Note created', [], 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create note: ' . $e->getMessage(), 500);
        }
    }
}
