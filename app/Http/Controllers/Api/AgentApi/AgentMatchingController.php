<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\MaidAssignment;
use App\Models\EmployerPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'maid_id'       => 'required|integer|exists:users,id',
            'preference_id' => 'required|integer|exists:employer_preferences,id',
            'assignment_type' => 'nullable|string|in:manual,auto,direct_selection,guarantee_match',
            'notes'         => 'nullable|string|max:5000',
        ]);

        try {
            $assignment = MaidAssignment::create([
                'employer_id'    => $validated['employer_id'],
                'maid_id'        => $validated['maid_id'],
                'preference_id'  => $validated['preference_id'],
                'assigned_by'    => null,
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

    public function showAssignment(int $id): JsonResponse
    {
        try {
            $assignment = MaidAssignment::with(['employer', 'maid'])->findOrFail($id);

            return $this->success($assignment, 'Assignment retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve assignment: ' . $e->getMessage(), 500);
        }
    }

    public function updateAssignmentStatus(Request $request, int $id): JsonResponse
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
