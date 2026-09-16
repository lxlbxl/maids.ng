<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The queue the group-announce-drain cron reads and reports back to.
 */
class GroupAnnouncementController extends ApiController
{
    /** Announcements waiting to be posted, oldest first. */
    public function pending(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 3), 10);

        $rows = DB::table('group_announcements')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['id', 'event', 'job_code', 'maid_user_id', 'body', 'attempts']);

        return $this->success(['announcements' => $rows, 'count' => $rows->count()],
            $rows->count() ? 'Pending announcements' : 'Nothing to post');
    }

    /**
     * Record the outcome of a send.
     *
     * A failure is retried until max_attempts, then parked as 'failed' — a
     * broken Evolution instance must not have the cron re-posting the same
     * congratulation on every run for days.
     */
    public function result(Request $request, int $id): JsonResponse
    {
        $v = $request->validate([
            'status'       => 'required|in:sent,failed,skipped',
            'error'        => 'nullable|string|max:200',
            'max_attempts' => 'nullable|integer|min:1|max:20',
        ]);

        $row = DB::table('group_announcements')->where('id', $id)->first();
        if (!$row) {
            return $this->error('No such announcement.', 404);
        }

        if ($v['status'] === 'sent') {
            DB::table('group_announcements')->where('id', $id)->update([
                'status' => 'sent', 'sent_at' => now(), 'updated_at' => now(),
            ]);
            return $this->success(['id' => $id, 'status' => 'sent'], 'Recorded');
        }

        $attempts = (int) $row->attempts + 1;
        $max      = $v['max_attempts'] ?? 5;

        DB::table('group_announcements')->where('id', $id)->update([
            'attempts'   => $attempts,
            'last_error' => $v['error'] ?? null,
            'status'     => $attempts >= $max ? 'failed' : 'pending',
            'updated_at' => now(),
        ]);

        return $this->success([
            'id' => $id, 'attempts' => $attempts,
            'status' => $attempts >= $max ? 'failed' : 'pending',
        ], $attempts >= $max ? 'Parked after repeated failures' : 'Will retry');
    }
}
