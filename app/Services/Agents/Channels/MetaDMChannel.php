<?php

namespace App\Services\Agents\Channels;

use App\Models\AgentChannelIdentity;
use App\Models\AgentConversation;
use App\Models\AgentLead;
use App\Models\AgentMessage;
use App\Services\Agents\AmbassadorAgent;
use App\Services\Agents\DTOs\InboundMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MetaDMChannel Handler
 * 
 * Handles inbound messages from Meta (Facebook/Instagram) Direct Messages.
 * Integrates with Meta's Messenger Platform API for sending responses.
 * 
 * Flow:
 * 1. User sends DM on Facebook/Instagram
 * 2. Meta webhook POSTs to /webhooks/meta-dm
 * 3. MetaDMChannel processes the webhook payload
 * 4. Routes through AmbassadorAgent
 * 5. Response sent back via Meta Messenger API
 * 
 * Webhook Setup:
 *   - Facebook App → Messenger → Settings → Webhooks
 *   - Callback URL: https://maids.ng/webhooks/meta-dm
 *   - Verify Token: configured in services.php
 *   - Subscribe to: messages, messaging_postbacks
 * 
 * Usage:
 *   $channel = new MetaDMChannel();
 *   $channel->handleWebhook($payload);
 */
class MetaDMChannel
{
    /**
     * Meta API base URL.
     */
    private const META_API_URL = 'https://graph.facebook.com/v18.0/me';

