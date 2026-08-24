<?php

namespace App\Http\Controllers\Api;

use App\Models\AgentApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * InCallRequestController — receives actionable requests from Vapi
 * voice agents during a call and routes them to Paperclip for the AI
 * agent to execute (send links, account details, follow-ups, etc.)
 *
 * Voice agents should NEVER send links, numbers, or sensitive info
 * verbally — users mishear them. Instead, push the request here and
 * the Paperclip agent sends it via WhatsApp/text.
 */
class InCallRequestController extends ApiController
{
    private const PAPERCLIP_COMPANY_ID = 'ada987c3-793e-4e0c-92fd-db3acc1a2f74';
    private const PAPERCLIP_API_URL = 'http://localhost:3100/api';

    /** Allowed request types — anything else is rejected */
    private const ALLOWED_TYPES = [
        'send_link',           // Send a URL (maids.ng search, payment page, onboarding)
        'send_details',        // Send structured info (account number, reference, address)
        'send_follow_up',      // Send a follow-up message via WhatsApp
        'escalate_to_human',   // Create a support ticket / escalate
        'log_note',            // Just log a note, no user action needed
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (!$token || !str_starts_with($token, 'mng_sk_')) {
            return $this->error('API key required.', Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = AgentApiKey::findByKey($token);
        if (!$apiKey || !$apiKey->isValid()) {
            return $this->error('Invalid API key.', Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'phone'          => 'required|string',
            'caller_name'    => 'nullable|string',
            'request_type'   => 'required|string|in:' . implode(',', self::ALLOWED_TYPES),
            'description'    => 'required|string|max:2000',
            'payload'        => 'nullable|array',
            'agent_name'     => 'nullable|string',
            'vapi_call_id'   => 'nullable|string',
            'urgency'        => 'nullable|in:now,soon,whenever',
        ]);

        $phone = $validated['phone'];
        $callerName = $validated['caller_name'] ?? 'Unknown';
        $requestType = $validated['request_type'];
        $description = $validated['description'];
        $payload = $validated['payload'] ?? [];
        $agentName = $validated['agent_name'] ?? 'Jane';
        $vapiCallId = $validated['vapi_call_id'] ?? null;
        $urgency = $validated['urgency'] ?? 'soon';

        Log::info('In-call request received', [
            'phone' => $phone,
            'type' => $requestType,
            'urgency' => $urgency,
        ]);

        try {
            $issueId = $this->findOrCreateIssue($phone, $callerName);
            $body = $this->buildActionComment(
                $phone, $callerName, $requestType, $description,
                $payload, $agentName, $vapiCallId, $urgency
            );
            $this->addCommentToIssue($issueId, $body);

            Log::info('In-call request routed to Paperclip', [
                'phone' => $phone,
                'issue_id' => $issueId,
                'type' => $requestType,
            ]);

            return $this->success([
                'issue_id' => $issueId,
                'phone' => $phone,
                'request_type' => $requestType,
                'urgency' => $urgency,
            ], 'In-call request routed to Paperclip agent for action.');

        } catch (\Throwable $e) {
            Log::error('In-call request routing failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to route request: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Build a clear, actionable comment for the Paperclip agent.
     */
    private function buildActionComment(
        string $phone, string $callerName, string $requestType,
        string $description, array $payload, string $agentName,
        ?string $vapiCallId, string $urgency
    ): string {
        $icons = [
            'send_link' => '🔗',
            'send_details' => '📋',
            'send_follow_up' => '💬',
            'escalate_to_human' => '🚨',
            'log_note' => '📝',
        ];
        $icon = $icons[$requestType] ?? '📞';

        $urgencyLabels = ['now' => 'ASAP', 'soon' => 'Soon', 'whenever' => 'Low priority'];
        $urgencyLabel = $urgencyLabels[$urgency] ?? $urgency;

        $body = "---\n";
        $body .= "{$icon} **In-Call Action Request** — {$urgencyLabel}\n";
        $body .= "channel: `phone`\n";
        $body .= "request_type: `{$requestType}`\n";
        $body .= "phone: `{$phone}`\n";
        $body .= "caller_name: `{$callerName}`\n";
        $body .= "agent: `{$agentName}`\n";
        if ($vapiCallId) {
            $body .= "vapi_call_id: `{$vapiCallId}`\n";
        }
        $body .= "\n**What to do:**\n{$description}\n";

        if (!empty($payload)) {
            $body .= "\n**Payload (use this data):**\n";
            foreach ($payload as $key => $value) {
                if (is_string($value)) {
                    $body .= "- `{$key}`: {$value}\n";
                }
            }
        }

        $body .= "\n**Context:** This was a phone call handled by Vapi. The caller is on the phone now (or just hung up). ";
        $body .= "Send the requested information via WhatsApp to {$phone}. ";
        $body .= "Do NOT reply in-thread unless you have questions for the Vapi agent.";

        return $body;
    }

    private function findOrCreateIssue(string $phone, string $callerName): string
    {
        $searchQuery = preg_replace('/[^\d]/', '', $phone);

        $response = Http::get(self::PAPERCLIP_API_URL . '/companies/' . self::PAPERCLIP_COMPANY_ID . '/search', [
            'q' => $searchQuery,
            'limit' => 5,
        ]);

        if ($response->successful()) {
            foreach ($response->json()['results'] ?? [] as $result) {
                if (($result['type'] ?? '') !== 'issue') continue;
                $title = $result['issue']['title'] ?? $result['title'] ?? '';
                if (str_contains($title, $searchQuery) || str_contains($title, 'WA: ') || str_contains($title, '📞')) {
                    return $result['issue']['id'];
                }
            }
        }

        // Create new issue
        $title = '📞 ' . ($callerName !== 'Unknown' ? "{$callerName} — " : '') . $phone;
        $createResp = Http::post(
            self::PAPERCLIP_API_URL . '/companies/' . self::PAPERCLIP_COMPANY_ID . '/issues',
            [
                'title' => $title,
                'description' => json_encode(['kind' => 'phone_call', 'phone' => $phone, 'caller_name' => $callerName]),
                'status' => 'todo',
                'priority' => 'medium',
            ]
        );

        if (!$createResp->successful()) {
            throw new \RuntimeException('Paperclip create-issue failed');
        }

        return $createResp->json()['id'];
    }

    private function addCommentToIssue(string $issueId, string $body): void
    {
        $resp = Http::post(self::PAPERCLIP_API_URL . '/issues/' . $issueId . '/comments', ['body' => $body]);
        if (!$resp->successful()) {
            throw new \RuntimeException('Paperclip add-comment failed: HTTP ' . $resp->status());
        }
    }
}
