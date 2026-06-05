<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class HumanTask extends Model
{
    protected $table = 'human_task_queue';

    protected $fillable = [
        'agent_name', 'task_type', 'reason', 'task_payload', 'description',
        'priority', 'status', 'assigned_to', 'completed_by', 'completion_notes',
        'original_job_class', 'original_job_payload', 'triggered_by_event_id',
        'related_user_id', 'due_by', 'assigned_at', 'completed_at',
    ];

    protected $casts = [
        'task_payload'         => 'array',
        'original_job_payload' => 'array',
        'due_by'               => 'datetime',
        'assigned_at'          => 'datetime',
        'completed_at'         => 'datetime',
    ];

    public function assignedOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedByOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function triggerEvent(): BelongsTo
    {
        return $this->belongsTo(AgentEvent::class, 'triggered_by_event_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_by')
                     ->where('due_by', '<', now())
                     ->whereNotIn('status', ['completed', 'skipped', 'delegated']);
    }

    public function isOverdue(): bool
    {
        return $this->due_by && $this->due_by->isPast()
            && !in_array($this->status, ['completed', 'skipped', 'delegated']);
    }
}
