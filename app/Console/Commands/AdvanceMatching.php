<?php

namespace App\Console\Commands;

use App\Services\MatchingCadenceService;
use Illuminate\Console\Command;

/**
 * Move every live match forward one step.
 *
 * Runs on a cron so a request cannot stall on a helper who stopped replying —
 * which is how Tafa waited on a helper who never showed up until a human
 * happened to notice.
 *
 * Prints only what changed, so a quiet run is silent.
 */
class AdvanceMatching extends Command
{
    protected $signature = 'requests:advance-matching {--dry-run : Show what would happen}';
    protected $description = 'Advance the matching cadence: build queues, chase silences, promote backups';

    public function handle(MatchingCadenceService $svc): int
    {
        $r = $svc->tick((bool) $this->option('dry-run'));

        if ($this->option('dry-run')) {
            $this->line('<fg=yellow>DRY RUN</>');
        }

        foreach ($r['queues_built'] as $ref) {
            $this->info("queue built for {$ref} — claim window closed");
        }

        foreach ($r['follow_ups'] as $f) {
            $this->line("follow up with {$f['name']} ({$f['phone']}) on {$f['request']} — no reply yet");
        }

        foreach ($r['promoted'] as $p) {
            $this->warn("{$p['request']}: {$p['dropped']} unreachable → "
                . ($p['next'] ? "approach {$p['next']}" : 'NO BACKUP LEFT'));
        }

        foreach ($r['thin'] as $t) {
            $this->warn("{$t['request']} has only {$t['remaining']} candidate(s) left — top the queue up");
        }

        $total = count($r['queues_built']) + count($r['follow_ups']) + count($r['promoted']);
        if (!$total && !$r['thin']) {
            $this->line('Nothing to advance.');
        }

        return self::SUCCESS;
    }
}
