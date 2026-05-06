<?php

namespace App\Services\Agents;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AgentService;

class ConciergeAgent extends AgentService
{
    public function getName(): string
    {
        return 'Concierge';
    }

    /**
     * Process a support query using LLM + Knowledge Base context.
     * Falls back to heuristic matching if AI is unavailable.
     */
    public function processQuery(User $user, string $queryText): SupportTicket
    {
        $action = "process_support_query";

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'query' => $queryText,
            'status' => 'open',
        ]);

        // Build system prompt from KnowledgeService
        $systemPrompt = $this->knowledge->buildContext('concierge', 'authenticated');

        $prompt = "User: {$user->name} ({$user->email})\nQuery: {$queryText}\n\nProvide a helpful, accurate response based on the Knowledge Base and Live Pricing context. If you cannot resolve it, say 'ESCALATE'.";

        $aiResponse = $this->think($prompt, [
            'system_prompt' => $systemPrompt,
        ]);

        if (isset($aiResponse['error']) || empty($aiResponse['content'])) {
            // AI unavailable — fall back to heuristics
            return $this->processQueryFallback($user, $queryText, $ticket);
        }

        $content = trim($aiResponse['content']);
        $confidence = $aiResponse['confidence'] ?? 50;

        // Check if AI wants to escalate
        if (str_starts_with($content, 'ESCALATE')) {
            $ticket->update([
                'agent_handled' => false,
                'status' => 'escalated',
            ]);

            $this->escalate(
                $action,
                "escalated_by_ai",
                "AI determined this query requires human review. Reason: " . substr($content, 8),
                $ticket,
                $confidence
            );

            return $ticket;
        }

        // AI resolved the query
        $ticket->update([
            'agent_handled' => true,
            'agent_resolution' => $content,
            'status' => 'resolved',
        ]);

        $this->logDecision(
            action: $action,
            decision: "auto_resolved_by_ai",
            confidenceScore: $confidence,
            reasoning: "AI Concierge resolved query using Knowledge Base context.",
            subject: $ticket
        );

        return $ticket;
    }

    /**
     * Fallback heuristic-based query processing when AI is unavailable.
     */
    private function processQueryFallback(User $user, string $queryText, SupportTicket $ticket): SupportTicket
    {
        $action = "process_support_query";

        $normalizedQuery = strtolower($queryText);
        $resolution = null;
        $confidence = 0;

        if (str_contains($normalizedQuery, 'refund')) {
            $resolution = "To request a refund, please go to your Payments page and click 'Request Refund'. Our system will automatically evaluate your eligibility based on our 10-day guarantee policy.";
            $confidence = 90;
        } elseif (str_contains($normalizedQuery, 'how do i get paid') || str_contains($normalizedQuery, 'payment schedule')) {
            $resolution = "Payouts are automatically processed by our Treasurer every Friday. Ensure your bank details in your Profile are up to date.";
            $confidence = 95;
        } elseif (str_contains($normalizedQuery, 'change password')) {
            $resolution = "You can change your password by going to Profile -> Security, or logging out and using the Forgot Password link.";
            $confidence = 99;
        } else {
            $confidence = 20;
        }

        if ($confidence >= 80) {
            $ticket->update([
                'agent_handled' => true,
                'agent_resolution' => $resolution,
                'status' => 'resolved',
            ]);

            $this->logDecision(
                action: $action,
                decision: "auto_resolved_fallback",
                confidenceScore: $confidence,
                reasoning: "Matched known intent (fallback).",
                subject: $ticket
            );
        } else {
            $ticket->update([
                'agent_handled' => false,
                'status' => 'escalated',
            ]);

            $this->escalate(
                $action,
                "escalated_fallback",
                "Could not confidently classify user query. Human review required.",
                $ticket,
                $confidence
            );
        }

        return $ticket;
    }
}