<?php

namespace App\Listeners;

use App\Events\SalaryPaymentProcessed;
use App\Services\SmartNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyMaidOfPayment implements ShouldQueue
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
        $maid = $schedule->assignment->maid;
        $employer = $schedule->assignment->employer;

        // Notify maid of payment
        $this->notificationService->send([
            'recipient_id' => $maid->id,
            'recipient_type' => 'maid',
            'type' => 'salary_paid',
            'channel' => 'sms',
            'message' => "Your salary of ₦" . number_format($event->amount) .
                " from {$employer->name} has been processed. " .
                "Check your wallet for details.",
            'context' => [
                'payment_id' => $payment->id,
                'schedule_id' => $schedule->id,
                'assignment_id' => $schedule->assignment_id,
                'maid_id' => $maid->id,
                'employer_id' => $employer->id,
                'amount' => $event->amount,
                'event' => 'salary_paid',
            ],
            'ai_generated' => false,
        ]);

        \Log::info('Maid notified of salary payment', [
            'payment_id' => $payment->id,
            'maid_id' => $maid->id,
            'amount' => $event->amount,
        ]);
    }
}
