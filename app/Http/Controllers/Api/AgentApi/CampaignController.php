<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgentOutreachLog;
use App\Models\AgentCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends ApiController
{
    public function sendDirect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'          => 'required|integer',
            'channel'          => 'required|string|in:whatsapp,sms,email,call',
            'message'          => 'required|string|max:4096',
            'campaign_name'    => 'nullable|string|max:100',
            'campaign_type'    => 'nullable|string|max:50',
        ]);

        $campaign = null;
        if ($name = $validated['campaign_name'] ?? null) {
            $campaign = AgentCampaign::firstOrCreate(
                ['name' => $name],
                [
                    'slug'               => \Illuminate\Support\Str::slug($name),
                    'preferred_channel'  => $validated['channel'],
                    'is_active'          => true,
                    'trigger_type'       => 'manual',
                ],
            );
        }

        $log = AgentOutreachLog::create([
            'campaign_id'    => $campaign?->id,
            'channel'        => $validated['channel'],
            'message_content' => mb_substr($validated['message'], 0, 500),
            'status'         => 'sent',
            'sent_at'        => now(),
        ]);

        return $this->success(['log_id' => $log->id, 'campaign_id' => $campaign?->id], 'Outreach logged');
    }

    public function checkCooldown(int $channelIdentityId): JsonResponse
    {
        $last24h = AgentOutreachLog::where('channel_identity_id', $channelIdentityId)
            ->where('sent_at', '>=', now()->subDay())
            ->count();

        $last1h = AgentOutreachLog::where('channel_identity_id', $channelIdentityId)
            ->where('sent_at', '>=', now()->subHour())
            ->exists();

        $canSend = ! $last1h && $last24h < 5;

        return $this->success([
            'can_send'          => $canSend,
            'sent_last_24h'     => $last24h,
            'sent_last_hour'    => $last1h,
            'next_available_at' => $last1h ? now()->addHour() : null,
        ]);
    }
}
