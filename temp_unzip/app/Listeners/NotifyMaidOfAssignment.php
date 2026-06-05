<?php

namespace App\Listeners;

use App\Events\AssignmentAccepted;
use App\Services\SmartNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyMaidOfAssignment implements ShouldQueue
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
    public function handle(AssignmentAccepted $event): void
    {
        $assignment = $event->assignment;
        $maid = $assignment->maid;
        $employer = $assignment->employer;

        // Send notification to maid
        $this->notificationService->send([
            'recipient_id' => $maid->id,
            'recipient_type' => 'maid',
            'type' => 'assignment_accepted',
            'channel' => 'sms',
            'message' => "Congratulations! {$employer->name} has accepted your assignment. " .
                "Monthly salary: ₦" . number_format($assignment->monthly_salary) . ". " .
                "Start date: " . $assignment->start_date->format('M d, Y'),
            'context' => [
                'assignment_id' => $assignment->id,
                'employer_id' => $employer->id,
                'employer_name' => $employer->name,
                'monthly_salary' => $assignment->monthly_salary,
                'start_date' => $assignment->start_date->toDateString(),
                'event' => 'assignment_accepted',
            ],
            'ai_generated' => false,
        ]);

        \Log::info('Maid notified of assignment acceptance', [
            'maid_id' => $maid->id,
            'assignment_id' => $assignment->id,
        ]);
    }
}
