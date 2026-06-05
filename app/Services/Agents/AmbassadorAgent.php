<?php

namespace App\Services\Agents;

use App\Models\AgentChannelIdentity;
use App\Models\AgentConversation;
use App\Models\AgentLead;
use App\Models\AgentMessage;
use App\Services\Ai\AiService;
use App\Services\Agents\DTOs\InboundMessage;
use App\Services\Agents\Tools\CreateAccountTool;
use App\Services\Agents\Tools\FindMaidMatchesTool;
use App\Services\Agents\Tools\GetPricingTool;
use App\Services\Agents\Tools\ResolveIdentityTool;
use App\Services\Agents\Tools\SendOtpTool;
use App\Agents\Concerns\LogsEvents;
use App\Services\KnowledgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

/**
 * Ambassador Agent — the front-facing SDR + support agent.
 *
 * Handles:
 * - Multi-channel inbound messages (web, email, WhatsApp, Instagram, Facebook)
 * - Identity resolution (guest → lead → authenticated)
 * - OTP-based authentication for external channels
 * - Tool-augmented responses (create account, find matches, get pricing)
 * - Conversation persistence and admin escalation
 *
 * Architecture:
 *   InboundMessage → ChannelIdentity → Conversation → LLM + Tools → Response
 */
class AmbassadorAgent
{
    use LogsEvents;

    protected string $agentName = 'ambassador';
    /** Available tools — each is a callable class with __invoke() */
    private const TOOLS = [
        'resolve_identity' => ResolveIdentityTool::class,
        'send_otp' => SendOtpTool::class,
        'create_account' => CreateAccountTool::class,
        'find_maid_matches' => FindMaidMatchesTool::class,
        'get_pricing' => GetPricingTool::class,
    ];

    public function __construct(
        private readonly AiService $ai,
        private readonly KnowledgeService $knowledge,
    ) {
    }

    /**
     * Main entry point. Process an inbound message and return the response.
     *
     * @return array{content: string, conversation_id: int, tool_calls?: array}
     */
    public function handle(InboundMessage $message): array
    {
        $stepLogs = [];
        $step = fn($n, $msg) => $stepLogs[] = "Step {$n}: {$msg}";

        DB::beginTransaction();
        try {
            $step(1, 'Begin transaction');

            // Step 1: Find or create the channel identity
            $step(2, 'Resolving identity...');
            $identity = $this->resolveOrCreateIdentity($message);
            $step(2, "Identity resolved: id={$identity->id}, tier={$identity->getTier()}");

            // Step 2: Find or create the conversation
            $step(3, 'Resolving conversation...');
            $conversation = $this->getOrCreateConversation($identity, $message);
            $step(3, "Conversation resolved: id={$conversation->id}");

            // Step 3: Build the system prompt from KnowledgeService
            $step(4, 'Building system prompt...');
            $systemPrompt = $this->knowledge->buildContext('ambassador', $identity->getTier());

            // Append memory/context-awareness instructions
            $systemPrompt .= "\n\n---\n## CONVERSATION MEMORY\n\n"
                . "You have access to the full conversation history above. "
                . "Always remember what the user has told you in this conversation — their name, what they need, "
                . "their budget, location, schedule preferences, and any other details they've shared. "
                . "Refer back to earlier messages naturally. For example: 'As you mentioned earlier, you need a cook in Lagos...'\n"
                . "Do NOT ask the user to repeat information they have already provided in this conversation. "
                . "Maintain context across the entire exchange — this is a continuous dialogue, not isolated questions.";

            $step(4, 'System prompt built (' . strlen($systemPrompt) . ' chars)');

            // Step 4: Get conversation history BEFORE storing the current message
            // This ensures $history contains only PRIOR turns, avoiding duplicate messages
            $step(5, 'Fetching conversation history...');
            $history = $conversation->getHistory(20);
            $step(5, 'History fetched: ' . count($history) . ' messages');

            // Step 5: NOW store the current user message (after history fetch)
            $step(6, 'Storing user message...');
            AgentMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $message->content,
                'external_message_id' => $message->externalMessageId,
            ]);
            $step(6, 'User message stored');

            // Step 6: Build tool definitions for the LLM
            $step(7, 'Building tool definitions...');
            $toolDefinitions = $this->buildToolDefinitions();
            $step(7, 'Tools built: ' . count($toolDefinitions) . ' available');

            // Step 7: Call the LLM
            $step(8, 'Calling AI provider...');
            $model = Setting::get('ai_active_provider') === 'openrouter'
                ? Setting::get('openrouter_model', 'google/gemini-flash-1.5')
                : Setting::get('openai_model', 'gpt-4o-mini');
            $step(8, "Model selected: {$model}");

            $aiPayload = [
                'model' => $model,
                'messages' => array_merge(
                    [['role' => 'system', 'content' => $systemPrompt]],
                    $history,
                    [['role' => 'user', 'content' => $message->content]],
                ),
                'tools' => $toolDefinitions,
                'tool_choice' => 'auto',
            ];

