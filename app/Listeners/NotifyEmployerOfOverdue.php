<?php

namespace App\Listeners;

use App\Events\SalaryOverdue;
use App\Services\SmartNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyEmployerOfOverdue implements ShouldQueue
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

        // Determine urgency level based on days overdue
        $urgency = $this->getUrgencyLevel($event->daysOverdue);

        $this->notificationService->send([
            'recipient_id' => $employer->id,
            'recipient_type' => 'employer',
            'type' => 'salary_overdue',
            'channel' => 'sms',
            'message' => $this->buildMessage($employer, $maid, $schedule, $event->daysOverdue, $urgency),
            'context' => [
                'schedule_id' => $schedule->id,
                'assignment_id' => $schedule->assignment_id,
                'employer_id' => $employer->id,
                'maid_id' => $maid->id,
                'maid_name' => $maid->name,
                'amount' => $schedule->monthly_salary,
                'days_overdue' => $event->daysOverdue,
                'due_date' => $schedule->next_salary_due_date->toDateString(),
                'urgency' => $urgency,
                'event' => 'salary_overdue',
            ],
            'ai_generated' => false,
            'priority' => $urgency === 'critical' ? 'high' : 'normal',
        ]);

        \Log::info('Employer notified of overdue salary', [
            'schedule_id' => $schedule->id,
            'employer_id' => $employer->id,
            'days_overdue' => $event->daysOverdue,
        ]);
    }

    /**
     * Determine urgency level based on days overdue.
     */
    protected function getUrgencyLevel(int $daysOverdue): string
    {
        if ($daysOverdue >= 7) {
            return 'critical';
        } elseif ($daysOverdue >= 3) {
            return 'high';
        }
        return 'normal';
    }

    /**
     * Build the notification message.
     */
    protected function buildMessage($employer, $maid, $schedule, int $daysOverdue, string $urgency): string
    {
        $amount = number_format($schedule->monthly_salary);

        $baseMessage = "Salary payment of ₦{$amount} for {$maid->name} is {$daysOverdue} days overdue. ";

        switch ($urgency) {
            case 'critical':
                return $baseMessage . "URGENT: Please make payment immediately to avoid service disruption and penalties.";
            case 'high':
                return $baseMessage . "Please make payment as soon as possible to avoid escalation.";
            default:
                return $baseMessage . "Please make payment at your earliest convenience.";
        }
    }
}
