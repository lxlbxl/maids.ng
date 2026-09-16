<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Controller;
use App\Services\WaContactIssueService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One thread per WhatsApp contact.
 *
 * Whatever the reason for reaching out — a job ack, a NIN reminder, a payment
 * chase, a campaign — it belongs on the contact's single issue, alongside
 * everything they have ever said to us. These endpoints are how agents and the
 * shell scripts get hold of it.
 */
class WaThreadController extends Controller
{
    use ApiResponse;

    /** The contact's issue, opening one if this is the first contact. */
    public function resolve(Request $request): JsonResponse
    {
        $v = $request->validate([
            'phone'   => 'required|string|max:32',
            'name'    => 'nullable|string|max:160',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        try {
            $issue = app(WaContactIssueService::class)
                ->resolve($v['phone'], $v['name'] ?? null, $v['user_id'] ?? null);

            return $this->success($issue, $issue['created'] ? 'Thread opened' : 'Thread found');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->error('Could not resolve thread: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Record a message we sent.
     *
     * Call this after the send succeeds. Until now nothing logged outbound at
     * all, so an issue showed only the customer's half of the conversation and
     * an agent reading it could not tell whether anyone had already replied.
     */
    public function logOutbound(Request $request): JsonResponse
    {
        $v = $request->validate([
            'phone'    => 'required|string|max:32',
            'text'     => 'required|string|max:8000',
            'purpose'  => 'nullable|string|max:80',
            'sent_by'  => 'nullable|string|max:80',
            'template' => 'nullable|string|max:120',
            'name'     => 'nullable|string|max:160',
            'user_id'  => 'nullable|integer|exists:users,id',
        ]);

        try {
            $issue = app(WaContactIssueService::class)->logOutbound($v['phone'], $v['text'], [
                'purpose'  => $v['purpose'] ?? null,
                'sent_by'  => $v['sent_by'] ?? null,
                'template' => $v['template'] ?? null,
                'name'     => $v['name'] ?? null,
                'user_id'  => $v['user_id'] ?? null,
                'wa_id'    => $v['phone'],
            ]);

            return $this->success($issue, 'Outbound recorded on thread');
        } catch (\Throwable $e) {
            return $this->error('Could not record outbound: ' . $e->getMessage(), 500);
        }
    }

    /** Everything we know about a contact's thread, for an agent about to reply. */
    public function show(string $phone): JsonResponse
    {
        $svc  = app(WaContactIssueService::class);
        $waId = $svc->normalize($phone);

        $row = DB::table('wa_contact_issues')->where('wa_id', $waId)->first();
        if (!$row) {
            return $this->success(['wa_id' => $waId, 'exists' => false], 'No thread yet for this contact');
        }

        $comments = DB::connection('paperclip')->table('issue_comments')
            ->where('issue_id', $row->issue_id)
            ->orderByDesc('created_at')->limit(20)
            ->get(['body', 'created_at']);

        return $this->success([
            'wa_id'           => $waId,
            'exists'          => true,
            'issue_id'        => $row->issue_id,
            'identifier'      => $row->issue_identifier,
            'user_id'         => $row->user_id,
            'display_name'    => $row->display_name,
            'last_inbound_at' => $row->last_inbound_at,
            'last_outbound_at'=> $row->last_outbound_at,
            'recent'          => $comments,
        ], 'Thread retrieved');
    }
}
