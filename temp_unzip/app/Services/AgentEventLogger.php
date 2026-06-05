<?php

namespace App\Services;

use App\Models\{AgentEvent, AgentOverride, HumanTask, Setting};
use Illuminate\Support\Facades\Log;

class AgentEventLogger
{
    private const TOKEN_COSTS = [
        'gpt-4o'              => ['input' => 5.00,  'output' => 15.00],
        'gpt-4o-mini'         => ['input' => 0.15,  'output' => 0.60],
        'gpt-4-turbo'         => ['input' => 10.00, 'output' => 30.00],
        'gpt-4.1'             => ['input' => 2.00,  'output' => 8.00],
        'gpt-4.1-mini'        => ['input' => 0.40,  'output' => 1.60],
        'gpt-4.1-nano'        => ['input' => 0.10,  'output' => 0.40],
        'o4-mini'             => ['input' => 1.10,  'output' => 4.40],
        'o3-mini'             => ['input' => 1.10,  'output' => 4.40],
        'o1-mini'             => ['input' => 1.10,  'output' => 4.40],
        'o1-preview'          => ['input' => 15.00, 'output' => 60.00],
        'claude-sonnet-4-20250514' => ['input' => 3.00, 'output' => 15.00],
        'claude-opus-4-20250514'   => ['input' => 15.00, 'output' => 75.00],
        'claude-haiku-4-5-20241022'=> ['input' => 0.80, 'output' => 4.00],
    ];

    private const OPENROUTER_PRICE_MULTIPLIER = 1.05;

    public function log(
        string $agentName,
        string $eventType,
        string $severity,
        string $summary,
        array  $detail = [],
        array  $options = []
    ): AgentEvent {
        $tokens = $options['tokens'] ?? null;
        $model  = $options['model'] ?? null;
        $provider = Setting::get('ai_active_provider', 'openai');

        $cost = $tokens ? $this->calculateCost($tokens, $model, $provider) : null;

        $event = AgentEvent::create([
            'agent_name'            => $agentName,
            'event_type'            => $eventType,
            'severity'              => $severity,
            'summary'               => $summary,
            'detail'                => $detail,
            'triggered_by_human'    => $options['triggered_by_human'] ?? false,
            'triggered_by_user_id'  => $options['triggered_by_user_id'] ?? null,
            'related_user_id'       => $options['related_user_id'] ?? null,
            'related_model'         => $options['related_model'] ?? null,
            'related_id'            => $options['related_id'] ?? null,
            'requires_approval'     => $options['requires_approval'] ?? false,
            'prompt_tokens'         => $tokens['prompt'] ?? null,
            'completion_tokens'     => $tokens['completion'] ?? null,
            'total_tokens'          => $tokens ? (($tokens['prompt'] ?? 0) + ($tokens['completion'] ?? 0)) : null,
            'estimated_cost_usd'    => $cost,
            'llm_model'             => $model,
            'duration_ms'           => $options['duration_ms'] ?? null,
            'channel'               => $options['channel'] ?? null,
        ]);

        if ($cost) {
            AgentOverride::where('agent_name', $agentName)
                ->increment('current_daily_spend_usd', $cost);
        }

        return $event;
    }

    public function logForApproval(
        string $agentName,
        string $eventType,
        string $summary,
        array  $detail,
        string $taskType,
        array  $taskPayload,
        array  $options = []
    ): array {
        $event = $this->log(
            $agentName, $eventType, 'pending', $summary, $detail,
            array_merge($options, ['requires_approval' => true])
        );

        $task = HumanTask::create([
            'agent_name'             => $agentName,
            'task_type'              => $taskType,
            'reason'                 => 'hitl_required',
            'task_payload'           => $taskPayload,
            'description'            => $summary,
            'priority'               => $options['priority'] ?? 2,
            'related_user_id'        => $options['related_user_id'] ?? null,
            'triggered_by_event_id'  => $event->id,
            'due_by'                 => $options['due_by'] ?? now()->addHours(4),
        ]);

        return compact('event', 'task');
    }

    public function logError(
        string $agentName,
        string $eventType,
        string $summary,
        \Throwable $exception,
        array $options = []
    ): AgentEvent {
        return $this->log(
            $agentName,
            $eventType,
            'error',
            $summary,
            [
                'exception'   => get_class($exception),
                'message'     => $exception->getMessage(),
                'file'        => $exception->getFile(),
                'line'        => $exception->getLine(),
                'trace'       => collect(explode("\n", $exception->getTraceAsString()))
                                    ->take(10)
                                    ->toArray(),
            ],
            $options
        );
    }

    private function calculateCost(array $tokens, ?string $model, string $provider): float
    {
        $pricing = self::TOKEN_COSTS[$model] ?? self::TOKEN_COSTS['gpt-4o'];

        if (isset($pricing['flat'])) {
            return $pricing['flat'];
        }

        $inputCost  = (($tokens['prompt'] ?? 0) / 1_000_000) * $pricing['input'];
        $outputCost = (($tokens['completion'] ?? 0) / 1_000_000) * $pricing['output'];
        $total = round($inputCost + $outputCost, 6);

        if ($provider === 'openrouter') {
            $total = round($total * self::OPENROUTER_PRICE_MULTIPLIER, 6);
        }

        return $total;
    }
}
