<?php

namespace App\Services;

use App\Models\AgentActivityLog;
use App\Agents\Concerns\LogsEvents;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

abstract class AgentService
{
    use LogsEvents;

    protected string $agentName;
    protected $aiService;
    protected KnowledgeService $knowledge;

    public function __construct()
    {
        $this->aiService = new \App\Services\Ai\AiService();
        // Resolve KnowledgeService from container (singleton)
        $this->knowledge = App::make(KnowledgeService::class);
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
     * Now also writes to agent_events so all agents automatically appear
     * in the Control Room live feed without per-agent code changes.
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

        $severity = match (true) {
            $requiresReview                => 'pending',
            $decision === 'auto_suspended' => 'error',
            $decision === 'warning'        => 'warning',
            str_contains($decision, 'rejected')  => 'error',
            str_contains($decision, 'approved')  => 'success',
            str_contains($decision, 'refund')    => 'warning',
            $confidenceScore >= 80         => 'success',
            $confidenceScore >= 50         => 'info',
            default                        => 'warning',
        };

        $summary = $reasoning ?? "{$this->getName()} — {$action}: {$decision}";
        if ($confidenceScore < 100) {
            $summary .= " ({$confidenceScore}% confidence)";
        }

        $this->logEvent(
            strtolower($this->getName()) . '.' . $action,
            $severity,
            $summary,
            [
                'action'           => $action,
                'decision'         => $decision,
                'confidence_score' => $confidenceScore,
                'reasoning'        => $reasoning,
                'subject_type'     => $subject ? get_class($subject) : null,
                'subject_id'       => $subject ? $subject->id : null,
            ],
            [
                'related_user_id' => $subject->user_id ?? $subject->employer_id ?? $subject->maid_id ?? null,
                'related_model'   => $subject ? class_basename($subject) : null,
                'related_id'      => $subject ? $subject->id : null,
                'requires_approval' => $requiresReview,
            ]
        );

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
