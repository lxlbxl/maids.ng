<?php

namespace App\Services;

use App\Models\{AgentOverride, AgentEvent};
use App\Models\User;

class AgentOverrideService
{
    public function __construct(
        private AgentEventLogger $logger
    ) {}

    public function pause(
        string $agentName,
        User   $operator,
        string $reason,
        ?int   $autoResumeMinutes = null
    ): AgentOverride {
        $override = AgentOverride::where('agent_name', $agentName)->firstOrFail();

        $override->update([
            'mode'            => 'paused',
            'override_reason' => $reason,
            'set_by'          => $operator->id,
            'auto_resume_at'  => $autoResumeMinutes ? now()->addMinutes($autoResumeMinutes) : null,
        ]);

        $override->clearCache();

        $this->logger->log(
            $agentName, 'agent.paused', 'warning',
            "Agent paused by {$operator->name}: {$reason}",
            ['reason' => $reason, 'auto_resume_minutes' => $autoResumeMinutes],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );

        return $override;
    }

    public function resume(string $agentName, User $operator): AgentOverride
    {
        $override = AgentOverride::where('agent_name', $agentName)->firstOrFail();

        $override->update([
            'mode'            => 'active',
            'override_reason' => null,
            'auto_resume_at'  => null,
        ]);

        $override->clearCache();

        $this->logger->log(
            $agentName, 'agent.resumed', 'success',
            "Agent resumed by {$operator->name}",
            [],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );

        return $override;
    }

    public function supervise(
        string  $agentName,
        User    $operator,
        string  $reason,
        ?array  $actionTypes = null
    ): AgentOverride {
        $override = AgentOverride::where('agent_name', $agentName)->firstOrFail();

        $override->update([
            'mode'                     => 'supervised',
            'supervised_action_types'  => $actionTypes,
            'override_reason'          => $reason,
            'set_by'                   => $operator->id,
        ]);

        $override->clearCache();

        $this->logger->log(
            $agentName, 'agent.supervised', 'warning',
            "Agent put under supervision by {$operator->name}: {$reason}",
            ['reason' => $reason, 'supervised_actions' => $actionTypes ?? 'all'],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );

        return $override;
    }

    public function killSwitch(string $agentName, User $operator, string $reason): AgentOverride
    {
        $override = AgentOverride::where('agent_name', $agentName)->firstOrFail();

        $override->update([
            'kill_switch'     => true,
            'override_reason' => "[KILL SWITCH] {$reason}",
            'set_by'          => $operator->id,
        ]);

        $override->clearCache();

        $this->logger->log(
            $agentName, 'agent.kill_switch', 'error',
            "KILL SWITCH activated by {$operator->name}: {$reason}",
            ['reason' => $reason],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );

        return $override;
    }

    public function releaseKillSwitch(string $agentName, User $operator): AgentOverride
    {
        $override = AgentOverride::where('agent_name', $agentName)->firstOrFail();

        $override->update([
            'kill_switch'     => false,
            'mode'            => 'active',
            'override_reason' => null,
        ]);

        $override->clearCache();

        $this->logger->log(
            $agentName, 'agent.kill_switch_released', 'success',
            "Kill switch released by {$operator->name} — agent is active",
            [],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );

        return $override;
    }

    public function updateSpendCap(string $agentName, User $operator, float $capUsd): void
    {
        AgentOverride::where('agent_name', $agentName)->update([
            'daily_spend_cap_usd' => $capUsd,
        ]);

        AgentOverride::forAgent($agentName)->clearCache();

        $this->logger->log(
            $agentName, 'agent.spend_cap_updated', 'info',
            "Daily spend cap updated to \${$capUsd} by {$operator->name}",
            ['new_cap' => $capUsd],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );
    }
}
