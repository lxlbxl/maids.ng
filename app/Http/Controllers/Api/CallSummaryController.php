<?php

namespace App\Http\Controllers\Api;

use App\Models\AgentApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CallSummaryController — receives Vapi post-call summaries and routes
 * them to the appropriate Paperclip issue (same conversation thread used
 * by WhatsApp, making it a true multi-channel conversation).
 *
 * Flow:
 *   1. Vapi agent finishes a call → calls this endpoint with transcript + summary
 *   2. Look up Paperclip for an existing issue keyed by the caller's phone number
 *   3. Found → add a comment with the call summary (agent gets FYI notification)
 *   4. Not found → create a new issue, add the call summary as the first entry
 *   5. Return the Paperclip issue ID for reference
 */
class CallSummaryController extends ApiController
{
    private const PAPERCLIP_COMPANY_ID = 'ada987c3-793e-4e0c-92fd-db3acc1a2f74';
    private const PAPERCLIP_API_URL = 'http://localhost:3100/api';

    public function __invoke(Request $request): JsonResponse
    {
        // Require agent API key auth
        $token = $request->bearerToken();
        if (!$token || !str_starts_with($token, 'mng_sk_')) {
            return $this->error('API key required.', Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = AgentApiKey::findByKey($token);
        if (!$apiKey || !$apiKey->isValid()) {
            return $this->error('Invalid API key.', Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'phone'              => 'required|string',
            'caller_name'        => 'nullable|string',
            'call_duration_sec'  => 'nullable|integer',
            'call_status'        => 'nullable|string',
            'transcript'         => 'nullable|string',
            'summary'            => 'required|string',
            'outcome'            => 'nullable|string',
            'agent_name'         => 'nullable|string',
            'vapi_call_id'       => 'nullable|string',
        ]);

        $phone = $validated['phone'];
        $callerName = $validated['caller_name'] ?? 'Unknown';
        $duration = $validated['call_duration_sec'] ?? 0;
        $status = $validated['call_status'] ?? 'completed';
        $transcript = $validated['transcript'] ?? '';
        $summary = $validated['summary'];
        $outcome = $validated['outcome'] ?? '';
        $agentName = $validated['agent_name'] ?? 'Jane';
        $vapiCallId = $validated['vapi_call_id'] ?? null;

        Log::info('Call summary received', [
            'phone' => $phone,
            'caller_name' => $callerName,
            'status' => $status,
            'vapi_call_id' => $vapiCallId,
        ]);

        try {
            // Look up existing Paperclip issue for this phone number
            $existingIssueId = $this->findIssueByPhone($phone);

            // Build the comment body
            $body = $this->buildCallSummaryComment(
                $phone, $callerName, $duration, $status,
                $transcript, $summary, $outcome, $agentName, $vapiCallId
            );

            if ($existingIssueId) {
                $this->addCommentToIssue($existingIssueId, $body);
                $issueId = $existingIssueId;
            } else {
                $issueId = $this->createIssueForCaller($phone, $callerName);
                $this->addCommentToIssue($issueId, $body);
            }

            Log::info('Call summary routed to Paperclip', [
                'phone' => $phone,
                'issue_id' => $issueId,
            ]);

            return $this->success([
                'issue_id'        => $issueId,
                'issue_created'   => !$existingIssueId,
                'phone'           => $phone,
            ], 'Call summary routed to Paperclip.');

        } catch (\Throwable $e) {
            Log::error('Call summary routing failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to route call summary: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Build a well-structured comment for the Paperclip issue.
     */
    private function buildCallSummaryComment(
        string $phone,
        string $callerName,
        int $duration,
        string $status,
        string $transcript,
        string $summary,
        string $outcome,
        string $agentName,
        ?string $vapiCallId
    ): string {
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;

        $body = "---\n**📞 Inbound Call — {$status}**\n";
        $body .= "channel: `phone`\n";
        $body .= "phone: `{$phone}`\n";
        $body .= "caller_name: `{$callerName}`\n";
        $body .= "agent: `{$agentName}`\n";
        if ($vapiCallId) {
            $body .= "vapi_call_id: `{$vapiCallId}`\n";
        }
        $body .= "duration: `{$minutes}m {$seconds}s`\n";
        $body .= "\n**Summary:**\n{$summary}\n";

        if ($outcome) {
            $body .= "\n**Outcome:** {$outcome}\n";
        }

        if ($transcript && strlen($transcript) > 10) {
            // Truncate long transcripts to keep Paperclip comments manageable
            $truncated = strlen($transcript) > 2000
                ? substr($transcript, 0, 1997) . '...'
                : $transcript;
            $body .= "\n**Transcript (excerpt):**\n> {$truncated}\n";
        }

        return $body;
    }

    /**
     * Search Paperclip for an existing issue tied to a phone number.
     */
    private function findIssueByPhone(string $phone): ?string
    {
        $searchQuery = preg_replace('/[^\d]/', '', $phone);

        $response = Http::get(self::PAPERCLIP_API_URL . '/companies/' . self::PAPERCLIP_COMPANY_ID . '/search', [
            'q' => $searchQuery,
            'limit' => 5,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $body = $response->json();

        foreach ($body['results'] ?? [] as $result) {
            if (($result['type'] ?? '') !== 'issue') continue;

            $issueTitle = $result['issue']['title'] ?? $result['title'] ?? '';
            // Match by phone number in title or by conversation format
            if (
                str_contains($issueTitle, $searchQuery) ||
                str_contains($issueTitle, 'WA: conversation') ||
                str_contains($issueTitle, 'WhatsApp:')
            ) {
                return $result['issue']['id'];
            }
        }

        return null;
    }

    /**
     * Create a new Paperclip issue for a phone caller.
     */
    private function createIssueForCaller(string $phone, string $callerName): string
    {
        $title = '📞 ' . ($callerName !== 'Unknown' ? "{$callerName} — " : '') . $phone;

        $response = Http::post(
            self::PAPERCLIP_API_URL . '/companies/' . self::PAPERCLIP_COMPANY_ID . '/issues',
            [
                'title' => $title,
                'description' => json_encode([
                    'kind' => 'phone_call',
                    'phone' => $phone,
                    'caller_name' => $callerName,
                ]),
                'status' => 'todo',
                'priority' => 'medium',
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Paperclip create-issue failed: HTTP ' . $response->status());
        }

        $issue = $response->json();

        Log::info('Call summary: created Paperclip issue', [
            'issue_id' => $issue['id'] ?? '?',
            'phone' => $phone,
        ]);

        return $issue['id'];
    }

    /**
     * Add a comment to a Paperclip issue.
     */
    private function addCommentToIssue(string $issueId, string $body): void
    {
        $response = Http::post(
            self::PAPERCLIP_API_URL . '/issues/' . $issueId . '/comments',
            ['body' => $body]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Paperclip add-comment failed: HTTP ' . $response->status());
        }
    }
}
