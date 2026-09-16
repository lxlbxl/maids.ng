<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import the existing helper-group claims from the file-based registry.
 *
 * job-registry.json holds every claim made since the group rollout — 18 across
 * 11 openings — none of which the app could see. Importing them means the
 * shortlists are useful from the moment this ships, rather than starting empty
 * and discarding a fortnight of volunteers.
 *
 * Applies the same screening as a live claim, so the imported set reflects the
 * quality rules rather than grandfathering everything in.
 */
class BackfillGroupJobClaims extends Command
{
    protected $signature = 'group:backfill-claims
                            {--registry=/home/brewadmin/maids-onboarding/group-rollout/job-registry.json}
                            {--apply : Write (default is a dry run)}';

    protected $description = 'Import helper-group job claims from job-registry.json into the database';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $path  = (string) $this->option('registry');

        if (!is_readable($path)) {
            $this->error("Registry not readable: {$path}");
            return self::FAILURE;
        }

        $reg = json_decode(file_get_contents($path), true);
        if (!is_array($reg)) {
            $this->error('Registry is not valid JSON.');
            return self::FAILURE;
        }

        $this->line($apply ? '<fg=red>APPLYING</>' : '<fg=yellow>DRY RUN</> — pass --apply to commit.');
        $this->newLine();

        $imported = 0; $screened = 0; $skipped = 0;

        foreach ($reg as $code => $job) {
            foreach ($job['claimants'] ?? [] as $c) {
                $maidId = $c['maid_id'] ?? null;
                if (!$maidId || !DB::table('users')->where('id', $maidId)->where('role', 'maid')->exists()) {
                    $skipped++;
                    continue;
                }
                if (DB::table('group_job_claims')->where('job_code', $code)->where('maid_user_id', $maidId)->exists()) {
                    $skipped++;
                    continue;
                }

                $p    = DB::table('maid_profiles')->where('user_id', $maidId)->first();
                $av   = $p->availability_status ?? null;
                $city = $p->city ?? $p->location ?? null;

                $status = 'claimed'; $reason = null;
                if ($av && $av !== 'available') {
                    $status = 'rejected'; $reason = "helper is marked {$av}";
                }

                $this->line(sprintf('  %-7s %-26s user=%-5s %-9s %s',
                    $code, substr($c['name'] ?? '?', 0, 26), $maidId,
                    $status === 'claimed' ? 'ok' : 'screened', $reason ?? ''));

                if ($apply) {
                    DB::table('group_job_claims')->insert([
                        'job_code'       => $code,
                        'maid_user_id'   => $maidId,
                        'wa_id'          => $c['wa'] ?? null,
                        'role'           => $job['role'] ?? null,
                        'area'           => $job['area'] ?? null,
                        'job_type'       => $job['type'] ?? null,
                        'employer_issue' => $job['employer_issue'] ?? null,
                        'status'         => $status,
                        'reject_reason'  => $reason,
                        'nin_verified'   => (bool) ($p->nin_verified ?? false),
                        'availability'   => $av,
                        'maid_city'      => $city,
                        'claimed_at'     => $c['claimed_at'] ?? now(),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }

                $status === 'claimed' ? $imported++ : $screened++;
            }
        }

        $this->newLine();
        $this->info(($apply ? 'Imported ' : 'Would import ') . ($imported + $screened) . " claim(s): {$imported} live, {$screened} screened out.");
        if ($skipped) { $this->line("  {$skipped} skipped (not a maid user, or already imported)."); }
        if (!$apply)  { $this->comment('Re-run with --apply to commit.'); }

        return self::SUCCESS;
    }
}
