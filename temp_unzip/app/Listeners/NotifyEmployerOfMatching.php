<?php

namespace App\Listeners;

use App\Events\MatchingJobCompleted;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyEmployerOfMatching implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(MatchingJobCompleted $event): void
    {
        $job = $event->job;
        $results = $event->results;
        $matchesFound = $event->matchesFound;
        $processingTime = $event->processingTime;

        // Only notify if there's an employer associated with this job
        if (!$job->employer_id) {
            \Log::info('Skipping employer notification - no employer associated with job', [
                'job_id' => $job->id,
                'job_type' => $job->job_type,
            ]);
            return;
        }

        $employer = User::find($job->employer_id);

        if (!$employer) {
            \Log::warning('Employer not found for matching job', [
                'job_id' => $job->id,
                'employer_id' => $job->employer_id,
            ]);
            return;
        }

        // Build notification message based on results
        $message = $this->buildNotificationMessage($job, $results, $matchesFound, $processingTime);

        // Prepare context for AI follow-up
        $context = [
            'job_id' => $job->id,
            'job_type' => $job->job_type,
            'employer_id' => $job->employer_id,
            'matches_found' => $matchesFound,
            'processing_time' => $processingTime,
            'results' => $results,
            'event' => 'matching_job_completed',
            'ai_confidence_score' => $job->ai_confidence_score,
            'ai_reasoning' => $job->ai_reasoning,
        ];

        // Send SMS notification
        $smsResult = $this->notificationService->sendSms(
            $employer,
            $message,
            $context,
            'matching_completed'
        );

        // Log the notification result
        if ($smsResult['success']) {
            \Log::info('Employer notified of matching completion', [
                'job_id' => $job->id,
                'employer_id' => $job->employer_id,
                'matches_found' => $matchesFound,
                'log_id' => $smsResult['log_id'] ?? null,
            ]);

            // Mark job notification as sent
            $job->markNotificationSent('sms');
        } else {
            \Log::warning('Failed to notify employer of matching completion', [
                'job_id' => $job->id,
                'employer_id' => $job->employer_id,
                'error' => $smsResult['error'] ?? 'Unknown error',
                'scheduled' => $smsResult['scheduled'] ?? false,
            ]);
        }

        // Also send email notification if employer has email
        if ($employer->email) {
            $this->sendEmailNotification($employer, $job, $results, $matchesFound, $context);
        }
    }

    /**
     * Build the notification message based on matching results.
     */
    protected function buildNotificationMessage($job, array $results, ?int $matchesFound, float $processingTime): string
    {
        $jobTypeLabel = $job->job_type_label;

        if ($matchesFound === null || $matchesFound === 0) {
            return "Maids.ng: Your {$jobTypeLabel} request has been processed. " .
                "Unfortunately, no suitable matches were found at this time. " .
                "Our team will review your requirements and contact you shortly with alternatives. " .
                "Ref: {$job->job_id}";
        }

        if ($matchesFound === 1) {
            $bestMatch = $results['matches'][0] ?? null;
            $confidenceScore = $bestMatch ? round($bestMatch['score'] * 100) : $job->ai_confidence_score;

            return "Maids.ng: Great news! We found a perfect match for your {$jobTypeLabel} request. " .
                "Match confidence: {$confidenceScore}%. " .
                "Please check your dashboard to review and accept the match. " .
                "Ref: {$job->job_id}";
        }

        // Multiple matches found
        $bestMatch = $results['matches'][0] ?? null;
        $confidenceScore = $bestMatch ? round($bestMatch['score'] * 100) : $job->ai_confidence_score;

        return "Maids.ng: We found {$matchesFound} potential matches for your {$jobTypeLabel} request! " .
            "Top match confidence: {$confidenceScore}%. " .
            "Please check your dashboard to review all candidates and select your preferred match. " .
            "Ref: {$job->job_id}";
    }

    /**
     * Send email notification to employer.
     */
    protected function sendEmailNotification(User $employer, $job, array $results, ?int $matchesFound, array $context): void
    {
        try {
            $emailData = [
                'employer' => $employer,
                'job' => $job,
                'matches' => $results['matches'] ?? [],
                'matches_found' => $matchesFound,
                'processing_time' => $job->duration_formatted,
                'ai_confidence_score' => $job->ai_confidence_score,
                'ai_reasoning' => $job->ai_reasoning,
            ];

            // Queue email for delivery
            \Mail::to($employer->email)->queue(new \App\Mail\MatchingResultsMail($emailData));

            \Log::info('Matching results email queued for employer', [
                'job_id' => $job->id,
                'employer_id' => $employer->id,
                'email' => $employer->email,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to queue matching results email', [
                'job_id' => $job->id,
                'employer_id' => $employer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(MatchingJobCompleted $event, \Throwable $exception): void
    {
        \Log::error('NotifyEmployerOfMatching listener failed', [
            'job_id' => $event->job->id,
            'employer_id' => $event->job->employer_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
