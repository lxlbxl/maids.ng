<?php

namespace App\Services;

use App\Models\EmployerWallet;
use App\Models\MaidWallet;
use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    /**
     * Get or create employer wallet.
     */
    public function getOrCreateEmployerWallet(int $employerId, string $currency = 'NGN'): EmployerWallet
    {
        return EmployerWallet::firstOrCreate(
            ['employer_id' => $employerId],
            [
                'balance' => 0,
                'escrow_balance' => 0,
                'total_deposited' => 0,
                'total_spent' => 0,
                'total_refunded' => 0,
                'currency' => $currency,
                'timezone' => config('app.timezone', 'Africa/Lagos'),
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create maid wallet.
     */
    public function getOrCreateMaidWallet(int $maidId, string $currency = 'NGN'): MaidWallet
    {
        return MaidWallet::firstOrCreate(
            ['maid_id' => $maidId],
            [
                'balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'pending_withdrawal' => 0,
                'currency' => $currency,
                'is_active' => true,
            ]
        );
    }

    /**
     * Credit employer wallet.
     */
    public function creditEmployerWallet(
        int $employerId,
        float $amount,
        string $description,
        ?int $referenceId = null,
        string $referenceType = ''
    ): ?WalletTransaction {
        try {
            DB::beginTransaction();

            $wallet = $this->getOrCreateEmployerWallet($employerId);
            $transaction = $wallet->credit($amount, $description, $referenceId, $referenceType);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to credit employer wallet', [
                'employer_id' => $employerId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Debit employer wallet.
     */
    public function debitEmployerWallet(
        int $employerId,
        float $amount,
        string $description,
        ?int $referenceId = null,
        string $referenceType = ''
    ): ?WalletTransaction {
        try {
            DB::beginTransaction();

            $wallet = $this->getOrCreateEmployerWallet($employerId);

            if (!$wallet->hasSufficientBalance($amount)) {
                DB::rollBack();
                return null;
            }

            $transaction = $wallet->debit($amount, $description, $referenceId, $referenceType);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to debit employer wallet', [
                'employer_id' => $employerId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Hold amount in escrow for employer.
     */
    public function holdInEscrow(
        int $employerId,
        float $amount,
        string $description,
        ?int $referenceId = null,
        string $referenceType = ''
    ): ?WalletTransaction {
        try {
            DB::beginTransaction();

            $wallet = $this->getOrCreateEmployerWallet($employerId);

            if (!$wallet->hasSufficientBalance($amount)) {
                DB::rollBack();
                return null;
            }

            $transaction = $wallet->holdInEscrow($amount, $description, $referenceId, $referenceType);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to hold in escrow', [
                'employer_id' => $employerId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Release from escrow to employer balance.
     */
    public function releaseFromEscrow(
        int $employerId,
        float $amount,
        string $description,
        ?int $referenceId = null,
        string $referenceType = ''
    ): ?WalletTransaction {
        try {
            DB::beginTransaction();

            $wallet = $this->getOrCreateEmployerWallet($employerId);
            $transaction = $wallet->releaseFromEscrow($amount, $description, $referenceId, $referenceType);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to release from escrow', [
                'employer_id' => $employerId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Transfer from employer escrow to maid wallet (salary payment).
     */
    public function transferEscrowToMaid(
        int $employerId,
        int $maidId,
        float $amount,
        string $description,
        ?int $referenceId = null
    ): ?WalletTransaction {
        try {
            DB::beginTransaction();

            $employerWallet = $this->getOrCreateEmployerWallet($employerId);
            $maidWallet = $this->getOrCreateMaidWallet($maidId, $employerWallet->currency);

            if ($employerWallet->escrow_balance < $amount) {
                DB::rollBack();
                return null;
            }

            // Release from employer escrow and transfer to maid
            $transaction = $employerWallet->releaseEscrowToMaid($amount, $maidId, $description, $referenceId);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to transfer escrow to maid', [
                'employer_id' => $employerId,
                'maid_id' => $maidId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Credit maid wallet.
     */
    public function creditMaidWallet(
        int $maidId,
        float $amount,
        string $description,
        ?int $referenceId = null,
        string $referenceType = ''
    ): ?WalletTransaction {
        try {
            DB::beginTransaction();

            $wallet = $this->getOrCreateMaidWallet($maidId);
            $transaction = $wallet->credit($amount, $description, $referenceId, $referenceType);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to credit maid wallet', [
                'maid_id' => $maidId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Request withdrawal from maid wallet.
     */
    public function requestMaidWithdrawal(
        int $maidId,
        float $amount,
        string $description
    ): ?WalletTransaction {
        try {
            DB::beginTransaction();

            $wallet = $this->getOrCreateMaidWallet($maidId);

            if (!$wallet->hasSufficientBalance($amount)) {
                DB::rollBack();
                return null;
            }

            $transaction = $wallet->requestWithdrawal($amount, $description);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to request maid withdrawal', [
                'maid_id' => $maidId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Approve maid withdrawal.
     */
    public function approveMaidWithdrawal(
        int $maidId,
        int $transactionId,
        string $paymentReference = ''
    ): ?WalletTransaction {
        try {
            DB::beginTransaction();

            $wallet = $this->getOrCreateMaidWallet($maidId);
            $transaction = $wallet->approveWithdrawal($transactionId, $paymentReference);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve maid withdrawal', [
                'maid_id' => $maidId,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Reject maid withdrawal.
     */
    public function rejectMaidWithdrawal(
        int $maidId,
        int $transactionId,
        string $reason = ''
    ): ?WalletTransaction {
        try {
            DB::beginTransaction();

            $wallet = $this->getOrCreateMaidWallet($maidId);
            $transaction = $wallet->rejectWithdrawal($transactionId, $reason);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject maid withdrawal', [
                'maid_id' => $maidId,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get employer wallet balance.
     */
    public function getEmployerBalance(int $employerId): array
    {
        $wallet = $this->getOrCreateEmployerWallet($employerId);

        return [
            'balance' => $wallet->balance,
            'escrow_balance' => $wallet->escrow_balance,
            'available_balance' => $wallet->getAvailableBalance(),
            'total_deposited' => $wallet->total_deposited,
            'total_spent' => $wallet->total_spent,
            'total_refunded' => $wallet->total_refunded,
            'currency' => $wallet->currency,
        ];
    }

    /**
     * Get maid wallet balance.
     */
    public function getMaidBalance(int $maidId): array
    {
        $wallet = $this->getOrCreateMaidWallet($maidId);

        return [
            'balance' => $wallet->balance,
            'available_balance' => $wallet->getAvailableBalance(),
            'pending_withdrawal' => $wallet->pending_withdrawal,
            'total_earned' => $wallet->total_earned,
            'total_withdrawn' => $wallet->total_withdrawn,
            'currency' => $wallet->currency,
        ];
    }

    /**
     * Get transaction history for employer.
     */
    public function getEmployerTransactions(int $employerId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return WalletTransaction::forEmployer($employerId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get transaction history for maid.
     */
    public function getMaidTransactions(int $maidId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return WalletTransaction::forMaid($maidId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Process refund for rejected assignment.
     */
    public function processAssignmentRefund(
        int $employerId,
        float $amount,
        int $assignmentId,
        string $reason = ''
    ): ?WalletTransaction {
        $description = "Refund for assignment #{$assignmentId}";
        if ($reason) {
            $description .= ": {$reason}";
        }

        return $this->creditEmployerWallet(
            $employerId,
            $amount,
            $description,
            $assignmentId,
            'assignment_refund'
        );
    }

    /**
     * Check if employer has sufficient balance.
     */
    public function employerHasSufficientBalance(int $employerId, float $amount): bool
    {
        $wallet = $this->getOrCreateEmployerWallet($employerId);
        return $wallet->hasSufficientBalance($amount);
    }

    /**
     * Check if maid has sufficient balance.
     */
    public function maidHasSufficientBalance(int $maidId, float $amount): bool
    {
        $wallet = $this->getOrCreateMaidWallet($maidId);
        return $wallet->hasSufficientBalance($amount);
    }
}
