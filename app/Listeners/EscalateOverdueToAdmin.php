<?php

namespace App\Listeners;

use App\Events\SalaryOverdue;
use App\Models\SalaryReminder;
use App\Services\SmartNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class EscalateOverdueToAdmin implements ShouldQueue
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
    public function handle(SalaryOverdue $event): void
    {
        $schedule = $event->schedule;
        $employer = $schedule->assignment->employer;
        $maid = $schedule->assignment->maid;

        // Create or update escalation record
        $reminder = SalaryReminder::updateOrCreate(
            [
                'schedule_id' => $schedule->id,
                'reminder_type' => 'escalated',
            ],
            [
                'escalated_to_admin_at' => now(),
                'reminder_sequence' => $event->daysOverdue,
                'context_json' => array_merge($event->context, [
                    'days_overdue' => $event->daysOverdue,
                    'last_reminder_type' => $event->lastReminderType,
                    'escalated_at' => now()->toIso8601String(),
                ]),
            ]
        );

        // Get admin users
        $admins = \App\Models\User::role('admin')->get();

        foreach ($admins as $admin) {
            $this->notificationService->send([
                'recipient_id' => $admin->id,
                'recipient_type' => 'admin',
                'type' => 'salary_overdue_escalated',
                'channel' => 'email',
                'message' => "SALARY OVERDUE ESCALATION\n\n" .
                    "Employer: {$employer->name} (ID: {$employer->id})\n" .
                    "Maid: {$maid->name} (ID: {$maid->id})\n" .
                    "Amount Due: ₦" . number_format($schedule->monthly_salary) . "\n" .
                    "Days Overdue: {$event->daysOverdue}\n" .
                    "Due Date: " . $schedule->next_salary_due_date->format('M d, Y') . "\n\n" .
                    "Action Required: Please contact employer immediately.",
                'context' => [
                    'schedule_id' => $schedule->id,
                    'assignment_id' => $schedule->assignment_id,
                    'employer_id' => $employer->id,
                    'employer_name' => $employer->name,
                    'maid_id' => $maid->id,
                    'maid_name' => $maid->name,
                    'amount' => $schedule->monthly_salary,
                    'days_overdue' => $event->daysOverdue,
                    'due_date' => $schedule->next_salary_due_date->toDateString(),
                    'event' => 'salary_overdue_escalated',
                ],
                'ai_generated' => false,
                'priority' => 'high',
            ]);
        }

        \Log::info('Salary overdue escalated to admin', [
            'schedule_id' => $schedule->id,
            'employer_id' => $employer->id,
            'days_overdue' => $event->daysOverdue,
            'reminder_id' => $reminder->id,
        ]);
    }
}
