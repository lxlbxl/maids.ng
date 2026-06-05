<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_schedule_id',
        'assignment_id',
        'employer_id',
        'maid_id',
        'period_start_date',
        'period_end_date',
        'due_date',
        'paid_date',
        'gross_amount',
        'deductions',
        'net_amount',
        'deduction_breakdown',
        'status',
        'employer_payment_method',
        'employer_payment_reference',
        'employer_paid_at',
        'maid_payment_method',
        'maid_payment_reference',
        'maid_paid_at',
        'employer_wallet_txn_id',
        'maid_wallet_txn_id',
        'reminder_count',
        'first_reminder_sent_at',
        'last_reminder_sent_at',
        'final_notice_sent_at',
        'auto_processed',
        'auto_processed_at',
        'processed_by',
        'dispute_reason',
        'disputed_at',
        'dispute_resolved_by',
        'dispute_resolved_at',
        'dispute_resolution',
        'receipt_number',
        'receipt_url',
        'payment_proof',
        'employer_notes',
        'maid_notes',
        'admin_notes',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'gross_amount' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'deduction_breakdown' => 'array',
        'employer_paid_at' => 'datetime',
        'maid_paid_at' => 'datetime',
        'first_reminder_sent_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'final_notice_sent_at' => 'datetime',
        'auto_processed' => 'boolean',
        'auto_processed_at' => 'datetime',
        'disputed_at' => 'datetime',
        'dispute_resolved_at' => 'datetime',
        'payment_proof' => 'array',
    ];

    /**
     * Get the salary schedule.
     */
    public function salarySchedule(): BelongsTo
    {
        return $this->belongsTo(SalarySchedule::class, 'salary_schedule_id');
    }

    /**
     * Get the assignment.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MaidAssignment::class, 'assignment_id');
    }

    /**
     * Get the employer.
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    /**
     * Get the maid.
     */
    public function maid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maid_id');
    }

    /**
     * Get the employer wallet transaction.
     */
    public function employerWalletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'employer_wallet_txn_id');
    }

    /**
     * Get the maid wallet transaction.
     */
    public function maidWalletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'maid_wallet_txn_id');
    }

    /**
     * Get the processor.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the dispute resolver.
     */
    public function disputeResolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispute_resolved_by');
    }

    /**
     * Scope for payments by employer.
     */
    public function scopeForEmployer($query, int $employerId)
    {
        return $query->where('employer_id', $employerId);
    }

    /**
     * Scope for payments by maid.
     */
    public function scopeForMaid($query, int $maidId)
    {
        return $query->where('maid_id', $maidId);
    }

    /**
     * Scope for pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for overdue payments.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', now());
    }

    /**
     * Scope for paid payments.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid_to_maid');
    }

    /**
     * Scope for disputed payments.
     */
    public function scopeDisputed($query)
    {
        return $query->where('status', 'disputed');
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }

    /**
     * Check if payment is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid_to_maid';
    }

    /**
     * Check if payment is disputed.
     */
    public function isDisputed(): bool
    {
        return $this->status === 'disputed';
    }

    /**
     * Mark employer as paid.
     */
    public function markEmployerPaid(string $method, string $reference): void
    {
        $this->update([
            'status' => 'employer_paid',
            'employer_payment_method' => $method,
            'employer_payment_reference' => $reference,
            'employer_paid_at' => now(),
        ]);
    }

    /**
     * Mark as paid to maid.
     */
    public function markPaidToMaid(string $method, string $reference): void
    {
        $this->update([
            'status' => 'paid_to_maid',
            'maid_payment_method' => $method,
            'maid_payment_reference' => $reference,
            'maid_paid_at' => now(),
            'paid_date' => now(),
        ]);
    }

    /**
     * Mark as processing.
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => 'processing',
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markFailed(): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    /**
     * Raise dispute.
     */
    public function raiseDispute(string $reason): void
    {
        $this->update([
            'status' => 'disputed',
            'dispute_reason' => $reason,
            'disputed_at' => now(),
        ]);
    }

    /**
     * Resolve dispute.
     */
    public function resolveDispute(string $resolution, int $resolvedBy): void
    {
        $this->update([
            'status' => 'paid_to_maid',
            'dispute_resolution' => $resolution,
            'dispute_resolved_by' => $resolvedBy,
            'dispute_resolved_at' => now(),
        ]);
    }

    /**
     * Send reminder.
     */
    public function sendReminder(): void
    {
        $this->reminder_count++;

        if ($this->reminder_count === 1) {
            $this->first_reminder_sent_at = now();
        }

        $this->last_reminder_sent_at = now();

        // Send final notice on 3rd reminder
        if ($this->reminder_count >= 3) {
            $this->final_notice_sent_at = now();
        }

        $this->save();
    }

    /**
     * Mark as auto processed.
     */
    public function markAutoProcessed(): void
    {
        $this->update([
            'auto_processed' => true,
            'auto_processed_at' => now(),
        ]);
    }

    /**
     * Generate receipt number.
     */
    public function generateReceiptNumber(): string
    {
        $number = 'SAL-' . now()->format('Y') . '-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
        $this->update(['receipt_number' => $number]);
        return $number;
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'employer_paid' => 'Employer Paid',
            'processing' => 'Processing',
            'paid_to_maid' => 'Paid to Maid',
            'failed' => 'Failed',
            'disputed' => 'Disputed',
            'refunded' => 'Refunded',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get days overdue.
     */
    public function getDaysOverdueAttribute(): ?int
    {
        if (!$this->isOverdue()) {
            return null;
        }

        return $this->due_date->diffInDays(now(), false);
    }

    /**
     * Get formatted period.
     */
    public function getPeriodFormattedAttribute(): string
    {
        return $this->period_start_date->format('M d') . ' - ' . $this->period_end_date->format('M d, Y');
    }
}
