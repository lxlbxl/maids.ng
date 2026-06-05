<?php

namespace App\Listeners;

use App\Events\MatchingJobCompleted;
use App\Models\Assignment;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateAssignmentFromMatch implements ShouldQueue
{
    use InteractsWithQueue;

    protected WalletService $walletService;

    /**
     * Create the event listener.
     */
    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Handle the event.
     */
    public function handle(MatchingJobCompleted $event): void
    {
        $job = $event->job;
        $results = $event->results;

        // Only create assignment if job type is 'new_assignment' and we have matches
        if ($job->job_type !== 'new_assignment' || empty($results['matches'])) {
            \Log::info('Skipping assignment creation - not a new assignment job or no matches', [
                'job_id' => $job->id,
                'job_type' => $job->job_type,
                'has_matches' => !empty($results['matches']),
            ]);
            return;
        }

        $employerId = $job->employer_id;
        $payload = $job->payload;

        // Get the best match (first one with highest score)
        $bestMatch = $results['matches'][0];
        $maidId = $bestMatch['maid_id'];

        // Check employer has sufficient balance for matching fee
        $matchingFee = config('services.matching_fee', 5000);
        $walletCheck = $this->walletService->checkBalance($employerId, $matchingFee);

        if (!$walletCheck['has_sufficient']) {
            \Log::warning('Employer has insufficient balance for matching fee', [
                'employer_id' => $employerId,
                'required' => $matchingFee,
                'available' => $walletCheck['balance'],
            ]);

            // Notify employer of insufficient balance
            $this->notifyInsufficientBalance($employerId, $matchingFee, $walletCheck['balance']);
            return;
        }

        // Create the assignment
        $assignment = Assignment::create([
            'employer_id' => $employerId,
            'maid_id' => $maidId,
            'job_type' => $payload['requirements']['job_type'] ?? 'full_time',
            'monthly_salary' => $payload['requirements']['salary_range']['max'] ?? 50000,
            'salary_day' => $payload['requirements']['salary_day'] ?? 1,
            'location' => $payload['requirements']['location'] ?? null,
            'start_date' => now()->addDays(7),
            'status' => 'pending_acceptance',
            'matching_job_id' => $job->id,
            'context_json' => [
                'match_score' => $bestMatch['score'] ?? null,
                'match_reasons' => $bestMatch['reasons'] ?? [],
                'ai_matching_job_id' => $job->id,
            ],
        ]);

        // Debit matching fee from employer wallet
        $this->walletService->debit(
            $employerId,
            $matchingFee,
            'matching_fee',
            $assignment->id
        );

        \Log::info('Assignment created from AI matching job', [
            'assignment_id' => $assignment->id,
            'employer_id' => $employerId,
            'maid_id' => $maidId,
            'matching_job_id' => $job->id,
            'matching_fee' => $matchingFee,
        ]);
    }

    /**
     * Notify employer of insufficient balance.
     */
    protected function notifyInsufficientBalance(int $employerId, float $required, float $available): void
    {
        $notificationService = app(\App\Services\SmartNotificationService::class);

        $notificationService->send([
            'recipient_id' => $employerId,
            'recipient_type' => 'employer',
            'type' => 'insufficient_balance',
            'channel' => 'sms',
            'message' => "We found a perfect match for you! However, your wallet balance (₦" .
                number_format($available) . ") is insufficient for the matching fee (₦" .
                number_format($required) . "). Please top up your wallet to proceed.",
            'context' => [
                'employer_id' => $employerId,
                'required_amount' => $required,
                'available_balance' => $available,
                'event' => 'insufficient_balance',
            ],
            'ai_generated' => false,
        ]);
    }
}
