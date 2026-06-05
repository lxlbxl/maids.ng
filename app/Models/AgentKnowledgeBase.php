<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentKnowledgeBase extends Model
{
    protected $table = 'agent_knowledge_base';

    protected $fillable = [
        'category',
        'title',
        'content',
        'applies_to',
        'visible_to_tiers',
        'priority',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'applies_to' => 'array',
        'visible_to_tiers' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAgent($query, string $agentName)
    {
        return $query->where(function ($q) use ($agentName) {
            $q->whereJsonContains('applies_to', 'all')
                ->orWhereJsonContains('applies_to', $agentName);
        });
    }

    public function scopeForTier($query, string $tier)
    {
        return $query->where(function ($q) use ($tier) {
            $q->whereJsonContains('visible_to_tiers', 'all')
                ->orWhereJsonContains('visible_to_tiers', $tier);
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    public function appliesToAgent(string $agentName): bool
    {
        return in_array('all', $this->applies_to)
            || in_array($agentName, $this->applies_to);
    }

    public function isVisibleToTier(string $tier): bool
    {
        return in_array('all', $this->visible_to_tiers)
            || in_array($tier, $this->visible_to_tiers);
    }
}