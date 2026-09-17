<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Models\AgentNote;
use App\Models\CallLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommsController extends ApiController
{
    public function threadByPhone(string $phone): JsonResponse
    {
        try {
            $conversations = AgentConversation::whereHas('identity', fn($q) => $q->where('phone', 'like', '%' . $phone . '%'))
                ->with(['messages' => fn($q) => $q->latest()->limit(100)])
                ->latest()
                ->get();

            return $this->success($conversations, 'Thread retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve thread: ' . $e->getMessage(), 500);
        }
    }

    public function threadByUser($userId): JsonResponse
    {
        try {
            $conversations = AgentConversation::where('user_id', $userId)
                ->with(['messages' => fn($q) => $q->latest()->limit(100)])
                ->latest()
                ->get();

            return $this->success($conversations, 'Threads retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve threads: ' . $e->getMessage(), 500);
        }
    }

    public function logEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type'  => 'required|string|max:100',
            'entity_id'    => 'required|integer',
            'note'         => 'required|string|max:5000',
            'action_taken' => 'nullable|string|max:100',
            'outcome'      => 'nullable|string|max:50',
            'metadata'     => 'nullable|array',
        ]);

        try {
            $note = AgentNote::create(array_merge($validated, [
                'agent_type'    => request()->agent_api_key->agent_type ?? null,
                'agent_user_id' => null,
            ]));

            return $this->success($note, 'Communication event logged', [], 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to log communication event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upsert the call log for a Vapi call.
     *
     * The voice assistant is told to log every call, but update_call_log was
     * pointed at /communications/event — a best-guess mapping that wants
     * entity_type/entity_id and rejects everything the tool actually sends. So
     * the one thing Jane does on every single call has been failing silently,
     * and 15,246 call_logs rows exist with nothing written by the assistant.
     *
     * Keyed on vapi_call_id so the assistant can call it mid-call and again at
     * the end without creating two rows.
     */
    public function storeCallLog(Request $request): JsonResponse
    {
        $v = $request->validate([
            'vapi_call_id'   => 'required|string|max:120',
            'caller_phone'   => 'nullable|string|max:32',
            'caller_name'    => 'nullable|string|max:160',
            'caller_type'    => 'nullable|string|max:32',
            'summary'        => 'nullable|string|max:8000',
            'actions_taken'  => 'nullable|array',
            'paperclip_tasks'=> 'nullable|array',
            'needs_followup' => 'nullable|boolean',
            'request_type'   => 'nullable|string|max:60',
            'status'         => 'nullable|string|max:40',
            'transcript'     => 'nullable|string',
            'goal_achieved'  => 'nullable|boolean',
        ]);

        $phone = $v['caller_phone'] ?? null;
        $userId = null;
        if ($phone) {
            $tail = substr(preg_replace('/\D+/', '', $phone), -10);
            if (strlen($tail) >= 10) {
                $userId = \App\Models\User::where('phone', 'like', "%{$tail}")->value('id');
            }
        }

        $payload = array_filter([
            'user_id'          => $userId,
            'phone'            => $phone,
            'call_type'        => $v['caller_type'] ?? null,
            'status'           => $v['status'] ?? 'completed',
            'summary'          => $v['summary'] ?? null,
            'transcript'       => $v['transcript'] ?? null,
            'goal_achieved'    => $v['goal_achieved'] ?? null,
            'follow_up_action' => $v['request_type'] ?? null,
            'notes'            => !empty($v['needs_followup']) ? 'needs follow-up' : null,
            'metadata'         => array_filter([
                'actions_taken'   => $v['actions_taken'] ?? null,
                'paperclip_tasks' => $v['paperclip_tasks'] ?? null,
                'caller_name'     => $v['caller_name'] ?? null,
            ]) ?: null,
            'updated_at'       => now(),
        ], fn ($x) => $x !== null);

        $log = \App\Models\CallLog::updateOrCreate(
            ['vapi_call_id' => $v['vapi_call_id']],
            $payload + ['created_at' => now()]
        );

        return $this->success([
            'call_log_id'  => $log->id,
            'vapi_call_id' => $log->vapi_call_id,
            'user_id'      => $log->user_id,
            'matched_user' => $userId !== null,
        ], 'Call log saved');
    }

    /**
     * Schedule a callback for the CS team.
     *
     * schedule_callback was pointed at /onboarding/touchpoints, which requires a
     * journey_id the voice assistant has no way to know — so every callback a
     * caller asked for on the phone was silently dropped at validation. Callbacks
     * are already tracked as human tasks ("Callback: <name> (<phone>) …"), so
     * that is where these belong.
     */
    public function scheduleCallback(Request $request): JsonResponse
    {
        $v = $request->validate([
            'phone'        => 'required|string|max:32',
            'scheduled_at' => 'required|date',
            'reason'       => 'required|string|max:300',
            'notes'        => 'nullable|string|max:2000',
            'caller_name'  => 'nullable|string|max:160',
        ]);

        $tail = substr(preg_replace('/\D+/', '', $v['phone']), -10);
        $user = strlen($tail) >= 10
            ? \App\Models\User::where('phone', 'like', "%{$tail}")->first()
            : null;

        $who = $v['caller_name'] ?? ($user->name ?? 'Unknown caller');

        $task = \App\Models\HumanTask::create([
            'agent_name'      => 'Jane (voice)',
            // task_type follows the existing convention for these rows.
            'task_type'       => 'maid_callback',
            // reason is a constrained enum (agent_disabled | agent_error |
            // hitl_required | manual_override | ai_downtime) — the caller's own
            // words go in description, not here.
            'reason'          => 'hitl_required',
            'description'     => "Callback: {$who} ({$v['phone']}) — {$v['reason']}",
            // priority is a smallint here, not a word: 1 high, 2 medium, 3 low.
            'priority'        => 2,
            'status'          => 'pending',
            'related_user_id' => $user->id ?? null,
            'due_by'          => $v['scheduled_at'],
            'task_payload'    => [
                'phone'  => $v['phone'],
                'notes'           => $v['notes'] ?? null,
                'caller_reason'   => $v['reason'],
                'source'          => 'voice_call',
            ],
        ]);

        return $this->success([
            'task_id'      => $task->id,
            'due_by'       => $task->due_by,
            'matched_user' => $user->id ?? null,
        ], 'Callback scheduled');
    }

    public function callLogs(Request $request): JsonResponse
    {
        try {
            $query = CallLog::query();

            if ($callType = $request->get('call_type')) {
                $query->where('call_type', $callType);
            }
            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            if ($userId = $request->get('user_id')) {
                $query->where('user_id', $userId);
            }

            $logs = $query->with('user:id,name,phone')->latest()->paginate(25);

            return $this->paginated($logs, 'Call logs retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to list call logs: ' . $e->getMessage(), 500);
        }
    }

    public function showCallLog($id): JsonResponse
    {
        try {
            $log = CallLog::with('user:id,name,phone')->findOrFail($id);

            return $this->success($log, 'Call log retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve call log: ' . $e->getMessage(), 500);
        }
    }

    public function updateCallOutcome(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'goal_achieved'    => 'nullable|boolean',
            'notes'            => 'nullable|string|max:5000',
            'follow_up_action' => 'nullable|string|max:255',
        ]);

        try {
            $log = CallLog::findOrFail($id);
            $log->update($validated);

            return $this->success($log->fresh(), 'Call outcome updated');
        } catch (\Throwable $e) {
            return $this->error('Failed to update call outcome: ' . $e->getMessage(), 500);
        }
    }
}
