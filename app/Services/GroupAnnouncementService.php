<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Announces results back to the helper group.
 *
 * Openings go out to the group and nothing ever comes back, so from a helper's
 * side the postings are a void — she cannot tell whether replying leads
 * anywhere. Naming the helper who got the job closes that loop: it is proof the
 * process works, it credits her publicly, and it teaches the behaviour we want
 * (reply quickly, keep NIN current).
 */
class GroupAnnouncementService
{
    /**
     * How the helper is named in a group of several hundred people.
     *
     * First name plus surname initial by default — enough for her to be
     * recognised and congratulated, without publishing a domestic worker's full
     * name to a large group she did not choose the membership of. Set
     * GROUP_ANNOUNCE_FULL_NAME=true to use the full name.
     */
    private function displayName(?User $u): string
    {
        $name = trim((string) ($u->name ?? ''));
        if ($name === '') {
            return 'A helper';
        }
        if (config('services.group.announce_full_name', env('GROUP_ANNOUNCE_FULL_NAME', false))) {
            return $name;
        }

        $parts = preg_split('/\s+/', $name) ?: [$name];
        $first = ucfirst(strtolower($parts[0]));

        return count($parts) > 1
            ? $first . ' ' . strtoupper(substr(end($parts), 0, 1)) . '.'
            : $first;
    }

    /**
     * Queue an announcement. Idempotent per (event, opening, helper).
     *
     * Employer details never appear — not the family name, not the address, not
     * the phone. The role and area are already public: they were in the original
     * posting. Anything more would be publishing a customer's household to a
     * few hundred strangers.
     */
    public function queue(string $event, ?string $jobCode, int $maidUserId, array $context = []): ?int
    {
        $u     = User::find($maidUserId);
        $name  = $this->displayName($u);
        // "Congratulations Deborah F." reads like a roll call; the greeting
        // wants the bare first name.
        $first = preg_split('/\s+/', trim((string) ($u->name ?? 'there')))[0] ?? 'there';
        $first = ucfirst(strtolower($first));

        $role = $context['role'] ?? null;
        $area = $context['area'] ?? null;

        if ((!$role || !$area) && $jobCode) {
            $claim = DB::table('group_job_claims')->where('job_code', $jobCode)->first();
            $role ??= $claim->role ?? null;
            $area ??= $claim->area ?? null;
        }

        $where = $role && $area ? "the {$role} role in {$area}"
               : ($role ? "the {$role} role" : 'a role posted here');
        // Without the article, for sentences that already have one in front.
        $whereBare = $role && $area ? "{$role}, {$area}"
                   : ($role ?: 'a role posted here');

        $body = match ($event) {
            'matched' => "✅ *{$jobCode} — FILLED*\n\n"
                . "{$name} has been matched to {$where}.\n\n"
                . "She put her hand up in this group and got the job. Congratulations {$first} 👏\n\n"
                . "_This is why it pays to reply fast. When a posting fits you, send AVAILABLE and the code straight away — "
                . "and keep your profile and NIN up to date so you are not skipped._",

            'started' => "🎉 *{$name} started work today* — {$whereBare}"
                . ($jobCode ? " ({$jobCode})" : '') . ".\n\n"
                . "Another match from this group, completed.\n\n"
                . "_New roles are posted here first. Reply AVAILABLE and the code the moment you see one that fits._",

            default => null,
        };

        if ($body === null) {
            Log::warning('Group announcement: unknown event', ['event' => $event]);
            return null;
        }

        try {
            return DB::table('group_announcements')->insertGetId([
                'event'        => $event,
                'job_code'     => $jobCode,
                'maid_user_id' => $maidUserId,
                'body'         => $body,
                'status'       => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique violation — already queued or sent. A retried outcome call
            // must not congratulate the same person twice.
            Log::info('Group announcement already queued', [
                'event' => $event, 'job_code' => $jobCode, 'maid_user_id' => $maidUserId,
            ]);
            return null;
        }
    }
}
