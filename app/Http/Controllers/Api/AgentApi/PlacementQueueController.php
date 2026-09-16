<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Services\PlacementQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

/**
 * The candidate queue for an opening: who we are approaching, and who is next.
 */
class PlacementQueueController extends ApiController
{
    /** Build or top up the queue. Group volunteers rank above matcher picks. */
    public function build(Request $request): JsonResponse
    {
        $v = $request->validate([
            'employer_id'   => 'required|integer|exists:users,id',
            'preference_id' => 'nullable|integer|exists:employer_preferences,id',
            'job_code'      => 'nullable|string|max:16',
            'size'          => 'nullable|integer|min:1|max:10',
        ]);

        $result = app(PlacementQueueService::class)->build(
            $v['employer_id'], $v['preference_id'] ?? null, $v['job_code'] ?? null, $v['size'] ?? 3
        );

        return $this->success($result, $result['queued']
            ? "Queued {$result['queued']} candidate(s) — rank 1 is who to approach now"
            : 'No new candidates available to queue');
    }

    /** The current queue, primary first. */
    public function show(int $employerId): JsonResponse
    {
        $rows = DB::table('placement_candidates')
            ->where('employer_id', $employerId)
            ->orderBy('rank')
            ->get();

        $out = $rows->map(function ($c) {
            $u = User::find($c->maid_user_id);
            return [
                'candidate_id'  => $c->id,
                'rank'          => $c->rank,
                'maid_user_id'  => $c->maid_user_id,   // use this for matching/assign
                'name'          => $u->name ?? null,
                'phone'         => $u->phone ?? null,
                'status'        => $c->status,
                'source'        => $c->source,
                'job_code'      => $c->job_code,
                'offered_at'    => $c->offered_at,
                'outcome_reason'=> $c->outcome_reason,
            ];
        });

        $active = $out->firstWhere('status', 'offered') ?? $out->firstWhere('status', 'queued');

        return $this->success([
            'employer_id' => $employerId,
            'approach_now' => $active,
            'queue'        => $out,
        ], $out->count() ? 'Queue retrieved' : 'No queue built yet — call POST /placements/queue');
    }

    /** Mark a candidate as offered, so she is held and not shown to other families. */
    public function offer(int $candidateId): JsonResponse
    {
        $updated = DB::table('placement_candidates')->where('id', $candidateId)
            ->whereIn('status', ['queued', 'offered'])
            ->update(['status' => 'offered', 'offered_at' => now(), 'updated_at' => now()]);

        if (!$updated) {
            return $this->error('Candidate not found, or no longer in the queue.', 404);
        }

        return $this->success(['candidate_id' => $candidateId, 'status' => 'offered'], 'Candidate marked as offered');
    }

    /**
     * Record the outcome and get the next candidate.
     *
     * declined / unreachable do not end the search — the backup is already
     * chosen and screened, so the family gets a name the same day instead of
     * starting over.
     */
    public function outcome(Request $request, int $candidateId): JsonResponse
    {
        $v = $request->validate([
            'status' => 'required|in:accepted,declined,unreachable,placed,superseded',
            'reason' => 'nullable|string|max:200',
        ]);

        try {
            $r = app(PlacementQueueService::class)->outcome($candidateId, $v['status'], $v['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }

        $msg = in_array($v['status'], ['accepted', 'placed'], true)
            ? 'Recorded. Opening filled — remaining candidates released.'
            : ($r['next']
                ? 'Recorded. Next candidate is ready — approach rank ' . $r['next']['rank'] . '.'
                : 'Recorded. No backups left — rebuild the queue.');

        return $this->success($r, $msg);
    }

    /** Who cannot be offered right now, and why. */
    public function unavailable(): JsonResponse
    {
        $blocked = app(PlacementQueueService::class)->unavailableMaids();
        $out = [];
        foreach ($blocked as $id => $why) {
            $u = User::find($id);
            $out[] = ['maid_user_id' => $id, 'name' => $u->name ?? null, 'reason' => $why];
        }

        return $this->success(['count' => count($out), 'maids' => $out], 'Maids not currently offerable');
    }
}
