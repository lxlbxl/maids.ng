<?php

namespace App\Services\Agents\Channels;

use App\Services\Agents\AmbassadorAgent;
use App\Services\Agents\DTOs\ChannelMessage;
use App\Services\Agents\IdentityResolver;
use App\Services\Agents\ConversationManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * EmailChannelHandler — Processes inbound emails and sends responses.
 *
 * Handles:
 * - Inbound email parsing (body, subject, attachments, thread tracking)
 * - Converting email format to ChannelMessage DTO
 * - Sending email responses via the platform's mail system
 * - Thread tracking via email_thread_id (Message-ID / In-Reply-To)
 */
class EmailChannelHandler
{
    public function __construct(
        private readonly AmbassadorAgent $ambassador,
        private readonly IdentityResolver $identityResolver,
        private readonly ConversationManager $conversationManager,
    ) {
    }

    /**
     * Process an inbound email webhook.
     *
     * @param array{
     *   message_id: string,
     *   from: string,
     *   from_name?: string,
     *   to: string,
     *   subject?: string,
     *   body?: string,
     *   html_body?: string,
     *   text_body?: string,
     *   in_reply_to?: string,
     *   references?: string,
     *   attachments?: array,
     *   timestamp?: int,
     * } $payload
     * @return array{ success: bool, conversation_id?: int, response_sent?: bool, message: string }
     */
    public function handleInbound(array $payload): array
    {
        try {
            // Parse the email content
            $content = $this->extractContent($payload);

            if (empty($content)) {
                return [
                    'success' => false,
                    'message' => 'Empty email body — ignored.',
                ];
            }

            // Convert to ChannelMessage DTO
            $message = ChannelMessage::fromEmail($payload);

            // Resolve identity from email address
            $externalId = $payload['message_id'] ?? Str::uuid()->toString();
            $identity = $this->identityResolver->resolve('email', $externalId, [
                'email' => $payload['from'] ?? null,
                'name' => $payload['from_name'] ?? null,
            ]);

            // Get or create conversation (thread-aware)
            $conversation = $this->conversationManager->getOrCreateConversation($identity, 'email', [
                'subject' => $payload['subject'] ?? null,
                'thread_id' => $payload['in_reply_to'] ?? $payload['message_id'] ?? null,
            ]);

            // Store user message
            $this->conversationManager->storeUserMessage(
                $conversation,
                $content,
                $payload['message_id'] ?? null,
            );

            // Process through Ambassador Agent
            $inboundMessage = new \App\Services\Agents\DTOs\InboundMessage(
                channel: 'email',
                externalId: $payload['from'] ?? '',
                content: $content,
                email: $payload['from'] ?? null,
                subject: $payload['subject'] ?? null,
                threadId: $payload['in_reply_to'] ?? null,
                externalMessageId: $payload['message_id'] ?? null,
                metadata: [
                    'from_name' => $payload['from_name'] ?? null,
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

            // Send email response
            $responseSent = $this->sendEmailResponse([
                'to' => $payload['from'] ?? null,
                'to_name' => $payload['from_name'] ?? null,
                'subject' => $this->buildReplySubject($payload['subject'] ?? ''),
                'body' => $response['content'] ?? '',
                'in_reply_to' => $payload['message_id'] ?? null,
                'references' => $payload['references'] ?? $payload['message_id'] ?? null,
            ]);

            Log::info('Email processed by Ambassador Agent', [
                'conversation_id' => $conversation->id,
                'identity_id' => $identity->id,
                'response_sent' => $responseSent,
                'from' => $payload['from'],
            ]);

            return [
                'success' => true,
                'conversation_id' => $conversation->id,
                'response_sent' => $responseSent,
                'message' => 'Email processed and response sent.',
            ];
        } catch (\Throwable $e) {
            Log::error('EmailChannelHandler error: ' . $e->getMessage(), [
                'payload' => Arr::only($payload, ['from', 'subject', 'message_id']),
            ]);

            return [
                'success' => false,
                'message' => 'Email processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Extract text content from email payload.
     *
     * @param array $payload
     * @return string
     */
    private function extractContent(array $payload): string
    {
        // Prefer text body for LLM processing
        if (!empty($payload['text_body'])) {
            return $this->cleanEmailContent($payload['text_body']);
        }

        if (!empty($payload['body'])) {
            return $this->cleanEmailContent($payload['body']);
        }

        if (!empty($payload['html_body'])) {
            return $this->cleanEmailContent(strip_tags($payload['html_body']));
        }

        return '';
    }

    /**
     * Clean email content — remove signatures, quoted replies, etc.
     *
     * @param string $content
     * @return string
     */
    private function cleanEmailContent(string $content): string
    {
        // Remove email signatures (common patterns)
        $content = preg_replace('/--\s*$/m', '', $content);
        $content = preg_replace('/Sent from my .*$/i', '', $content);
        $content = preg_replace('/Get Outlook for .*$/i', '', $content);

        // Remove quoted reply blocks
        $content = preg_replace('/^>.*$/m', '', $content);
        $content = preg_replace('/^On .* wrote:$/m', '', $content);

        // Trim and clean up
        $content = trim($content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return $content;
    }

    /**
     * Build a reply subject line.
     *
     * @param string $originalSubject
     * @return string
     */
    private function buildReplySubject(string $originalSubject): string
    {
        if (Str::startsWith(strtolower($originalSubject), 're:')) {
            return $originalSubject;
        }

        return 'Re: ' . $originalSubject;
    }

    /**
     * Send an email response.
     *
     * @param array{ to: string, to_name?: string, subject: string, body: string, in_reply_to?: string, references?: string } $emailData
     * @return bool
     */
    private function sendEmailResponse(array $emailData): bool
    {
        try {
            \Mail::send('emails.agent-response', [
                'body' => $emailData['body'],
            ], function ($message) use ($emailData) {
                $message->to($emailData['to'], $emailData['to_name'] ?? null)
                    ->subject($emailData['subject'])
                    ->replyTo(config('mail.from.address', 'support@maids.ng'));

                // Thread tracking headers
                if (!empty($emailData['in_reply_to'])) {
                    $message->getHeaders()->addTextHeader('In-Reply-To', $emailData['in_reply_to']);
                }
                if (!empty($emailData['references'])) {
                    $message->getHeaders()->addTextHeader('References', $emailData['references']);
                }
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Email response send failed: ' . $e->getMessage(), [
                'to' => $emailData['to'],
            ]);

            return false;
        }
    }
}