<?php

namespace App\Listeners;

use App\Events\WithdrawalRequested;
use App\Services\SmartNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAdminOfWithdrawalRequest implements ShouldQueue
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
    public function handle(WithdrawalRequested $event): void
    {
        $wallet = $event->wallet;
        $maid = $wallet->maid;

        // Get admin users
        $admins = \App\Models\User::role('admin')->get();

        foreach ($admins as $admin) {
            $this->notificationService->send([
                'recipient_id' => $admin->id,
                'recipient_type' => 'admin',
                'type' => 'withdrawal_requested',
                'channel' => 'email',
                'message' => "NEW WITHDRAWAL REQUEST\n\n" .
                    "Maid: {$maid->name} (ID: {$maid->id})\n" .
                    "Amount: ₦" . number_format($event->amount) . "\n" .
                    "Reference: {$event->reference}\n" .
                    "Bank: {$event->bankCode}\n" .
                    "Account: {$event->accountNumber}\n" .
                    "Account Name: {$event->accountName}\n\n" .
                    "Action Required: Please review and approve/reject this withdrawal request.",
                'context' => [
                    'wallet_id' => $wallet->id,
                    'maid_id' => $maid->id,
                    'maid_name' => $maid->name,
                    'amount' => $event->amount,
                    'reference' => $event->reference,
                    'bank_code' => $event->bankCode,
                    'account_number' => $event->accountNumber,
                    'account_name' => $event->accountName,
                    'available_balance' => $wallet->available_balance,
                    'event' => 'withdrawal_requested',
                ],
                'ai_generated' => false,
                'priority' => 'normal',
            ]);
        }

        \Log::info('Admin notified of withdrawal request', [
            'wallet_id' => $wallet->id,
            'maid_id' => $maid->id,
            'amount' => $event->amount,
            'reference' => $event->reference,
        ]);
    }
}
