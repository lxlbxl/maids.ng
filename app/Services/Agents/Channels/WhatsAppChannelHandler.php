<?php

namespace App\Services\Agents\Channels;

use App\Services\Agents\AmbassadorAgent;
use App\Services\Agents\DTOs\ChannelMessage;
use App\Services\Agents\IdentityResolver;
use App\Services\Agents\ConversationManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WhatsAppChannelHandler — Processes inbound WhatsApp messages and sends responses.
 *
 * Handles:
 * - Inbound WhatsApp messages (text, buttons, list replies, interactive)
 * - Converting WhatsApp format to ChannelMessage DTO
 * - Sending WhatsApp responses via Meta Business API
 * - Phone number normalization and profile name tracking
 */
class WhatsAppChannelHandler
{
    private string $apiUrl;
    private string $phoneNumberId;
    private string $accessToken;

    public function __construct(
        private readonly AmbassadorAgent $ambassador,
        private readonly IdentityResolver $identityResolver,
        private readonly ConversationManager $conversationManager,
    ) {
        $this->apiUrl = 'https://graph.facebook.com/v18.0';
        $this->phoneNumberId = config('services.whatsapp.phone_number_id') ?? '';
        $this->accessToken = config('services.whatsapp.access_token') ?? '';

        if (empty($this->phoneNumberId)) {
            Log::warning('WhatsApp Channel: phone_number_id not configured in services.whatsapp');
        }
        if (empty($this->accessToken)) {
            Log::warning('WhatsApp Channel: access_token not configured in services.whatsapp');
        }
    }

    /**
     * Process an inbound WhatsApp webhook.
     *
     * @param array{
     *   message_id: string,
     *   from: string,
     *   body: string,
     *   timestamp?: int,
     *   type?: string,
     *   profile_name?: string,
     * } $payload
     * @return array{ success: bool, conversation_id?: int, response_sent?: bool, message: string }
     */
    public function handleInbound(array $payload): array
    {
        try {
            $content = $payload['body'] ?? '';

            if (empty($content)) {
                return [
                    'success' => false,
                    'message' => 'Empty WhatsApp message — ignored.',
                ];
            }

            // Normalize phone number
            $phone = $this->normalizePhoneNumber($payload['from'] ?? '');

            // Resolve identity from phone number
            $identity = $this->identityResolver->resolve('whatsapp', $phone, [
                'phone' => $phone,
                'name' => $payload['profile_name'] ?? null,
            ]);

            // Get or create conversation
            $conversation = $this->conversationManager->getOrCreateConversation($identity, 'whatsapp');

            // Store user message
            $this->conversationManager->storeUserMessage(
                $conversation,
                $content,
                $payload['message_id'] ?? null,
            );

            // Process through Ambassador Agent
            $inboundMessage = new \App\Services\Agents\DTOs\InboundMessage(
                channel: 'whatsapp',
                externalId: $phone,
                content: $content,
                phone: $phone,
                externalMessageId: $payload['message_id'] ?? null,
                metadata: [
                    'profile_name' => $payload['profile_name'] ?? null,
                    'identity_id' => $identity->id,
                    'user_id' => $identity->user_id,
                    'tier' => $identity->getTier(),
                ],
            );

            $response = $this->ambassador->handle($inboundMessage);

            // Store assistant response
            $this->conversationManager->storeAssistantMessage(
                $conversation,
                $response['content'] ?? '',
            );

            // Send WhatsApp response
            $responseSent = $this->sendWhatsAppResponse($phone, $response['content'] ?? '');

            Log::info('WhatsApp message processed by Ambassador Agent', [
                'conversation_id' => $conversation->id,
                'identity_id' => $identity->id,
                'response_sent' => $responseSent,
                'from' => $phone,
            ]);

            return [
                'success' => true,
                'conversation_id' => $conversation->id,
                'response_sent' => $responseSent,
                'message' => 'WhatsApp message processed and response sent.',
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsAppChannelHandler error: ' . $e->getMessage(), [
                'from' => $payload['from'] ?? 'unknown',
            ]);

            return [
                'success' => false,
                'message' => 'WhatsApp processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send a WhatsApp response message via Meta API.
     *
     * @param string $toPhone
     * @param string $message
     * @return bool
     */
    public function sendWhatsAppResponse(string $toPhone, string $message): bool
    {
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            Log::warning('WhatsApp API credentials not configured.');
            return false;
        }

        try {
            // WhatsApp has a 4096 character limit per message
            $chunks = $this->splitMessage($message, 4000);

            foreach ($chunks as $chunk) {
                Http::withHeaders([
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ])->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
                            'messaging_product' => 'whatsapp',
                            'to' => $toPhone,
                            'type' => 'text',
                            'text' => [
                                'body' => $chunk,
                            ],
                        ])->throw();
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsApp response send failed: ' . $e->getMessage(), [
                'to' => $toPhone,
            ]);

            return false;
        }
    }

    /**
     * Split a long message into WhatsApp-compatible chunks.
     *
     * @param string $message
     * @param int $maxLen
     * @return array<string>
     */
    private function splitMessage(string $message, int $maxLen): array
    {
        if (Str::length($message) <= $maxLen) {
            return [$message];
        }

        $chunks = [];
        $lines = explode("\n", $message);
        $current = '';

        foreach ($lines as $line) {
            if (Str::length($current . "\n" . $line) > $maxLen) {
                if ($current !== '') {
                    $chunks[] = trim($current);
                }
                $current = $line;
            } else {
                $current = $current === '' ? $line : $current . "\n" . $line;
            }
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * Normalize a phone number to E.164 format.
     *
     * @param string $phone
     * @return string
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digit characters
        $phone = preg_replace('/[^\d]/', '', $phone);

        // Add country code if missing (default to Nigeria +234)
        if (Str::startsWith($phone, '0')) {
            $phone = '234' . Str::substr($phone, 1);
        } elseif (!Str::startsWith($phone, '234') && Str::length($phone) === 10) {
            $phone = '234' . $phone;
        }

        return $phone;
    }
}