            // Reasoning models (o1, o3, o4, gpt-5) only support temperature=1
            $modelBase = str_contains($model, '/') ? explode('/', $model)[1] : $model;
            $isReasoning = str_starts_with($modelBase, 'o1')
                || str_starts_with($modelBase, 'o3')
                || str_starts_with($modelBase, 'o4')
                || str_starts_with($modelBase, 'gpt-5');
            if (!$isReasoning) {
                $aiPayload['temperature'] = 0.7;
            }
            $step(8, "Model base: {$modelBase}, reasoning: " . ($isReasoning ? 'yes' : 'no') . ", temp: " . ($isReasoning ? 'skipped' : '0.7'));

            $response = $this->ai->chat($aiPayload);
            $step(8, 'AI response received');

            // Handle AI Provider errors (e.g. missing API keys)
            if (isset($response['error'])) {
                $step(8, 'AI returned error: ' . ($response['message'] ?? 'unknown'));
                DB::rollBack();
                return [
                    'content' => "AI Service Unavailable: " . ($response['message'] ?? 'Unknown AI error'),
                    'conversation_id' => $conversation->id,
                    '_step_logs' => $stepLogs,
                    '_debug_payload_keys' => array_keys($aiPayload),
                    '_debug_model' => $model,
                    '_debug_model_base' => $modelBase,
                ];
            }

