<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\EmployerPreference;
use App\Models\MaidAssignment;
use App\Models\SalesPipeline;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesController extends ApiController
{
    public function pipeline(int $userId): JsonResponse
    {
        try {
            $pipeline = SalesPipeline::firstOrCreate(
                ['user_id' => $userId],
                [
                    'funnel_stage'   => 'lead',
                    'lead_score'     => 0,
                    'outreach_count' => 0,
                ]
            );

            $pipeline->load('user');

            return $this->success($pipeline, 'Sales pipeline retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve sales pipeline: ' . $e->getMessage(), 500);
        }
    }

    public function scanHotLeads(): JsonResponse
    {
        try {
            $pipelines = SalesPipeline::where('lead_score', '>=', 80)
                ->whereHas('user', fn($q) => $q->where('status', 'active'))
                ->whereDoesntHave('user.employerPreferences', fn($q) => $q->where('matching_status', 'paid'))
                ->with('user:id,name,phone,email')
                ->get();

            return $this->success([
                'count' => $pipelines->count(),
                'leads' => $pipelines,
            ], 'Hot leads');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan hot leads: ' . $e->getMessage(), 500);
        }
    }

    public function scanWarmLeads(): JsonResponse
    {
        try {
            $pipelines = SalesPipeline::whereBetween('lead_score', [50, 79])
                ->whereHas('user.employerPreferences', fn($q) => $q->where('quiz_status', 'in_progress'))
                ->with('user:id,name,phone,email')
                ->get();

            return $this->success([
                'count' => $pipelines->count(),
                'leads' => $pipelines,
            ], 'Warm leads');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan warm leads: ' . $e->getMessage(), 500);
        }
    }

    public function scanPaymentPending(): JsonResponse
    {
        try {
            $preferences = EmployerPreference::where('matches_shown_at', '<', now()->subHour())
                ->whereNotNull('matches_shown_at')
                ->where('matching_status', '!=', 'paid')
                ->with('employer:id,name,phone,email')
                ->get();

            return $this->success([
                'count'       => $preferences->count(),
                'preferences' => $preferences,
            ], 'Payment pending leads');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan payment pending: ' . $e->getMessage(), 500);
        }
    }

    public function scanWinbackRecent(): JsonResponse
    {
        try {
            $users = User::whereHas('roles', fn($q) => $q->where('name', 'employer'))
                ->whereHas('employerPreferences', function ($q) {
                    $q->whereHas('assignments', fn($sub) => $sub->whereNotNull('ended_at')
                        ->whereBetween('ended_at', [now()->subDays(30), now()->subDays(14)]));
                })
                ->get(['id', 'name', 'phone', 'email']);

            return $this->success([
                'count' => $users->count(),
                'users' => $users,
            ], 'Winback recent');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan winback recent: ' . $e->getMessage(), 500);
        }
    }

    public function scanWinbackLapsed(): JsonResponse
    {
        try {
            $users = User::whereHas('roles', fn($q) => $q->where('name', 'employer'))
                ->where(function ($q) {
                    $q->where('last_login_at', '<', now()->subDays(45))
                      ->orWhereNull('last_login_at');
                })
                ->where('created_at', '<', now()->subDays(45))
                ->get(['id', 'name', 'phone', 'email', 'last_login_at']);

            return $this->success([
                'count' => $users->count(),
                'users' => $users,
            ], 'Winback lapsed');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan winback lapsed: ' . $e->getMessage(), 500);
        }
    }

    public function scanUpsellCandidates(): JsonResponse
    {
        try {
            $preferences = EmployerPreference::where('matching_status', 'paid')
                ->whereHas('payment', fn($q) => $q->where('paid_at', '<', now()->subHours(48)))
                ->whereDoesntHave('employer.bookingsAsEmployer', fn($q) => $q->where('status', 'accepted'))
                ->with('employer:id,name,phone,email')
                ->get();

            return $this->success([
                'count'       => $preferences->count(),
                'preferences' => $preferences,
            ], 'Upsell candidates');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan upsell candidates: ' . $e->getMessage(), 500);
        }
    }

    public function updatePipeline(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'funnel_stage' => 'nullable|string|max:100',
            'lead_score'   => 'nullable|integer|min:0|max:100',
            'notes'        => 'nullable|string|max:5000',
        ]);

        try {
            $pipeline = SalesPipeline::firstOrCreate(
                ['user_id' => $userId],
                ['funnel_stage' => 'lead', 'lead_score' => 0, 'outreach_count' => 0]
            );

            $pipeline->update($validated);

            return $this->success($pipeline->fresh(), 'Pipeline updated');
        } catch (\Throwable $e) {
            return $this->error('Failed to update pipeline: ' . $e->getMessage(), 500);
        }
    }

    public function logEvent(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string|max:100',
            'notes'      => 'nullable|string|max:5000',
            'outcome'    => 'nullable|string|max:50',
        ]);

        try {
            $pipeline = SalesPipeline::firstOrCreate(
                ['user_id' => $userId],
                ['funnel_stage' => 'lead', 'lead_score' => 0, 'outreach_count' => 0]
            );

            $pipeline->update([
                'last_outreach_at' => now(),
                'outreach_count'   => $pipeline->outreach_count + 1,
            ]);

            $actions = $pipeline->actions_taken ?? [];
            $actions[] = [
                'event_type' => $validated['event_type'],
                'notes'      => $validated['notes'] ?? '',
                'outcome'    => $validated['outcome'] ?? '',
                'timestamp'  => now()->toIso8601String(),
            ];
            $pipeline->update(['actions_taken' => $actions]);

            return $this->success($pipeline->fresh(), 'Event logged');
        } catch (\Throwable $e) {
            return $this->error('Failed to log event: ' . $e->getMessage(), 500);
        }
    }

    public function actionTaken(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'action'           => 'required|string|max:100',
            'channel'          => 'required|string|max:50',
            'message_preview'  => 'nullable|string|max:500',
        ]);

        try {
            $pipeline = SalesPipeline::firstOrCreate(
                ['user_id' => $userId],
                ['funnel_stage' => 'lead', 'lead_score' => 0, 'outreach_count' => 0]
            );

            $pipeline->update([
                'last_outreach_at'     => now(),
                'outreach_count'       => $pipeline->outreach_count + 1,
                'outreach_channel'     => $validated['channel'],
                'last_message_preview' => $validated['message_preview'] ?? '',
            ]);

            $actions = $pipeline->actions_taken ?? [];
            $actions[] = [
                'action'   => $validated['action'],
                'channel'  => $validated['channel'],
                'message'  => $validated['message_preview'] ?? '',
                'timestamp' => now()->toIso8601String(),
            ];
            $pipeline->update(['actions_taken' => $actions]);

            return $this->success($pipeline->fresh(), 'Action recorded');
        } catch (\Throwable $e) {
            return $this->error('Failed to record action: ' . $e->getMessage(), 500);
        }
    }
}
