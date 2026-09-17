<?php

namespace App\Http\Controllers\Api;

use App\Models\AgentApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutboundCallController extends ApiController
{
    private const VAPI_API_KEY = '82e5e922-a162-4d08-a744-212ba81b58a7';
    private const OUTBOUND_ASSISTANT_ID = '60138e5b-bd99-476d-97b6-ca9fcef4b7a6';
    private const OUTBOUND_PHONE_ID = 'a005b4a7-3242-48a8-870b-9029429c5f9e';
    private const PAPERCLIP_API_URL = 'http://localhost:3100/api';
    private const PAPERCLIP_COMPANY_ID = 'ada987c3-793e-4e0c-92fd-db3acc1a2f74';

    /**
     * One line describing who we are ringing, for the assistant to open with.
     *
     * Returns a plain sentence rather than a blob: it is read by a model that
     * then speaks, so anything structured tends to leak into the call as field
     * names said out loud.
     */
    private function callerContext(string $phone): string
    {
        $tail = substr(preg_replace('/\D+/', '', $phone), -10);
        if (strlen($tail) < 10) {
            return 'We have no record for this number. Treat them as new.';
        }

        $user = \App\Models\User::where('phone', 'like', "%{$tail}")->first();
        if (!$user) {
            return 'This number is not in our records. Treat them as a new contact.';
        }

        $bits = ["{$user->name} is an existing {$user->role}."];

        $req = \App\Models\HireRequest::where('employer_id', $user->id)
            ->whereIn('status', \App\Models\HireRequest::OPEN_STATUSES)
            ->latest()->first();

        if ($req) {
            $bits[] = $req->isPaid()
                ? 'They have paid their matching fee.'
                : 'Their matching fee is not paid yet.';
            if ($req->area) {
                $bits[] = "They are in {$req->area}.";
            }
        }

        $bits[] = 'Greet them by name and do not ask for details we already hold.';

        return implode(' ', $bits);
    }

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
            'customer_phone'     => 'required|string',
            'customer_name'      => 'required|string|max:255',
            'call_purpose'       => 'required|string|max:500',
            'expected_outcome'   => 'required|string|max:500',
            'user_type'          => 'nullable|in:employer,maid',
            'variables'          => 'nullable|array',
            'paperclip_issue_id' => 'nullable|string',
            'urgency'            => 'nullable|in:now,scheduled',
            'scheduled_at'       => 'nullable|date',
        ]);

        $digits = preg_replace('/[^\d]/', '', $validated['customer_phone']);
        $phone = '+' . $digits;
        $customVars = $validated['variables'] ?? [];
        $issueId = $validated['paperclip_issue_id'] ?? null;

        // Every variable the assistant prompt references must be supplied. Vapi
        // leaves an unset one as the literal text "{{maid_name}}", which the
        // assistant then reads aloud or passes into a tool — an unresolved
        // {{customer_phone}} is how a lookup once returned a different
        // customer's identity mid-call. Empty beats braces.
        $variableValues = array_merge([
            'customer_name'    => $validated['customer_name'],
            'customer_phone'   => $validated['customer_phone'],
            'call_purpose'     => $validated['call_purpose'],
            'expected_outcome' => $validated['expected_outcome'],
            'user_type'        => $validated['user_type'] ?? 'employer',
            'maid_name'        => '',
            'start_date'       => '',
            // Same pre-loaded context the inbound webhook builds, so an outbound
            // call opens knowing the person too instead of looking them up mid-
            // conversation on a call they were not expecting.
            'caller_context'   => $this->callerContext($validated['customer_phone']),
        ], $customVars);

        $payload = [
            'name' => mb_substr($validated['call_purpose'], 0, 40),
            'assistantId' => self::OUTBOUND_ASSISTANT_ID,
            'phoneNumberId' => self::OUTBOUND_PHONE_ID,
            'customer' => ['number' => $phone],
            'assistantOverrides' => ['variableValues' => $variableValues],
        ];

        Log::info('Outbound call requested', [
            'phone' => $validated['customer_phone'],
            'purpose' => $validated['call_purpose']
        ]);

        $resp = Http::withToken(self::VAPI_API_KEY)
            ->timeout(30)
            ->post('https://api.vapi.ai/call', $payload);

        if (!$resp->successful()) {
            Log::error('Vapi call creation failed', [
                'status' => $resp->status(),
                'body' => $resp->body()
            ]);
            return $this->error('Vapi call creation failed: ' . $resp->body(), Response::HTTP_BAD_GATEWAY);
        }

        $call = $resp->json();

        if ($issueId) {
            $body = "---\n📞 **Outbound Call Initiated**\nphone: `{$validated['customer_phone']}`\ncaller: `{$validated['customer_name']}`\npurpose: `{$validated['call_purpose']}`\nexpected: `{$validated['expected_outcome']}`\nvapi_call_id: `{$call['id']}`\n\nJane Outbound is calling now. Results will be posted automatically when the call ends.";
            try {
                Http::post(self::PAPERCLIP_API_URL . '/issues/' . $issueId . '/comments', ['body' => $body]);
            } catch (\Throwable $e) {
                Log::warning('Failed to add Paperclip comment', ['error' => $e->getMessage()]);
            }
        }

        return $this->success([
            'vapi_call_id'    => $call['id'] ?? null,
            'status'          => $call['status'] ?? 'queued',
            'phone'           => $validated['customer_phone'],
            'caller_name'     => $validated['customer_name'],
            'call_purpose'    => $validated['call_purpose'],
            'expected_outcome' => $validated['expected_outcome'],
        ], 'Outbound call initiated');
    }
}
