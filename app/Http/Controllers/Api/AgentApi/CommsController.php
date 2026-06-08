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

    public function threadByUser(int $userId): JsonResponse
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

    public function showCallLog(int $id): JsonResponse
    {
        try {
            $log = CallLog::with('user:id,name,phone')->findOrFail($id);

            return $this->success($log, 'Call log retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve call log: ' . $e->getMessage(), 500);
        }
    }

    public function updateCallOutcome(Request $request, int $id): JsonResponse
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
