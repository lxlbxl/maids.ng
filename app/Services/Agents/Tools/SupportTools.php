<?php

namespace App\Services\Agents\Tools;

use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Models\SupportTicket;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * SupportTools — Policy info, escalation, and support operations.
 */
class SupportTools
{
    /**
     * Get support/policy information.
     *
     * @param array{ topic: string } $args
     * @param \App\Models\AgentChannelIdentity $identity
     * @param \App\Services\Agents\DTOs\ChannelMessage $message
     * @return array{ topic: string, information: string }
     */
    public function __invoke(array $args, $identity, $message): array
    {
        $topic = $args['topic'] ?? 'general';

        $information = match ($topic) {
            'guarantee' => $this->getGuaranteeInfo(),
            'verification' => $this->getVerificationInfo(),
            'refund' => $this->getRefundInfo(),
            'matching_process' => $this->getMatchingProcessInfo(),
            'wallet' => $this->getWalletInfo(),
            'withdrawal' => $this->getWithdrawalInfo(),
            default => $this->getGeneralInfo(),
        };

        return [
            'topic' => $topic,
            'information' => $information,
        ];
    }

    /**
     * Escalate conversation to a human agent.
     *
     * @param array{ reason: string, priority?: string, summary: string } $args
     * @param \App\Models\AgentChannelIdentity $identity
     * @param \App\Services\Agents\DTOs\ChannelMessage $message
     * @return array{ success: bool, ticket_id?: int, message: string }
     */
    public function escalate(array $args, $identity, $message): array
    {
        $ticket = SupportTicket::create([
            'user_id' => $identity->user_id,
            'type' => 'agent_escalation',
            'subject' => 'Ambassador Agent Escalation — ' . ($args['reason'] ?? 'general'),
            'description' => $args['summary'] ?? 'Escalated from AI conversation',
            'priority' => $args['priority'] ?? 'normal',
            'status' => 'open',
            'channel' => $message->channel,
            'conversation_id' => null, // Could link to AgentConversation
        ]);

        Log::info('Conversation escalated to human agent', [
            'ticket_id' => $ticket->id,
            'reason' => $args['reason'],
            'channel' => $message->channel,
            'identity_id' => $identity->id,
        ]);

        return [
            'success' => true,
            'ticket_id' => $ticket->id,
            'message' => 'I have escalated your conversation to our support team. They will reach out shortly.',
        ];
    }

    private function getGuaranteeInfo(): string
    {
        $days = (int) Setting::get('guarantee_period_days', 10);

        return "Maids.ng offers a {$days}-day money-back guarantee on all standard matches. "
            . "If you are not satisfied with your maid within {$days} days of their start date, "
            . "you can request a full refund. The refund will be credited to your Maids.ng wallet. "
            . "To request a refund, contact our support team through this chat or your dashboard.";
    }

    private function getVerificationInfo(): string
    {
        return "All maids on Maids.ng undergo a thorough verification process before appearing on the platform. "
            . "This includes NIN (National Identity Number) verification, background checks, "
            . "and skill assessment. Our Gatekeeper Agent handles verification automatically, "
            . "with borderline cases escalated to manual review. Verification typically takes 24-48 hours.";
    }

    private function getRefundInfo(): string
    {
        return "Refunds are processed within the guarantee period (currently "
            . Setting::get('guarantee_period_days', 10) . " days). "
            . "To request a refund: 1) Log into your dashboard, 2) Go to your active booking, "
            . "3) Click 'Request Refund' and provide details. "
            . "Refunds are reviewed within 1-2 business days and credited to your wallet.";
    }

    private function getMatchingProcessInfo(): string
    {
        return "Our matching process works in 5 steps: "
            . "1) Complete the matching quiz with your preferences (help type, schedule, location, budget). "
            . "2) Our Scout Agent scores all available maids against your preferences. "
            . "3) You receive top matches with detailed profiles and match scores. "
            . "4) Select your preferred maid and pay the one-time matching fee "
            . "(₦" . number_format((int) Setting::get('matching_fee_amount', 5000)) . "). "
            . "5) Your assignment is activated and the maid is notified. "
            . "Typical time from quiz to active assignment: 24-72 hours.";
    }

    private function getWalletInfo(): string
    {
        return "Maids.ng uses a wallet system for all payments. "
            . "Employers load funds to their wallet via Paystack or bank transfer. "
            . "When a salary payment is due, funds move from your wallet to escrow. "
            . "After the employer confirms the pay period, the platform commission is deducted "
            . "and the maid receives the net amount. "
            . "You can view all transactions in your dashboard under 'Wallet'.";
    }

    private function getWithdrawalInfo(): string
    {
        $min = number_format((int) Setting::get('withdrawal_minimum', 5000));

        return "Maids can withdraw earnings to their registered bank account. "
            . "The minimum withdrawal amount is ₦{$min}. "
            . "Withdrawal requests are processed within 1-3 business days. "
            . "To request a withdrawal: go to your Maid Dashboard > Earnings > Withdraw. "
            . "All withdrawals are reviewed by our Treasurer Agent for security.";
    }

    private function getGeneralInfo(): string
    {
        $days = (int) Setting::get('guarantee_period_days', 10);

        return "Maids.ng is Nigeria's premier domestic staff matching platform. "
            . "We connect employers with verified, background-checked maids, nannies, cooks, "
            . "and other domestic workers. "
            . "Our services include: maid matching with AI-powered scoring, "
            . "NIN verification for all staff, secure wallet and escrow payments, "
            . "a {$days}-day money-back guarantee, and ongoing support. "
            . "How can I help you today?";
    }
}