<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_type',
        'employer_id',
        'maid_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference_id',
        'reference_type',
        'payment_method',
        'payment_reference',
        'metadata',
        'status',
        'processed_at',
        'processed_by',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the employer associated with this transaction.
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    /**
     * Get the maid associated with this transaction.
     */
    public function maid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maid_id');
    }

    /**
     * Get the admin who processed this transaction.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the assignment associated with this transaction.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MaidAssignment::class, 'reference_id')
            ->where('reference_type', 'assignment');
    }

    /**
     * Get the preference associated with this transaction.
     */
    public function preference(): BelongsTo
    {
        return $this->belongsTo(EmployerPreference::class, 'reference_id')
            ->where('reference_type', 'preference');
    }

    /**
     * Scope for employer transactions.
     */
    public function scopeForEmployer($query, int $employerId)
    {
        return $query->where('wallet_type', 'employer')
            ->where('employer_id', $employerId);
    }

    /**
     * Scope for maid transactions.
     */
    public function scopeForMaid($query, int $maidId)
    {
        return $query->where('wallet_type', 'maid')
            ->where('maid_id', $maidId);
    }

    /**
     * Scope for credits.
     */
    public function scopeCredits($query)
    {
        return $query->where('transaction_type', 'credit');
    }

    /**
     * Scope for debits.
     */
    public function scopeDebits($query)
    {
        return $query->where('transaction_type', 'debit');
    }

    /**
     * Scope for escrow transactions.
     */
    public function scopeEscrow($query)
    {
        return $query->whereIn('transaction_type', ['escrow_hold', 'escrow_release', 'escrow_transfer']);
    }

    /**
     * Scope for completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get transaction type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->transaction_type) {
            'credit' => 'Credit',
            'debit' => 'Debit',
            'escrow_hold' => 'Escrow Hold',
            'escrow_release' => 'Escrow Release',
            'escrow_transfer' => 'Escrow Transfer',
            'refund' => 'Refund',
            'salary_payment' => 'Salary Payment',
            'withdrawal' => 'Withdrawal',
            'deposit' => 'Deposit',
            default => ucfirst(str_replace('_', ' ', $this->transaction_type)),
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Check if transaction is a credit.
     */
    public function isCredit(): bool
    {
        return in_array($this->transaction_type, ['credit', 'escrow_release', 'refund', 'deposit']);
    }

    /**
     * Check if transaction is a debit.
     */
    public function isDebit(): bool
    {
        return in_array($this->transaction_type, ['debit', 'escrow_hold', 'escrow_transfer', 'withdrawal']);
    }

    /**
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark transaction as failed.
     */
    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Mark transaction as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }
}
