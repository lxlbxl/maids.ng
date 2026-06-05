<?php

namespace App\Agents\Concerns;

use App\Services\{ActionDispatcher, AgentEventLogger};

trait LogsEvents
{
    protected function getLogger(): AgentEventLogger
    {
        return app(AgentEventLogger::class);
    }

    protected function getDispatcher(): ActionDispatcher
    {
        return app(ActionDispatcher::class);
    }

    protected function logEvent(
        string $eventType,
        string $severity,
        string $summary,
        array  $detail = [],
        array  $options = []
    ): \App\Models\AgentEvent {
        return $this->getLogger()->log(
            $this->agentName,
            $eventType,
            $severity,
            $summary,
            $detail,
            $options
        );
    }

    protected function canProceed(
        string $eventType,
        string $taskType,
        string $taskDescription,
        array  $taskPayload,
        array  $options = []
    ): bool {
        $permission = $this->getDispatcher()->check($this->agentName, $eventType);

        if ($permission === 'execute') {
            return true;
        }

        if ($permission === 'hitl') {
            $this->getDispatcher()->routeToHuman(
                $this->agentName,
                $taskType,
                $taskDescription,
                $taskPayload,
                array_merge($options, ['reason' => 'hitl_required'])
            );
        }

        if ($permission === 'killed') {
            $this->logEvent($eventType, 'error', "Kill switch active — {$this->agentName} is halted.", []);
        }

        return false;
    }
}
