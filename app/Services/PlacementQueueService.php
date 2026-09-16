<?php

namespace App\Services;

use App\Models\MaidAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Decides who may be offered, and keeps a ranked queue per opening.
 *
 * The rule that was missing: a helper can only be in one placement at a time.
 * Without it the matcher happily re-offered maids who were already committed,
 * so every family was shown the same handful of names.
 */
class PlacementQueueService
{
    /**
     * How long a queued-but-not-yet-answered candidate is held for one family.
     *
     * Long enough that a family thinking it over does not lose her to another
     * household; short enough that a helper who never replies is not withheld
     * from work for days.
     */
    private const HOLD_HOURS = 48;

    /**
     * users.id of every maid who must not be offered right now, with why.
     *
     * @return array<int, string>  maid_user_id => reason
     */
    public function unavailableMaids(?int $exceptEmployerId = null): array
    {
        $out = [];

        // Committed: an assignment that has not ended.
        $assigned = MaidAssignment::whereIn('status', ['pending_acceptance', 'accepted'])
            ->when($exceptEmployerId, fn ($q) => $q->where('employer_id', '!=', $exceptEmployerId))
            ->get(['maid_id', 'employer_id', 'status']);

        foreach ($assigned as $a) {
            $out[(int) $a->maid_id] = "already assigned to employer {$a->employer_id} ({$a->status})";
        }

        // Committed on the queue: she said yes, or has started. This holds with
        // no time limit — there is a window between accepting and the assignment
        // row being written, and without this she drops back into the pool
        // during it and can be promised to a second family.
        $committed = DB::table('placement_candidates')
            ->whereIn('status', ['accepted', 'placed'])
            ->when($exceptEmployerId, fn ($q) => $q->where('employer_id', '!=', $exceptEmployerId))
            ->get(['maid_user_id', 'employer_id', 'status']);

        foreach ($committed as $h) {
            $out[(int) $h->maid_user_id] ??= "accepted a placement with employer {$h->employer_id}";
        }

        // Held: queued or offered to another family and still inside the window.
        // Time-limited, so a helper who never answers is not withheld from work
        // indefinitely.
        $held = DB::table('placement_candidates')
            ->whereIn('status', ['queued', 'offered'])
            ->where('updated_at', '>=', now()->subHours(self::HOLD_HOURS))
            ->when($exceptEmployerId, fn ($q) => $q->where('employer_id', '!=', $exceptEmployerId))
            ->get(['maid_user_id', 'employer_id', 'status']);

        foreach ($held as $h) {
            $out[(int) $h->maid_user_id] ??= "on hold for employer {$h->employer_id} ({$h->status})";
        }

        return $out;
    }

