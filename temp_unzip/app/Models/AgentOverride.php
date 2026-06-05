<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AgentOverride extends Model
{
    protected $fillable = [
        'agent_name', 'mode', 'supervised_action_types', 'auto_route_to_human',
        'kill_switch', 'override_reason', 'set_by', 'auto_resume_at',
        'max_calls_per_hour', 'daily_spend_cap_usd',
        'current_daily_spend_usd', 'spend_reset_at',
    ];

    protected $casts = [
        'supervised_action_types' => 'array',
        'auto_route_to_human'     => 'boolean',
        'kill_switch'             => 'boolean',
        'auto_resume_at'          => 'datetime',
        'spend_reset_at'          => 'datetime',
    ];

    public static function forAgent(string $agentName): self
    {
        return Cache::remember("agent_override_{$agentName}", 60, function () use ($agentName) {
            return static::where('agent_name', $agentName)->firstOrFail();
        });
    }

    public function setByUser()
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function isActive(): bool
    {
        return !$this->kill_switch && $this->mode === 'active';
    }

    public function isPaused(): bool
    {
        return $this->kill_switch || $this->mode === 'paused';
    }

    public function isSupervised(): bool
    {
        return $this->mode === 'supervised';
    }

    public function requiresApprovalFor(string $eventType): bool
    {
        if (!$this->isSupervised()) {
            return false;
        }
        if ($this->supervised_action_types === null) {
            return true;
        }
        return in_array($eventType, $this->supervised_action_types);
    }

    public function wouldBreachSpendCap(float $estimatedCostUsd): bool
    {
        if (!$this->daily_spend_cap_usd) {
            return false;
        }
        return ($this->current_daily_spend_usd + $estimatedCostUsd) > $this->daily_spend_cap_usd;
    }

    public function clearCache(): void
    {
        Cache::forget("agent_override_{$this->agent_name}");
    }
}
