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
        $startDate = $assignment->started_at ?? $assignment->start_date ?? now()->addDays(7);
        $salary = $assignment->salary_amount ?? $assignment->monthly_salary ?? 0;

        $this->notificationService->send([
            'recipient_id' => $maid->id,
            'recipient_type' => 'maid',
            'type' => 'assignment_accepted',
            'channel' => 'sms',
            'message' => "Congratulations! {$employer->name} has accepted your assignment. " .
                "Monthly salary: ₦" . number_format($salary) . ". " .
                "Start date: " . (is_object($startDate) ? $startDate->format('M d, Y') : $startDate),
            'context' => [
                'assignment_id' => $assignment->id,
                'employer_id' => $employer->id,
                'employer_name' => $employer->name,
                'monthly_salary' => $salary,
                'start_date' => is_object($startDate) ? $startDate->toDateString() : $startDate,
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
