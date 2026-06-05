<?php

namespace App\Services\Agents;

use App\Models\AgentChannelIdentity;
use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Models\AgentLead;
use Illuminate\Support\Facades\Log;

/**
 * ConversationManager — Manages conversation lifecycle.
 *
 * Handles finding/creating conversations, storing messages,
 * and retrieving conversation history for the Ambassador Agent.
 */
class ConversationManager
{
    /**
     * Find or create an open conversation for an identity.
     *
     * @param AgentChannelIdentity $identity
     * @param string $channel Channel name
     * @param array{ subject?: string, thread_id?: string } $metadata
     * @return AgentConversation
     */
    public function getOrCreateConversation(
        AgentChannelIdentity $identity,
        string $channel,
        array $metadata = [],
    ): AgentConversation {
        // Find existing open conversation
        $conversation = AgentConversation::where('channel_identity_id', $identity->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        if ($conversation) {
            return $conversation;
        }

        // Create new conversation
        $conversation = AgentConversation::create([
            'channel_identity_id' => $identity->id,
            'user_id' => $identity->user_id,
            'channel' => $channel,
            'status' => 'open',
            'email_subject' => $metadata['subject'] ?? null,
            'email_thread_id' => $metadata['thread_id'] ?? null,
            'last_message_at' => now(),
        ]);

        Log::info('New conversation created', [
            'conversation_id' => $conversation->id,
            'identity_id' => $identity->id,
            'channel' => $channel,
        ]);

        // Create a lead record if this is a new external channel contact
        if ($channel !== 'web' && !$identity->user_id) {
            $this->createLeadIfNeeded($identity, $channel);
        }

        return $conversation;
    }

    /**
     * Store a user message in the conversation.
     *
     * @param AgentConversation $conversation
     * @param string $content
     * @param string|null $externalMessageId
     * @return AgentMessage
     */
    public function storeUserMessage(
        AgentConversation $conversation,
        string $content,
        ?string $externalMessageId = null,
    ): AgentMessage {
        return AgentMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $content,
            'external_message_id' => $externalMessageId,
        ]);
    }

    /**
     * Store an assistant response in the conversation.
     *
     * @param AgentConversation $conversation
     * @param string $content
     * @param int|null $tokensUsed
     * @return AgentMessage
     */
    public function storeAssistantMessage(
        AgentConversation $conversation,
        string $content,
        ?int $tokensUsed = null,
    ): AgentMessage {
        return AgentMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $content,
            'tokens_used' => $tokensUsed,
        ]);
    }

    /**
     * Store a tool call and its result.
     *
     * @param AgentConversation $conversation
     * @param array $toolCall The tool call from the LLM
     * @param array $result The tool execution result
     * @return AgentMessage
     */
    public function storeToolMessage(
        AgentConversation $conversation,
        array $toolCall,
        array $result,
    ): AgentMessage {
        return AgentMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'tool',
            'content' => json_encode($result),
            'tool_call' => $toolCall,
        ]);
    }

    /**
     * Get conversation history for context building.
     *
     * @param AgentConversation $conversation
     * @param int $limit Number of messages to retrieve
     * @return array Array of message arrays in OpenAI format
     */
    public function getHistory(AgentConversation $conversation, int $limit = 20): array
    {
        $messages = AgentMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        return $messages->map(function ($message) {
            $role = match ($message->role) {
                'user' => 'user',
                'assistant' => 'assistant',
                'tool' => 'tool',
                default => 'user',
            };

            $result = ['role' => $role];

            if ($message->role === 'tool' && $message->tool_call) {
                $result['tool_call_id'] = $message->tool_call['id'] ?? null;
                $result['content'] = $message->content;
            } else {
                $result['content'] = $message->content;
            }

            return $result;
        })->toArray();
    }

    /**
     * Close a conversation.
     *
     * @param AgentConversation $conversation
     * @param string $reason Reason for closing
     * @return void
     */
    public function closeConversation(AgentConversation $conversation, string $reason = 'completed'): void
    {
        $conversation->update([
            'status' => 'closed',
            'closed_at' => now(),
            'close_reason' => $reason,
        ]);

        Log::info('Conversation closed', [
            'conversation_id' => $conversation->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Create a lead record for external channel contacts.
     *
     * @param AgentChannelIdentity $identity
     * @param string $channel
     * @return void
     */
    private function createLeadIfNeeded(AgentChannelIdentity $identity, string $channel): void
    {
        // Check if lead already exists
        $existing = AgentLead::where('channel_identity_id', $identity->id)->first();
        if ($existing) {
            return;
        }

        AgentLead::create([
            'channel_identity_id' => $identity->id,
            'phone' => $identity->phone,
            'email' => $identity->email,
            'channel' => $channel,
            'status' => 'new',
        ]);

        Log::info('New lead created', [
            'identity_id' => $identity->id,
            'channel' => $channel,
        ]);
    }

    /**
     * Update conversation metadata after a message is processed.
     *
     * @param AgentConversation $conversation
     * @return void
     */
    public function updateConversationActivity(AgentConversation $conversation): void
    {
        $conversation->update([
            'last_message_at' => now(),
        ]);
    }
}