<?php

namespace App\Console\Commands;

use App\Models\AgentOverride;
use App\Services\AgentEventLogger;
use Illuminate\Console\Command;

class ResumeAllAgents extends Command
{
    protected $signature = 'agents:resume-all';
    protected $description = 'Resume all agents to active state';

    public function handle(AgentEventLogger $logger): int
    {
        AgentOverride::query()->update([
            'mode'            => 'active',
            'kill_switch'     => false,
            'override_reason' => null,
        ]);

        foreach (AgentOverride::all() as $override) {
            $override->clearCache();
        }

        $logger->log('system', 'system.all_resumed', 'success', 'All agents resumed', []);
        $this->info('All agents resumed.');

        return Command::SUCCESS;
    }
}
