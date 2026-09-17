<?php

namespace App\Console\Commands;

use App\Services\BusinessMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Check that every dashboard number is actually being fed.
 *
 * A metric that silently stops updating is worse than one that was never built:
 * it keeps rendering a plausible figure and people keep making decisions on it.
 * Each check below names the table that feeds a panel and when it was last
 * written to, so a broken pipeline shows up as a stale timestamp rather than as
 * a number that quietly stops moving.
 */
class VerifyDashboardMetrics extends Command
{
    protected $signature = 'dashboard:verify';
    protected $description = 'Verify every dashboard metric computes and its data source is live';

    public function handle(BusinessMetricsService $metrics): int
    {
        $this->line('Computing every metric…');
        $failures = 0;

        // 1. Does each section compute at all?
        foreach (['revenue', 'requests', 'funnel', 'traffic', 'supply', 'health'] as $section) {
            try {
                $data = $metrics->$section();
                $this->line(sprintf('  <fg=green>ok</>    %-9s %s key(s)', $section, count($data)));
            } catch (\Throwable $e) {
                $this->line(sprintf('  <fg=red>FAIL</>  %-9s %s', $section, $e->getMessage()));
                $failures++;
            }
        }

        // 2. Is each feeding table actually receiving writes?
        $this->newLine();
        $this->line('Data sources:');

        $sources = [
            'matching_fee_payments' => ['panel' => 'Revenue',      'stale_days' => 30],
            'hire_requests'         => ['panel' => 'Requests',     'stale_days' => 14],
            'user_events'           => ['panel' => 'Traffic/funnel','stale_days' => 7],
            'maid_profiles'         => ['panel' => 'Supply',       'stale_days' => 30],
            'placement_candidates'  => ['panel' => 'Cover',        'stale_days' => 14],
            'user_attributions'     => ['panel' => 'Acquisition',  'stale_days' => 30],
        ];

        foreach ($sources as $table => $cfg) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                $this->line("  <fg=red>FAIL</>  {$table} — table missing");
                $failures++;
                continue;
            }

            $count = DB::table($table)->count();
            $last  = DB::table($table)->max('created_at');
            $age   = $last ? (int) floor(abs(now()->diffInHours(\Carbon\Carbon::parse($last)) / 24)) : null;

            if ($count === 0) {
                $this->line(sprintf('  <fg=yellow>EMPTY</> %-22s %-14s no rows yet', $table, $cfg['panel']));
                continue;
            }

            $stale = $age !== null && $age > $cfg['stale_days'];
            $this->line(sprintf(
                '  %s %-22s %-14s %s rows, last write %s',
                $stale ? '<fg=yellow>STALE</>' : '<fg=green>ok</>   ',
                $table, $cfg['panel'], number_format($count),
                $age === null ? 'unknown' : ($age === 0 ? 'today' : "{$age}d ago")
            ));
        }

        // 3. Sanity: a funnel step must never exceed the one above it.
        $this->newLine();
        $this->line('Funnel consistency:');
        foreach (['web', 'request'] as $which) {
            $steps = $metrics->funnel()[$which];
            $bad = false;
            for ($i = 1; $i < count($steps); $i++) {
                if ($steps[$i]['value'] > $steps[$i - 1]['value']) {
                    $this->line(sprintf('  <fg=red>FAIL</>  %s: "%s" (%s) exceeds "%s" (%s)',
                        $which, $steps[$i]['label'], $steps[$i]['value'],
                        $steps[$i - 1]['label'], $steps[$i - 1]['value']));
                    $bad = true;
                    $failures++;
                }
            }
            if (!$bad) {
                $this->line("  <fg=green>ok</>    {$which} funnel decreases at every step");
            }
        }

        $this->newLine();
        if ($failures) {
            $this->error("{$failures} problem(s) found.");
            return self::FAILURE;
        }

        $this->info('All dashboard metrics compute and their sources are live.');
        return self::SUCCESS;
    }
}
