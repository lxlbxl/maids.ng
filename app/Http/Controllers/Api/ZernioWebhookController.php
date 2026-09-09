<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Services\Agents\AmbassadorAgent;
use App\Services\Agents\Channels\MetaDMChannel;
use App\Services\Agents\ConversationManager;
use App\Services\Agents\DTOs\InboundMessage;
use App\Services\Agents\IdentityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Zernio bridge API base URL (local multi-key aggregation bridge) */
const ZERNIO_BRIDGE_URL = 'http://127.0.0.1:3200';
const ZERNIO_API_BASE = 'https://zernio.com/api/v1';

/**
 * Zernio Webhook Controller
 *
 * Receives inbound social-media events (comments & DMs) from Zernio's inbox
 * aggregation platform and routes them through AmbassadorAgent for AI processing.
 *
 * Zernio event types:
 *   - comment.created  → public comments on Instagram/Facebook posts
 *   - dm.received      → private direct messages on Instagram/Telegram
 *
 * Platform mapping:
 *   instagram comment / dm → InboundMessage::fromInstagram()
 *   facebook comment       → InboundMessage::fromFacebook()
 *
 * Paperclip is NOT used — all routing goes directly to AmbassadorAgent.
 */
class ZernioWebhookController extends ApiController
{
    public function __construct(
        private readonly IdentityResolver   $identityResolver,
        private readonly ConversationManager $conversationManager,
        private readonly AmbassadorAgent     $ambassador,
        private readonly MetaDMChannel       $metaDMChannel,
    ) {}

