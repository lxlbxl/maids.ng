<?php

namespace App\Listeners;

use App\Events\WithdrawalApproved;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessBankTransfer implements ShouldQueue
{
    use InteractsWithQueue;

    protected WalletService $walletService;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

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
    public function handle(WithdrawalApproved $event): void
    {
        $wallet = $event->wallet;
        $maid = $wallet->maid;

        // Process the bank transfer
        // This would integrate with a payment provider like Paystack, Flutterwave, etc.
        $transferResult = $this->initiateBankTransfer($event);

        if ($transferResult['success']) {
            // Record the transaction
            $this->walletService->recordTransaction(
                $wallet->id,
                'withdrawal',
                $event->amount,
                'completed',
                [
                    'reference' => $event->reference,
                    'transaction_reference' => $transferResult['transaction_reference'],
                    'bank_code' => $event->bankCode ?? null,
                    'account_number' => $event->accountNumber ?? null,
                    'processed_by' => $event->processedBy,
                ]
            );

            \Log::info('Bank transfer processed successfully', [
                'wallet_id' => $wallet->id,
                'maid_id' => $maid->id,
                'amount' => $event->amount,
                'reference' => $event->reference,
                'transaction_reference' => $transferResult['transaction_reference'],
            ]);
        } else {
            // Log failure for manual review
            \Log::error('Bank transfer failed', [
                'wallet_id' => $wallet->id,
                'maid_id' => $maid->id,
                'amount' => $event->amount,
                'reference' => $event->reference,
                'error' => $transferResult['error'] ?? 'Unknown error',
            ]);

            // Notify admin of failure
            $this->notifyAdminOfFailure($event, $transferResult);

            // Throw exception to trigger retry
            throw new \Exception('Bank transfer failed: ' . ($transferResult['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Initiate bank transfer via payment provider.
     */
    protected function initiateBankTransfer(WithdrawalApproved $event): array
    {
        // This is a placeholder for actual bank transfer integration
        // In production, this would call Paystack, Flutterwave, or similar

        // Simulate successful transfer for now
        return [
            'success' => true,
            'transaction_reference' => 'TRF_' . uniqid(),
            'message' => 'Transfer initiated successfully',
        ];
    }

    /**
     * Notify admin of transfer failure.
     */
    protected function notifyAdminOfFailure(WithdrawalApproved $event, array $result): void
    {
        $admins = \App\Models\User::role('admin')->get();
        $notificationService = app(SmartNotificationService::class);

        foreach ($admins as $admin) {
            $notificationService->send([
                'recipient_id' => $admin->id,
                'recipient_type' => 'admin',
                'type' => 'withdrawal_failed',
                'channel' => 'email',
                'message' => "WITHDRAWAL FAILED - MANUAL INTERVENTION REQUIRED\n\n" .
                    "Maid ID: {$event->wallet->maid_id}\n" .
                    "Amount: ₦" . number_format($event->amount) . "\n" .
                    "Reference: {$event->reference}\n" .
                    "Error: " . ($result['error'] ?? 'Unknown error') . "\n\n" .
                    "Please process this withdrawal manually.",
                'context' => [
                    'wallet_id' => $event->wallet->id,
                    'maid_id' => $event->wallet->maid_id,
                    'amount' => $event->amount,
                    'reference' => $event->reference,
                    'error' => $result['error'] ?? 'Unknown error',
                    'event' => 'withdrawal_failed',
                ],
                'ai_generated' => false,
                'priority' => 'high',
            ]);
        }
    }
}