    /**
     * Build (or top up) the candidate queue for an opening.
     *
     * Group volunteers go first, in the order they raised their hand — someone
     * who asked for this exact job beats anyone a score can find. Matcher
     * suggestions fill the remaining slots.
     *
     * @return array{queued:int, candidates:array<int,array<string,mixed>>, skipped:array<int,string>}
     */
    public function build(int $employerId, ?int $preferenceId, ?string $jobCode, int $size = 3): array
    {
        $blocked  = $this->unavailableMaids($employerId);
        $skipped  = [];

        $existing = DB::table('placement_candidates')
            ->where('employer_id', $employerId)
            ->where(fn ($q) => $q->where('preference_id', $preferenceId)->orWhereNull('preference_id'))
            ->pluck('maid_user_id')->map(fn ($v) => (int) $v)->all();

        $picks = [];

        // 1. Helpers who volunteered for this opening in the group.
        if ($jobCode) {
            $claims = DB::table('group_job_claims')
                ->where('job_code', $jobCode)
                ->whereIn('status', ['claimed', 'shortlisted'])
                ->orderByDesc('nin_verified')
                ->orderBy('claimed_at')
                ->get();

            foreach ($claims as $c) {
                if (count($picks) >= $size) { break; }
                $id = (int) $c->maid_user_id;
                if (in_array($id, $existing, true)) { continue; }
                if (isset($blocked[$id])) { $skipped[$id] = $blocked[$id]; continue; }
                $picks[] = ['maid_user_id' => $id, 'source' => 'group_claim'];
            }
        }

        // 2. Top up from the general pool, best first, never re-offering someone
        //    who is committed or held.
        if (count($picks) < $size) {
            $pool = DB::table('maid_profiles')
                ->where('availability_status', 'available')
                ->whereNotNull('user_id')
                ->orderByDesc('nin_verified')
                ->orderByDesc('profile_completeness')
                ->limit(200)
                ->get(['user_id', 'nin_verified']);

            foreach ($pool as $p) {
                if (count($picks) >= $size) { break; }
                $id = (int) $p->user_id;
                if (in_array($id, $existing, true)) { continue; }
                if (isset($blocked[$id])) { $skipped[$id] = $blocked[$id]; continue; }
                if (in_array($id, array_column($picks, 'maid_user_id'), true)) { continue; }
                if (!User::where('id', $id)->where('role', 'maid')->exists()) { continue; }
                $picks[] = ['maid_user_id' => $id, 'source' => 'matcher'];
            }
        }

        $nextRank = (int) DB::table('placement_candidates')
            ->where('employer_id', $employerId)->max('rank');

        $written = [];
        foreach ($picks as $p) {
            $nextRank++;
            DB::table('placement_candidates')->insert([
                'employer_id'   => $employerId,
                'preference_id' => $preferenceId,
                'job_code'      => $jobCode,
                'maid_user_id'  => $p['maid_user_id'],
                'rank'          => $nextRank,
                'status'        => 'queued',
                'source'        => $p['source'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $u = User::find($p['maid_user_id']);
            $written[] = [
                'rank'   => $nextRank,
                'maid_user_id' => $p['maid_user_id'],
                'name'   => $u->name ?? null,
                'phone'  => $u->phone ?? null,
                'source' => $p['source'],
            ];
        }

        Log::info('Placement queue built', [
            'employer_id' => $employerId, 'job_code' => $jobCode,
            'queued' => count($written), 'skipped' => count($skipped),
        ]);

        return ['queued' => count($written), 'candidates' => $written, 'skipped' => $skipped];
    }

    /**
     * Record what happened with a candidate, and surface the next one.
     *
     * A decline or a silence is not a dead end any more: the backup is already
     * chosen and screened, so the family can be given a name the same day.
     *
     * @return array{candidate:array<string,mixed>, next:?array<string,mixed>}
     */
    public function outcome(int $candidateId, string $status, ?string $reason = null): array
    {
        $c = DB::table('placement_candidates')->where('id', $candidateId)->first();
        if (!$c) {
            throw new \RuntimeException("No candidate {$candidateId}");
        }

        DB::table('placement_candidates')->where('id', $candidateId)->update([
            'status'         => $status,
            'outcome_reason' => $reason,
            'responded_at'   => now(),
            'updated_at'     => now(),
        ]);

        // She took it: everyone else on this opening is done.
        if (in_array($status, ['accepted', 'placed'], true)) {
            DB::table('placement_candidates')
                ->where('employer_id', $c->employer_id)
                ->where('id', '!=', $candidateId)
                ->whereIn('status', ['queued', 'offered'])
                ->update(['status' => 'superseded', 'outcome_reason' => 'opening filled', 'updated_at' => now()]);

            if ($c->job_code) {
                DB::table('group_job_claims')->where('job_code', $c->job_code)
                    ->where('maid_user_id', $c->maid_user_id)
                    ->update(['status' => 'assigned', 'updated_at' => now()]);
                DB::table('group_job_claims')->where('job_code', $c->job_code)
                    ->where('status', 'claimed')
                    ->update(['status' => 'closed', 'reject_reason' => 'opening filled', 'updated_at' => now()]);
            }

            return ['candidate' => (array) $c, 'next' => null];
        }

        $next = DB::table('placement_candidates')
            ->where('employer_id', $c->employer_id)
            ->where('status', 'queued')
            ->orderBy('rank')
            ->first();

        if ($next) {
            $u = User::find($next->maid_user_id);
            return ['candidate' => (array) $c, 'next' => [
                'candidate_id' => $next->id,
                'rank'         => $next->rank,
                'maid_user_id' => $next->maid_user_id,
                'name'         => $u->name ?? null,
                'phone'        => $u->phone ?? null,
                'source'       => $next->source,
            ]];
        }

        return ['candidate' => (array) $c, 'next' => null];
    }
}
