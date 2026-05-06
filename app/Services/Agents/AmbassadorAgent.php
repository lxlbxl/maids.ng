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
use App\Services\KnowledgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        DB::beginTransaction();
        try {
            // Step 1: Find or create the channel identity
            $identity = $this->resolveOrCreateIdentity($message);

            // Step 2: Find or create the conversation
            $conversation = $this->getOrCreateConversation($identity, $message);

            // Step 3: Store the user message
            AgentMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $message->content,
                'external_message_id' => $message->externalMessageId,
            ]);

            // Step 4: Build the system prompt from KnowledgeService
            $systemPrompt = $this->knowledge->buildContext('ambassador', $identity->getTier());

            // Step 5: Get conversation history
            $history = $conversation->getHistory(20);

            // Step 6: Build tool definitions for the LLM
            $toolDefinitions = $this->buildToolDefinitions();

            // Step 7: Call the LLM
            $response = $this->ai->chat([
                'model' => 'gpt-4o',
                'messages' => array_merge(
                    [['role' => 'system', 'content' => $systemPrompt]],
                    $history,
                    [['role' => 'user', 'content' => $message->content]],
                ),
                'tools' => $toolDefinitions,
                'tool_choice' => 'auto',
                'temperature' => 0.7,
            ]);

            // Handle AI Provider errors (e.g. missing API keys)
            if (isset($response['error'])) {
                DB::rollBack();
                return [
                    'content' => "AI Service Unavailable: " . $response['message'],
                    'conversation_id' => $conversation->id,
                ];
            }

            // Step 8: Handle tool calls if present
            $toolResults = [];
            if (!empty($response['tool_calls'])) {
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

                // Get final response after tool execution
                $response = $this->ai->chat([
                    'model' => 'gpt-4o',
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
                    'temperature' => 0.7,
                ]);
            }

            // Step 9: Store the assistant response
            $assistantContent = $response['content'] ?? '';
            AgentMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $assistantContent,
                'tokens_used' => $response['usage']['total_tokens'] ?? null,
            ]);

            // Step 10: Update conversation metadata
            $conversation->update([
                'last_message_at' => now(),
                'status' => $conversation->status === 'open' ? 'open' : $conversation->status,
            ]);

            // Step 11: Update identity last seen
            $identity->update(['last_seen_at' => now()]);

            DB::commit();

            return [
                'content' => $assistantContent,
                'conversation_id' => $conversation->id,
                'tool_calls' => $toolResults,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AmbassadorAgent error: ' . $e->getMessage(), [
                'channel' => $message->channel,
                'external_id' => $message->externalId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'content' => "I'm sorry, I encountered an error. Let me connect you with our team.",
                'conversation_id' => $conversation->id ?? 0,
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
     */
    private function getOrCreateConversation(
        AgentChannelIdentity $identity,
        InboundMessage $message,
    ): AgentConversation {
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