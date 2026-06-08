<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgentCampaign;
use App\Models\AgentOutreachLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignsController extends ApiController
{
    public function index(): JsonResponse
    {
        try {
            $campaigns = AgentCampaign::with('logs')->latest()->paginate(25);

            return $this->paginated($campaigns, 'Campaigns retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to list campaigns: ' . $e->getMessage(), 500);
        }
    }

    public function logs(int $id): JsonResponse
    {
        try {
            AgentCampaign::findOrFail($id);

            $logs = AgentOutreachLog::where('campaign_id', $id)
                ->with('identity')
                ->latest()
                ->paginate(25);

            return $this->paginated($logs, 'Campaign logs retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve campaign logs: ' . $e->getMessage(), 500);
        }
    }

    public function dispatch(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'channel_identity_id' => 'required|integer|exists:agent_channel_identities,id',
            'channel'             => 'required|string|max:50',
            'message_content'     => 'required|string|max:4096',
        ]);

        try {
            AgentCampaign::findOrFail($id);

            $log = AgentOutreachLog::create([
                'campaign_id'         => $id,
                'channel_identity_id' => $validated['channel_identity_id'],
                'channel'             => $validated['channel'],
                'message_content'     => mb_substr($validated['message_content'], 0, 500),
                'status'              => 'sent',
                'sent_at'             => now(),
            ]);

            return $this->success($log, 'Outreach dispatched', [], 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to dispatch outreach: ' . $e->getMessage(), 500);
        }
    }
}
