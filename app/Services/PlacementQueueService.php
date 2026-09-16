<?php

namespace App\Services;

use App\Models\MaidAssignment;
use App\Models\User;
use App\Services\GroupAnnouncementService;
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
     * A claim older than this is not hot any more — she has probably taken
     * other work and needs asking again before we put her in front of a family.
     */
    private const CLAIM_STALE_DAYS = 10;

    /**
     * How warm a group volunteer is, highest first.
     *
     * Volunteering is the only unprompted signal we get, so it is the base of
     * the score — but it decays. Someone who replied two hours ago is looking
     * for work right now; someone who replied nine days ago may already be
     * placed, and offering her wastes the family's time and ours.
     *
     * Composed of:
     *   recency of the claim     — the dominant term, and the only one we have
     *                              reliable data for today
     *   recency of any reply     — from the contact thread. Sparse until the
     *                              unified threads accumulate inbound history,
     *                              so it adds to the score rather than gating it
     *   NIN verified             — a family will not take an unverified helper
     *   profile completeness     — a thin profile is hard to present
     *
     * @param object $claim  row from group_job_claims
     */
    public function heatScore(object $claim): array
    {
        $ageDays = $claim->claimed_at
            ? abs(now()->diffInHours(\Carbon\Carbon::parse($claim->claimed_at)) / 24)
            : 999;

        // Steep early decay: the first two days carry most of the signal.
        $recency = match (true) {
            $ageDays <= 1  => 100,
            $ageDays <= 2  => 85,
            $ageDays <= 4  => 65,
            $ageDays <= 7  => 40,
            $ageDays <= 10 => 20,
            default        => 5,
        };

        // Did she actually reply to us recently, on any subject?
        $replied = 0;
        $u = User::find($claim->maid_user_id);
        $tail = substr(preg_replace('/\D+/', '', (string) ($u->phone ?? '')), -10);
        if ($tail !== '') {
            $t = DB::table('wa_contact_issues')->where('wa_id', 'like', "%{$tail}")->first();
            if ($t && $t->last_inbound_at) {
                $h = abs(now()->diffInHours(\Carbon\Carbon::parse($t->last_inbound_at)));
                $replied = match (true) {
                    $h <= 24  => 40,
                    $h <= 72  => 25,
                    $h <= 168 => 10,
                    default   => 0,
                };
            }
        }

        $profile = DB::table('maid_profiles')->where('user_id', $claim->maid_user_id)->first();

        $nin        = ($claim->nin_verified || ($profile->nin_verified ?? false)) ? 25 : 0;
        $complete   = (int) round(((int) ($profile->profile_completeness ?? 0)) / 10);   // 0-10

        return [
            'score'          => $recency + $replied + $nin + $complete,
            'age_days'       => round($ageDays, 1),
            'stale'          => $ageDays > self::CLAIM_STALE_DAYS,
            'recency'        => $recency,
            'replied_recently' => $replied,
            'nin'            => $nin > 0,
        ];
    }

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
    public function build(int $employerId, ?int $preferenceId, ?string $jobCode, int $size = 3, ?string $area = null): array
    {
        $blocked  = $this->unavailableMaids($employerId);
        $skipped  = [];

        // Fall back to whatever the request or opening says the area is.
        if (!$area) {
            $area = DB::table('hire_requests')
                ->where('employer_id', $employerId)
                ->whereIn('status', ['open', 'paid', 'matching', 'matched'])
                ->value('area');
        }
        if (!$area && $jobCode) {
            $area = DB::table('group_job_claims')->where('job_code', $jobCode)->value('area');
        }

        $existing = DB::table('placement_candidates')
            ->where('employer_id', $employerId)
            ->where(fn ($q) => $q->where('preference_id', $preferenceId)->orWhereNull('preference_id'))
            ->pluck('maid_user_id')->map(fn ($v) => (int) $v)->all();

        $picks = [];

        // 1. Helpers who volunteered for THIS opening, hottest first.
        //
        // Ordered by heat, not by who claimed first. Claiming order is fair but
        // it is not useful: a helper who replied two hours ago is looking for
        // work right now, while a nine-day-old claim has probably already found
        // some. Ranking oldest-first was putting the coldest volunteer in front
        // of the family.
        if ($jobCode) {
            $claims = DB::table('group_job_claims')
                ->where('job_code', $jobCode)
                ->whereIn('status', ['claimed', 'shortlisted'])
                ->get();

            $scored = $claims->map(fn ($c) => ['claim' => $c, 'heat' => $this->heatScore($c)])
                             ->sortByDesc(fn ($r) => $r['heat']['score'])
                             ->values();

            foreach ($scored as $r) {
                if (count($picks) >= $size) { break; }
                $c  = $r['claim'];
                $id = (int) $c->maid_user_id;
                if (in_array($id, $existing, true)) { continue; }
                if (isset($blocked[$id])) { $skipped[$id] = $blocked[$id]; continue; }
                if (!$this->canWorkHere($id, $area)) { $skipped[$id] = 'not available in ' . $area; continue; }
                if ($why = $this->failedHereBefore($id, $employerId)) { $skipped[$id] = $why; continue; }

                $picks[] = [
                    'maid_user_id' => $id,
                    'source'       => 'group_claim',
                    'heat'         => $r['heat'],
                ];
            }
        }

        // 2. Helpers active in the group on OTHER openings.
        //
        // She did not ask for this specific job, but she answered a group post
        // recently — which is more than anyone the matcher surfaces has done.
        // Worth putting ahead of a cold profile, behind a direct volunteer.
        if (count($picks) < $size) {
            $others = DB::table('group_job_claims')
                ->when($jobCode, fn ($q) => $q->where('job_code', '!=', $jobCode))
                ->whereIn('status', ['claimed', 'shortlisted'])
                ->where('claimed_at', '>=', now()->subDays(self::CLAIM_STALE_DAYS))
                ->get()
                ->unique('maid_user_id');

            $scoredOthers = $others->map(fn ($c) => ['claim' => $c, 'heat' => $this->heatScore($c)])
                                   ->sortByDesc(fn ($r) => $r['heat']['score'])
                                   ->values();

            foreach ($scoredOthers as $r) {
                if (count($picks) >= $size) { break; }
                $id = (int) $r['claim']->maid_user_id;
                if (in_array($id, $existing, true)) { continue; }
                if (isset($blocked[$id])) { $skipped[$id] = $blocked[$id]; continue; }
                if (in_array($id, array_column($picks, 'maid_user_id'), true)) { continue; }
                if (!$this->canWorkHere($id, $area)) { $skipped[$id] = 'not available in ' . $area; continue; }
                if ($why = $this->failedHereBefore($id, $employerId)) { $skipped[$id] = $why; continue; }

                $picks[] = [
                    'maid_user_id' => $id,
                    'source'       => 'group_active',
                    'heat'         => $r['heat'],
                ];
            }
        }

        // 3. Cold pool. Nobody here has expressed interest in anything — last
        //    resort, and only to fill remaining slots.
        if (count($picks) < $size) {
            $pool = DB::table('maid_profiles')
                ->where('availability_status', 'available')
                ->whereNotNull('user_id')
                ->orderByDesc('nin_verified')
                ->orderByDesc('profile_completeness')
                ->limit(400)
                ->get(['user_id', 'nin_verified', 'location', 'willing_states']);

            foreach ($pool as $p) {
                if (count($picks) >= $size) { break; }
                $id = (int) $p->user_id;

                // Geography is not a preference, it is a hard constraint. A
                // helper in Lagos is no use to a family in Abuja, and offering
                // one wastes everybody's time — this is exactly how Onyinyechi
                // (Isheri Lagos, willing_states ["Lagos"]) came to be assigned
                // to a household in Lugbe, Abuja.
                if ($area && !$this->servesArea($p, $area)) {
                    $skipped[$id] = 'not available in ' . $area;
                    continue;
                }
                if ($why = $this->failedHereBefore($id, $employerId)) {
                    $skipped[$id] = $why;
                    continue;
                }
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
                'rank'         => $nextRank,
                'maid_user_id' => $p['maid_user_id'],
                'name'         => $u->name ?? null,
                'phone'        => $u->phone ?? null,
                'source'       => $p['source'],
                'heat'         => $p['heat']['score'] ?? null,
                'claimed_days_ago' => $p['heat']['age_days'] ?? null,
                // A stale claim is still worth approaching, but ask whether she
                // is still free before naming her to the family.
                'needs_reconfirm'  => $p['heat']['stale'] ?? false,
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

        // Tell the group. A helper who sees one of their own named as placed
        // learns that answering a posting actually leads to work — which no
        // amount of instruction in the posting itself achieves.
        if (in_array($status, ['accepted', 'placed'], true)) {
            app(GroupAnnouncementService::class)->queue(
                $status === 'placed' ? 'started' : 'matched',
                $c->job_code,
                (int) $c->maid_user_id,
                ['role' => $c->job_code ? null : null]
            );
        }

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

    /**
     * Can this helper work in the requested area?
     *
     * Checks her stated location and, more importantly, willing_states — a
     * helper who lists only Lagos will not relocate to Abuja for a housekeeping
     * role, however good the match looks on paper. This is exactly how
     * Onyinyechi (Isheri Oshun, Lagos; willing_states ["Lagos"]) came to be
     * assigned to a household in Lugbe, Abuja.
     */
    private function servesArea(object $profile, string $area): bool
    {
        $want = $this->stateOf($area);
        if ($want === null) {
            return true;   // area we cannot place — never exclude on a guess
        }

        $willing = $profile->willing_states ?? null;
        if (is_string($willing)) {
            $willing = json_decode($willing, true);
        }
        if (is_array($willing) && $willing) {
            foreach ($willing as $w) {
                if ($this->stateOf((string) $w) === $want) {
                    return true;
                }
            }
            return false;   // she named her states and this is not one of them
        }

        $loc = $this->stateOf((string) ($profile->location ?? ''));

        return $loc === null || $loc === $want;
    }

    /** Coarse Nigerian state extraction; Abuja and FCT are the same place. */
    private function stateOf(string $text): ?string
    {
        $t = strtolower($text);
        foreach (['lagos','abuja','fct','ogun','oyo','rivers','kano','kaduna','enugu','delta',
                  'edo','anambra','imo','akwa','cross river','plateau','benue','niger','kwara',
                  'osun','ondo','ekiti'] as $st) {
            if (str_contains($t, $st)) {
                return $st === 'fct' ? 'abuja' : $st;
            }
        }
        return null;
    }

    /** Area check by user id, for the tiers that work from claims not profiles. */
    private function canWorkHere(int $maidUserId, ?string $area): bool
    {
        if (!$area) {
            return true;
        }
        $p = DB::table('maid_profiles')->where('user_id', $maidUserId)
            ->first(['location', 'willing_states']);

        return $p ? $this->servesArea($p, $area) : true;
    }

    /**
     * Has this helper already failed with this household?
     *
     * Re-offering the helper who did not show up is worse than offering nobody:
     * it tells the family we are not paying attention. Tafa's replacement search
     * started precisely because Damilola never arrived, and a naive queue put her
     * back at rank 1.
     *
     * @return string|null  reason to skip, or null if she is fine
     */
    private function failedHereBefore(int $maidUserId, int $employerId): ?string
    {
        $failedCase = DB::table('fulfillment_cases')
            ->where('employer_id', $employerId)
            ->where('maid_id', $maidUserId)
            ->where('status', 'failed')
            ->exists();

        if ($failedCase) {
            return 'a previous placement with this employer failed';
        }

        $badOutcome = DB::table('placement_candidates')
            ->where('employer_id', $employerId)
            ->where('maid_user_id', $maidUserId)
            ->whereIn('status', ['declined', 'unreachable'])
            ->value('status');

        return $badOutcome ? "previously {$badOutcome} for this employer" : null;
    }
}
