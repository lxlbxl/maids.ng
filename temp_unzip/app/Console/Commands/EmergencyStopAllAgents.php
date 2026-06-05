<?php

namespace App\Console\Commands;

use App\Models\AgentOverride;
use App\Services\AgentEventLogger;
use Illuminate\Console\Command;

class EmergencyStopAllAgents extends Command
{
    protected $signature   = 'agents:emergency-stop {reason}';
    protected $description = 'Immediately pause all agents and route all tasks to humans';

    public function handle(AgentEventLogger $logger): int
    {
        $reason = $this->argument('reason');

        AgentOverride::query()->update([
            'mode'            => 'paused',
            'kill_switch'     => true,
            'override_reason' => "[EMERGENCY] {$reason}",
        ]);

        foreach (AgentOverride::all() as $override) {
            $override->clearCache();
        }

        $logger->log(
            'system', 'system.emergency_stop', 'error',
            "EMERGENCY STOP: All agents halted. Reason: {$reason}",
            ['reason' => $reason, 'triggered_by' => 'CLI']
        );

        $this->error("All agents halted. Reason: {$reason}");
        $this->line("To resume all agents: php artisan agents:resume-all");

        return Command::SUCCESS;
    }
}
