<?php

namespace App\Console\Commands;

use App\Models\FulfillmentCase;
use App\Models\HireRequest;
use App\Models\MaidAssignment;
use App\Models\MatchingFeePayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconstruct hire requests from the history that predates them.
 *
 * Without this every existing payment, assignment and placement is orphaned:
 * the app would report zero requests while plainly holding a dozen live
 * placements, and "has this request been paid?" would be false for customers who
 * paid weeks ago.
 *
 * A request is reconstructed per (employer, preference) — that pairing is the
 * closest thing the old schema had to a request. Status is inferred from what
 * actually happened, not assumed: a case at day_one or later means the helper
 * resumed and the request is fulfilled.
 */
class BackfillHireRequests extends Command
{
    protected $signature = 'requests:backfill {--apply : Write (default is a dry run)}';
    protected $description = 'Create hire_requests from existing payments, assignments and fulfillment cases';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->line($apply ? '<fg=red>APPLYING</>' : '<fg=yellow>DRY RUN</> — pass --apply to commit.');
        $this->newLine();

        // Every (employer, preference) pairing that any real artefact points at.
        $keys = collect();
        foreach ([
            [MatchingFeePayment::class, 'employer_id'],
            [MaidAssignment::class,     'employer_id'],
            [FulfillmentCase::class,    'employer_id'],
        ] as [$model, $empCol]) {
            $model::query()->get([$empCol, 'preference_id'])->each(function ($r) use ($keys, $empCol) {
                if ($r->$empCol) {
                    $keys->push($r->$empCol . '|' . ($r->preference_id ?? 'null'));
                }
            });
        }

        $made = 0; $skipped = 0;

        foreach ($keys->unique()->sort() as $key) {
            [$employerId, $prefRaw] = explode('|', $key);
            $employerId = (int) $employerId;
            $prefId     = $prefRaw === 'null' ? null : (int) $prefRaw;

            $exists = HireRequest::where('employer_id', $employerId)
                ->when($prefId, fn ($q) => $q->where('preference_id', $prefId),
                       fn ($q) => $q->whereNull('preference_id'))
                ->exists();
            if ($exists) { $skipped++; continue; }

            $scope = fn ($q) => $q->where('employer_id', $employerId)
                ->when($prefId, fn ($x) => $x->where('preference_id', $prefId),
                       fn ($x) => $x->whereNull('preference_id'));

            $payment    = MatchingFeePayment::where(fn ($q) => $scope($q))
                            ->whereIn('status', ['paid', 'completed'])->latest('paid_at')->first();
            $assignment = MaidAssignment::where(fn ($q) => $scope($q))
                            ->whereNotIn('status', ['cancelled', 'rejected'])->latest()->first();
            $case       = FulfillmentCase::where(fn ($q) => $scope($q))->latest()->first();
            $pref       = $prefId ? DB::table('employer_preferences')->where('id', $prefId)->first() : null;

            // Only reconstruct a request that actually happened. The old
            // generate-pwbt minted a fresh preference on every retry, so
            // employer 460 alone has seven preferences from one evening of
            // failed PWBT attempts. Those were never separate requests, and
            // turning each into an open one would invent a backlog that does
            // not exist. Require a real artefact: money received, a helper
            // assigned, or a placement opened.
            if (!$payment && !$assignment && !$case) {
                $skipped++;
                continue;
            }

            // Infer the furthest point actually reached.
            $resumed = $case && in_array($case->stage, ['day_one', 'active', 'completed'], true);
            $status  = match (true) {
                $resumed              => 'fulfilled',
                (bool) $assignment    => 'matched',
                (bool) $payment       => 'paid',
                default               => 'open',
            };
            // A failed case means the placement did not stick — the request
            // falls back to wherever it genuinely got to. It must not claim
            // 'paid' when no money was ever received: status and paid_at would
            // then disagree, and an agent reading the request would chase the
            // wrong thing.
            if ($case && $case->status === 'failed' && !$resumed) {
                $status = $assignment ? 'matched' : ($payment ? 'paid' : 'open');
            }

            $this->line(sprintf('  employer %-5s pref %-6s -> %-10s %s',
                $employerId, $prefId ?? '-', $status,
                $assignment ? "maid {$assignment->maid_id}" : ''));

            if ($apply) {
                $r = HireRequest::open([
                    'employer_id'         => $employerId,
                    'preference_id'       => $prefId,
                    'role'                => $pref->help_types ?? null,
                    'area'                => $pref->location ?? ($pref->city ?? null),
                    'status'              => $status,
                    'fee_amount'          => (int) ($payment->amount ?? 20000),
                    'paid_at'             => $payment->paid_at ?? null,
                    'matched_at'          => $assignment->created_at ?? null,
                    'resumed_at'          => $resumed ? ($case->updated_at ?? now()) : null,
                    'closed_at'           => $resumed ? ($case->updated_at ?? now()) : null,
                    'close_reason'        => $resumed ? 'backfilled: helper resumed' : null,
                    'assignment_id'       => $assignment->id ?? null,
                    'fulfillment_case_id' => $case->id ?? null,
                    'maid_user_id'        => $assignment->maid_id ?? ($case->maid_id ?? null),
                ]);

                // Point the artefacts back at their request.
                MatchingFeePayment::where(fn ($q) => $scope($q))->update(['hire_request_id' => $r->id]);
                MaidAssignment::where(fn ($q) => $scope($q))->update(['hire_request_id' => $r->id]);
                FulfillmentCase::where(fn ($q) => $scope($q))->update(['hire_request_id' => $r->id]);
            }
            $made++;
        }

        $this->newLine();
        $this->info(($apply ? 'Created ' : 'Would create ') . "{$made} request(s); {$skipped} already existed.");
        if (!$apply) { $this->comment('Re-run with --apply to commit.'); }

        return self::SUCCESS;
    }
}
