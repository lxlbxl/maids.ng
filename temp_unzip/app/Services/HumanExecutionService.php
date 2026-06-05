<?php

namespace App\Services;

use App\Models\{HumanTask, User, AgentConversation, AgentMessage, SeoPage};
use App\Models\{AgentCampaign, AgentChannelIdentity, AgentOutreachLog};
use App\Services\Agents\{ScoutAgent, SeoContentAgent};
use Illuminate\Support\Facades\Log;

class HumanExecutionService
{
    public function __construct(
        private AgentEventLogger $logger
    ) {}

    public function execute(HumanTask $task, User $operator, array $inputs = []): array
    {
        $task->update([
            'status'      => 'in_progress',
            'assigned_to' => $operator->id,
            'assigned_at' => now(),
        ]);

        try {
            $result = match ($task->task_type) {
                'match_employer'      => $this->executeMatchEmployer($task, $operator, $inputs),
                'send_message'        => $this->executeSendMessage($task, $operator, $inputs),
                'verify_nin'          => $this->executeVerifyNin($task, $operator, $inputs),
                'process_payout'      => $this->executeProcessPayout($task, $operator, $inputs),
                'resolve_dispute'     => $this->executeResolveDispute($task, $operator, $inputs),
                'review_maid_quality' => $this->executeReviewMaidQuality($task, $operator, $inputs),
                'generate_content'    => $this->executeGenerateContent($task, $operator, $inputs),
                'generate_seo_content'=> $this->executeGenerateSeoContent($task, $operator, $inputs),
                'send_outreach'       => $this->executeSendOutreach($task, $operator, $inputs),
                'approve_hitl'        => $this->executeApproveHitl($task, $operator, $inputs),
                default               => throw new \InvalidArgumentException("Unknown task type: {$task->task_type}"),
            };

            $task->update([
                'status'           => 'completed',
                'completed_by'     => $operator->id,
                'completed_at'     => now(),
                'completion_notes' => $inputs['notes'] ?? null,
            ]);

            $this->logger->log(
                $task->agent_name,
                "human.{$task->task_type}.completed",
                'success',
                "Human operator {$operator->name} completed: {$task->description}",
                ['result' => $result, 'operator_id' => $operator->id],
                ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
            );

            return ['success' => true, 'result' => $result];

        } catch (\Exception $e) {
            $task->update(['status' => 'pending']);

            $this->logger->logError(
                $task->agent_name,
                "human.{$task->task_type}.failed",
                "Human execution failed: {$e->getMessage()}",
                $e,
                ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function executeMatchEmployer(HumanTask $task, User $operator, array $inputs): array
    {
        $preferenceId = $task->task_payload['preference_id'];
        $preference   = \App\Models\EmployerPreference::findOrFail($preferenceId);
        $matches      = app(ScoutAgent::class)->findMatches($preference);

        return ['matches' => $matches, 'preference_id' => $preferenceId];
    }

    private function executeSendMessage(HumanTask $task, User $operator, array $inputs): array
    {
        $conversation = AgentConversation::findOrFail($task->task_payload['conversation_id']);
        $message      = $inputs['message'] ?? throw new \InvalidArgumentException('Message content required');

        AgentMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'admin',
            'content'         => $message,
            'created_at'      => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        app(ChannelSender::class)->send($conversation, $message);

        return ['sent' => true, 'channel' => $conversation->channel];
    }

    private function executeProcessPayout(HumanTask $task, User $operator, array $inputs): array
    {
        $assignmentId = $task->task_payload['assignment_id'];
        $amount       = $task->task_payload['amount'];
        $maidId       = $task->task_payload['maid_id'];
        $employerId   = $task->task_payload['employer_id'] ?? null;

        app(WalletService::class)->transferToMaid($employerId, $maidId, $amount, $assignmentId);

        return ['processed' => true, 'amount' => $amount, 'maid_id' => $maidId];
    }

    private function executeVerifyNin(HumanTask $task, User $operator, array $inputs): array
    {
        $maidId   = $task->task_payload['maid_id'];
        $decision = $inputs['decision'] ?? throw new \InvalidArgumentException('Decision required: approved|rejected');
        $notes    = $inputs['notes'] ?? null;

        \DB::table('nin_verifications')
            ->where('user_id', $maidId)
            ->update([
                'status'       => $decision,
                'review_notes' => "[Manual review by {$operator->name}] " . $notes,
                'reviewed_at'  => now(),
            ]);

        $user = User::find($maidId);
        if ($user && $user->maidProfile) {
            app(MaidProfileService::class)->recalculate($user);
        }

        return ['decision' => $decision, 'maid_id' => $maidId];
    }

    private function executeResolveDispute(HumanTask $task, User $operator, array $inputs): array
    {
        $resolution = $inputs['resolution'] ?? throw new \InvalidArgumentException('Resolution required');

        return app(AssignmentService::class)->resolveDispute(
            $task->task_payload['assignment_id'],
            $resolution,
            $inputs['refund_amount'] ?? 0
        );
    }

    private function executeReviewMaidQuality(HumanTask $task, User $operator, array $inputs): array
    {
        $maidId = $task->task_payload['maid_id'];
        $action = $inputs['action'] ?? 'coaching';
        $notes  = $inputs['notes'] ?? '';

        return ['action' => $action, 'maid_id' => $maidId, 'notes' => $notes];
    }

    private function executeGenerateContent(HumanTask $task, User $operator, array $inputs): array
    {
        $agent = app(\App\Services\Agents\MarketerAgent::class);
        return $agent->generatePost(
            $task->task_payload['funnel_stage'] ?? null,
            $task->task_payload['theme_id'] ?? null
        );
    }

    private function executeGenerateSeoContent(HumanTask $task, User $operator, array $inputs): array
    {
        $pageId = $task->task_payload['page_id'];
        $page   = SeoPage::findOrFail($pageId);

        $agent = app(SeoContentAgent::class);
        return $agent->generatePageContent($page);
    }

    private function executeSendOutreach(HumanTask $task, User $operator, array $inputs): array
    {
        $campaign = AgentCampaign::findOrFail($task->task_payload['campaign_id']);

        $engine = app(\App\Services\Agents\OutreachEngine::class);
        return $engine->dispatchCampaign($campaign);
    }

    private function executeApproveHitl(HumanTask $task, User $operator, array $inputs): array
    {
        $eventId  = $task->task_payload['event_id'];
        $decision = $inputs['decision'] ?? throw new \InvalidArgumentException('Decision: approved|rejected');

        $event = \App\Models\AgentEvent::findOrFail($eventId);
        $event->update([
            'approved'     => $decision === 'approved',
            'approved_by'  => $operator->id,
            'approval_note'=> $inputs['note'] ?? null,
            'approved_at'  => now(),
        ]);

        if ($decision === 'approved' && isset($task->task_payload['callback_job'])) {
            $jobClass   = $task->task_payload['callback_job'];
            $jobPayload = $task->task_payload['callback_payload'] ?? [];
            dispatch(new $jobClass(...$jobPayload));
        }

        return ['decision' => $decision, 'event_id' => $eventId];
    }
}
