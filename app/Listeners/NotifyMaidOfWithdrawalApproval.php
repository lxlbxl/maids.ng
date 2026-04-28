<?php

namespace App\Listeners;

use App\Events\WithdrawalApproved;
use App\Services\SmartNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyMaidOfWithdrawalApproval implements ShouldQueue
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
    public function handle(WithdrawalApproved $event): void
    {
        $wallet = $event->wallet;
        $maid = $wallet->maid;

        $this->notificationService->send([
            'recipient_id' => $maid->id,
            'recipient_type' => 'maid',
            'type' => 'withdrawal_approved',
            'channel' => 'sms',
            'message' => "Your withdrawal request of ₦" . number_format($event->amount) .
                " has been approved and is being processed. " .
                "Reference: {$event->reference}. " .
                "You should receive the funds within 24-48 hours.",
            'context' => [
                'wallet_id' => $wallet->id,
                'maid_id' => $maid->id,
                'amount' => $event->amount,
                'reference' => $event->reference,
                'transaction_reference' => $event->transactionReference,
                'processed_by' => $event->processedBy,
                'available_balance' => $wallet->available_balance,
                'event' => 'withdrawal_approved',
            ],
            'ai_generated' => false,
        ]);

        \Log::info('Maid notified of withdrawal approval', [
            'wallet_id' => $wallet->id,
            'maid_id' => $maid->id,
            'amount' => $event->amount,
            'reference' => $event->reference,
        ]);
    }
}
