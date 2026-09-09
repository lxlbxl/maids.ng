<?php

namespace App\Console\Commands;

use App\Models\LeadAttribution;
use App\Models\WaClick;
use Illuminate\Console\Command;

class PruneAttribution extends Command
{
    protected $signature = 'attribution:prune';

    protected $description = 'Drop stale WhatsApp click rows never tied to a user, and expired lead-attribution stashes';

    public function handle(): int
    {
        $clicks = WaClick::whereNull('resolved_user_id')
            ->where('created_at', '<', now()->subDays(45))
            ->delete();

        $leads = LeadAttribution::where('expires_at', '<', now())->delete();

        $this->info("Pruned {$clicks} unresolved wa_clicks and {$leads} expired lead_attributions.");

        return self::SUCCESS;
    }
}
