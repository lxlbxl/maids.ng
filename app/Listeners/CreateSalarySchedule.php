<?php

namespace App\Listeners;

use App\Events\AssignmentAccepted;
use App\Models\SalarySchedule;
use App\Services\SalaryManagementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateSalarySchedule implements ShouldQueue
{
    use InteractsWithQueue;

    protected SalaryManagementService $salaryService;

    /**
     * Create the event listener.
     */
    public function __construct(SalaryManagementService $salaryService)
    {
        $this->salaryService = $salaryService;
    }

    /**
     * Handle the event.
     */
    public function handle(AssignmentAccepted $event): void
    {
        $assignment = $event->assignment;

        // Create initial salary schedule for the assignment
        $this->salaryService->createSchedule([
            'assignment_id' => $assignment->id,
            'employer_id' => $assignment->employer_id,
            'maid_id' => $assignment->maid_id,
            'monthly_salary' => $assignment->monthly_salary,
            'salary_day' => $assignment->salary_day ?? 1,
            'employment_start_date' => $assignment->start_date,
            'next_salary_due_date' => $this->calculateNextSalaryDue(
                $assignment->start_date,
                $assignment->salary_day ?? 1
            ),
            'payment_status' => 'pending',
            'is_active' => true,
        ]);

        // Log the action
        \Log::info('Salary schedule created for assignment', [
            'assignment_id' => $assignment->id,
            'employer_id' => $assignment->employer_id,
            'maid_id' => $assignment->maid_id,
        ]);
    }

    /**
     * Calculate the next salary due date based on start date and salary day.
     */
    protected function calculateNextSalaryDue(\Carbon\Carbon $startDate, int $salaryDay): \Carbon\Carbon
    {
        $now = now();
        $nextDue = $startDate->copy()->day($salaryDay);

        // If the salary day has passed this month, move to next month
        if ($nextDue->isPast() || $nextDue->isToday()) {
            $nextDue->addMonth();
        }

        return $nextDue;
    }
}
