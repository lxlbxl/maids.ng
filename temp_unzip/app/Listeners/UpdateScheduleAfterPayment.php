<?php

namespace App\Listeners;

use App\Events\SalaryPaymentProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateScheduleAfterPayment implements ShouldQueue
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
    public function handle(SalaryPaymentProcessed $event): void
    {
        $schedule = $event->schedule;
        $payment = $event->payment;

        // Update schedule status
        $schedule->update([
            'payment_status' => 'paid',
            'last_payment_at' => now(),
        ]);

        // Calculate next salary due date
        $nextDueDate = $this->calculateNextDueDate($schedule);

        // Create next month's schedule if assignment is still active
        if ($schedule->assignment->status === 'active') {
            \App\Models\SalarySchedule::create([
                'assignment_id' => $schedule->assignment_id,
                'employer_id' => $schedule->employer_id,
                'maid_id' => $schedule->maid_id,
                'monthly_salary' => $schedule->monthly_salary,
                'salary_day' => $schedule->salary_day,
                'employment_start_date' => $schedule->employment_start_date,
                'next_salary_due_date' => $nextDueDate,
                'payment_status' => 'pending',
                'is_active' => true,
            ]);

            \Log::info('Next salary schedule created', [
                'previous_schedule_id' => $schedule->id,
                'assignment_id' => $schedule->assignment_id,
                'next_due_date' => $nextDueDate->toDateString(),
            ]);
        }

        \Log::info('Salary schedule updated after payment', [
            'schedule_id' => $schedule->id,
            'payment_id' => $payment->id,
            'payment_status' => 'paid',
        ]);
    }

    /**
     * Calculate the next salary due date.
     */
    protected function calculateNextDueDate($schedule): \Carbon\Carbon
    {
        $nextDue = $schedule->next_salary_due_date->copy()->addMonth();

        // Ensure it's on the correct day
        $salaryDay = $schedule->salary_day;
        $daysInMonth = $nextDue->daysInMonth;

        if ($salaryDay > $daysInMonth) {
            $salaryDay = $daysInMonth;
        }

        $nextDue->day($salaryDay);

        return $nextDue;
    }
}
