<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agents\Channels\EmailChannelHandler;
use App\Services\Agents\Channels\WhatsAppChannelHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * AgentChannelWebhookController — Receives inbound messages from all channels.
 *
 * Endpoints:
 * - POST /api/agent/webhook/email     (Mailgun, SendGrid, SES, etc.)
 * - POST /api/agent/webhook/whatsapp  (WhatsApp Business API)
 * - POST /api/agent/webhook/instagram (Instagram Graph API)
 * - POST /api/agent/webhook/facebook  (Facebook Messenger API)
 */
class AgentChannelWebhookController extends ApiController
{
    public function __construct(
        private readonly EmailChannelHandler $emailHandler,
        private readonly WhatsAppChannelHandler $whatsappHandler,
    ) {
    }

    /**
     * Handle inbound email webhook.
     *
     * Supports Mailgun, SendGrid, and generic email webhook formats.
     */
    public function emailWebhook(Request $request): JsonResponse
    {
        Log::info('Email webhook received', ['ip' => $request->ip()]);

        $payload = $this->normalizeEmailPayload($request);

        $result = $this->emailHandler->handleInbound($payload);

        return $result['success'] 
            ? $this->success($result, 'Email processed')
            : $this->error($result['message'] ?? 'Email processing failed', 500, $result);
    }

    /**
     * Handle inbound WhatsApp webhook.
     */
    public function whatsappWebhook(Request $request): JsonResponse
    {
        Log::info('WhatsApp webhook received', ['ip' => $request->ip()]);

        $payload = $this->normalizeWhatsAppPayload($request);

        $result = $this->whatsappHandler->handleInbound($payload);

        return $result['success'] 
            ? $this->success($result, 'WhatsApp message processed')
            : $this->error($result['message'] ?? 'WhatsApp processing failed', 500, $result);
    }

    /**
     * Handle Instagram webhook (placeholder).
     */
    public function instagramWebhook(Request $request): JsonResponse
    {
        // TODO: Implement Instagram handler
        return $this->error('Instagram channel not yet implemented.', Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Handle Facebook Messenger webhook (placeholder).
     */
    public function facebookWebhook(Request $request): JsonResponse
    {
        // TODO: Implement Facebook handler
        return $this->error('Facebook channel not yet implemented.', Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Handle Facebook webhook verification challenge.
     */
    public function facebookVerify(Request $request): mixed
    {
        $mode = $request->input('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');
 
        if ($mode === 'subscribe' && $token === config('services.facebook.webhook_verify_token')) {
            // Facebook verification challenge MUST return the raw challenge string as text/plain or json
            return response($challenge, 200);
        }
 
        return $this->forbidden('Verification failed.');
    }

    // ─── Payload Normalizers ──────────────────────────────────────────────────

    /**
     * Normalize various email webhook formats into a common structure.
     */
    private function normalizeEmailPayload(Request $request): array
    {
        $input = $request->all();

        // Mailgun format
        if (isset($input['message-headers'])) {
            return [
                'message_id' => $input['message-id'] ?? $input['Message-Id'] ?? null,
                'from' => $input['from'] ?? $input['From'] ?? null,
                'from_name' => $input['from-name'] ?? null,
                'to' => $input['to'] ?? $input['To'] ?? null,
                'subject' => $input['subject'] ?? $input['Subject'] ?? null,
                'body' => $input['body-plain'] ?? $input['body'] ?? null,
                'html_body' => $input['body-html'] ?? null,
                'in_reply_to' => $input['in-reply-to'] ?? null,
                'references' => $input['references'] ?? null,
            ];
        }

        // SendGrid format
        if (isset($input['email'])) {
            return [
                'message_id' => $input['message_id'] ?? null,
                'from' => $input['email'] ?? null,
                'from_name' => $input['name'] ?? null,
                'to' => $input['to'] ?? null,
                'subject' => $input['subject'] ?? null,
                'body' => $input['text'] ?? $input['body'] ?? null,
                'html_body' => $input['html'] ?? null,
                'in_reply_to' => $input['in_reply_to'] ?? null,
                'references' => $input['references'] ?? null,
            ];
        }

        // Generic / raw format
        return [
            'message_id' => $input['message_id'] ?? $input['Message-Id'] ?? uniqid('email_'),
            'from' => $input['from'] ?? $input['From'] ?? $input['sender'] ?? null,
            'from_name' => $input['from_name'] ?? $input['sender_name'] ?? null,
            'to' => $input['to'] ?? $input['To'] ?? null,
            'subject' => $input['subject'] ?? $input['Subject'] ?? null,
            'body' => $input['body'] ?? $input['text'] ?? $input['text_body'] ?? null,
            'html_body' => $input['html_body'] ?? $input['html'] ?? null,
            'in_reply_to' => $input['in_reply_to'] ?? $input['In-Reply-To'] ?? null,
            'references' => $input['references'] ?? $input['References'] ?? null,
        ];
    }

    /**
     * Normalize WhatsApp webhook payload.
     */
    private function normalizeWhatsAppPayload(Request $request): array
    {
        $input = $request->all();

        // WhatsApp Business API (Meta) format
        if (isset($input['entry'][0]['changes'][0]['value']['messages'])) {
            $msg = $input['entry'][0]['changes'][0]['value']['messages'][0];
            $from = $msg['from'] ?? null;
            $body = $msg['text']['body'] ?? $msg['button']['text'] ?? $msg['interactive']['list_reply']['title'] ?? null;

            return [
                'message_id' => $msg['id'] ?? null,
                'from' => $from,
                'body' => $body,
                'timestamp' => $msg['timestamp'] ?? null,
                'type' => $msg['type'] ?? 'text',
                'profile_name' => $input['entry'][0]['changes'][0]['value']['contacts'][0]['profile']['name'] ?? null,
            ];
        }

        // Generic format
        return [
            'message_id' => $input['message_id'] ?? $input['MessageId'] ?? uniqid('wa_'),
            'from' => $input['from'] ?? $input['From'] ?? $input['phone'] ?? null,
            'body' => $input['body'] ?? $input['text'] ?? $input['message'] ?? null,
            'timestamp' => $input['timestamp'] ?? null,
            'type' => $input['type'] ?? 'text',
            'profile_name' => $input['profile_name'] ?? null,
        ];
    }
}