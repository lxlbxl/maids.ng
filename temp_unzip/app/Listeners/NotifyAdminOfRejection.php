<?php

namespace App\Listeners;

use App\Events\AssignmentRejected;
use App\Services\SmartNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAdminOfRejection implements ShouldQueue
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
    public function handle(AssignmentRejected $event): void
    {
        $assignment = $event->assignment;
        $employer = $assignment->employer;
        $maid = $assignment->maid;

        // Get admin users
        $admins = \App\Models\User::role('admin')->get();

        foreach ($admins as $admin) {
            $this->notificationService->send([
                'recipient_id' => $admin->id,
                'recipient_type' => 'admin',
                'type' => 'assignment_rejected',
                'channel' => 'email',
                'message' => "Assignment has been rejected by employer.\n\n" .
                    "Employer: {$employer->name} (ID: {$employer->id})\n" .
                    "Maid: {$maid->name} (ID: {$maid->id})\n" .
                    "Assignment ID: {$assignment->id}\n" .
                    "Rejection Reason: " . ($event->rejectionReason ?: 'No reason provided') . "\n" .
                    "Refund Amount: ₦" . number_format($event->refundAmount),
                'context' => [
                    'assignment_id' => $assignment->id,
                    'employer_id' => $employer->id,
                    'employer_name' => $employer->name,
                    'maid_id' => $maid->id,
                    'maid_name' => $maid->name,
                    'rejection_reason' => $event->rejectionReason,
                    'refund_amount' => $event->refundAmount,
                    'event' => 'assignment_rejected',
                ],
                'ai_generated' => false,
            ]);
        }

        \Log::info('Admin notified of assignment rejection', [
            'assignment_id' => $assignment->id,
            'employer_id' => $employer->id,
            'maid_id' => $maid->id,
        ]);
    }
}
