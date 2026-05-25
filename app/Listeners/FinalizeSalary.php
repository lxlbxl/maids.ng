<?php

namespace App\Listeners;

use App\Events\AssignmentCompleted;
use App\Models\SalarySchedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class FinalizeSalary implements ShouldQueue
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
    public function handle(AssignmentCompleted $event): void
    {
        $assignment = $event->assignment;

        // Mark all pending salary schedules as completed
        $schedules = SalarySchedule::where('assignment_id', $assignment->id)
            ->where('payment_status', 'pending')
            ->get();

        foreach ($schedules as $schedule) {
            $schedule->update([
                'is_active' => false,
            ]);

            \Log::info('Salary schedule finalized on assignment completion', [
                'schedule_id' => $schedule->id,
                'assignment_id' => $assignment->id,
            ]);
        }

        // Release any remaining escrow
        $walletService = app(\App\Services\WalletService::class);
        $balanceInfo = $walletService->getEmployerBalance($assignment->employer_id);
        $remainingEscrow = $balanceInfo['escrow_balance'] ?? 0;

        if ($remainingEscrow > 0) {
            $walletService->releaseFromEscrow(
                $assignment->employer_id,
                $remainingEscrow,
                'Assignment completed - escrow released',
                $assignment->id,
                'assignment_completed'
            );

            \Log::info('Remaining escrow released on assignment completion', [
                'assignment_id' => $assignment->id,
                'amount' => $remainingEscrow,
            ]);
        }
    }
}
