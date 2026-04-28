<?php

namespace App\Listeners;

use App\Events\AssignmentRejected;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessRefund implements ShouldQueue
{
    use InteractsWithQueue;

    protected WalletService $walletService;

    /**
     * Create the event listener.
     */
    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Handle the event.
     */
    public function handle(AssignmentRejected $event): void
    {
        $assignment = $event->assignment;
        $refundAmount = $event->refundAmount;

        if ($refundAmount <= 0) {
            \Log::info('No refund needed for rejected assignment', [
                'assignment_id' => $assignment->id,
                'refund_amount' => $refundAmount,
            ]);
            return;
        }

        // Release escrow and credit employer wallet
        $result = $this->walletService->releaseEscrow(
            $assignment->employer_id,
            $refundAmount,
            'assignment_rejected',
            $assignment->id
        );

        if ($result['success']) {
            \Log::info('Refund processed for rejected assignment', [
                'assignment_id' => $assignment->id,
                'employer_id' => $assignment->employer_id,
                'refund_amount' => $refundAmount,
            ]);
        } else {
            \Log::error('Failed to process refund for rejected assignment', [
                'assignment_id' => $assignment->id,
                'error' => $result['message'],
            ]);
        }
    }
}
