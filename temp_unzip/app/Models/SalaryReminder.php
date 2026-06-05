<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_schedule_id',
        'reminder_type',
        'sent_to_employer_at',
        'employer_response',
        'escalated_to_admin_at',
        'admin_notes',
        'reminder_sequence',
        'context_json',
    ];

    protected $casts = [
        'sent_to_employer_at' => 'datetime',
        'escalated_to_admin_at' => 'datetime',
        'reminder_sequence' => 'integer',
        'context_json' => 'array',
    ];

    /**
     * Get the salary schedule for this reminder.
     */
    public function salarySchedule(): BelongsTo
    {
        return $this->belongsTo(SalarySchedule::class, 'salary_schedule_id');
    }

    /**
     * Scope for reminders by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('reminder_type', $type);
    }

    /**
     * Scope for upcoming reminders.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('reminder_type', 'upcoming');
    }

    /**
     * Scope for overdue reminders.
     */
    public function scopeOverdue($query)
    {
        return $query->where('reminder_type', 'overdue');
    }

    /**
     * Scope for escalated reminders.
     */
    public function scopeEscalated($query)
    {
        return $query->where('reminder_type', 'escalated');
    }

    /**
     * Scope for reminders not yet sent.
     */
    public function scopeNotSent($query)
    {
        return $query->whereNull('sent_to_employer_at');
    }

    /**
     * Scope for reminders already sent.
     */
    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_to_employer_at');
    }

    /**
     * Scope for reminders awaiting employer response.
     */
    public function scopeAwaitingResponse($query)
    {
        return $query->whereNotNull('sent_to_employer_at')
            ->whereNull('employer_response');
    }

    /**
     * Mark reminder as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'sent_to_employer_at' => now(),
        ]);
    }

    /**
     * Record employer response.
     */
    public function recordResponse(string $response, ?string $notes = null): void
    {
        $this->update([
            'employer_response' => $response,
            'admin_notes' => $notes ? $this->admin_notes . "\n" . $notes : $this->admin_notes,
        ]);
    }

    /**
     * Escalate to admin.
     */
    public function escalateToAdmin(?string $notes = null): void
    {
        $this->update([
            'reminder_type' => 'escalated',
            'escalated_to_admin_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    /**
     * Check if reminder has been escalated.
     */
    public function isEscalated(): bool
    {
        return $this->reminder_type === 'escalated' || !is_null($this->escalated_to_admin_at);
    }

    /**
     * Check if reminder has been sent.
     */
    public function isSent(): bool
    {
        return !is_null($this->sent_to_employer_at);
    }

    /**
     * Check if awaiting employer response.
     */
    public function isAwaitingResponse(): bool
    {
        return $this->isSent() && is_null($this->employer_response);
    }

    /**
     * Get reminder type label.
     */
    public function getReminderTypeLabelAttribute(): string
    {
        return match ($this->reminder_type) {
            'upcoming' => 'Upcoming Payment',
            'overdue' => 'Overdue Payment',
            'escalated' => 'Escalated to Admin',
            default => ucfirst($this->reminder_type),
        };
    }
}
