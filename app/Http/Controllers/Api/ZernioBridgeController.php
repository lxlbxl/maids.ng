<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ZernioBridgeController — receives Zernio social media webhooks and routes
 * them to Paperclip AI as Issues (one per conversation/post).
 *
 * Handles two event types from Zernio's actual webhook format:
 *   - "message.received" — Facebook/Instagram DMs
 *   - "comment.received"  — Post comments
 *
 * Flow:
 *   1. Zernio fires webhook → this endpoint
 *   2. Search Paperclip for an existing issue tied to this conversation/post
 *   3. Found  → add a comment (agent wakes with full context)
 *   4. Not found → create a new issue, assign to Onboarding-CS agent
 *   5. Return 200 immediately — Zernio needs fast ack
 */
class ZernioBridgeController extends ApiController
{
    /** Paperclip company UUID for Maids.ng */
    private const PAPERCLIP_COMPANY_ID = 'ada987c3-793e-4e0c-92fd-db3acc1a2f74';

    /** Paperclip agent UUID for Onboarding & Customer Success */
    private const ONBOARDING_CS_AGENT_ID = '369293e5-88da-4469-a44e-4397624aa3d5';

    /** Paperclip internal API base URL (authenticated mode — uses PAPERCLIP_API_TOKEN) */
    private const PAPERCLIP_API_URL = 'http://localhost:3100/api';

    /** Bearer token for Paperclip; resolved per-request so .env changes are picked up at runtime */
    private function paperclipToken(): string
    {
        return (string) (env('PAPERCLIP_API_TOKEN') ?: '');
    }

