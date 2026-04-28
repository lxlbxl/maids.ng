<?php

namespace App\Listeners;

use App\Events\AssignmentCompleted;
use App\Services\SmartNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyBothPartiesOfCompletion implements ShouldQueue
{
    use InteractsWithQueue;

    protected SmartNotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(SmartNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(AssignmentCompleted $event): void
    {
        $assignment = $event->assignment;
        $employer = $assignment->employer;
        $maid = $assignment->maid;

        // Notify employer
        $this->notificationService->send([
            'recipient_id' => $employer->id,
            'recipient_type' => 'employer',
            'type' => 'assignment_completed',
            'channel' => 'sms',
            'message' => "Your assignment with {$maid->name} has been completed. " .
                "Thank you for using Maids.ng!",
            'context' => [
                'assignment_id' => $assignment->id,
                'employer_id' => $employer->id,
                'maid_id' => $maid->id,
                'maid_name' => $maid->name,
                'event' => 'assignment_completed',
                'party' => 'employer',
            ],
            'ai_generated' => false,
        ]);

        // Notify maid
        $this->notificationService->send([
            'recipient_id' => $maid->id,
            'recipient_type' => 'maid',
            'type' => 'assignment_completed',
            'channel' => 'sms',
            'message' => "Your assignment with {$employer->name} has been completed. " .
                "You are now available for new assignments.",
            'context' => [
                'assignment_id' => $assignment->id,
                'employer_id' => $employer->id,
                'employer_name' => $employer->name,
                'maid_id' => $maid->id,
                'event' => 'assignment_completed',
                'party' => 'maid',
            ],
            'ai_generated' => false,
        ]);

        \Log::info('Both parties notified of assignment completion', [
            'assignment_id' => $assignment->id,
            'employer_id' => $employer->id,
            'maid_id' => $maid->id,
        ]);
    }
}
