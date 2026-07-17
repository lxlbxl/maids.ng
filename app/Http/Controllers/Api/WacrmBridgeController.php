<?php

namespace App\Http\Controllers\Api;

use App\Models\AgentApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WacrmBridgeController — receives WACRM outbound webhooks and routes them
 * to Paperclip AI as Issues (one per WhatsApp conversation).
 *
 * Flow:
 *   1. WACRM fires "message.received" → this endpoint
 *   2. Search Paperclip for an existing open Issue tagged with the conversation_id
 *   3. Found  → add a comment to the existing issue (agent wakes with full context)
 *   4. Not found → create a new issue, assign to Onboarding-CS agent, add first comment
 *   5. Return 200 immediately — Meta/WACRM needs fast ack
 */
class WacrmBridgeController extends ApiController
{
    /** Paperclip company UUID for Maids.ng */
    private const PAPERCLIP_COMPANY_ID = 'ada987c3-793e-4e0c-92fd-db3acc1a2f74';

    /** Paperclip agent UUID for Onboarding & Customer Success */
    private const ONBOARDING_CS_AGENT_ID = '369293e5-88da-4469-a44e-4397624aa3d5';

    /** Paperclip internal API base URL (trusted mode, no auth on localhost) */
    private const PAPERCLIP_API_URL = 'http://localhost:3100/api';

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('WACRM bridge webhook received', [
            'conversation_id' => $payload['data']['conversation_id'] ?? 'unknown',
            'event' => $payload['event'] ?? 'unknown',
        ]);

        $event = $payload['event'] ?? null;
        if ($event !== 'message.received') {
            return $this->success(null, 'Event acknowledged but not processed (non-message event).');
        }

        $data = $payload['data'] ?? [];
        $conversationId = $data['conversation_id'] ?? null;
        $contactId = $data['contact_id'] ?? null;
        $whatsappMessageId = $data['whatsapp_message_id'] ?? null;
        $contentType = $data['content_type'] ?? 'text';
        $text = $data['text'] ?? '';
        $deliveryId = $payload['id'] ?? uniqid('bridge_', true);

        if (!$conversationId || !$contactId) {
            Log::warning('WACRM bridge: missing conversation_id or contact_id', $payload);
            return $this->error('Missing required fields: conversation_id, contact_id.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $existingIssueId = $this->findExistingIssue($conversationId);

            if ($existingIssueId) {
                Log::info('WACRM bridge: adding comment to existing issue', [
                    'issue_id' => $existingIssueId,
                    'conversation_id' => $conversationId,
                ]);
                $this->reopenIssueIfDone($existingIssueId, $conversationId);
                $this->addCommentToIssue($existingIssueId, $contactId, $conversationId, $whatsappMessageId, $contentType, $text, $deliveryId);
            } else {
                Log::info('WACRM bridge: creating new issue for conversation', [
                    'conversation_id' => $conversationId,
                ]);
                $newIssueId = $this->createIssueForConversation($conversationId);
                $this->addCommentToIssue($newIssueId, $contactId, $conversationId, $whatsappMessageId, $contentType, $text, $deliveryId);
            }
        } catch (\Throwable $e) {
            Log::error('WACRM bridge failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Bridge processing failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->success(['delivery_id' => $deliveryId], 'Message routed to Paperclip.');
    }

    /**
     * Search Paperclip for an issue tied to this WhatsApp conversation.
     *
     * Returns the issue ID regardless of status — even if `done`, because
     * a new message re-opens the conversation. The bridge will re-open
     * done issues to `in_progress` after adding the comment.
     */
    private function findExistingIssue(string $conversationId): ?string
    {
        $response = Http::get(self::PAPERCLIP_API_URL . '/companies/' . self::PAPERCLIP_COMPANY_ID . '/search', [
            'q' => $conversationId,
            'limit' => 5,
        ]);

        if (!$response->successful()) {
            Log::warning('WACRM bridge: Paperclip search failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $body = $response->json();

        foreach ($body['results'] ?? [] as $result) {
            if (($result['type'] ?? '') !== 'issue') continue;

            $issueTitle = $result['issue']['title'] ?? $result['title'] ?? '';
            if (str_contains($issueTitle, $conversationId)) {
                return $result['issue']['id'];
            }
        }

        return null;
    }

    /**
     * Create a new Paperclip issue for a WhatsApp conversation.
     */
    private function createIssueForConversation(string $conversationId): string
    {
        $response = Http::post(
            self::PAPERCLIP_API_URL . '/companies/' . self::PAPERCLIP_COMPANY_ID . '/issues',
            [
                'title' => 'WA: conversation ' . $conversationId,
                'description' => json_encode([
                    'kind' => 'whatsapp_conversation',
                    'conversation_id' => $conversationId,
                ]),
                'status' => 'todo',
                'priority' => 'medium',
                'assigneeAgentId' => self::ONBOARDING_CS_AGENT_ID,
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Paperclip create-issue failed: HTTP ' . $response->status() . ' — ' . $response->body()
            );
        }

        $issue = $response->json();
        $issueId = $issue['id'] ?? null;

        if (!$issueId) {
            throw new \RuntimeException('Paperclip create-issue response missing id: ' . $response->body());
        }

        Log::info('WACRM bridge: created Paperclip issue', [
            'issue_id' => $issueId,
            'issue_identifier' => $issue['identifier'] ?? '?',
            'conversation_id' => $conversationId,
        ]);

        return $issueId;
    }

    /**
     * If the existing issue is marked as done, re-open it to in_progress
     * so the agent wakes and processes the new incoming message.
     */
    private function reopenIssueIfDone(string $issueId, string $conversationId): void
    {
        $response = Http::patch(
            self::PAPERCLIP_API_URL . '/issues/' . $issueId,
            ['status' => 'in_progress']
        );

        if ($response->successful()) {
            Log::info('WACRM bridge: re-opened issue for new message', [
                'issue_id' => $issueId,
                'conversation_id' => $conversationId,
            ]);
        } else {
            Log::warning('WACRM bridge: re-open issue failed (may already be open)', [
                'issue_id' => $issueId,
                'status' => $response->status(),
            ]);
        }
    }

    /**
     * Add a comment to a Paperclip issue representing the inbound WhatsApp message.
     *
     * The comment carries the full WACRM payload context so the Paperclip agent
     * has everything it needs (contact_id, conversation_id, message content, etc.)
     * to look up the user, reason, and reply.
     */
    private function addCommentToIssue(
        string $issueId,
        string $contactId,
        string $conversationId,
        ?string $whatsappMessageId,
        string $contentType,
        string $text,
        string $deliveryId
    ): void {
        // Build a clean, structured comment body that the agent can parse
        $body = sprintf(
            "---\n" .
            "**WhatsApp incoming message**\n" .
            "delivery_id: `%s`\n" .
            "conversation_id: `%s`\n" .
            "contact_id: `%s`\n" .
            "whatsapp_message_id: `%s`\n" .
            "content_type: `%s`\n\n" .
            "> %s\n",
            $deliveryId,
            $conversationId,
            $contactId,
            $whatsappMessageId ?? 'N/A',
            $contentType,
            $text
        );

        $response = Http::post(
            self::PAPERCLIP_API_URL . '/issues/' . $issueId . '/comments',
            ['body' => $body]
        );

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Paperclip add-comment failed: HTTP ' . $response->status() . ' — ' . $response->body()
            );
        }

        Log::info('WACRM bridge: comment added', [
            'issue_id' => $issueId,
            'conversation_id' => $conversationId,
        ]);
    }
}
