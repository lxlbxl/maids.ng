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
        $this->salaryService->createSalarySchedule(
            $assignment->id,
            (float) ($assignment->salary_amount ?? 0.0),
            $assignment->start_date ?? now()
        );

        // Log the action
        \Log::info('Salary schedule created for assignment', [
            'assignment_id' => $assignment->id,
            'employer_id' => $assignment->employer_id,
            'maid_id' => $assignment->maid_id,
        ]);
    }
}
