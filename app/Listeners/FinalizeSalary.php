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
                'payment_status' => 'completed',
                'is_active' => false,
            ]);

            \Log::info('Salary schedule finalized on assignment completion', [
                'schedule_id' => $schedule->id,
                'assignment_id' => $assignment->id,
            ]);
        }

        // Release any remaining escrow
        $walletService = app(\App\Services\WalletService::class);
        $remainingEscrow = $walletService->getEscrowBalance($assignment->employer_id);

        if ($remainingEscrow > 0) {
            $result = $walletService->releaseEscrow(
                $assignment->employer_id,
                $remainingEscrow,
                'assignment_completed',
                $assignment->id
            );

            if ($result['success']) {
                \Log::info('Remaining escrow released on assignment completion', [
                    'assignment_id' => $assignment->id,
                    'amount' => $remainingEscrow,
                ]);
            }
        }
    }
}
