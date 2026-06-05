<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiMatchingQueue extends Model
{
    use HasFactory;

    protected $table = 'ai_matching_queue';

    protected $fillable = [
        'job_id',
        'job_type',
        'employer_id',
        'maid_id',
        'preference_id',
        'assignment_id',
        'priority',
        'scheduled_at',
        'started_at',
        'completed_at',
        'status',
        'attempt_count',
        'max_attempts',
        'next_attempt_at',
        'retry_delay_minutes',
        'payload',
        'result',
        'match_candidates',
        'selected_maid_id',
        'ai_confidence_score',
        'ai_reasoning',
        'ai_analysis_data',
        'last_error',
        'error_log',
        'failure_category',
        'processed_by_instance',
        'processing_duration_ms',
        'worker_pid',
        'context_snapshot',
        'parent_job_id',
        'job_chain_sequence',
        'requires_review',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'review_decision',
        'notification_sent',
        'notification_sent_at',
        'notification_channel',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'notification_sent_at' => 'datetime',
        'payload' => 'array',
        'result' => 'array',
        'match_candidates' => 'array',
        'ai_analysis_data' => 'array',
        'error_log' => 'array',
        'context_snapshot' => 'array',
        'ai_confidence_score' => 'decimal:2',
        'attempt_count' => 'integer',
        'max_attempts' => 'integer',
        'retry_delay_minutes' => 'integer',
        'priority' => 'integer',
        'job_chain_sequence' => 'integer',
        'processing_duration_ms' => 'integer',
        'requires_review' => 'boolean',
        'notification_sent' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->job_id)) {
                $model->job_id = (string) \Illuminate\Support\Str::uuid();
            }
        });
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
     * Get the selected maid.
     */
    public function selectedMaid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_maid_id');
    }

    /**
     * Get the preference.
     */
    public function preference(): BelongsTo
    {
        return $this->belongsTo(EmployerPreference::class, 'preference_id');
    }

    /**
     * Get the assignment.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MaidAssignment::class, 'assignment_id');
    }

    /**
     * Get the parent job.
     */
    public function parentJob(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_job_id');
    }

    /**
     * Get child jobs.
     */
    public function childJobs(): HasMany
    {
        return $this->hasMany(self::class, 'parent_job_id');
    }

    /**
     * Get the reviewer.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for pending jobs.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for scheduled jobs.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope for processing jobs.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope for completed jobs.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed jobs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for jobs ready to process.
     */
    public function scopeReadyToProcess($query)
    {
        return $query->whereIn('status', ['pending', 'scheduled'])
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('priority')
            ->orderBy('scheduled_at');
    }

    /**
     * Scope for jobs by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('job_type', $type);
    }

    /**
     * Scope for jobs by employer.
     */
    public function scopeForEmployer($query, int $employerId)
    {
        return $query->where('employer_id', $employerId);
    }

    /**
     * Scope for jobs requiring review.
     */
    public function scopeRequiresReview($query)
    {
        return $query->where('requires_review', true)
            ->whereNull('reviewed_at');
    }

    /**
     * Scope for high priority jobs.
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', '<=', 3);
    }

    /**
     * Check if job is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if job is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if job is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if job is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if job can be retried.
     */
    public function canRetry(): bool
    {
        return $this->attempt_count < $this->max_attempts
            && in_array($this->status, ['failed', 'pending']);
    }

    /**
     * Mark as processing.
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
            'attempt_count' => $this->attempt_count + 1,
        ]);
    }

    /**
     * Mark as completed.
     */
    public function markCompleted(array $result = []): void
    {
        $duration = $this->started_at ? now()->diffInMilliseconds($this->started_at) : null;

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'result' => $result,
            'processing_duration_ms' => $duration,
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markFailed(string $error, string $category = 'unknown'): void
    {
        $errorLog = $this->error_log ?? [];
        $errorLog[] = [
            'attempt' => $this->attempt_count,
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ];

        $update = [
            'status' => 'failed',
            'last_error' => $error,
            'error_log' => $errorLog,
            'failure_category' => $category,
        ];

        if ($this->canRetry()) {
            $update['status'] = 'pending';
            $update['next_attempt_at'] = now()->addMinutes($this->retry_delay_minutes);
        }

        $this->update($update);
    }

    /**
     * Schedule for later.
     */
    public function scheduleFor(\Carbon\Carbon $when): void
    {
        $this->update([
            'status' => 'scheduled',
            'scheduled_at' => $when,
        ]);
    }

    /**
     * Cancel the job.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    /**
     * Pause the job.
     */
    public function pause(): void
    {
        $this->update([
            'status' => 'paused',
        ]);
    }

    /**
     * Resume the job.
     */
    public function resume(): void
    {
        $this->update([
            'status' => 'pending',
        ]);
    }

    /**
     * Set match candidates.
     */
    public function setMatchCandidates(array $candidates): void
    {
        $this->update([
            'match_candidates' => $candidates,
        ]);
    }

    /**
     * Set AI analysis results.
     */
    public function setAiResults(float $confidenceScore, string $reasoning, array $analysisData = []): void
    {
        $this->update([
            'ai_confidence_score' => $confidenceScore,
            'ai_reasoning' => $reasoning,
            'ai_analysis_data' => $analysisData,
        ]);
    }

    /**
     * Mark for review.
     */
    public function markForReview(): void
    {
        $this->update([
            'requires_review' => true,
        ]);
    }

    /**
     * Review the job.
     */
    public function review(int $reviewerId, string $decision, string $notes = ''): void
    {
        $this->update([
            'requires_review' => false,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_decision' => $decision,
            'review_notes' => $notes,
        ]);
    }

    /**
     * Mark notification as sent.
     */
    public function markNotificationSent(string $channel): void
    {
        $this->update([
            'notification_sent' => true,
            'notification_sent_at' => now(),
            'notification_channel' => $channel,
        ]);
    }

    /**
     * Create a child job.
     */
    public function createChildJob(string $jobType, array $payload = [], int $priority = null): self
    {
        return self::create([
            'job_type' => $jobType,
            'employer_id' => $this->employer_id,
            'preference_id' => $this->preference_id,
            'assignment_id' => $this->assignment_id,
            'priority' => $priority ?? $this->priority,
            'payload' => $payload,
            'parent_job_id' => $this->id,
            'job_chain_sequence' => $this->childJobs()->count() + 1,
            'context_snapshot' => $this->context_snapshot,
            'status' => 'pending',
        ]);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'scheduled' => 'Scheduled',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'paused' => 'Paused',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get job type label.
     */
    public function getJobTypeLabelAttribute(): string
    {
        return match ($this->job_type) {
            'auto_match' => 'Auto Match',
            'replacement_search' => 'Replacement Search',
            'guarantee_match' => 'Guarantee Match',
            'status_check' => 'Status Check',
            'reminder_send' => 'Reminder Send',
            'salary_reminder' => 'Salary Reminder',
            'follow_up' => 'Follow Up',
            default => ucfirst(str_replace('_', ' ', $this->job_type)),
        };
    }

    /**
     * Get priority label.
     */
    public function getPriorityLabelAttribute(): string
    {
        return match (true) {
            $this->priority <= 2 => 'Critical',
            $this->priority <= 4 => 'High',
            $this->priority <= 6 => 'Medium',
            $this->priority <= 8 => 'Low',
            default => 'Very Low',
        };
    }

    /**
     * Get processing duration in human readable format.
     */
    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->processing_duration_ms) {
            return null;
        }

        $seconds = $this->processing_duration_ms / 1000;

        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }

        $minutes = $seconds / 60;
        return round($minutes, 2) . 'm';
    }
}
