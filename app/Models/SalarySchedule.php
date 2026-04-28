<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalarySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'employer_id',
        'maid_id',
        'monthly_salary',
        'salary_day',
        'employment_start_date',
        'first_salary_date',
        'current_period_start',
        'current_period_end',
        'next_salary_due_date',
        'reminder_days_before',
        'last_reminder_sent_at',
        'next_reminder_scheduled_at',
        'reminder_3_days_sent',
        'reminder_1_day_sent',
        'reminder_due_sent',
        'payment_status',
        'escrow_amount',
        'escrow_funded',
        'escrow_funded_at',
        'reminder_count',
        'escalation_level',
        'last_escalation_at',
        'salary_breakdown',
        'special_notes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'monthly_salary' => 'decimal:2',
        'salary_day' => 'integer',
        'employment_start_date' => 'date',
        'first_salary_date' => 'date',
        'current_period_start' => 'date',
        'current_period_end' => 'date',
        'next_salary_due_date' => 'date',
        'reminder_days_before' => 'integer',
        'last_reminder_sent_at' => 'datetime',
        'next_reminder_scheduled_at' => 'datetime',
        'reminder_3_days_sent' => 'boolean',
        'reminder_1_day_sent' => 'boolean',
        'reminder_due_sent' => 'boolean',
        'escrow_amount' => 'decimal:2',
        'escrow_funded' => 'boolean',
        'escrow_funded_at' => 'datetime',
        'reminder_count' => 'integer',
        'escalation_level' => 'integer',
        'last_escalation_at' => 'datetime',
        'salary_breakdown' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the assignment for this salary schedule.
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
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all salary payments for this schedule.
     */
    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class, 'salary_schedule_id');
    }

    /**
     * Scope for active schedules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for schedules by employer.
     */
    public function scopeForEmployer($query, int $employerId)
    {
        return $query->where('employer_id', $employerId);
    }

    /**
     * Scope for schedules by maid.
     */
    public function scopeForMaid($query, int $maidId)
    {
        return $query->where('maid_id', $maidId);
    }

    /**
     * Scope for schedules with salary due.
     */
    public function scopeSalaryDue($query)
    {
        return $query->where('next_salary_due_date', '<=', now()->addDays(7))
            ->where('is_active', true);
    }

    /**
     * Scope for schedules needing reminders.
     */
    public function scopeNeedsReminder($query)
    {
        return $query->where('is_active', true)
            ->where('payment_status', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_reminder_scheduled_at')
                    ->orWhere('next_reminder_scheduled_at', '<=', now());
            });
    }

    /**
     * Check if salary is due soon.
     */
    public function isSalaryDueSoon(int $days = 7): bool
    {
        if (!$this->next_salary_due_date) {
            return false;
        }

        return $this->next_salary_due_date->diffInDays(now(), false) <= $days;
    }

    /**
     * Check if reminder should be sent.
     */
    public function shouldSendReminder(): bool
    {
        if (!$this->is_active || $this->payment_status !== 'pending') {
            return false;
        }

        if (!$this->next_salary_due_date) {
            return false;
        }

        $reminderDate = $this->next_salary_due_date->copy()->subDays($this->reminder_days_before);

        return now()->gte($reminderDate) &&
            ($this->next_reminder_scheduled_at === null || now()->gte($this->next_reminder_scheduled_at));
    }

    /**
     * Calculate next salary due date.
     */
    public function calculateNextSalaryDate(): \Carbon\Carbon
    {
        $today = now();
        $salaryDate = $today->copy()->setDay($this->salary_day);

        // If salary day has passed this month, set to next month
        if ($salaryDate->isPast() || $salaryDate->isToday()) {
            $salaryDate->addMonth();
        }

        return $salaryDate;
    }

    /**
     * Advance to next salary period.
     */
    public function advancePeriod(): void
    {
        $this->current_period_end = $this->next_salary_due_date;
        $this->current_period_start = $this->next_salary_due_date->copy()->addDay();
        $this->next_salary_due_date = $this->calculateNextSalaryDate();
        $this->payment_status = 'pending';
        $this->reminder_count = 0;
        $this->escalation_level = 0;
        $this->last_reminder_sent_at = null;
        $this->next_reminder_scheduled_at = null;
        $this->save();
    }

    /**
     * Mark reminder as sent.
     */
    public function markReminderSent(): void
    {
        $this->reminder_count++;
        $this->last_reminder_sent_at = now();

        // Schedule next reminder for 2 days later if still pending
        if ($this->reminder_count < 3) {
            $this->next_reminder_scheduled_at = now()->addDays(2);
        }

        $this->payment_status = 'reminder_sent';
        $this->save();
    }

    /**
     * Fund escrow for salary.
     */
    public function fundEscrow(float $amount): bool
    {
        $wallet = EmployerWallet::where('employer_id', $this->employer_id)->first();

        if (!$wallet || !$wallet->hasSufficientBalance($amount)) {
            return false;
        }

        $wallet->holdInEscrow(
            $amount,
            "Salary escrow for schedule #{$this->id}",
            $this->id,
            'salary_schedule'
        );

        $this->escrow_amount += $amount;
        $this->escrow_funded_at = now();
        $this->payment_status = 'payment_initiated';
        $this->save();

        return true;
    }

    /**
     * Release escrow to maid.
     */
    public function releaseEscrowToMaid(): ?WalletTransaction
    {
        if ($this->escrow_amount <= 0) {
            return null;
        }

        $wallet = EmployerWallet::where('employer_id', $this->employer_id)->first();

        if (!$wallet) {
            return null;
        }

        $transaction = $wallet->releaseEscrowToMaid(
            $this->escrow_amount,
            $this->maid_id,
            "Salary payment for schedule #{$this->id}",
            $this->id
        );

        if ($transaction) {
            $this->escrow_amount = 0;
            $this->payment_status = 'paid';
            $this->save();
        }

        return $transaction;
    }

    /**
     * Escalate to higher level.
     */
    public function escalate(): void
    {
        $this->escalation_level++;
        $this->last_escalation_at = now();
        $this->save();
    }

    /**
     * Get payment status label.
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'pending' => 'Pending',
            'reminder_sent' => 'Reminder Sent',
            'payment_initiated' => 'Payment Initiated',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'disputed' => 'Disputed',
            default => ucfirst($this->payment_status),
        };
    }

    /**
     * Get days until salary is due.
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->next_salary_due_date) {
            return null;
        }

        return now()->diffInDays($this->next_salary_due_date, false);
    }
}
