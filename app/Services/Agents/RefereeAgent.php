<?php

namespace App\Services\Agents;

use App\Models\Dispute;
use App\Models\Booking;
use App\Services\AgentService;

class RefereeAgent extends AgentService
{
    public function getName(): string
    {
        return 'Referee';
    }

    /**
     * Analyze a dispute and attempt auto-resolution.
     */
    public function assessDispute(Booking $booking, string $reason, string $evidence, $filedBy): Dispute
    {
        $action = "assess_dispute";

        $dispute = Dispute::create([
            'booking_id' => $booking->id,
            'filed_by' => $filedBy->id,
            'reason' => $reason,
            'evidence' => $evidence,
            'status' => 'pending'
        ]);

        $confidence = 0;
        $recommendation = "";

        // Heuristics
        $bookingDurationDays = now()->diffInDays($booking->start_date);

        if ($reason === 'no_show' && $booking->status === 'pending' && $bookingDurationDays < 3) {
            // Employer files a no-show dispute early on
            $recommendation = "Auto-Refund Employer 100%. Maid flagged for No-Show.";
            $confidence = 90;
        } elseif ($reason === 'unsatisfactory_work' && $bookingDurationDays > 10) {
            // Claiming unsatisfactory work after 10 days is beyond guarantee
            $recommendation = "Reject Refund. Beyond 10-day guarantee period.";
            $confidence = 85;
        } else {
            $recommendation = "Complex case. Requires human arbitration to review evidence.";
            $confidence = 30;
        }

        $dispute->update([
            'agent_recommendation' => $recommendation
        ]);

        // Build system prompt from KnowledgeService
        $systemPrompt = $this->knowledge->buildContext('referee', 'authenticated');

        // Construct the prompt for the AI Brain
        $prompt = "A booking dispute has occurred. Result: Maid cancelled.
                   Booking details:
                   - Agreed Salary: {$booking->agreed_salary}
                   - Status: {$booking->status}

                   Task: Determine if this should be flagged for manual review or auto-cleared.
                   Rule: If the maid cancels without a documented emergency, it's a strike.
                   Return your decision in JSON: { 'decision': 'flagged'|'cleared', 'confidence': 0-100, 'reasoning': 'string' }";

        $aiResponse = $this->think($prompt, [
            'system_prompt' => $systemPrompt,
        ]);

        if (isset($aiResponse['error'])) {
            // Fallback to manual if AI fails
            $this->escalate($action, "queued_for_review", "AI Intelligence Layer offline: " . $aiResponse['message'], $booking);
            return $dispute;
        }

        // Parse AI Decision (highly simplified)
        $result = json_decode($aiResponse['content'], true) ?? [
            'decision' => 'flagged',
            'confidence' => 100,
            'reasoning' => $aiResponse['content']
        ];

        if ($result['decision'] === 'flagged') {
            $this->escalate($action, "escalated_by_referee", $result['reasoning'] ?? $aiResponse['content'], $booking, $result['confidence'] ?? 50);
        } else {
            $this->logDecision($action, "dispute_cleared", $result['confidence'] ?? 100, $result['reasoning'] ?? $aiResponse['content'], $booking);
        }

        return $dispute;
    }
}
