<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AgentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_name', 'event_type', 'severity', 'summary', 'detail',
        'triggered_by_human', 'triggered_by_user_id', 'related_user_id',
        'related_model', 'related_id', 'requires_approval', 'approved',
        'approved_by', 'approval_note', 'approved_at',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'estimated_cost_usd', 'llm_model', 'duration_ms', 'channel',
    ];

    protected $casts = [
        'detail'              => 'array',
        'triggered_by_human'  => 'boolean',
        'requires_approval'   => 'boolean',
        'approved'            => 'boolean',
        'approved_at'         => 'datetime',
        'created_at'          => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('requires_approval', true)->whereNull('approved');
    }

    public function scopeForAgent(Builder $query, string $agent): Builder
    {
        return $query->where('agent_name', $agent);
    }

    public function scopeRecent(Builder $query, int $minutes = 60): Builder
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    public function getCostStringAttribute(): string
    {
        if (!$this->estimated_cost_usd) {
            return '—';
        }
        return '$' . number_format($this->estimated_cost_usd, 4);
    }

    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'success' => 'green',
            'warning' => 'yellow',
            'error'   => 'red',
            'pending' => 'purple',
            default   => 'blue',
        };
    }
}
