<?php

namespace App\Services\Agents\Channels;

use App\Models\AgentChannelIdentity;
use App\Models\AgentConversation;
use App\Models\AgentLead;
use App\Models\AgentMessage;
use App\Services\Agents\AmbassadorAgent;
use App\Services\Agents\DTOs\InboundMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * WebChatChannel Handler
 * 
 * Handles real-time chat messages from the web chat widget.
 * Uses Laravel Echo / Pusher for real-time communication.
 * 
 * Flow:
 * 1. User sends message via WebSocket to /chat/{conversationId}
 * 2. WebChatChannel receives the message
 * 3. Routes through AmbassadorAgent
 * 4. Response sent back via WebSocket
 * 
 * Usage:
 *   $channel = new WebChatChannel();
 *   $channel->handleMessage($userId, $messageContent, $conversationId);
 */
class WebChatChannel
{
    /**
     * Handle an inbound web chat message.
     *
     * @param int|null $userId Authenticated user ID (null for guests)
     * @param string $messageContent The user's message
     * @param string|null $conversationId Existing conversation ID (null for new)
     * @param array $metadata Additional metadata (page_url, referrer, etc.)
     * @return array Response with conversation_id, content, and message_id
     */
    public function handleMessage(
        ?int $userId,
        string $messageContent,
        ?string $conversationId = null,
        array $metadata = []
    ): array {
        Log::info('WebChat message received', [
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'content_length' => strlen($messageContent),
        ]);

        try {
            // Create inbound message DTO
            $message = new InboundMessage(
                channel: 'web_chat',
                externalId: uniqid('webchat_'),
                content: $messageContent,
                phone: null,
                email: $userId ? optional(\App\Models\User::find($userId))->email : null,
                subject: null,
                threadId: $conversationId,
            );

            // Process through AmbassadorAgent
            $ambassador = app(AmbassadorAgent::class);
            $response = $ambassador->handle($message);

            // Broadcast response via Laravel Echo / Pusher
            $this->broadcastResponse(
                $response['conversation_id'] ?? $conversationId,
                $response['content'] ?? '',
                $userId
            );

            return $response;
        } catch (\Throwable $e) {
            Log::error('WebChat message handling failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
            ]);

            return [
                'success' => false,
                'error' => 'Sorry, I encountered an error. Please try again.',
                'conversation_id' => $conversationId,
            ];
        }
    }

    /**
     * Handle a new conversation start from web chat.
     *
     * @param int|null $userId
     * @param string $initialMessage
     * @param array $metadata
     * @return array
     */
    public function startConversation(
        ?int $userId,
        string $initialMessage,
        array $metadata = []
    ): array {
        // Create or resolve the channel identity
        $identity = $this->resolveIdentity($userId);

        // Create a new conversation
        $conversation = AgentConversation::create([
            'identity_id' => $identity->id,
            'channel' => 'web_chat',
            'status' => 'active',
            'metadata' => $metadata,
        ]);

        // Handle the initial message
        return $this->handleMessage($userId, $initialMessage, $conversation->id, $metadata);
    }

    /**
     * Broadcast a response to the user via WebSocket.
     */
    private function broadcastResponse(
        ?string $conversationId,
        string $content,
        ?int $userId
    ): void {
        if (!$conversationId) {
            return;
        }

        // Broadcast via Laravel Echo / Pusher
        // In production, this would use:
        // broadcast(new AgentMessageBroadcast($conversationId, $content, $userId));

        // For now, log the broadcast
        Log::debug('Broadcasting agent response', [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'content_length' => strlen($content),
        ]);
    }

    /**
     * Resolve or create a channel identity for the user.
     */
    private function resolveIdentity(?int $userId): AgentChannelIdentity
    {
        if ($userId) {
            // Authenticated user - find or create identity
            return AgentChannelIdentity::firstOrCreate(
                [
                    'channel' => 'web_chat',
                    'external_id' => 'user_' . $userId,
                ],
                [
                    'user_id' => $userId,
                    'channel' => 'web_chat',
                    'external_id' => 'user_' . $userId,
                    'display_name' => optional(\App\Models\User::find($userId))->name,
                    'email' => optional(\App\Models\User::find($userId))->email,
                ]
            );
        }

        // Guest user - create anonymous identity
        $sessionId = session()->getId();
        return AgentChannelIdentity::firstOrCreate(
            [
                'channel' => 'web_chat',
                'external_id' => 'guest_' . $sessionId,
            ],
            [
                'channel' => 'web_chat',
                'external_id' => 'guest_' . $sessionId,
                'display_name' => 'Guest User',
            ]
        );
    }

    /**
     * Get active conversations for a user.
     */
    public function getActiveConversations(?int $userId): array
    {
        if (!$userId) {
            return [];
        }

        return AgentConversation::whereHas('identity', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->where('status', 'active')
            ->with([
                'identity',
                'messages' => function ($q) {
                    $q->latest()->limit(50);
                }
            ])
            ->get()
            ->map(function ($conv) {
                return [
                    'id' => $conv->id,
                    'channel' => $conv->channel,
                    'status' => $conv->status,
                    'created_at' => $conv->created_at,
                    'last_message' => $conv->messages->first(),
                ];
            })
            ->toArray();
    }

    /**
     * End a conversation.
     */
    public function endConversation(string $conversationId): bool
    {
        $conversation = AgentConversation::find($conversationId);

        if (!$conversation) {
            return false;
        }

        $conversation->update(['status' => 'ended']);

        Log::info('WebChat conversation ended', [
            'conversation_id' => $conversationId,
        ]);

        return true;
    }
}