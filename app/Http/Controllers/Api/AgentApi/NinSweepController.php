<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\NinVerification;
use App\Services\Agents\GatekeeperAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/agent-api/v1/admin/nin/sweep
 *
 * Manual trigger of the QoreID verification sweep, exposed over HTTP so the
 * Onboarding agent (Peace) can re-run it from a Paperclip heartbeat without
 * depending on a human invoking `php artisan ai:verify-pending-nins` over SSH
 * or waiting for the every-30-minutes Laravel scheduler.
 *
 * Mirrors App\Console\Commands\VerifyPendingNinsCommand semantics:
 *   - Picks up NinVerification rows in 'pending' status (oldest first).
 *   - Calls GatekeeperAgent::verifyIdentity per row, which writes through
 *     to the tracking table (verified/pending/failed) and to MaidProfile
 *     (nin_verified, nin_report).
 *   - Skips users whose profile already has nin_verified=true.
 *   - Per-user failures are caught and logged so a single bad NIN does not
 *     abort the rest of the sweep.
 *
 * Auth: agent.auth middleware (Bearer mng_sk_... key). Restrict by issuing
 * a key with scopes=['admin'] via the AgentApiKey table.
 */
class NinSweepController extends ApiController
{
    public function sweep(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:200',
        ]);
        $limit = (int) ($validated['limit'] ?? 50);

        $gatekeeper = app(GatekeeperAgent::class);

        $pending = NinVerification::with(['user', 'user.maidProfile'])
            ->where('status', 'pending')
            ->whereNotNull('user_id')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            return $this->success([
                'processed'    => 0,
                'verified'     => 0,
                'still_pending'=> 0,
                'failed'       => 0,
                'skipped'      => 0,
                'remaining'    => NinVerification::where('status', 'pending')->count(),
                'results'      => [],
            ], 'No pending verifications to process');
        }

        $results  = [];
        $verified = 0;
        $stillPending = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($pending as $verification) {
            $user = $verification->user;
            $entry = [
                'verification_id' => $verification->id,
                'user_id'         => $user?->id,
                'name'            => $user?->name,
                'status'          => null,
            ];

            if (! $user) {
                $verification->update([
                    'status' => 'failed',
                    'review_notes' => 'User not found during manual sweep.',
                    'reviewed_at' => now(),
                ]);
                $entry['status'] = 'failed';
                $entry['reason'] = 'user_missing';
                $failed++;
                $results[] = $entry;
                continue;
            }

            $profile = $user->maidProfile;
            if (! $profile || ! $profile->nin) {
                $verification->update([
                    'status' => 'failed',
                    'review_notes' => 'Maid profile or NIN missing during manual sweep.',
                    'reviewed_at' => now(),
                ]);
                $entry['status'] = 'failed';
                $entry['reason'] = 'profile_or_nin_missing';
                $failed++;
                $results[] = $entry;
                continue;
            }

            if ($profile->nin_verified) {
                $verification->update([
                    'status' => 'verified',
                    'reviewed_at' => now(),
                ]);
                $entry['status'] = 'verified';
                $entry['reason'] = 'already_verified_on_profile';
                $skipped++;
                $results[] = $entry;
                continue;
            }

            try {
                $result = $gatekeeper->verifyIdentity($profile, $profile->nin);
                $status = $result['status'] ?? ($result['success'] ? 'verified' : 'failed');

                $entry['status'] = $status;
                $entry['reason'] = $result['reason'] ?? null;
                $entry['confidence_score'] = $result['confidence_score'] ?? null;

                if ($status === 'verified') {
                    $verified++;
                } elseif ($status === 'pending') {
                    $stillPending++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                Log::warning('Manual NIN sweep failed for user ' . $user->id . ': ' . $e->getMessage());
                $entry['status'] = 'failed';
                $entry['reason'] = 'exception: ' . $e->getMessage();
                $failed++;
            }

            $results[] = $entry;
        }

        return $this->success([
            'processed'    => $pending->count(),
            'verified'     => $verified,
            'still_pending'=> $stillPending,
            'failed'       => $failed,
            'skipped'      => $skipped,
            'remaining'    => NinVerification::where('status', 'pending')->count(),
            'results'      => $results,
        ], sprintf('Sweep complete: %d processed, %d verified, %d still pending, %d failed, %d skipped',
            $pending->count(), $verified, $stillPending, $failed, $skipped));
    }
}