    /**
     * Handle incoming Zernio webhook POST.
     *
     * Route: POST /api/zernio-bridge
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Validate required top-level fields
        // Validate required top-level fields
        if (empty($payload['event'])) {
            Log::warning('Zernio webhook received with missing event', $payload);
            return $this->error('Missing event or platform', 400);
        }

        $event = $payload['event'];

        // Zernio nests platform inside comment object for comments
        $platform = $payload['platform']
            ?? ($payload['comment']['platform'] ?? null);

        if (empty($platform)) {
            Log::warning('Zernio webhook received with missing platform', $payload);
            return $this->error('Missing event or platform', 400);
        }

        try {
            // Zernio fires "comment.received" for comments and "message.received" for DMs
            // Normalise to our internal event names
            $normalisedEvent = match ($event) {
                'message.received'  => 'dm.received',
                'comment.received'  => 'comment.created',  // Zernio sends comment.received
                'comment.created'   => 'comment.created',
                default             => $event,
            };

            $result = match ($normalisedEvent) {
                'comment.created' => $this->handleComment($payload, $platform),
                'dm.received'     => $this->handleDM($payload, $platform),
                default           => $this->handleUnsupportedEvent($event),
            };

            return $this->success($result, 'Webhook processed');
        } catch (\Throwable $e) {
            Log::error('Zernio webhook processing failed: ' . $e->getMessage(), [
                'event'    => $event,
                'platform' => $platform,
                'trace'    => $e->getTraceAsString(),
            ]);

            // Always return 200 to Zernio to prevent webhook retries for internal errors
            return $this->success(['processed' => false, 'error' => $e->getMessage()], 'Error handled');
        }
    }

    // ─── Comment handlers ─────────────────────────────────────────────────────

    private function handleComment(array $payload, string $platform): array
    {
        // Only Instagram and Facebook comments are supported via MetaDMChannel
        if (!in_array($platform, ['instagram', 'facebook'], true)) {
            Log::info('Zernio: skipping comment on unsupported platform', ['platform' => $platform]);
            return ['processed' => false, 'reason' => 'unsupported_platform'];
        }

        // Zernio nests comment data under 'comment' key
        $comment = $payload['comment'] ?? [];
        $author  = $comment['author']  ?? [];
        // Comment text is in comment.text
        $commentText = $comment['text'] ?? '';

        // Real post ID: use platformPostId (the Instagram/Facebook post ID)
        // comment.postId may be null; fall back to comment.platformPostId or post.platformPostId
        $postId = $comment['postId']
            ?? ($comment['platformPostId'] ?? null)
            ?? ($payload['post']['platformPostId'] ?? null);

        $authorId = $author['id'] ?? null;

        if (!$authorId) {
            throw new \RuntimeException('comment payload missing author.id');
        }

        $senderPayload = [
            'sender_id' => $authorId,
            'message'   => $commentText,
            'mid'       => $comment['id'] ?? null,
        ];

        $inbound = $platform === 'instagram'
            ? InboundMessage::fromInstagram($senderPayload)
            : InboundMessage::fromFacebook($senderPayload);

        // Reconstruct with Zernio-specific metadata
        $inbound = new InboundMessage(
            channel:             $inbound->channel,
            externalId:          $inbound->externalId,
            content:             $inbound->content,
            phone:               $inbound->phone,
            email:               $inbound->email,
            subject:             $inbound->subject,
            threadId:            $inbound->threadId,
            externalMessageId:    $inbound->externalMessageId,
            conversationId:      $inbound->conversationId,
            metadata: [
                'zernio_post_id'      => $postId,
                'zernio_comment_id'   => $comment['id'] ?? null,
                'zernio_author_name'  => $author['username'] ?? null,
                'zernio_author_username' => $author['username'] ?? null,
                'zernio_created_at'   => $comment['createdAt'] ?? null,
                'zernio_event'        => 'comment.created',
                'zernio_account_id'   => ($payload['account'] ?? [])['id'] ?? null,
            ],
        );

        return $this->routeToAmbassador($inbound, $platform, $author);
    }

    // ─── DM handlers ───────────────────────────────────────────────────────────

    private function handleDM(array $payload, string $platform): array
    {
        // Only Instagram DMs are supported via MetaDMChannel; Telegram excluded
        if (!in_array($platform, ['instagram'], true)) {
            Log::info('Zernio: skipping DM on unsupported platform', ['platform' => $platform]);
            return ['processed' => false, 'reason' => 'unsupported_platform'];
        }

        // Zernio sends sender inside the message object
        $message = $payload['message'] ?? [];
        $sender  = $message['sender']  ?? [];
        $senderId = $sender['id'] ?? null;

        if (!$senderId) {
            throw new \RuntimeException('dm.received payload missing sender.id');
        }

        $senderPayload = [
            'sender_id' => $senderId,
            'message'   => $message['text'] ?? '',
            'mid'       => $message['id']   ?? null,
        ];

        $inbound = InboundMessage::fromInstagram($senderPayload);

        $inbound = new InboundMessage(
            channel:             $inbound->channel,
            externalId:          $inbound->externalId,
            content:             $inbound->content,
            phone:               $inbound->phone,
            email:               $inbound->email,
            subject:             $inbound->subject,
            threadId:            $inbound->threadId,
            externalMessageId:    $inbound->externalMessageId,
            conversationId:      $inbound->conversationId,
            metadata: [
                'zernio_conversation_id' => $message['conversationId'] ?? null,
                'zernio_message_id'     => $message['id'] ?? null,
                'zernio_sender_name'    => $sender['name'] ?? null,
                'zernio_sender_username'=> $sender['username'] ?? null,
                'zernio_created_at'     => $message['createdAt'] ?? null,
                'zernio_event'          => 'dm.received',
            ],
        );

        return $this->routeToAmbassador($inbound, $platform, $sender);
    }

    // ─── Core routing ─────────────────────────────────────────────────────────

    private function routeToAmbassador(InboundMessage $inbound, string $platform, array $contact): array
    {
        // Resolve identity
        $identity = $this->identityResolver->resolve(
            $inbound->channel,
            $inbound->externalId,
            [
                'name'  => $contact['name'] ?? null,
                'phone' => null,
                'email' => null,
            ]
        );

        // Get or create conversation
        $conversation = $this->conversationManager->getOrCreateConversation($identity, $inbound->channel);

        // Store user message
        $this->conversationManager->storeUserMessage($conversation, $inbound->content);

        // Route to AmbassadorAgent
        $response = $this->ambassador->handle($inbound);

        // Store assistant reply
        $this->conversationManager->storeAssistantMessage(
            $conversation,
            $response['content'] ?? '',
        );

        $this->conversationManager->updateConversationActivity($conversation);

        // Send reply back via Zernio API
        $sent = false;
        if (!empty($response['content'])) {
            $sent = $this->sendZernioReply(
                $inbound->channel,
                $inbound->metadata['zernio_conversation_id'] ?? null,
                $inbound->metadata['zernio_post_id'] ?? null,
                $inbound->metadata['zernio_comment_id'] ?? null,
                $inbound->metadata['zernio_account_id'] ?? null,
                $response['content'],
            );
        }

        return [
            'processed'     => true,
            'identity_id'   => $identity->id,
            'conversation_id' => $conversation->id,
            'reply_sent'   => $sent,
            'response'     => $response['content'] ?? '',
        ];
    }

    // ─── Zernio Reply Sender ────────────────────────────────────────────────

    /**
     * Send a reply via Zernio's inbox API through the local bridge.
     *
     * @param  string      $channel     'instagram' | 'facebook'
     * @param  string|null $conversationId  For DMs
     * @param  string|null $postId         For post comments
     * @param  string|null $commentId
     * @param  string|null $accountId
     * @param  string      $text
     * @return bool
     */
    private function sendZernioReply(
        string $channel,
        ?string $conversationId,
        ?string $postId,
        ?string $commentId,
        ?string $accountId,
        string $text,
    ): bool {
        // Use the Zernio API key directly — bypass the bridge for sending
        $apiKey = config('services.zernio.api_key')
            ?? env('ZERNIO_API_KEY')
            ?? '';

        if ($apiKey === '') {
            Log::warning('Zernio reply: no API key configured');
            return false;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ];

        try {
            if ($conversationId !== null) {
                // DM: POST /v1/inbox/conversations/{conversationId}/messages
                // Body: { "accountId": "...", "message": "text" }
                $resp = Http::withHeaders($headers)
                    ->timeout(10)
                    ->post(ZERNIO_API_BASE . '/inbox/conversations/' . urlencode($conversationId) . '/messages', [
                        'accountId' => $accountId ?? '',
                        'message'  => $text,
                    ]);
            } elseif ($postId !== null) {
                // Comment private-reply: POST /v1/inbox/comments/{postId}/{commentId}/private-reply
                // Body: { "text": "..." }
                $path = '/inbox/comments/' . urlencode($postId) . '/' . urlencode($commentId ?? '') . '/private-reply';
                $resp = Http::withHeaders($headers)
                    ->timeout(10)
                    ->post(ZERNIO_API_BASE . $path, [
                        'text' => $text,
                    ]);
            } else {
                Log::warning('Zernio reply: no conversationId or postId provided');
                return false;
            }

            if ($resp->successful()) {
                Log::info('Zernio reply sent', [
                    'conversation_id' => $conversationId,
                    'post_id' => $postId,
                    'text_len' => strlen($text),
                ]);
                return true;
            }

            Log::error('Zernio reply failed', [
                'status' => $resp->status(),
                'body'   => $resp->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Zernio reply exception: ' . $e->getMessage());
            return false;
        }
    }

    // ─── Unsupported Event ─────────────────────────────────────────────────

    private function handleUnsupportedEvent(string $event): array
    {
        Log::info('Zernio: unsupported event type', ['event' => $event]);
        return ['processed' => false, 'reason' => 'unsupported_event'];
    }
}
