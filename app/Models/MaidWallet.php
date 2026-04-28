<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaidWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'maid_id',
        'balance',
        'total_earned',
        'total_withdrawn',
        'pending_withdrawal',
        'salary_day',
        'employment_start_date',
        'next_salary_due_date',
        'bank_name',
        'account_number',
        'account_name',
        'currency',
        'is_active',
        'last_activity_at',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'pending_withdrawal' => 'decimal:2',
        'salary_day' => 'integer',
        'employment_start_date' => 'date',
        'next_salary_due_date' => 'date',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Get the maid that owns the wallet.
     */
    public function maid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maid_id');
    }

    /**
     * Get all transactions for this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'maid_id', 'maid_id')
            ->where('wallet_type', 'maid');
    }

    /**
     * Get all salary schedules for this maid.
     */
    public function salarySchedules(): HasMany
    {
        return $this->hasMany(SalarySchedule::class, 'maid_id');
    }

    /**
     * Get all salary payments for this maid.
     */
    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class, 'maid_id');
    }

    /**
     * Check if wallet has sufficient balance.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Get available balance (excluding pending withdrawals).
     */
    public function getAvailableBalance(): float
    {
        return $this->balance - $this->pending_withdrawal;
    }

    /**
     * Credit the wallet (salary payment).
     */
    public function credit(float $amount, string $description, ?int $referenceId = null, string $referenceType = ''): WalletTransaction
    {
        $balanceBefore = $this->balance;
        $this->balance += $amount;
        $this->total_earned += $amount;
        $this->last_activity_at = now();
        $this->save();

        return WalletTransaction::create([
            'wallet_type' => 'maid',
            'maid_id' => $this->maid_id,
            'transaction_type' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Debit the wallet (withdrawal).
     */
    public function debit(float $amount, string $description, ?int $referenceId = null, string $referenceType = ''): ?WalletTransaction
    {
        if (!$this->hasSufficientBalance($amount)) {
            return null;
        }

        $balanceBefore = $this->balance;
        $this->balance -= $amount;
        $this->total_withdrawn += $amount;
        $this->last_activity_at = now();
        $this->save();

        return WalletTransaction::create([
            'wallet_type' => 'maid',
            'maid_id' => $this->maid_id,
            'transaction_type' => 'withdrawal',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Request a withdrawal.
     */
    public function requestWithdrawal(float $amount, string $description): ?WalletTransaction
    {
        if (!$this->hasSufficientBalance($amount)) {
            return null;
        }

        $balanceBefore = $this->balance;
        $this->pending_withdrawal += $amount;
        $this->last_activity_at = now();
        $this->save();

        return WalletTransaction::create([
            'wallet_type' => 'maid',
            'maid_id' => $this->maid_id,
            'transaction_type' => 'withdrawal_request',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'description' => $description,
            'status' => 'pending',
            'processed_at' => null,
        ]);
    }

    /**
     * Approve a pending withdrawal.
     */
    public function approveWithdrawal(int $transactionId, string $paymentReference = ''): ?WalletTransaction
    {
        $transaction = WalletTransaction::where('id', $transactionId)
            ->where('maid_id', $this->maid_id)
            ->where('transaction_type', 'withdrawal_request')
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            return null;
        }

        $amount = $transaction->amount;
        $balanceBefore = $this->balance;

        $this->balance -= $amount;
        $this->pending_withdrawal -= $amount;
        $this->total_withdrawn += $amount;
        $this->last_activity_at = now();
        $this->save();

        $transaction->update([
            'transaction_type' => 'withdrawal',
            'balance_after' => $this->balance,
            'payment_reference' => $paymentReference,
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        return $transaction;
    }

    /**
     * Reject a pending withdrawal.
     */
    public function rejectWithdrawal(int $transactionId, string $reason = ''): ?WalletTransaction
    {
        $transaction = WalletTransaction::where('id', $transactionId)
            ->where('maid_id', $this->maid_id)
            ->where('transaction_type', 'withdrawal_request')
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            return null;
        }

        $amount = $transaction->amount;
        $this->pending_withdrawal -= $amount;
        $this->last_activity_at = now();
        $this->save();

        $transaction->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'processed_at' => now(),
        ]);

        return $transaction;
    }

    /**
     * Calculate next salary due date based on salary day.
     */
    public function calculateNextSalaryDate(): ?\Carbon\Carbon
    {
        if (!$this->salary_day) {
            return null;
        }

        $today = now();
        $salaryDate = $today->copy()->setDay($this->salary_day);

        // If salary day has passed this month, set to next month
        if ($salaryDate->isPast() || $salaryDate->isToday()) {
            $salaryDate->addMonth();
        }

        return $salaryDate;
    }

    /**
     * Update next salary due date.
     */
    public function updateNextSalaryDate(): void
    {
        $nextDate = $this->calculateNextSalaryDate();
        if ($nextDate) {
            $this->next_salary_due_date = $nextDate;
            $this->save();
        }
    }

    /**
     * Get bank details as formatted string.
     */
    public function getBankDetailsFormatted(): string
    {
        if (!$this->bank_name || !$this->account_number) {
            return 'No bank details provided';
        }

        return "{$this->bank_name} - {$this->account_number} ({$this->account_name})";
    }

    /**
     * Check if bank details are complete.
     */
    public function hasCompleteBankDetails(): bool
    {
        return !empty($this->bank_name)
            && !empty($this->account_number)
            && !empty($this->account_name);
    }
}
