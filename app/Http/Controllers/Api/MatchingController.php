<?php

namespace App\Http\Controllers\Api;

use App\Events\MatchingJobCompleted;
use App\Http\Controllers\Controller;
use App\Models\AiMatchingQueue;
use App\Services\MatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MatchingController extends Controller
{
    protected MatchingService $matchingService;

    public function __construct(MatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Request AI matching for an employer.
     */
    public function requestMatch(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return response()->json([
                'success' => false,
                'message' => 'Only employers can request matching.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'job_type' => 'required|string|in:full_time,part_time,live_in,live_out',
            'location' => 'required|string|max:255',
            'salary_min' => 'required|numeric|min:10000',
            'salary_max' => 'required|numeric|gte:salary_min',
            'salary_day' => 'nullable|integer|min:1|max:31',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'experience_years' => 'nullable|integer|min:0',
            'age_preference' => 'nullable|string',
            'language' => 'nullable|string',
            'religion' => 'nullable|string',
            'additional_requirements' => 'nullable|string|max:2000',
            'priority' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check employer has sufficient balance for matching fee
        $matchingFee = config('services.matching_fee', 5000);
        $walletService = app(\App\Services\WalletService::class);
        $balanceCheck = $walletService->checkBalance($user->id, $matchingFee);

        if (!$balanceCheck['has_sufficient']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance for matching fee.',
                'data' => [
                    'required' => $matchingFee,
                    'available' => $balanceCheck['balance'],
                ],
            ], 422);
        }

        // Create matching job
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $user->id,
            'priority' => $request->priority ?? 5,
            'payload' => [
                'requirements' => [
                    'job_type' => $request->job_type,
                    'location' => $request->location,
                    'salary_range' => [
                        'min' => $request->salary_min,
                        'max' => $request->salary_max,
                    ],
                    'salary_day' => $request->salary_day ?? 1,
                    'skills' => $request->skills ?? [],
                    'experience_years' => $request->experience_years,
                    'age_preference' => $request->age_preference,
                    'language' => $request->language,
                    'religion' => $request->religion,
                    'additional_requirements' => $request->additional_requirements,
                ],
            ],
            'status' => 'pending',
            'max_attempts' => 3,
            'retry_delay_minutes' => 5,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Matching request submitted successfully.',
            'data' => [
                'job_id' => $job->job_id,
                'status' => $job->status,
                'estimated_processing_time' => '5-15 minutes',
            ],
        ]);
    }

    /**
     * Get matching job status.
     */
    public function status(string $jobId): JsonResponse
    {
        $user = Auth::user();

        $job = AiMatchingQueue::where('job_id', $jobId)->firstOrFail();

        // Check authorization
        if ($user->role === 'employer' && $job->employer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $job->job_id,
                'status' => $job->status,
                'job_type' => $job->job_type,
                'priority' => $job->priority,
                'created_at' => $job->created_at,
                'started_at' => $job->started_at,
                'completed_at' => $job->completed_at,
                'processing_duration' => $job->duration_formatted,
                'result' => $job->status === 'completed' ? $job->result : null,
                'match_candidates' => $job->status === 'completed' ? $job->match_candidates : null,
                'ai_confidence_score' => $job->ai_confidence_score,
                'ai_reasoning' => $job->ai_reasoning,
                'requires_review' => $job->requires_review,
                'error' => $job->last_error,
            ],
        ]);
    }

    /**
     * Get matching results.
     */
    public function results(string $jobId): JsonResponse
    {
        $user = Auth::user();

        $job = AiMatchingQueue::where('job_id', $jobId)->firstOrFail();

        // Check authorization
        if ($user->role === 'employer' && $job->employer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        if ($job->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Matching job not yet completed. Current status: ' . $job->status,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $job->job_id,
                'matches' => $job->match_candidates ?? [],
                'ai_confidence_score' => $job->ai_confidence_score,
                'ai_reasoning' => $job->ai_reasoning,
                'ai_analysis_data' => $job->ai_analysis_data,
                'assignment_created' => $job->assignment_id !== null,
                'assignment_id' => $job->assignment_id,
            ],
        ]);
    }

    /**
     * Get user's matching history.
     */
    public function history(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = AiMatchingQueue::query();

        if ($user->role === 'employer') {
            $query->where('employer_id', $user->id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $jobs = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

    /**
     * Cancel a pending matching job.
     */
    public function cancel(string $jobId): JsonResponse
    {
        $user = Auth::user();

        $job = AiMatchingQueue::where('job_id', $jobId)->firstOrFail();

        if ($job->employer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        if (!in_array($job->status, ['pending', 'scheduled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel job with status: ' . $job->status,
            ], 422);
        }

        $job->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Matching job cancelled successfully.',
        ]);
    }

    /**
     * Get matching queue statistics (admin only).
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
            'total_jobs' => AiMatchingQueue::count(),
            'pending' => AiMatchingQueue::where('status', 'pending')->count(),
            'processing' => AiMatchingQueue::where('status', 'processing')->count(),
            'completed' => AiMatchingQueue::where('status', 'completed')->count(),
            'failed' => AiMatchingQueue::where('status', 'failed')->count(),
            'requires_review' => AiMatchingQueue::where('requires_review', true)->count(),
            'avg_processing_time' => AiMatchingQueue::whereNotNull('processing_duration_ms')
                ->avg('processing_duration_ms'),
            'today_completed' => AiMatchingQueue::where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get jobs requiring review (admin only).
     */
    public function pendingReview(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $jobs = AiMatchingQueue::requiresReview()
            ->with('employer')
            ->orderBy('priority')
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

    /**
     * Review a matching job (admin only).
     */
    public function review(Request $request, string $jobId): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $job = AiMatchingQueue::where('job_id', $jobId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'decision' => 'required|string|in:approve,reject,retry',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $job->review($user->id, $request->decision, $request->notes);

        // If approved and has results, fire completion event
        if ($request->decision === 'approve' && $job->result) {
            MatchingJobCompleted::dispatch(
                $job,
                $job->result,
                count($job->match_candidates ?? []),
                $job->processing_duration_ms / 1000,
                ['admin_reviewed' => true, 'reviewer_id' => $user->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Job review submitted successfully.',
            'data' => [
                'job_id' => $job->job_id,
                'decision' => $request->decision,
                'reviewed_at' => $job->reviewed_at,
            ],
        ]);
    }
}
