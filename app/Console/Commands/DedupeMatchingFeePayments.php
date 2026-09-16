<?php

namespace App\Console\Commands;

use App\Models\AgentNote;
use App\Models\MatchingFeePayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Collapse duplicate settled matching-fee payments down to one row per employer.
 *
 * Background: GET /payments/status/{id} filtered on status='completed', a value
 * no write path in this codebase ever sets, so it answered has_paid=false for
 * every customer who had ever paid. Agents believed the payment had not
 * registered and recorded it again — eight 'paid' rows for one ₦20,000 transfer
 * on employer 460, four on employer 450, ₦200,000 of revenue that never existed.
 *
 * The oldest settled row is kept: it is the one closest to the real transfer and
 * usually carries the genuine bank reference, with later rows being
 * reconciliation attempts wrapped in RECON_/mng_manual_ prefixes.
 *
 * Agent notes attached to a removed row are re-pointed at the survivor so the
 * audit trail stays navigable rather than dangling.
 *
 * Defaults to a dry run. Pass --apply to write.
 */
class DedupeMatchingFeePayments extends Command
{
    protected $signature = 'payments:dedupe-matching-fees
                            {--apply : Actually delete (default is a dry run)}
                            {--employer= : Restrict to a single employer id}';

    protected $description = 'Collapse duplicate paid matching-fee payments to one row per employer';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $query = MatchingFeePayment::whereIn('status', ['paid', 'completed']);
        if ($employer = $this->option('employer')) {
            $query->where('employer_id', $employer);
        }

        $groups = $query->get()->groupBy(fn ($p) => $p->employer_id . '|' . ($p->payment_type ?? 'matching_fee'))
                        ->filter(fn ($g) => $g->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate settled payments found.');
            return self::SUCCESS;
        }

        $this->line($apply ? '<fg=red>APPLYING</> — rows will be deleted.' : '<fg=yellow>DRY RUN</> — nothing will be written. Pass --apply to commit.');
        $this->newLine();

        $phantom = 0;
        $deleted = 0;

        foreach ($groups as $key => $rows) {
            [$employerId, $type] = explode('|', $key);
            $sorted  = $rows->sortBy(fn ($p) => [$p->paid_at ?? $p->created_at, $p->id])->values();
            $keep    = $sorted->first();
            $remove  = $sorted->slice(1);

            $this->line("Employer <info>{$employerId}</info> ({$type}) — {$rows->count()} settled rows, ₦"
                . number_format($rows->sum('amount')) . ' booked');
            $this->line("   <fg=green>KEEP</>   #{$keep->id}  {$keep->reference}  ({$keep->paid_at})");

            foreach ($remove as $r) {
                $this->line("   <fg=red>DELETE</> #{$r->id}  {$r->reference}  ({$r->paid_at})");
                $phantom += (int) $r->amount;

                if ($apply) {
                    DB::transaction(function () use ($r, $keep, &$deleted) {
                        // Keep the audit trail pointing somewhere real.
                        AgentNote::where('entity_type', 'payment')
                            ->where('entity_id', $r->id)
                            ->update(['entity_id' => $keep->id]);

                        Log::info('Phantom matching-fee payment removed', [
                            'deleted_id'   => $r->id,
                            'reference'    => $r->reference,
                            'employer_id'  => $r->employer_id,
                            'amount'       => $r->amount,
                            'kept_id'      => $keep->id,
                        ]);

                        $r->delete();
                        $deleted++;
                    });
                }
            }
            $this->newLine();
        }

        $this->info(($apply ? 'Deleted ' : 'Would delete ') . ($apply ? $deleted : $groups->sum(fn ($g) => $g->count() - 1))
            . ' phantom row(s), removing ₦' . number_format($phantom) . ' of revenue that was never received.');

        if (!$apply) {
            $this->comment('Re-run with --apply to commit.');
        }

        return self::SUCCESS;
    }
}
