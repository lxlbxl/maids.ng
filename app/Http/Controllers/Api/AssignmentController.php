<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Events\AssignmentAccepted;
use App\Events\AssignmentRejected;
use App\Events\AssignmentCompleted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AssignmentController extends Controller
{
    /**
     * Get assignments for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Assignment::query();

        if ($user->role === 'employer') {
            $query->where('employer_id', $user->id);
        } elseif ($user->role === 'maid') {
            $query->where('maid_id', $user->id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $assignments = $query->with(['employer', 'maid', 'salarySchedules'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    /**
     * Get a specific assignment.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $assignment = Assignment::with(['employer', 'maid', 'salarySchedules', 'payments'])
            ->findOrFail($id);

        // Check authorization
        if ($user->role === 'employer' && $assignment->employer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this assignment.',
            ], 403);
        }

        if ($user->role === 'maid' && $assignment->maid_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this assignment.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    /**
     * Accept an assignment (employer only).
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return response()->json([
                'success' => false,
                'message' => 'Only employers can accept assignments.',
            ], 403);
        }

        $assignment = Assignment::findOrFail($id);

        if ($assignment->employer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this assignment.',
            ], 403);
        }

        if ($assignment->status !== 'pending_acceptance') {
            return response()->json([
                'success' => false,
                'message' => 'Assignment cannot be accepted. Current status: ' . $assignment->status,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update assignment
        $assignment->update([
            'status' => 'accepted',
            'start_date' => $request->start_date ?? now()->addDays(7),
            'accepted_at' => now(),
            'context_json' => array_merge($assignment->context_json ?? [], [
                'acceptance_notes' => $request->notes,
                'accepted_by' => $user->id,
            ]),
        ]);

        // Fire event
        AssignmentAccepted::dispatch($assignment);

        return response()->json([
            'success' => true,
            'message' => 'Assignment accepted successfully.',
            'data' => $assignment->fresh(),
        ]);
    }

    /**
     * Reject an assignment (employer only).
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return response()->json([
                'success' => false,
                'message' => 'Only employers can reject assignments.',
            ], 403);
        }

        $assignment = Assignment::findOrFail($id);

        if ($assignment->employer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this assignment.',
            ], 403);
        }

        if ($assignment->status !== 'pending_acceptance') {
            return response()->json([
                'success' => false,
                'message' => 'Assignment cannot be rejected. Current status: ' . $assignment->status,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
            'request_replacement' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update assignment
        $assignment->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'context_json' => array_merge($assignment->context_json ?? [], [
                'rejection_reason' => $request->reason,
                'request_replacement' => $request->boolean('request_replacement', true),
                'rejected_by' => $user->id,
            ]),
        ]);

        // Fire event
        AssignmentRejected::dispatch($assignment, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Assignment rejected successfully.',
            'data' => $assignment->fresh(),
        ]);
    }

    /**
     * Complete an assignment (employer only).
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return response()->json([
                'success' => false,
                'message' => 'Only employers can complete assignments.',
            ], 403);
        }

        $assignment = Assignment::findOrFail($id);

        if ($assignment->employer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this assignment.',
            ], 403);
        }

        if ($assignment->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Assignment cannot be completed. Current status: ' . $assignment->status,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'completion_notes' => 'nullable|string|max:1000',
            'rating' => 'nullable|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update assignment
        $assignment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'context_json' => array_merge($assignment->context_json ?? [], [
                'completion_notes' => $request->completion_notes,
                'rating' => $request->rating,
                'feedback' => $request->feedback,
                'completed_by' => $user->id,
            ]),
        ]);

        // Fire event
        AssignmentCompleted::dispatch($assignment);

        return response()->json([
            'success' => true,
            'message' => 'Assignment completed successfully.',
            'data' => $assignment->fresh(),
        ]);
    }

    /**
     * Get assignment statistics (admin only).
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $stats = [
            'total' => Assignment::count(),
            'pending_acceptance' => Assignment::where('status', 'pending_acceptance')->count(),
            'accepted' => Assignment::where('status', 'accepted')->count(),
            'active' => Assignment::where('status', 'active')->count(),
            'completed' => Assignment::where('status', 'completed')->count(),
            'rejected' => Assignment::where('status', 'rejected')->count(),
            'cancelled' => Assignment::where('status', 'cancelled')->count(),
            'this_month' => Assignment::whereMonth('created_at', now()->month)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
