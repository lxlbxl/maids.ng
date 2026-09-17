<?php

namespace App\Services;

use App\Models\HireRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves a match along on a clock instead of on someone remembering.
 *
 * The flow, and what each wait is for:
 *
 *   1. posted     the opening goes to the helper group
 *                 └─ CLAIM_WINDOW_HOURS for helpers to answer. Real people
 *                    reply when they next open WhatsApp; closing after an hour
 *                    would waste the volunteers the group exists to produce.
 *   2. matching   the window closes, the queue is built — volunteers first,
 *                 topped up from the pool only if they do not fill it
 *   3. offered    rank 1 is approached
 *                 └─ OFFER_RESPONSE_HOURS, then ONE follow-up
 *                 └─ FOLLOW_UP_GRACE_HOURS, then she is treated as unreachable
 *                    and the next candidate comes up
 *   4. matched    she accepted; salary and start date are agreed
 *   5. fulfilled  she actually resumed — the request is done
 *
 * Nothing here messages anyone at night: every outbound step is gated on the
 * same quiet-hours rule as the rest of the platform.
 */
class MatchingCadenceService
{
    /** Helpers answer a group post in their own time; a few hours is realistic. */
    public const CLAIM_WINDOW_HOURS = 6;

    /** A helper gets most of a day to reply before we chase. */
    public const OFFER_RESPONSE_HOURS = 12;

    /** And the same again after the chase before we move on. */
    public const FOLLOW_UP_GRACE_HOURS = 12;

    /** Below this the queue is too thin to survive one refusal. */
    public const MIN_QUEUE_DEPTH = 3;

    /**
     * Advance every live request one step. Safe to run repeatedly.
     *
     * @return array<string, mixed> what changed, for the command to print
     */
    public function tick(bool $dryRun = false): array
    {
        $out = ['queues_built' => [], 'follow_ups' => [], 'promoted' => [], 'thin' => []];

        foreach (HireRequest::whereIn('status', ['paid', 'matching', 'matched'])->get() as $req) {
            // 1. The group has had its window — build the queue.
            $windowClosed = !$req->claims_close_at || now()->greaterThanOrEqualTo($req->claims_close_at);

            // A matched request needs cover too. "Matched" is a promise, not an
            // arrival: Tafa's first helper was matched and then never showed up,
            // and because nobody was lined up behind her the search restarted
            // from nothing. Backups are cheapest to arrange before they are
            // needed.
            if (!$req->queue_built_at && $windowClosed) {
                $depth = DB::table('placement_candidates')
                    ->where('hire_request_id', $req->id)
                    ->whereIn('status', ['queued', 'offered'])->count();

                if ($depth < self::MIN_QUEUE_DEPTH) {
                    if (!$dryRun) {
                        app(PlacementQueueService::class)->build(
                            $req->employer_id, $req->preference_id, $req->job_code,
                            self::MIN_QUEUE_DEPTH, $req->area, $req->id
                        );
                        $req->update([
                            'queue_built_at' => now(),
                            // Building cover behind an existing match must not
                            // undo the match itself.
                            'status' => $req->status === 'matched' ? 'matched' : 'matching',
                        ]);
                    }
                    $out['queues_built'][] = $req->reference;
                }
            }

            // 2. A candidate has been sitting unanswered.
            $offered = DB::table('placement_candidates')
                ->where('hire_request_id', $req->id)
                ->where('status', 'offered')
                ->whereNotNull('respond_by')
                ->get();

            foreach ($offered as $c) {
                $due = \Carbon\Carbon::parse($c->respond_by);

                // First deadline: chase once, do not give up.
                if (!$c->followed_up_at && now()->greaterThanOrEqualTo($due)) {
                    if (!$dryRun) {
                        DB::table('placement_candidates')->where('id', $c->id)->update([
                            'followed_up_at' => now(),
                            'respond_by'     => now()->addHours(self::FOLLOW_UP_GRACE_HOURS),
                            'updated_at'     => now(),
                        ]);
                    }
                    $out['follow_ups'][] = [
                        'request'   => $req->reference,
                        'candidate' => $c->id,
                        'name'      => User::find($c->maid_user_id)?->name,
                        'phone'     => User::find($c->maid_user_id)?->phone,
                    ];
                    continue;
                }

                // Second deadline: silence is an answer. Move to the backup.
                if ($c->followed_up_at && now()->greaterThanOrEqualTo($due)) {
                    if (!$dryRun) {
                        $r = app(PlacementQueueService::class)
                            ->outcome($c->id, 'unreachable', 'no reply within the follow-up window');
                        $next = $r['next'] ?? null;
                    } else {
                        $next = DB::table('placement_candidates')
                            ->where('employer_id', $req->employer_id)
                            ->where('status', 'queued')->orderBy('rank')->first();
                        $next = $next ? ['rank' => $next->rank, 'name' => User::find($next->maid_user_id)?->name] : null;
                    }

                    $out['promoted'][] = [
                        'request' => $req->reference,
                        'dropped' => User::find($c->maid_user_id)?->name,
                        'next'    => $next['name'] ?? null,
                    ];
                }
            }

            // 3. Running out of cover — say so before it becomes a crisis.
            $left = DB::table('placement_candidates')
                ->where('hire_request_id', $req->id)
                ->whereIn('status', ['queued', 'offered'])->count();

            if ($req->status !== 'matched' && $left < 2) {
                $out['thin'][] = ['request' => $req->reference, 'remaining' => $left];
            }
        }

        if (!$dryRun && ($out['queues_built'] || $out['promoted'])) {
            Log::info('Matching cadence advanced', $out);
        }

        return $out;
    }

    /**
     * Start the clock on an opening that has just gone to the group.
     */
    public function openClaimWindow(HireRequest $req, ?string $jobCode = null): void
    {
        $req->update([
            'job_code'        => $jobCode ?? $req->job_code,
            'group_posted_at' => now(),
            'claims_close_at' => now()->addHours(self::CLAIM_WINDOW_HOURS),
            'status'          => $req->status === 'open' ? $req->status : $req->status,
        ]);
    }

    /** Start the clock on a candidate we have just approached. */
    public function markOffered(int $candidateId): void
    {
        DB::table('placement_candidates')->where('id', $candidateId)->update([
            'status'     => 'offered',
            'offered_at' => now(),
            'respond_by' => now()->addHours(self::OFFER_RESPONSE_HOURS),
            'updated_at' => now(),
        ]);
    }
}
