<?php

namespace App\Http\Controllers\Api;

use App\Events\AssignmentAccepted;
use App\Events\AssignmentRejected;
use App\Events\AssignmentCompleted;
use App\Http\Requests\Api\Assignment\{AcceptAssignmentRequest, RejectAssignmentRequest, CompleteAssignmentRequest};
use App\Models\MaidAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends ApiController
{
    /**
     * Get assignments for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = MaidAssignment::query();

        if ($user->role === 'employer') {
            $query->where('employer_id', $user->id);
        } elseif ($user->role === 'maid') {
            $query->where('maid_id', $user->id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $assignments = $query->with(['employer', 'maid', 'salarySchedule'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($assignments, 'Assignments retrieved successfully');
    }

    /**
     * Get a specific assignment.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $assignment = MaidAssignment::with(['employer', 'maid', 'salarySchedule', 'salaryPayments'])
            ->findOrFail($id);

        // Check authorization
        if ($user->role === 'employer' && $assignment->employer_id !== $user->id) {
            return $this->forbidden('Unauthorized access to this assignment.');
        }

        if ($user->role === 'maid' && $assignment->maid_id !== $user->id) {
            return $this->forbidden('Unauthorized access to this assignment.');
        }

        return $this->success($assignment, 'Assignment details retrieved successfully');
    }

    /**
     * Accept an assignment (employer only).
     */
    public function accept(AcceptAssignmentRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $assignment = MaidAssignment::findOrFail($id);

        if ($assignment->employer_id !== $user->id) {
            return $this->forbidden('Unauthorized access to this assignment.');
        }

        if ($assignment->status !== 'pending_acceptance') {
            return $this->error('Assignment cannot be accepted. Current status: ' . $assignment->status, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update assignment
        $assignment->update([
            'status' => 'accepted',
            'started_at' => $validated['start_date'] ?? now()->addDays(7),
            'employer_accepted_at' => now(),
            'context_json' => array_merge($assignment->context_json ?? [], [
                'acceptance_notes' => $validated['notes'] ?? null,
                'accepted_by' => $user->id,
            ]),
        ]);

        // Fire event
        AssignmentAccepted::dispatch($assignment);

        return $this->success($assignment->fresh(), 'Assignment accepted successfully.');
    }

    /**
     * Reject an assignment (employer only).
     */
    public function reject(RejectAssignmentRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $assignment = MaidAssignment::findOrFail($id);

        if ($assignment->employer_id !== $user->id) {
            return $this->forbidden('Unauthorized access to this assignment.');
        }

        if ($assignment->status !== 'pending_acceptance') {
            return $this->error('Assignment cannot be rejected. Current status: ' . $assignment->status, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update assignment
        $assignment->update([
            'status' => 'rejected',
            'employer_rejected_at' => now(),
            'context_json' => array_merge($assignment->context_json ?? [], [
                'rejection_reason' => $validated['reason'],
                'request_replacement' => $validated['request_replacement'] ?? true,
                'rejected_by' => $user->id,
            ]),
        ]);

        // Fire event
        AssignmentRejected::dispatch($assignment, $validated['reason']);

        return $this->success($assignment->fresh(), 'Assignment rejected successfully.');
    }

    /**
     * Complete an assignment (employer only).
     */
    public function complete(CompleteAssignmentRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $assignment = MaidAssignment::findOrFail($id);

        if ($assignment->employer_id !== $user->id) {
            return $this->forbidden('Unauthorized access to this assignment.');
        }

        if (!in_array($assignment->status, ['active', 'accepted'])) {
            return $this->error('Assignment cannot be completed. Current status: ' . $assignment->status, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update assignment
        $assignment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'context_json' => array_merge($assignment->context_json ?? [], [
                'completion_notes' => $validated['completion_notes'] ?? null,
                'rating' => $validated['rating'] ?? null,
                'feedback' => $validated['feedback'] ?? null,
                'completed_by' => $user->id,
            ]),
        ]);

        // Fire event
        AssignmentCompleted::dispatch($assignment);

        return $this->success($assignment->fresh(), 'Assignment completed successfully.');
    }

    /**
     * Get assignment statistics (admin only).
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->forbidden('Unauthorized. Admin access required.');
        }

        $stats = [
            'total' => MaidAssignment::count(),
            'pending_acceptance' => MaidAssignment::where('status', 'pending_acceptance')->count(),
            'accepted' => MaidAssignment::where('status', 'accepted')->count(),
            'active' => MaidAssignment::where('status', 'active')->count(),
            'completed' => MaidAssignment::where('status', 'completed')->count(),
            'rejected' => MaidAssignment::where('status', 'rejected')->count(),
            'cancelled' => MaidAssignment::where('status', 'cancelled')->count(),
            'this_month' => MaidAssignment::whereMonth('created_at', now()->month)->count(),
        ];

        return $this->success($stats, 'Assignment statistics retrieved successfully');
    }
}
