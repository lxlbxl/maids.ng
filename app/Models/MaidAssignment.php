<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaidAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employer_id',
        'maid_id',
        'preference_id',
        'assigned_by',
        'assigned_by_type',
        'assignment_type',
        'status',
        'matching_fee_paid',
        'matching_fee_amount',
        'guarantee_match',
        'guarantee_period_days',
        'ai_match_score',
        'ai_match_reasoning',
        'employer_accepted_at',
        'employer_rejected_at',
        'employer_responded_at',
        'rejection_reason',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by',
        'reminder_sent',
        'ended_at',
        'response_deadline',
        'context_json',
        'matched_until',
        'salary_amount',
        'salary_currency',
        'job_location',
        'job_type',
        'special_requirements',
        'notes',
        'refund_amount',
        'refund_transaction_id',
    ];

    protected $casts = [
        'matching_fee_paid' => 'boolean',
        'matching_fee_amount' => 'decimal:2',
        'guarantee_match' => 'boolean',
        'ai_match_score' => 'decimal:2',
        'ai_match_reasoning' => 'array',
        'employer_accepted_at' => 'datetime',
        'employer_rejected_at' => 'datetime',
        'employer_responded_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reminder_sent' => 'boolean',
        'ended_at' => 'datetime',
        'response_deadline' => 'datetime',
        'context_json' => 'array',
        'matched_until' => 'datetime',
        'salary_amount' => 'decimal:2',
        'special_requirements' => 'array',
        'refund_amount' => 'decimal:2',
        'salary_amount' => 'decimal:2',
        'special_requirements' => 'array',
    ];

    /**
     * Get the employer for this assignment.
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    /**
     * Get the maid for this assignment.
     */
    public function maid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maid_id');
    }

    /**
     * Get the preference that led to this assignment.
     */
    public function preference(): BelongsTo
    {
        return $this->belongsTo(EmployerPreference::class, 'preference_id');
    }

    /**
     * Get the user who assigned the maid (AI, admin, or employer).
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the user who cancelled the assignment.
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Get the salary schedule for this assignment.
     */
    public function salarySchedule(): HasOne
    {
        return $this->hasOne(SalarySchedule::class, 'assignment_id');
    }

    /**
     * Get all salary payments for this assignment.
     */
    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class, 'assignment_id');
    }

    /**
     * Get all wallet transactions related to this assignment.
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'reference_id')
            ->where('reference_type', 'assignment');
    }

    /**
     * Get all notification logs for this assignment.
     */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'reference_id')
            ->where('reference_type', 'assignment');
    }

    /**
     * Get AI matching queue jobs for this assignment.
     */
    public function aiQueueJobs(): HasMany
    {
        return $this->hasMany(AiMatchingQueue::class, 'assignment_id');
    }

    /**
     * Scope for pending acceptance assignments.
     */
    public function scopePendingAcceptance($query)
    {
        return $query->where('status', 'pending_acceptance');
    }

    /**
     * Scope for accepted assignments.
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope for active assignments (accepted and not completed/cancelled).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'accepted')
            ->whereNull('completed_at')
            ->whereNull('cancelled_at');
    }

    /**
     * Scope for guarantee match assignments.
     */
    public function scopeGuaranteeMatch($query)
    {
        return $query->where('guarantee_match', true);
    }

    /**
     * Scope for assignments by employer.
     */
    public function scopeForEmployer($query, int $employerId)
    {
        return $query->where('employer_id', $employerId);
    }

    /**
     * Scope for assignments by maid.
     */
    public function scopeForMaid($query, int $maidId)
    {
        return $query->where('maid_id', $maidId);
    }

    /**
     * Check if assignment is pending acceptance.
     */
    public function isPendingAcceptance(): bool
    {
        return $this->status === 'pending_acceptance';
    }

    /**
     * Check if assignment is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if assignment is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'accepted'
            && is_null($this->completed_at)
            && is_null($this->cancelled_at);
    }

    /**
     * Check if assignment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if assignment is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if assignment is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if this is a guarantee match.
     */
    public function isGuaranteeMatch(): bool
    {
        return $this->guarantee_match;
    }

    /**
     * Check if matching fee is paid.
     */
    public function isMatchingFeePaid(): bool
    {
        return $this->matching_fee_paid;
    }

    /**
     * Check if maid is currently matched (unavailable to others).
     */
    public function isMaidMatched(): bool
    {
        return $this->isAccepted()
            && $this->matched_until
            && $this->matched_until->isFuture();
    }

    /**
     * Accept the assignment (employer accepts the maid).
     */
    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'employer_accepted_at' => now(),
            'started_at' => now(),
            'matched_until' => now()->addDays($this->guarantee_period_days ?? 90),
        ]);

        // Update maid availability
        if ($this->maid) {
            $this->maid->maidProfile()->update(['availability_status' => 'matched']);
        }
    }

    /**
     * Reject the assignment (employer rejects the maid).
     */
    public function reject(string $reason = ''): void
    {
        $this->update([
            'status' => 'rejected',
            'employer_rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // Refund matching fee to employer wallet
        if ($this->matching_fee_paid && $this->matching_fee_amount > 0) {
            $wallet = EmployerWallet::firstOrCreate(
                ['employer_id' => $this->employer_id],
                ['balance' => 0, 'currency' => $this->salary_currency ?? 'NGN']
            );

            $wallet->credit(
                $this->matching_fee_amount,
                "Refund for rejected maid assignment #{$this->id}",
                $this->id,
                'assignment'
            );
        }

        // Trigger replacement search if guarantee match
        if ($this->guarantee_match) {
            // This will be handled by an AI agent or event listener
            event(new \App\Events\AssignmentRejected($this));
        }
    }

    /**
     * Complete the assignment.
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Update maid availability back to available
        if ($this->maid) {
            $this->maid->maidProfile()->update(['availability_status' => 'available']);
        }
    }

    /**
     * Cancel the assignment.
     */
    public function cancel(string $reason = '', ?int $cancelledBy = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'cancelled_by' => $cancelledBy,
        ]);

        // Update maid availability back to available
        if ($this->maid) {
            $this->maid->maidProfile()->update(['availability_status' => 'available']);
        }
    }

    /**
     * Mark matching fee as paid.
     */
    public function markMatchingFeePaid(float $amount): void
    {
        $this->update([
            'matching_fee_paid' => true,
            'matching_fee_amount' => $amount,
        ]);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending_acceptance' => 'Pending Acceptance',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get assignment type label.
     */
    public function getAssignmentTypeLabelAttribute(): string
    {
        return match ($this->assignment_type) {
            'direct_selection' => 'Direct Selection',
            'guarantee_match' => 'Guarantee Match',
            'manual' => 'Manual Assignment',
            'auto' => 'Auto Assignment',
            default => ucfirst(str_replace('_', ' ', $this->assignment_type)),
        };
    }

    /**
     * Get remaining guarantee days.
     */
    public function getRemainingGuaranteeDaysAttribute(): ?int
    {
        if (!$this->matched_until) {
            return null;
        }

        return now()->diffInDays($this->matched_until, false);
    }

    /**
     * Check if guarantee period is still active.
     */
    public function isGuaranteeActive(): bool
    {
        return $this->guarantee_match
            && $this->matched_until
            && $this->matched_until->isFuture();
    }
}
