<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\MaidAssignment;
use App\Models\EmployerPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AgentMatchingController extends ApiController
{
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preference_id' => 'required|integer|exists:employer_preferences,id',
        ]);

        try {
            $preference = EmployerPreference::findOrFail($validated['preference_id']);

            return $this->success([
                'preference_id' => $preference->id,
                'status'        => 'queued',
                'matches'       => [],
                'message'       => 'Matching job queued (ScoutAgent stub)',
            ], 'Matching triggered');
        } catch (\Throwable $e) {
            return $this->error('Failed to trigger matching: ' . $e->getMessage(), 500);
        }
    }

    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employer_id'   => 'required|integer|exists:users,id',
            // Must be a user whose role is 'maid', not merely a user that exists.
            // Assignment #44 was created with maid_id=23 — a maid_profiles.id,
            // whose users.id is 43. users.id 23 is an *employer* ("Adeayo"), so
            // `exists:users,id` passed and an employer was assigned as a
            // housemaid. Ops read this as a join bug; the FK is correct, the
            // check was just too weak to catch the wrong id space.
            'maid_id'       => ['required','integer', \Illuminate\Validation\Rule::exists('users','id')->where('role','maid')],
            'preference_id' => 'required|integer|exists:employer_preferences,id',
            'assignment_type' => 'nullable|string|in:manual,auto,direct_selection,guarantee_match',
            'notes'         => 'nullable|string|max:5000',
        ]);

        try {
            // assigned_by must be an integer user ID (not a string like 'agent')
            // Use 1 as system/agent user ID
            $agentUserId = 1;
            // An open assignment for this employer + preference is the same
            // placement, not a new one. Without this guard employer 460 ended up
            // with two *accepted* assignments for different maids (#44 and #45)
            // and employer 450 with two for the same maid.
            $existing = MaidAssignment::where('employer_id', $validated['employer_id'])
                ->where('preference_id', $validated['preference_id'])
                ->whereIn('status', ['pending_acceptance', 'accepted'])
                ->latest()
                ->first();

            if ($existing && !$request->boolean('replace_existing')) {
                $sameMaid = (int) $existing->maid_id === (int) $validated['maid_id'];
                return $this->success([
                    'assignment_id' => $existing->id,
                    'employer_id'   => $existing->employer_id,
                    'maid_id'       => $existing->maid_id,
                    'status'        => $existing->status,
                    'duplicate'     => true,
                    'hint'          => $sameMaid
                        ? 'This maid is already assigned to this employer.'
                        : 'This employer already has an open assignment for maid '
                          . $existing->maid_id . '. To swap maids, cancel that one first '
                          . 'or resend with replace_existing=true.',
                ], 'Assignment already exists — existing record returned');
            }

            if ($existing && $request->boolean('replace_existing')) {
                $existing->update(['status' => 'cancelled']);
                Log::info('Assignment superseded on request', [
                    'cancelled_id' => $existing->id,
                    'employer_id'  => $validated['employer_id'],
                ]);
            }

            $assignment = MaidAssignment::create([
                'employer_id'    => $validated['employer_id'],
                'maid_id'        => $validated['maid_id'],
                'preference_id'  => $validated['preference_id'],
                'assigned_by'    => $agentUserId,
                'assigned_by_type' => 'agent',
                'assignment_type'  => $validated['assignment_type'] ?? 'manual',
                'status'         => 'pending_acceptance',
                'matching_fee_paid' => false,
                'notes'          => $validated['notes'] ?? null,
            ]);

            return $this->success($assignment, 'Assignment created', [], 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create assignment: ' . $e->getMessage(), 500);
        }
    }

    public function showAssignment($id): JsonResponse
    {
        try {
            $assignment = MaidAssignment::with(['employer', 'maid'])->findOrFail($id);

            return $this->success($assignment, 'Assignment retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve assignment: ' . $e->getMessage(), 500);
        }
    }

    public function updateAssignmentStatus(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending_acceptance,accepted,rejected,completed,cancelled',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $assignment = MaidAssignment::findOrFail($id);
            $assignment->update(['status' => $validated['status']]);

            if ($validated['status'] === 'cancelled') {
                $assignment->update([
                    'cancelled_at'        => now(),
                    'cancellation_reason' => $validated['reason'] ?? null,
                ]);
            }

            return $this->success($assignment->fresh(), 'Assignment status updated');
        } catch (\Throwable $e) {
            return $this->error('Failed to update assignment status: ' . $e->getMessage(), 500);
        }
    }

    public function scanNoStartDate(): JsonResponse
    {
        try {
            $assignments = MaidAssignment::where('status', 'accepted')
                ->whereNull('start_date')
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'count'       => $assignments->count(),
                'assignments' => $assignments,
            ], 'Assignments without start date');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan no-start-date: ' . $e->getMessage(), 500);
        }
    }

    public function scanExpiringSoon(): JsonResponse
    {
        try {
            $cutoff = now()->subDays(83);
            $upcoming = now()->addDays(7);

            $assignments = MaidAssignment::where('status', 'accepted')
                ->where('created_at', '>=', $cutoff)
                ->where('created_at', '<=', $upcoming)
                ->whereNull('end_date')
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get()
                ->filter(fn($a) => $a->created_at->addDays(90)->isFuture())
                ->values();

            return $this->success([
                'count'       => $assignments->count(),
                'assignments' => $assignments,
            ], 'Expiring soon assignments');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan expiring soon: ' . $e->getMessage(), 500);
        }
    }
}