    /**
     * Handle a Meta webhook payload.
     *
     * @param array $payload The webhook payload from Meta
     * @return array Processing results
     */
    public function handleWebhook(array $payload): array
    {
        $results = [
            'processed' => 0,
            'errors' => [],
        ];

        try {
            // Meta sends data in entries array
            $entries = $payload['entry'] ?? [];

            foreach ($entries as $entry) {
                $messagingEvents = $entry['messaging'] ?? [];

                foreach ($messagingEvents as $event) {
                    try {
                        $result = $this->processMessagingEvent($event);
                        if ($result['success']) {
                            $results['processed']++;
                        } else {
                            $results['errors'][] = $result['error'] ?? 'Unknown error';
                        }
                    } catch (\Throwable $e) {
                        Log::error('Meta DM event processing failed: ' . $e->getMessage(), [
                            'event' => $event,
                        ]);
                        $results['errors'][] = $e->getMessage();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Meta webhook handling failed: ' . $e->getMessage(), [
                'payload_keys' => array_keys($payload),
            ]);
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Verify a webhook subscription challenge.
     * Called when Meta verifies the webhook URL.
     *
     * @param array $query The query parameters from Meta
     * @return string The challenge response
     */
    public function verifyWebhook(array $query): string
    {
        $verifyToken = $this->getMetaConfig('webhook_verify_token');
        $mode = $query['hub_mode'] ?? '';
        $token = $query['hub_verify_token'] ?? '';
        $challenge = $query['hub_challenge'] ?? '';

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('Meta webhook verified successfully');
            return $challenge;
        }

        Log::warning('Meta webhook verification failed', [
            'mode' => $mode,
            'token_match' => $token === $verifyToken,
        ]);

        return '';
    }

    /**
     * Process a single messaging event from Meta.
     */
    private function processMessagingEvent(array $event): array
    {
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? time();

        if (!$senderId) {
            return ['success' => false, 'error' => 'No sender ID'];
        }

        // Handle message
        if (isset($event['message'])) {
            $messageData = $event['message'];
            $messageText = $messageData['text'] ?? '';
            $messageId = $messageData['mid'] ?? uniqid('meta_');

            if (empty($messageText)) {
                // Handle attachments (images, etc.)
                return $this->handleAttachment($event);
            }

            // Create inbound message DTO
            $message = new InboundMessage(
                channel: 'meta_dm',
                externalId: $messageId,
                content: $messageText,
                phone: null,
                email: null,
                subject: null,
                threadId: $senderId,
            );

            // Process through AmbassadorAgent
            $ambassador = app(AmbassadorAgent::class);
            $response = $ambassador->handle($message);

            // Send response back via Meta API
            if (!empty($response['content'])) {
                $this->sendMetaMessage($senderId, $response['content']);
            }

            return [
                'success' => true,
                'conversation_id' => $response['conversation_id'] ?? null,
            ];
        }

        // Handle postback (quick reply buttons)
        if (isset($event['postback'])) {
            $postbackData = $event['postback'];
            $payload = $postbackData['payload'] ?? '';
            $title = $postbackData['title'] ?? '';

            // Treat postback as a message
            $message = new InboundMessage(
                channel: 'meta_dm',
                externalId: uniqid('meta_postback_'),
                content: $payload,
                phone: null,
                email: null,
                subject: "Postback: {$title}",
                threadId: $senderId,
            );

            $ambassador = app(AmbassadorAgent::class);
            $response = $ambassador->handle($message);

            if (!empty($response['content'])) {
                $this->sendMetaMessage($senderId, $response['content']);
            }

            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Unknown event type'];
    }

    /**
     * Handle attachment messages (images, files).
     */
    private function handleAttachment(array $event): array
    {
        $senderId = $event['sender']['id'] ?? null;
        $attachments = $event['message']['attachments'] ?? [];

        $description = 'User sent ' . count($attachments) . ' attachment(s)';

        $message = new InboundMessage(
            channel: 'meta_dm',
            externalId: $event['message']['mid'] ?? uniqid('meta_attach_'),
            content: $description,
            phone: null,
            email: null,
            subject: 'Attachment received',
            threadId: $senderId,
        );

        $ambassador = app(AmbassadorAgent::class);
        $response = $ambassador->handle($message);

        if (!empty($response['content'])) {
            $this->sendMetaMessage($senderId, $response['content']);
        }

        return ['success' => true];
    }

    /**
     * Send a message back to the user via Meta Messenger API.
     */
    private function sendMetaMessage(string $recipientId, string $text): bool
    {
        $accessToken = $this->getMetaConfig('page_access_token');

        if (!$accessToken) {
            Log::error('Meta access token not configured');
            return false;
        }

        try {
            $response = Http::post(self::META_API_URL . '/messages', [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $text],
                'messaging_type' => 'RESPONSE',
                'tag' => 'ACCOUNT_UPDATE',
            ], [
                'Authorization' => 'Bearer ' . $accessToken,
            ]);

            if ($response->successful()) {
                Log::info('Meta message sent', ['recipient' => $recipientId]);
                return true;
            }

            Log::error('Meta API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Failed to send Meta message: ' . $e->getMessage(), [
                'recipient' => $recipientId,
            ]);
            return false;
        }
    }

    /**
     * Get user profile from Meta.
     */
    public function getMetaUserProfile(string $psid): ?array
    {
        $accessToken = $this->getMetaConfig('page_access_token');

        if (!$accessToken) {
            return null;
        }

        try {
            $response = Http::get(
                "https://graph.facebook.com/v18.0/{$psid}",
                ['fields' => 'first_name,last_name,profile_pic,locale,timezone',],
                ['Authorization' => 'Bearer ' . $accessToken]
            );

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error('Failed to fetch Meta user profile: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Set up Meta Messenger greeting text and get started button.
     */
    public function setupMessengerProfile(): array
    {
        $accessToken = $this->getMetaConfig('page_access_token');

        if (!$accessToken) {
            return ['success' => false, 'error' => 'Meta access token not configured'];
        }

        $profileData = [
            'greeting' => [
                [
                    'locale' => 'default',
                    'text' => "👋 Welcome to Maids.ng! I'm your AI assistant. How can I help you find the perfect domestic staff?",
                ],
            ],
            'get_started' => [
                'payload' => 'WELCOME_MESSAGE',
            ],
            'persistent_menu' => [
                [
                    'locale' => 'default',
                    'composer_input_disabled' => false,
                    'call_to_actions' => [
                        [
                            'title' => '🔍 Find a Maid',
                            'payload' => 'FIND_MAID',
                        ],
                        [
                            'title' => '📋 My Bookings',
                            'payload' => 'MY_BOOKINGS',
                        ],
                        [
                            'title' => '💰 Pricing',
                            'payload' => 'PRICING_INFO',
                        ],
                        [
                            'title' => '🆘 Support',
                            'payload' => 'SUPPORT',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::post(
                self::META_API_URL . '/messenger_profile',
                $profileData,
                ['Authorization' => 'Bearer ' . $accessToken]
            );

            return [
                'success' => $response->successful(),
                'response' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get a Meta configuration value from the database settings table.
     * Falls back to config/services.php for backward compatibility.
     */
    private function getMetaConfig(string $key, mixed $default = null): mixed
    {
        // Try database setting first (admin-configurable via /admin/settings)
        $dbValue = \App\Models\Setting::get("meta_{$key}");
        if ($dbValue !== null && $dbValue !== '') {
            return $dbValue;
        }

        // Fallback to config/services.php
        return config("services.meta.{$key}", $default);
    }
}
