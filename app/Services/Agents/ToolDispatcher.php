<?php

namespace App\Services\Agents;

use App\Services\Agents\Tools\AuthTools;
use App\Services\Agents\Tools\MatchingTools;
use App\Services\Agents\Tools\SupportTools;
use App\Services\Agents\Tools\UserTools;
use App\Services\Agents\Tools\ToolSchemas;
use Illuminate\Support\Facades\Log;

/**
 * ToolDispatcher — Routes LLM tool calls to the appropriate tool class.
 *
 * Centralizes tool execution so the AmbassadorAgent doesn't need to know
 * the implementation details of each tool.
 */
class ToolDispatcher
{
    public function __construct(
        private readonly UserTools $userTools,
        private readonly MatchingTools $matchingTools,
        private readonly SupportTools $supportTools,
        private readonly AuthTools $authTools,
    ) {
    }

    /**
     * Get all tool definitions in OpenAI function-calling format.
     */
    public function getToolDefinitions(): array
    {
        return ToolSchemas::all();
    }

    /**
     * Execute a tool call and return the result.
     *
     * @param array $toolCall OpenAI tool call object with 'function' key
     * @param \App\Models\AgentChannelIdentity $identity
     * @param \App\Services\Agents\DTOs\ChannelMessage $message
     * @return array{ tool_call_id: string, result: string }
     */
    public function dispatch(array $toolCall, $identity, $message): array
    {
        $functionName = $toolCall['function']['name'] ?? '';
        $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];

        try {
            $result = match ($functionName) {
                'resolve_identity' => $this->userTools->lookup($arguments),
                'send_otp' => $this->authTools($arguments, $identity, $message),
                'verify_otp' => $this->authTools->verify($arguments),
                'create_account' => $this->userTools($arguments, $identity, $message),
                'find_maid_matches' => $this->matchingTools($arguments, $identity, $message),
                'get_pricing' => $this->supportTools(['topic' => 'general'], $identity, $message),
                'check_assignment_status' => $this->matchingTools->checkStatus($arguments),
                'get_support_info' => $this->supportTools($arguments, $identity, $message),
                'escalate_to_human' => $this->supportTools->escalate($arguments, $identity, $message),
                default => ['error' => "Unknown tool: {$functionName}"],
            };

            return [
                'tool_call_id' => $toolCall['id'],
                'result' => is_string($result) ? $result : json_encode($result),
            ];
        } catch (\Throwable $e) {
            Log::error('ToolDispatcher error: ' . $e->getMessage(), [
                'tool' => $functionName,
                'arguments' => $arguments,
            ]);

            return [
                'tool_call_id' => $toolCall['id'],
                'result' => json_encode([
                    'error' => 'Tool execution failed. Please try again or contact support.',
                ]),
            ];
        }
    }

    /**
     * Execute a tool call and return structured result (for internal use).
     *
     * @param string $functionName
     * @param array $arguments
     * @param \App\Models\AgentChannelIdentity $identity
     * @param \App\Services\Agents\DTOs\ChannelMessage $message
     * @return array
     */
    public function dispatchStructured(
        string $functionName,
        array $arguments,
        $identity,
        $message,
    ): array {
        return match ($functionName) {
            'resolve_identity' => $this->userTools->lookup($arguments),
            'send_otp' => $this->authTools($arguments, $identity, $message),
            'verify_otp' => $this->authTools->verify($arguments),
            'create_account' => $this->userTools($arguments, $identity, $message),
            'find_maid_matches' => $this->matchingTools($arguments, $identity, $message),
            'get_pricing' => $this->supportTools(['topic' => 'general'], $identity, $message),
            'check_assignment_status' => $this->matchingTools->checkStatus($arguments),
            'get_support_info' => $this->supportTools($arguments, $identity, $message),
            'escalate_to_human' => $this->supportTools->escalate($arguments, $identity, $message),
            default => ['error' => "Unknown tool: {$functionName}"],
        };
    }
}