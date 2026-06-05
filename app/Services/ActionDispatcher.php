<?php

namespace App\Services;

use App\Models\{AgentOverride, HumanTask};
use Illuminate\Support\Facades\Log;

class ActionDispatcher
{
    public function __construct(
        private AgentEventLogger $logger
    ) {}

    public function check(
        string $agentName,
        string $eventType,
        float  $estimatedCostUsd = 0.0
    ): string {
        try {
            $override = AgentOverride::forAgent($agentName);
        } catch (\Exception $e) {
            Log::warning("ActionDispatcher: No override record for {$agentName}");
            return 'execute';
        }

        if ($override->kill_switch) {
            return 'killed';
        }

        if ($estimatedCostUsd > 0 && $override->wouldBreachSpendCap($estimatedCostUsd)) {
            $this->logger->log(
                $agentName, 'budget.cap_reached', 'warning',
                "Daily spend cap reached for {$agentName}. Task held.",
                ['estimated_cost' => $estimatedCostUsd, 'daily_cap' => $override->daily_spend_cap_usd]
            );
            return 'skip';
        }

        if ($override->isPaused()) {
            return $override->auto_route_to_human ? 'hitl' : 'skip';
        }

        if ($override->isSupervised() && $override->requiresApprovalFor($eventType)) {
            return 'hitl';
        }

        if ($override->mode === 'paused' && $override->auto_resume_at?->isPast()) {
            $override->update(['mode' => 'active', 'auto_resume_at' => null]);
            $override->clearCache();
        }

        return 'execute';
    }

    public function routeToHuman(
        string $agentName,
        string $taskType,
        string $description,
        array  $payload,
        array  $options = []
    ): HumanTask {
        return HumanTask::create([
            'agent_name'      => $agentName,
            'task_type'       => $taskType,
            'reason'          => $options['reason'] ?? 'agent_disabled',
            'task_payload'    => $payload,
            'description'     => $description,
            'priority'        => $options['priority'] ?? 3,
            'related_user_id' => $options['related_user_id'] ?? null,
            'due_by'          => $options['due_by'] ?? now()->addHours(8),
        ]);
    }
}
