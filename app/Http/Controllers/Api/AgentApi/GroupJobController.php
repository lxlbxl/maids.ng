<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Openings posted to the helper group, and the helpers who volunteer for them.
 *
 * A claim is the strongest signal we ever get: this specific person wants this
 * specific job. Recording it here is what lets the matcher rank volunteers
 * above a generic search result.
 */
class GroupJobController extends ApiController
{
    /**
     * Record a helper volunteering for an opening.
     *
     * Screens at the door rather than accepting everything. A helper who is
     * marked unavailable, or who is in a different state from the job, is
     * recorded as rejected with a reason — so she can be told why instead of
     * waiting on a shortlist she was never going to reach, and so the family
     * sees a shortlist worth reading.
     */
    public function claim(Request $request): JsonResponse
    {
        $v = $request->validate([
            'job_code'       => 'required|string|max:16',
            'wa_id'          => 'nullable|string|max:32',
            'maid_user_id'   => 'nullable|integer|exists:users,id',
            'role'           => 'nullable|string|max:160',
            'area'           => 'nullable|string|max:160',
            'job_type'       => 'nullable|string|max:80',
            'employer_issue' => 'nullable|string|max:32',
            'employer_id'    => 'nullable|integer|exists:users,id',
        ]);

        $maidId = $v['maid_user_id'] ?? null;
        if (!$maidId && !empty($v['wa_id'])) {
            $tail = substr(preg_replace('/\D+/', '', $v['wa_id']), -10);
            $maidId = User::where('role', 'maid')->where('phone', 'like', "%{$tail}")->value('id');
        }
        if (!$maidId) {
            return $this->error('Could not identify the helper. Pass maid_user_id, or a wa_id that matches a maid.', 422);
        }

        $profile = DB::table('maid_profiles')->where('user_id', $maidId)->first();

        $availability = $profile->availability_status ?? null;
        $ninVerified  = (bool) ($profile->nin_verified ?? false);
        $city         = $profile->city ?? $profile->location ?? null;

        // Screening. Both checks are about not wasting the helper's time as much
        // as the family's: a Lagos helper will not take a Lugbe live-in role.
        $status = 'claimed';
        $reason = null;

        // willing_states is the helper's own declaration of where she will work,
        // so it outranks where she happens to live. Faith Olasunkanmi (Ikorodu)
        // and EJIGA Charity (Agege) both answered ["Lagos"] and then claimed a
        // Lugbe, Abuja role — a claim neither could take. Checking only the city
        // let those through, because "Ikorodu" carries no state name to compare.
        $queue   = app(\App\Services\PlacementQueueService::class);
        $willing = $profile && !empty($v['area']) ? $queue->canServeArea($profile, $v['area']) : true;

        if ($availability && $availability !== 'available') {
            $status = 'rejected';
            $reason = "helper is marked {$availability}";
        } elseif (!$willing) {
            $status = 'rejected';
            $reason = "helper does not work in {$v['area']}";
        } elseif ($city && !empty($v['area']) && !$this->sameState($city, $v['area'])) {
            $status = 'rejected';
            $reason = "helper is in {$city}, job is in {$v['area']}";
        }

        $existing = DB::table('group_job_claims')
            ->where('job_code', $v['job_code'])->where('maid_user_id', $maidId)->first();

        if ($existing) {
            return $this->success([
                'claim_id' => $existing->id, 'status' => $existing->status,
                'duplicate' => true,
            ], 'Already recorded for this opening');
        }

        // Spray-and-pray cap. One helper claimed five openings across five
        // different areas in a week, which crowds out genuinely local
        // candidates and tells the family nothing.
        $recent = DB::table('group_job_claims')
            ->where('maid_user_id', $maidId)
            ->where('created_at', '>=', now()->subDays(7))
            ->where('status', '!=', 'rejected')
            ->count();

        if ($recent >= 3 && $status === 'claimed') {
            $status = 'rejected';
            $reason = 'more than 3 open claims in the last 7 days';
        }

        $id = DB::table('group_job_claims')->insertGetId([
            'job_code'       => $v['job_code'],
            'maid_user_id'   => $maidId,
            'wa_id'          => $v['wa_id'] ?? null,
            'role'           => $v['role'] ?? null,
            'area'           => $v['area'] ?? null,
            'job_type'       => $v['job_type'] ?? null,
            'employer_issue' => $v['employer_issue'] ?? null,
            'employer_id'    => $v['employer_id'] ?? null,
            'status'         => $status,
            'reject_reason'  => $reason,
            'nin_verified'   => $ninVerified,
            'availability'   => $availability,
            'maid_city'      => $city,
            'claimed_at'     => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Log::info('Group job claim recorded', [
            'job_code' => $v['job_code'], 'maid_user_id' => $maidId, 'status' => $status, 'reason' => $reason,
        ]);

        return $this->success([
            'claim_id' => $id, 'job_code' => $v['job_code'], 'maid_user_id' => $maidId,
            'status' => $status, 'reject_reason' => $reason,
            'nin_verified' => $ninVerified, 'availability' => $availability,
        ], $status === 'claimed' ? 'Claim recorded' : 'Claim recorded but screened out');
    }

    /**
     * The shortlist for an opening: who volunteered, best first.
     *
     * This is what makes a claim worth making. Ranking mirrors maid-match —
     * NIN-verified first, then profile completeness, then how quickly she
     * responded, because promptness has been the best proxy we have for a
     * helper who will actually show up.
     */
    public function shortlist(string $jobCode): JsonResponse
    {
        // Ordered by heat, matching the placement queue. Claim order is fair but
        // not useful — a helper who replied two hours ago is job-hunting now,
        // while a nine-day-old claim has often already found work.
        $queue = app(\App\Services\PlacementQueueService::class);

        $claims = DB::table('group_job_claims')
            ->where('job_code', $jobCode)
            ->where('status', '!=', 'rejected')
            ->get()
            ->map(fn ($c) => tap($c, fn ($x) => $x->heat = $queue->heatScore($x)))
            ->sortByDesc(fn ($c) => $c->heat['score'])
            ->values();

        $out = $claims->map(function ($c) {
            $u = User::find($c->maid_user_id);
            $p = DB::table('maid_profiles')->where('user_id', $c->maid_user_id)->first();
            return [
                'claim_id'      => $c->id,
                'maid_user_id'  => $c->maid_user_id,
                'name'          => $u->name ?? null,
                'phone'         => $u->phone ?? $c->wa_id,
                'nin_verified'  => (bool) $c->nin_verified,
                'availability'  => $c->availability,
                'city'          => $c->maid_city,
                'completeness'  => $p->profile_completeness ?? null,
                'claimed_at'    => $c->claimed_at,
                'status'        => $c->status,
                'heat'          => $c->heat['score'],
                'claimed_days_ago' => $c->heat['age_days'],
                // Still worth approaching, but ask whether she is free before
                // naming her to a family.
                'needs_reconfirm'  => $c->heat['stale'],
            ];
        });

        $rejected = DB::table('group_job_claims')->where('job_code', $jobCode)->where('status', 'rejected')
            ->get(['maid_user_id', 'reject_reason']);

        return $this->success([
            'job_code'  => $jobCode,
            'shortlist' => $out,
            'count'     => $out->count(),
            'screened_out' => $rejected,
        ], $out->count() ? 'Shortlist built from group volunteers' : 'No volunteers yet for this opening');
    }

    /** Close an opening once it is filled, so helpers stop claiming it. */
    public function close(Request $request, string $jobCode): JsonResponse
    {
        $v = $request->validate([
            'filled_by_maid_user_id' => 'nullable|integer|exists:users,id',
            'reason'                 => 'nullable|string|max:120',
        ]);

        $updated = DB::table('group_job_claims')
            ->where('job_code', $jobCode)
            ->where('status', 'claimed')
            ->update([
                'status'        => 'closed',
                'reject_reason' => $v['reason'] ?? 'opening filled',
                'updated_at'    => now(),
            ]);

        if (!empty($v['filled_by_maid_user_id'])) {
            DB::table('group_job_claims')
                ->where('job_code', $jobCode)
                ->where('maid_user_id', $v['filled_by_maid_user_id'])
                ->update(['status' => 'assigned', 'reject_reason' => null, 'updated_at' => now()]);
        }

        return $this->success([
            'job_code' => $jobCode, 'claims_closed' => $updated,
        ], 'Opening closed');
    }

    /**
     * Two places are the same state when either name contains the other's state
     * word. Deliberately coarse — the aim is to stop an Abuja opening
     * collecting Lagos volunteers, not to adjudicate neighbourhoods.
     */
    private function sameState(string $a, string $b): bool
    {
        $states = ['lagos','abuja','fct','ogun','oyo','rivers','kano','kaduna','enugu','delta',
                   'edo','anambra','imo','akwa','cross river','plateau','benue','niger','kwara','osun','ondo','ekiti'];

        $sa = $this->stateOf(strtolower($a), $states);
        $sb = $this->stateOf(strtolower($b), $states);

        // Unknown on either side: do not reject. A missing city should never
        // silently disqualify someone.
        if ($sa === null || $sb === null) {
            return true;
        }
        // Abuja and FCT are the same place.
        $norm = fn ($s) => $s === 'fct' ? 'abuja' : $s;

        return $norm($sa) === $norm($sb);
    }

    private function stateOf(string $text, array $states): ?string
    {
        foreach ($states as $s) {
            if (str_contains($text, $s)) {
                return $s;
            }
        }
        return null;
    }
}
