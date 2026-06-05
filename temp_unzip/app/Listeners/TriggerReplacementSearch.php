<?php

namespace App\Listeners;

use App\Events\AssignmentRejected;
use App\Models\AiMatchingQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerReplacementSearch implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AssignmentRejected $event): void
    {
        $assignment = $event->assignment;
        $employer = $assignment->employer;

        // Create a new AI matching job for replacement
        $matchingJob = AiMatchingQueue::create([
            'employer_id' => $employer->id,
            'job_type' => 'replacement_search',
            'status' => 'pending',
            'priority' => 'high', // Higher priority for replacements
            'payload' => [
                'original_assignment_id' => $assignment->id,
                'rejected_maid_id' => $assignment->maid_id,
                'rejection_reason' => $event->rejectionReason,
                'requirements' => [
                    'job_type' => $assignment->job_type,
                    'location' => $assignment->location,
                    'salary_range' => [
                        'min' => $assignment->monthly_salary * 0.9,
                        'max' => $assignment->monthly_salary * 1.1,
                    ],
                ],
            ],
            'context_json' => [
                'triggered_by' => 'assignment_rejected',
                'rejection_reason' => $event->rejectionReason,
                'created_at' => now()->toIso8601String(),
            ],
        ]);

        \Log::info('Replacement search triggered for rejected assignment', [
            'assignment_id' => $assignment->id,
            'employer_id' => $employer->id,
            'matching_job_id' => $matchingJob->id,
        ]);
    }
}
