<?php

namespace App\Listeners;

use App\Events\SalaryPaymentProcessed;
use App\Services\SmartNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyEmployerOfPayment implements ShouldQueue
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
    public function handle(SalaryPaymentProcessed $event): void
    {
        $payment = $event->payment;
        $schedule = $event->schedule;
        $employer = $schedule->assignment->employer;
        $maid = $schedule->assignment->maid;

        // Notify employer of payment
        $this->notificationService->send([
            'recipient_id' => $employer->id,
            'recipient_type' => 'employer',
            'type' => 'salary_paid',
            'channel' => 'sms',
            'message' => "Your salary payment of ₦" . number_format($event->amount) .
                " to {$maid->name} has been processed successfully.",
            'context' => [
                'payment_id' => $payment->id,
                'schedule_id' => $schedule->id,
                'assignment_id' => $schedule->assignment_id,
                'employer_id' => $employer->id,
                'maid_id' => $maid->id,
                'maid_name' => $maid->name,
                'amount' => $event->amount,
                'event' => 'salary_paid',
            ],
            'ai_generated' => false,
        ]);

        \Log::info('Employer notified of salary payment', [
            'payment_id' => $payment->id,
            'employer_id' => $employer->id,
            'amount' => $event->amount,
        ]);
    }
}
