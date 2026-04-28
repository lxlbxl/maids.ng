<?php

namespace App\Services;

use App\Models\AgentActivityLog;
use Illuminate\Support\Facades\Log;

abstract class AgentService
{
    protected $aiService;

    public function __construct()
    {
        $this->aiService = new \App\Services\Ai\AiService();
    }

    /**
     * Helper for agents to perform LLM reasoning.
     */
    protected function think(string $prompt, array $options = []): array
    {
        return $this->aiService->chat($prompt, $options);
    }

    /**
     * Get the name of this agent.
     */
    abstract public function getName(): string;

    /**
     * Log a decision made by the agent.
     */
    protected function logDecision(
        string $action,
        string $decision,
        int $confidenceScore = 100,
        ?string $reasoning = null,
        $subject = null,
        bool $requiresReview = false
    ): AgentActivityLog {
        
        $log = AgentActivityLog::create([
            'agent_name' => static::getName(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->id : null,
            'decision' => $decision,
            'confidence_score' => $confidenceScore,
            'reasoning' => $reasoning,
            'requires_review' => $requiresReview,
        ]);

        if ($requiresReview) {
            Log::info("Agent [{$this->getName()}] ESCALATED action [{$action}]. Reason: {$reasoning}");
        }

        return $log;
    }

    /**
     * Fast-track method to flag a decision for human review.
     */
    protected function escalate(
        string $action,
        string $decision,
        string $reasoning,
        $subject = null,
        int $confidenceScore = 50
    ): AgentActivityLog {
        return $this->logDecision(
            action: $action,
            decision: $decision,
            confidenceScore: $confidenceScore,
            reasoning: "ESCALATION: " . $reasoning,
            subject: $subject,
            requiresReview: true
        );
    }
}
