<?php

namespace App\Services\Agents;

use App\Agents\Concerns\LogsEvents;
use App\Models\{AgentCampaign, AgentChannelIdentity, AgentOutreachLog};
use App\Services\Ai\AiService;
use App\Services\ChannelSender;
use App\Services\KnowledgeService;

class OutreachEngine
{
    use LogsEvents;

    protected string $agentName = 'outreach';

    public function __construct(
        private readonly AiService $ai,
        private readonly KnowledgeService $knowledge,
        private readonly ChannelSender $sender,
    ) {}

    public function dispatchCampaign(AgentCampaign $campaign): array
    {
        if (!$this->canProceed(
            'outreach.dispatch',
            'send_outreach',
            "Dispatch campaign '{$campaign->name}'",
            ['campaign_id' => $campaign->id]
        )) {
            return [];
        }

        $startTime = now();
        $results = ['sent' => 0, 'failed' => 0, 'logs' => []];

        $identities = AgentChannelIdentity::where('channel', $campaign->preferred_channel)
            ->whereNotNull($campaign->preferred_channel === 'email' ? 'email' : 'phone')
            ->where('is_verified', true)
            ->whereDoesntHave('outreachLogs', fn($q) =>
                $q->where('campaign_id', $campaign->id)
            )
            ->take($campaign->max_contacts_per_day)
            ->get();

        foreach ($identities as $identity) {
            try {
                $message = $this->personalizeMessage($campaign->message_template, $identity);

                $conversation = $identity->conversations()
                    ->where('channel', $campaign->preferred_channel)
                    ->where('status', 'open')
                    ->first();

                if (!$conversation) {
                    $conversation = $identity->conversations()->create([
                        'channel'   => $campaign->preferred_channel,
                        'status'    => 'open',
                        'user_id'   => $identity->user_id,
                    ]);
                }

                \App\Models\AgentMessage::create([
                    'conversation_id' => $conversation->id,
                    'role'            => 'assistant',
                    'content'         => $message,
                    'created_at'      => now(),
                ]);

                $sent = $this->sender->send($conversation, $message);

                $log = AgentOutreachLog::create([
                    'campaign_id'         => $campaign->id,
                    'channel_identity_id' => $identity->id,
                    'channel'             => $campaign->preferred_channel,
                    'message_content'     => $message,
                    'status'              => $sent ? 'sent' : 'failed',
                    'sent_at'             => $sent ? now() : null,
                    'error_message'       => $sent ? null : 'Channel send failed',
                ]);

                $results[$sent ? 'sent' : 'failed']++;
                $results['logs'][] = $log;

                $this->logEvent(
                    'outreach.sent',
                    $sent ? 'success' : 'error',
                    "Sent '{$campaign->name}' campaign to " . ($identity->display_name ?? 'Lead #' . $identity->id),
                    [
                        'campaign_slug'   => $campaign->slug,
                        'channel'         => $campaign->preferred_channel,
                        'identity_id'     => $identity->id,
                        'message_preview' => substr($message, 0, 200),
                    ],
                    [
                        'related_user_id' => $identity->user_id,
                        'related_model'   => 'AgentOutreachLog',
                        'related_id'      => $log->id,
                        'channel'         => $campaign->preferred_channel,
                    ]
                );
            } catch (\Exception $e) {
                $results['failed']++;
                $this->getLogger()->logError(
                    $this->agentName,
                    'outreach.failed',
                    "Outreach failed for identity #{$identity->id}: {$e->getMessage()}",
                    $e
                );
            }
        }

        $campaign->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRun($campaign),
        ]);

        $this->logEvent(
            'outreach.batch_completed',
            'info',
            "Campaign '{$campaign->name}' dispatched: {$results['sent']} sent, {$results['failed']} failed",
            $results,
            ['duration_ms' => now()->diffInMilliseconds($startTime)]
        );

        return $results;
    }

    private function personalizeMessage(string $template, AgentChannelIdentity $identity): string
    {
        $replacements = [
            '{name}'          => $identity->display_name ?? 'there',
            '{first_name}'    => explode(' ', $identity->display_name ?? 'there')[0],
            '{platform}'      => 'Maids.ng',
            '{channel}'       => $identity->channel,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    private function calculateNextRun(AgentCampaign $campaign): ?\DateTime
    {
        if (!$campaign->schedule_cron) return null;

        $expression = new \Cron\CronExpression($campaign->schedule_cron);
        return $expression->getNextRunDate(now())->format('Y-m-d H:i:s');
    }
}
