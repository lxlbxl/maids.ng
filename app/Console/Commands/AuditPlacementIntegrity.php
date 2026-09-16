<?php

namespace App\Console\Commands;

use App\Models\FulfillmentCase;
use App\Models\MaidAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Detect placement records that contradict themselves.
 *
 * The guards on the write paths stop these being created through the agent API,
 * but they cannot stop a direct database edit, an admin screen, or a path that
 * predates them. This is the backstop: the Fatima case sat wrong for a day
 * because nothing was looking, and Operations diagnosed it as a join bug rather
 * than bad data.
 *
 * Runs daily. Silent when clean.
 */
class AuditPlacementIntegrity extends Command
{
    protected $signature = 'placements:audit {--quiet-ok : Print nothing when everything is clean}';
    protected $description = 'Find assignments and fulfillment cases whose records contradict each other';

    public function handle(): int
    {
        $problems = [];

        // 1. An assignment whose "maid" is not a maid. This is the maid_profiles.id
        //    vs users.id confusion that put an employer into a placement.
        foreach (MaidAssignment::whereNotIn('status', ['cancelled', 'rejected'])->get() as $a) {
            $u = User::find($a->maid_id);
            if (!$u) {
                $problems[] = "assignment #{$a->id}: maid_id {$a->maid_id} is not a user at all";
            } elseif ($u->role !== 'maid') {
                $profile = DB::table('maid_profiles')->where('id', $a->maid_id)->first();
                $hint = $profile
                    ? " — looks like maid_profiles.id {$a->maid_id}, whose user is {$profile->user_id}"
                    : '';
                $problems[] = "assignment #{$a->id}: maid_id {$a->maid_id} is '{$u->name}' with role '{$u->role}', not a maid{$hint}";
            }
        }

        // 2. A case pointing at an assignment that belongs to someone else, or to
        //    a different maid than the case itself names.
        foreach (FulfillmentCase::whereNotNull('assignment_id')->get() as $c) {
            $a = MaidAssignment::find($c->assignment_id);
            if (!$a) {
                $problems[] = "case #{$c->id}: assignment_id {$c->assignment_id} does not exist";
                continue;
            }
            if ((int) $a->employer_id !== (int) $c->employer_id) {
                $problems[] = "case #{$c->id} (employer {$c->employer_id}) points at assignment #{$a->id}, which belongs to employer {$a->employer_id}";
            }
            if ($c->maid_id && (int) $a->maid_id !== (int) $c->maid_id) {
                $problems[] = "case #{$c->id} names maid {$c->maid_id} but its assignment #{$a->id} is for maid {$a->maid_id}";
            }
            if ($c->status === 'active' && in_array($a->status, ['cancelled', 'rejected'], true)) {
                $problems[] = "case #{$c->id} is active but its assignment #{$a->id} is {$a->status}";
            }
        }

        // 3. A maid holding more than one live placement.
        $multi = MaidAssignment::whereIn('status', ['pending_acceptance', 'accepted'])
            ->selectRaw('maid_id, count(distinct employer_id) n')
            ->groupBy('maid_id')->havingRaw('count(distinct employer_id) > 1')->get();

        foreach ($multi as $m) {
            $u = User::find($m->maid_id);
            $problems[] = "maid {$m->maid_id} ({$u->name}) holds live assignments with {$m->n} different employers";
        }

        // 4. More than one active case per employer.
        $dupCases = FulfillmentCase::where('status', 'active')
            ->selectRaw('employer_id, count(*) n')
            ->groupBy('employer_id')->havingRaw('count(*) > 1')->get();

        foreach ($dupCases as $d) {
            $problems[] = "employer {$d->employer_id} has {$d->n} active fulfillment cases";
        }

        if (!$problems) {
            if (!$this->option('quiet-ok')) {
                $this->info('Placement records are consistent.');
            }
            return self::SUCCESS;
        }

        $this->error(count($problems) . ' placement integrity problem(s):');
        foreach ($problems as $p) {
            $this->line('  - ' . $p);
        }
        Log::warning('Placement integrity audit found problems', ['count' => count($problems), 'problems' => $problems]);

        return self::FAILURE;
    }
}
