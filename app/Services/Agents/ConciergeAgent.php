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
     * Process a support query and attempt to auto-resolve it.
     */
    public function processQuery(User $user, string $queryText): SupportTicket
    {
        $action = "process_support_query";
        
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'query' => $queryText,
            'status' => 'open',
        ]);

        $normalizedQuery = strtolower($queryText);
        $resolution = null;
        $confidence = 0;

        // Basic intent mapping (In production, replace with NLP/LLM hook)
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
            // Unrecognized intent
            $confidence = 20;
        }

        if ($confidence >= 80) {
            $ticket->update([
                'agent_handled' => true,
                'agent_resolution' => $resolution,
                'status' => 'resolved'
            ]);

            $this->logDecision(
                action: $action,
                decision: "auto_resolved",
                confidenceScore: $confidence,
                reasoning: "Matched known intent.",
                subject: $ticket
            );
        } else {
            $ticket->update([
                'agent_handled' => false,
                'status' => 'escalated'
            ]);

            $this->escalate(
                $action,
                "escalated",
                "Could not confidently classify user query. Human review required.",
                $ticket,
                $confidence
            );
        }

        return $ticket;
    }
}