            // Step 8: Handle tool calls if present
            $toolResults = [];
            if (!empty($response['tool_calls'])) {
                $step(9, 'Processing ' . count($response['tool_calls']) . ' tool call(s)...');
                foreach ($response['tool_calls'] as $toolCall) {
                    $result = $this->executeTool($toolCall, $identity, $message);
                    $toolResults[] = $result;

                    // Store tool call and result
                    AgentMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'tool',
                        'content' => json_encode($result),
                        'tool_call' => $toolCall,
                    ]);
                }
                $step(9, 'Tool calls completed');

                // Get final response after tool execution
                $step(10, 'Calling AI for final response...');
                $finalPayload = [
                    'model' => $model,
                    'messages' => array_merge(
                        [['role' => 'system', 'content' => $systemPrompt]],
                        $history,
                        [['role' => 'user', 'content' => $message->content]],
                        $response['tool_calls'],
                        array_map(fn($r) => [
                            'role' => 'tool',
                            'tool_call_id' => $r['tool_call_id'],
                            'content' => $r['result'],
                        ], $toolResults),
                    ),
                ];
                if (
                    !str_starts_with($modelBase, 'o1')
                    && !str_starts_with($modelBase, 'o3')
                    && !str_starts_with($modelBase, 'o4')
                    && !str_starts_with($modelBase, 'gpt-5')
                ) {
                    $finalPayload['temperature'] = 0.7;
                }
                $response = $this->ai->chat($finalPayload);
                $step(10, 'Final response received');
            }

            // Step 9: Store the assistant response
            $assistantContent = $response['content'] ?? '';
            $step(11, 'Storing assistant response (' . strlen($assistantContent) . ' chars)...');
            AgentMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $assistantContent,
                'tokens_used' => $response['usage']['total_tokens'] ?? null,
            ]);
            $step(11, 'Assistant response stored');

            // Step 10: Update conversation metadata
            $conversation->update([
                'last_message_at' => now(),
                'status' => $conversation->status === 'open' ? 'open' : $conversation->status,
            ]);
            $step(12, 'Conversation metadata updated');

            // Step 11: Update identity last seen
            $identity->update(['last_seen_at' => now()]);
            $step(13, 'Identity last_seen updated');

            DB::commit();
            $step(14, 'Transaction committed');

            $this->logEvent(
                'message.sent',
                'info',
                "Replied on {$message->channel} to " . ($identity->display_name ?? $identity->external_id),
                [
                    'channel' => $message->channel,
                    'user_message' => substr($message->content, 0, 200),
                    'reply_preview' => substr($assistantContent, 0, 200),
                    'tools_called' => $toolResults ? array_column($toolResults, 'function_name') : [],
                    'conversation_id' => $conversation->id,
                    'identity_id' => $identity->id,
                    'tier' => $identity->getTier(),
                ],
                [
                    'related_user_id' => $identity->user_id,
                    'related_model' => 'AgentConversation',
                    'related_id' => $conversation->id,
                    'tokens' => $response['usage'] ?? null,
                    'model' => $model,
                    'channel' => $message->channel,
                ]
            );

            return [
                'content' => $assistantContent,
                'conversation_id' => $conversation->id,
                'tool_calls' => $toolResults,
                '_step_logs' => $stepLogs,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            $errorDetail = class_basename($e) . ': ' . $e->getMessage();
            $errorFile = $e->getFile() . ':' . $e->getLine();

            Log::error('AmbassadorAgent error: ' . $e->getMessage(), [
                'channel' => $message->channel,
                'external_id' => $message->externalId,
                'trace' => $e->getTraceAsString(),
                'step_logs' => $stepLogs,
            ]);

            // Always log to agent_events for visibility in Control Room
            try {
                $this->logEvent(
                    'message.error',
                    'error',
                    'AmbassadorAgent failed at step ' . count($stepLogs) . ': ' . $e->getMessage(),
                    [
                        'channel' => $message->channel,
                        'external_id' => $message->externalId,
                        'error_class' => get_class($e),
                        'error_file' => $errorFile,
                        'user_message' => substr($message->content, 0, 200),
                        'step_logs' => $stepLogs,
                    ],
                    [
                        'channel' => $message->channel,
                    ]
                );
            } catch (\Throwable $logEx) {
                // If logging itself fails, just continue
            }

            $debug = config('app.debug');

            return [
                'content' => $debug
                    ? "[Agent Error] " . $errorDetail
                    : "I'm sorry, I encountered an error. [Ref: " . substr(md5($e->getMessage()), 0, 8) . "]",
                'conversation_id' => $conversation->id ?? 0,
                '_error' => $errorDetail,
                '_file' => $errorFile,
                '_step_logs' => $stepLogs,
            ];
        }
    }

    /**
     * Find or create a channel identity from the inbound message.
     */
    private function resolveOrCreateIdentity(InboundMessage $message): AgentChannelIdentity
    {
        $identity = AgentChannelIdentity::where('channel', $message->channel)
            ->where('external_id', $message->externalId)
            ->first();

        if (!$identity) {
            // Try to resolve by phone or email across channels
            $resolved = null;
            if ($message->phone) {
                $resolved = AgentChannelIdentity::where('phone', $message->phone)
                    ->where('is_verified', true)
                    ->first();
            }
            if (!$resolved && $message->email) {
                $resolved = AgentChannelIdentity::where('email', $message->email)
                    ->where('is_verified', true)
                    ->first();
            }

            if ($resolved) {
                // Link this channel to the existing identity
                $identity = AgentChannelIdentity::create([
                    'channel' => $message->channel,
                    'external_id' => $message->externalId,
                    'user_id' => $resolved->user_id,
                    'phone' => $message->phone ?? $resolved->phone,
                    'email' => $message->email ?? $resolved->email,
                    'display_name' => $resolved->display_name,
                    'is_verified' => true,
                ]);
            } else {
                // Create a new guest/lead identity
                $identity = AgentChannelIdentity::create([
                    'channel' => $message->channel,
                    'external_id' => $message->externalId,
                    'phone' => $message->phone,
                    'email' => $message->email,
                    'is_verified' => false,
                ]);
            }
        }

        return $identity;
    }

    /**
     * Find or create a conversation for this identity.
     * Prioritizes the conversation_id from the frontend if provided.
     */
    private function getOrCreateConversation(
        AgentChannelIdentity $identity,
        InboundMessage $message,
    ): AgentConversation {
        // Priority 1: Resume by explicit conversation_id from the frontend
        if ($message->conversationId) {
            $conversation = AgentConversation::where('id', $message->conversationId)
                ->where('channel_identity_id', $identity->id)
                ->first();

            if ($conversation) {
                // Reopen if it was closed
                if ($conversation->status !== 'open') {
                    $conversation->update(['status' => 'open']);
                }
                return $conversation;
            }
        }

        // Priority 2: Find existing open conversation for this identity
        $conversation = AgentConversation::where('channel_identity_id', $identity->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        if (!$conversation) {
            $conversation = AgentConversation::create([
                'channel_identity_id' => $identity->id,
                'user_id' => $identity->user_id,
                'channel' => $message->channel,
                'status' => 'open',
                'email_subject' => $message->subject,
                'email_thread_id' => $message->threadId,
                'last_message_at' => now(),
            ]);

            // Create a lead record if this is a new external channel contact
            if ($message->channel !== 'web' && !$identity->user_id) {
                AgentLead::create([
                    'channel_identity_id' => $identity->id,
                    'phone' => $message->phone,
                    'email' => $message->email,
                    'status' => 'new',
                ]);
            }
        }

        return $conversation;
    }

    /**
     * Build tool definitions in OpenAI function-calling format.
     */
    private function buildToolDefinitions(): array
    {
        $definitions = [];

        foreach (self::TOOLS as $name => $class) {
            $tool = app($class);
            $definitions[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $tool->description(),
                    'parameters' => $tool->parameters(),
                ],
            ];
        }

        return $definitions;
    }

    /**
     * Execute a tool call and return the result.
     */
    private function executeTool(
        array $toolCall,
        AgentChannelIdentity $identity,
        InboundMessage $message,
    ): array {
        $functionName = $toolCall['function']['name'];
        $arguments = json_decode($toolCall['function']['arguments'], true);

        $class = self::TOOLS[$functionName] ?? null;
        if (!$class) {
            return [
                'tool_call_id' => $toolCall['id'],
                'result' => json_encode(['error' => "Unknown tool: {$functionName}"]),
            ];
        }

        $tool = app($class);
        $result = $tool($arguments, $identity, $message);

        return [
            'tool_call_id' => $toolCall['id'],
            'result' => is_string($result) ? $result : json_encode($result),
        ];
    }
}