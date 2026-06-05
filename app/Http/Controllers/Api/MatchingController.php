<?php

namespace App\Http\Controllers\Api;

use App\Events\MatchingJobCompleted;
use App\Http\Requests\Api\Matching\{RequestMatchRequest, ReviewMatchRequest};
use App\Models\AiMatchingQueue;
use App\Models\EmployerPreference;
use App\Services\MatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class MatchingController extends ApiController
{
    protected MatchingService $matchingService;

    public function __construct(MatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Request AI matching for an employer.
     */
    public function requestMatch(RequestMatchRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        if ($user->role !== 'employer') {
            return $this->forbidden('Only employers can request matching.');
        }

        // Check employer has sufficient balance for matching fee
        $matchingFee = config('services.matching_fee', 5000);
        $walletService = app(\App\Services\WalletService::class);
        $balanceCheck = $walletService->checkBalance($user->id, $matchingFee);

        if (!$balanceCheck['has_sufficient']) {
            return $this->error('Insufficient wallet balance for matching fee.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'required' => $matchingFee,
                'available' => $balanceCheck['balance'],
            ]);
        }

        $preference = null;
        if (!empty($validated['preference_id'])) {
            $preference = EmployerPreference::findOrFail($validated['preference_id']);
            if ($preference->employer_id !== $user->id) {
                return $this->forbidden('Unauthorized access to this preference.');
            }

            $jobType = is_array($preference->help_types) ? ($preference->help_types[0] ?? 'full_time') : 'full_time';
            $location = $preference->location ?? '';
            $salaryMin = $preference->budget_min ?? 10000;
            $salaryMax = $preference->budget_max ?? 20000;
        } else {
            $jobType = $validated['job_type'];
            $location = $validated['location'];
            $salaryMin = $validated['salary_min'];
            $salaryMax = $validated['salary_max'];
        }

        // Create matching job
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $user->id,
            'preference_id' => $preference?->id,
            'priority' => $validated['priority'] ?? 5,
            'payload' => [
                'requirements' => [
                    'job_type' => $jobType,
                    'location' => $location,
                    'salary_range' => [
                        'min' => $salaryMin,
                        'max' => $salaryMax,
                    ],
                    'salary_day' => $validated['salary_day'] ?? 1,
                    'skills' => $validated['skills'] ?? [],
                    'experience_years' => $validated['experience_years'] ?? null,
                    'age_preference' => $validated['age_preference'] ?? null,
                    'language' => $validated['language'] ?? null,
                    'religion' => $validated['religion'] ?? null,
                    'additional_requirements' => $validated['additional_requirements'] ?? null,
                ],
            ],
            'status' => 'pending',
            'max_attempts' => 3,
            'retry_delay_minutes' => 5,
        ]);
 
        return $this->success([
            'job_id' => $job->job_id,
            'status' => $job->status,
            'estimated_processing_time' => '5-15 minutes',
        ], 'Matching request submitted successfully.');
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
            return $this->forbidden('Unauthorized access.');
        }

        return $this->success([
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
        ], 'Job status retrieved successfully');
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
            return $this->forbidden('Unauthorized access.');
        }

        if ($job->status !== 'completed') {
            return $this->error('Matching job not yet completed. Current status: ' . $job->status, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->success([
            'job_id' => $job->job_id,
            'status' => $job->status,
            'matches' => $job->match_candidates ?? [],
            'ai_confidence_score' => $job->ai_confidence_score,
            'ai_reasoning' => $job->ai_reasoning,
            'ai_analysis_data' => $job->ai_analysis_data,
            'assignment_created' => $job->assignment_id !== null,
            'assignment_id' => $job->assignment_id,
        ], 'Matching results retrieved successfully');
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
 
        return $this->paginated($jobs, 'Matching history retrieved successfully');
    }

    /**
     * Cancel a pending matching job.
     */
    public function cancel(string $jobId): JsonResponse
    {
        $user = Auth::user();

        $job = AiMatchingQueue::where('job_id', $jobId)->firstOrFail();

        if ($job->employer_id !== $user->id) {
            return $this->forbidden('Unauthorized access.');
        }

        if (!in_array($job->status, ['pending', 'scheduled'])) {
            return $this->error('Cannot cancel job with status: ' . $job->status, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $job->cancel();
 
        return $this->success(null, 'Matching job cancelled successfully.');
    }

    /**
     * Get matching queue statistics (admin only).
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->forbidden('Unauthorized. Admin access required.');
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

        return $this->success($stats, 'Matching queue statistics retrieved successfully');
    }

    /**
     * Get jobs requiring review (admin only).
     */
    public function pendingReview(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->forbidden('Unauthorized. Admin access required.');
        }

        $jobs = AiMatchingQueue::requiresReview()
            ->with('employer')
            ->orderBy('priority')
            ->orderBy('created_at')
            ->paginate(20);

        return $this->paginated($jobs, 'Jobs requiring review retrieved successfully');
    }

    /**
     * Review a matching job (admin only).
     */
    public function review(ReviewMatchRequest $request, string $jobId): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();
 
        if ($user->role !== 'admin') {
            return $this->forbidden('Unauthorized. Admin access required.');
        }

        $job = AiMatchingQueue::where('job_id', $jobId)->firstOrFail();

        $job->review($user->id, $validated['decision'], $validated['notes'] ?? null);
 
        // If approved and has results, fire completion event
        if ($validated['decision'] === 'approve' && $job->result) {
            MatchingJobCompleted::dispatch(
                $job,
                $job->result,
                count($job->match_candidates ?? []),
                $job->processing_duration_ms / 1000,
                ['admin_reviewed' => true, 'reviewer_id' => $user->id]
            );
        }
 
        return $this->success([
            'job_id' => $job->job_id,
            'decision' => $validated['decision'],
            'reviewed_at' => $job->reviewed_at,
        ], 'Job review submitted successfully.');
    }
}