    /** Fluent HTTP client pre-configured with the Paperclip bearer token. */
    private function paperclipHttp(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->paperclipToken();
        $pending = \Illuminate\Support\Facades\Http::withHeaders($token !== ''
            ? ['Authorization' => 'Bearer ' . $token]
            : []);
        $pending = $pending->acceptJson();
        return $pending;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Zernio bridge webhook received', [
            'event' => $payload['event'] ?? 'unknown',
            'payload_keys' => array_keys($payload),
        ]);

        $event = $payload['event'] ?? null;

        // Actual Zernio webhook payload structure:
        // { event, message: {...}, conversation: {...}, account: {...} }
        // or
        // { event, data: {...} } (for comment.received)

        $message = $payload['message'] ?? [];
        $conversation = $payload['conversation'] ?? [];
        $account = $payload['account'] ?? [];
        $data = $payload['data'] ?? [];

        if ($event === 'message.received') {
            return $this->handleDM($message, $conversation, $account);
        }

        if ($event === 'comment.received') {
            return $this->handleComment($data, $account);
        }

        return $this->success(null, 'Event acknowledged but not processed (non-DM/comment event).');
    }

    /**
     * Handle an incoming DM (direct message) from Facebook or Instagram.
     *
     * Zernio webhook payload for DMs:
     *   message.id           → unique message ID
     *   message.conversationId → the conversation to reply to
     *   message.text         → the message body
     *   message.sender.name  → sender's display name
     *   message.sender.id    → sender's platform ID
     *   message.platform     → "facebook" or "instagram"
     *   account.id           → Zernio account ID
     *   account.platform     → platform
     *   account.displayName  → page name
     */
    private function handleDM(array $message, array $conversation, array $account): JsonResponse
    {
        $conversationId = $message['conversationId'] ?? $conversation['id'] ?? null;
        $messageId = $message['id'] ?? null;
        $text = $message['text'] ?? '';
        $sender = $message['sender'] ?? [];
        $senderName = $sender['name'] ?? $conversation['participantName'] ?? 'Unknown';
        $senderId = $sender['id'] ?? $conversation['participantId'] ?? null;
        $platform = $message['platform'] ?? $account['platform'] ?? 'facebook';
        $accountId = $account['id'] ?? $message['accountId'] ?? null;
        $displayName = $account['displayName'] ?? '';
        $attachments = $message['attachments'] ?? [];
        $deliveryId = $payload['id'] ?? $messageId ?? uniqid('zr_', true);

        if (!$conversationId) {
            Log::warning('Zernio bridge: missing conversationId for DM', [
                'message_keys' => array_keys($message),
                'conversation_keys' => array_keys($conversation),
            ]);
            return $this->error('Missing required field: conversationId.', Response::HTTP_BAD_REQUEST);
        }

        Log::info('Zernio bridge: processing DM', [
            'conversation_id' => $conversationId,
            'sender' => $senderName,
            'platform' => $platform,
            'text_len' => strlen($text),
        ]);

        try {
            $existingIssueId = $this->findExistingIssue($conversationId);

            if ($existingIssueId) {
                Log::info('Zernio bridge: adding DM to existing issue', [
                    'issue_id' => $existingIssueId,
                    'conversation_id' => $conversationId,
                ]);
                $this->reopenIssueIfDone($existingIssueId);
                $this->addDMComment($existingIssueId, $conversationId, $senderName, $senderId, $text, $platform, $accountId, $displayName, $messageId, $deliveryId, $attachments);
            } else {
                Log::info('Zernio bridge: creating new issue for DM', [
                    'conversation_id' => $conversationId,
                    'platform' => $platform,
                ]);
                $newIssueId = $this->createDMIssue($conversationId, $senderName, $platform, $accountId);
                $this->addDMComment($newIssueId, $conversationId, $senderName, $senderId, $text, $platform, $accountId, $displayName, $messageId, $deliveryId, $attachments);
            }
        } catch (\Throwable $e) {
            Log::error('Zernio bridge failed (DM)', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Bridge processing failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->success(['delivery_id' => $deliveryId], 'DM routed to Paperclip.');
    }

    /**
     * Handle an incoming comment on a social media post.
     */
    private function handleComment(array $data, array $account): JsonResponse
    {
        $postId = $data['postId'] ?? $data['post_id'] ?? null;
        $postUrn = $data['postUrn'] ?? $data['post_urn'] ?? null;
        $commentId = $data['commentId'] ?? $data['comment_id'] ?? null;
        $commentText = $data['text'] ?? $data['commentText'] ?? $data['comment_text'] ?? '';
        $commenter = $data['commenter'] ?? $data['author'] ?? [];
        $commenterName = $commenter['name'] ?? $data['commenterName'] ?? $data['authorName'] ?? 'Unknown';
        $commenterId = $commenter['id'] ?? $data['commenterId'] ?? null;
        $platform = $data['platform'] ?? $account['platform'] ?? 'facebook';
        $accountId = $account['id'] ?? $data['accountId'] ?? null;
        $deliveryId = $payload['id'] ?? uniqid('zr_', true);

        if (!$postId) {
            Log::warning('Zernio bridge: missing postId for comment', $data);
            return $this->error('Missing required field: postId.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $existingIssueId = $this->findExistingIssue($postId);

            if ($existingIssueId) {
                $this->reopenIssueIfDone($existingIssueId);
                $this->addCommentReply($existingIssueId, $postId, $commentId, $commenterName, $commenterId, $commentText, $platform, $accountId, $deliveryId);
            } else {
                $newIssueId = $this->createPostIssue($postId, $postUrn, $commenterName, $platform, $accountId);
                $this->addCommentReply($newIssueId, $postId, $commentId, $commenterName, $commenterId, $commentText, $platform, $accountId, $deliveryId);
            }
        } catch (\Throwable $e) {
            Log::error('Zernio bridge failed (comment)', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Bridge processing failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->success(['delivery_id' => $deliveryId], 'Comment routed to Paperclip.');
    }

    // ─── Paperclip API helpers ───────────────────────────────────────

    private function findExistingIssue(string $identifier): ?string
    {
        $response = $this->paperclipHttp()->get(self::PAPERCLIP_API_URL . '/companies/' . self::PAPERCLIP_COMPANY_ID . '/search', [
            'q' => $identifier,
            'limit' => 5,
        ]);

        if (!$response->successful()) {
            Log::warning('Zernio bridge: Paperclip search failed', [
                'status' => $response->status(),
            ]);
            return null;
        }

        foreach (($response->json()['results'] ?? []) as $result) {
            if (($result['type'] ?? '') !== 'issue') continue;
            $title = $result['issue']['title'] ?? $result['title'] ?? '';
            if (str_contains($title, $identifier)) {
                return $result['issue']['id'];
            }
        }
        return null;
    }

    private function createDMIssue(string $conversationId, string $senderName, string $platform, ?string $accountId): string
    {
        $emoji = $platform === 'instagram' ? 'IG' : 'FB';
        $title = "ZR: {$emoji} DM {$senderName} — {$conversationId}";

        $response = $this->paperclipHttp()->post(self::PAPERCLIP_API_URL . '/companies/' . self::PAPERCLIP_COMPANY_ID . '/issues', [
            'title' => $title,
            'description' => json_encode([
                'kind' => 'zernio_dm',
                'conversation_id' => $conversationId,
                'platform' => $platform,
                'account_id' => $accountId,
                'sender_name' => $senderName,
            ]),
            'status' => 'todo',
            'priority' => 'medium',
            'assigneeAgentId' => self::ONBOARDING_CS_AGENT_ID,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Paperclip create-issue failed: HTTP ' . $response->status());
        }

        $issue = $response->json();
        $issueId = $issue['id'] ?? null;
        if (!$issueId) {
            throw new \RuntimeException('Paperclip create-issue response missing id');
        }

        Log::info('Zernio bridge: created issue', [
            'issue_id' => $issueId,
            'identifier' => $issue['identifier'] ?? '?',
        ]);
        return $issueId;
    }

    private function createPostIssue(string $postId, ?string $postUrn, string $commenterName, string $platform, ?string $accountId): string
    {
        $emoji = $platform === 'instagram' ? 'IG' : 'FB';
        $title = "ZR: {$emoji} Post {$postId}";

        $response = $this->paperclipHttp()->post(self::PAPERCLIP_API_URL . '/companies/' . self::PAPERCLIP_COMPANY_ID . '/issues', [
            'title' => $title,
            'description' => json_encode([
                'kind' => 'zernio_comment',
                'post_id' => $postId,
                'post_urn' => $postUrn,
                'platform' => $platform,
                'account_id' => $accountId,
            ]),
            'status' => 'todo',
            'priority' => 'medium',
            'assigneeAgentId' => self::ONBOARDING_CS_AGENT_ID,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Paperclip create-issue failed: HTTP ' . $response->status());
        }

        $issue = $response->json();
        $issueId = $issue['id'] ?? null;
        if (!$issueId) {
            throw new \RuntimeException('Paperclip create-issue response missing id');
        }

        return $issueId;
    }

    private function reopenIssueIfDone(string $issueId): void
    {
        $this->paperclipHttp()->patch(self::PAPERCLIP_API_URL . '/issues/' . $issueId, ['status' => 'in_progress']);
    }

    /**
     * Add a DM comment to a Paperclip issue — formatted for the Onboarding agent.
     */
    private function addDMComment(
        string $issueId,
        string $conversationId,
        string $senderName,
        ?string $senderId,
        string $text,
        string $platform,
        ?string $accountId,
        string $displayName,
        ?string $messageId,
        string $deliveryId,
        array $attachments = []
    ): void {
        $icon = $platform === 'instagram' ? '📸' : '📘';
        $platformLabel = $platform === 'instagram' ? 'Instagram' : 'Facebook';

        $body = "---\n**{$icon} {$platformLabel} DM via Zernio**\n";
        $body .= "delivery_id: `{$deliveryId}`\n";
        $body .= "conversation_id: `{$conversationId}`\n";
        $body .= "sender: `{$senderName}`";
        if ($senderId) $body .= " ({$senderId})";
        $body .= "\n";
        if ($messageId) $body .= "message_id: `{$messageId}`\n";
        if ($displayName) $body .= "page: `{$displayName}`\n";
        $body .= "platform: `{$platform}`\n";
        if ($accountId) $body .= "account_id: `{$accountId}`\n";

        // Handle text content
        if ($text && !str_starts_with($text, '[')) {
            $body .= "\n> {$text}\n";
        }

        // Handle attachments (audio, images, video, documents)
        if (!empty($attachments)) {
            $body .= "\n**📎 Attachments:**\n";
            foreach ($attachments as $idx => $att) {
                $attType = $att['type'] ?? 'file';
                $attUrl = $att['url'] ?? $att['link'] ?? '';
                $attMime = $att['mimeType'] ?? $att['mime_type'] ?? '';
                $attName = $att['filename'] ?? $att['name'] ?? '';

                if ($attType === 'audio' || $attType === 'voice') {
                    $body .= "\n🎤 **Voice Note #" . ($idx + 1) . "**";
                    if ($attName) $body .= " — {$attName}";
                    $body .= "\nDownload: {$attUrl}\n";
                    $body .= "_Transcribe with: `hermes stt --file <downloaded_file>`. Download the file first, then transcribe before responding._\n";
                } elseif ($attType === 'image') {
                    $body .= "\n🖼️ **Image #" . ($idx + 1) . "**";
                    if ($attName) $body .= " — {$attName}";
                    $body .= "\nView: {$attUrl}\n";
                    $body .= "_Analyze with Hermes vision tool before responding._\n";
                } elseif ($attType === 'video') {
                    $body .= "\n🎬 **Video #" . ($idx + 1) . "**";
                    if ($attName) $body .= " — {$attName}";
                    $body .= "\nDownload: {$attUrl}\n";
                    $body .= "_Download and extract frames if needed for context._\n";
                } else {
                    $body .= "\n📎 **Attachment #" . ($idx + 1) . "**";
                    if ($attName) $body .= " — {$attName}";
                    if ($attUrl) $body .= "\nURL: {$attUrl}";
                    $body .= "\n";
                }
                if ($attMime) $body .= "mime_type: `{$attMime}`\n";
            }
            $body .= "\n";
        }

        $body .= "---\n";
        $body .= "**Reply:**\n```bash\nsource .zernio_env && zernio inbox:send {$conversationId}";
        if ($accountId) $body .= " --accountId {$accountId}";
        $body .= " --message \"Your reply\"\n```\n";

        $response = $this->paperclipHttp()->post(self::PAPERCLIP_API_URL . '/issues/' . $issueId . '/comments', ['body' => $body]);
        if (!$response->successful()) {
            throw new \RuntimeException('Paperclip add-comment failed: HTTP ' . $response->status());
        }
    }

    /**
     * Add a post comment to a Paperclip issue.
     */
    private function addCommentReply(
        string $issueId,
        string $postId,
        ?string $commentId,
        string $commenterName,
        ?string $commenterId,
        string $commentText,
        string $platform,
        ?string $accountId,
        string $deliveryId
    ): void {
        $icon = $platform === 'instagram' ? '📸' : '📘';
        $platformLabel = $platform === 'instagram' ? 'Instagram' : 'Facebook';

        $body = "---\n**{$icon} {$platformLabel} Post Comment via Zernio**\n";
        $body .= "delivery_id: `{$deliveryId}`\n";
        $body .= "post_id: `{$postId}`\n";
        if ($commentId) $body .= "comment_id: `{$commentId}`\n";
        $body .= "commenter: `{$commenterName}`";
        if ($commenterId) $body .= " ({$commenterId})";
        $body .= "\nplatform: `{$platform}`\n";
        if ($accountId) $body .= "account_id: `{$accountId}`\n";
        $body .= "\n> {$commentText}\n\n---\n";
        $body .= "**Reply:**\n```bash\nsource .zernio_env && zernio inbox:reply {$postId}";
        if ($accountId) $body .= " --accountId {$accountId}";
        if ($commentId) $body .= " --commentId {$commentId}";
        $body .= " --text \"Your reply\"\n```\n";

        $response = $this->paperclipHttp()->post(self::PAPERCLIP_API_URL . '/issues/' . $issueId . '/comments', ['body' => $body]);
        if (!$response->successful()) {
            throw new \RuntimeException('Paperclip add-comment failed: HTTP ' . $response->status());
        }
    }
}
