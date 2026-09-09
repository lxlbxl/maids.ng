<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WacrmBridgeController
{
    private const PAPERCLIP_DB = 'paperclip';
    private const PAPERCLIP_API_URL = 'http://localhost:3100/api';
    private const PAPERCLIP_API_KEY = 'pk_live_wacrm_bridge_bc8415553d84d18cfe19a1addc6ff888d1b76a8609c5b467';

    private const INSTANCE_COMPANY_MAP = [
        'Maids' => 'ada987c3-793e-4e0c-92fd-db3acc1a2f74',
        'ojg' => 'c074fd34-d020-48b3-9801-fc64ae755925',
        'Digital20' => '499fdbd4-b660-4972-b627-af17ac65f2e6',
        'Digital20 Ltd' => '499fdbd4-b660-4972-b627-af17ac65f2e6',
    ];

    private const DEFAULT_AGENT_ID = '369293e5-88da-4469-a44e-4397624aa3d5';

    public function __invoke(Request $request)
    {
        // Handle Meta webhook verification (GET request)
        if ($request->isMethod('GET')) {
            // PHP converts dots to underscores in query params
            $mode = $request->input('hub_mode');
            $token = $request->input('hub_verify_token');
            $challenge = $request->input('hub_challenge');
            
            Log::info('Webhook verification', [
                'mode' => $mode,
                'token' => $token,
                'challenge' => $challenge,
            ]);
            
            if ($mode === 'subscribe' && $token === 'evolution') {
                return response($challenge, 200);
            }
            
            return response('Verification failed', 403);
        }

        // Handle POST webhook events
        $payload = $request->all();
        $instanceName = $payload['instance'] ?? $payload['source'] ?? 'unknown';
        
        Log::info('Bridge webhook received', [
            'instance' => $instanceName,
            'event' => $payload['event'] ?? 'unknown',
        ]);

        if (isset($payload['event']) && str_contains($payload['event'], 'messages')) {
            return $this->handleEvolutionMessage($payload, $instanceName);
        }

        return response()->json([
            'success' => true,
            'message' => 'Event acknowledged but not processed.',
            'data' => null,
            'meta' => ['timestamp' => now()->toIso8601String()]
        ]);
    }

    private function handleEvolutionMessage(array $payload, string $instanceName): array
    {
        $messages = $payload['messages'] ?? [];
        if (empty($messages)) {
            return ['success' => true, 'message' => 'No messages in payload.', 'data' => null];
        }

        $companyId = self::INSTANCE_COMPANY_MAP[$instanceName] ?? null;
        if (!$companyId) {
            Log::warning('Bridge: no company mapped for instance', ['instance' => $instanceName]);
            return ['success' => false, 'message' => 'Unknown instance: ' . $instanceName, 'data' => null];
        }

        $count = 0;
        foreach ($messages as $msg) {
            $from = $msg['from'] ?? '';
            $text = $msg['message']['body'] ?? $msg['message']['conversation'] ?? '';
            $msgId = $msg['id'] ?? uniqid('evo_');
            $conversationId = $instanceName . '-' . preg_replace('/[^0-9]/', '', $from);

            $this->routeToPaperclip($companyId, [
                'conversation_id' => $conversationId,
                'contact_id' => $from,
                'whatsapp_message_id' => $msgId,
                'content_type' => 'text',
                'text' => $text,
                'instance_name' => $instanceName,
            ]);
            $count++;
        }

        return ['success' => true, 'message' => 'Messages routed.', 'data' => ['count' => $count]];
    }

    private function routeToPaperclip(string $companyId, array $data): void
    {
        $conversationId = $data['conversation_id'];
        $contactId = $data['contact_id'];
        $instanceName = $data['instance_name'];
        $agentId = self::DEFAULT_AGENT_ID;

        try {
            $existingIssueId = $this->findExistingIssue($companyId, $conversationId);
            if ($existingIssueId) {
                $this->reopenIssueIfDone($existingIssueId);
                $this->addCommentToIssue($existingIssueId, $data);
            } else {
                $newIssueId = $this->createIssueForConversation($companyId, $conversationId, $contactId, $instanceName, $agentId);
                $this->addCommentToIssue($newIssueId, $data);
            }
        } catch (\Throwable $e) {
            Log::error('Bridge failed', ['error' => $e->getMessage(), 'instance' => $instanceName]);
        }
    }

    private function findExistingIssue(string $companyId, string $conversationId): ?string
    {
        try {
            $issue = DB::connection(self::PAPERCLIP_DB)
                ->table('issues')
                ->where('company_id', $companyId)
                ->where('title', 'like', "%{$conversationId}%")
                ->orderBy('created_at', 'desc')
                ->first();

            return $issue ? $issue->id : null;
        } catch (\Throwable $e) {
            Log::error('Bridge find-issue failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function createIssueForConversation(string $companyId, string $conversationId, string $contactId, string $instanceName, string $agentId): string
    {
        try {
            $id = (string) \Ramsey\Uuid\Uuid::uuid4();
            DB::connection(self::PAPERCLIP_DB)->table('issues')->insert([
                'id' => $id,
                'company_id' => $companyId,
                'title' => '[' . strtoupper($instanceName) . '] WA: ' . $conversationId,
                'description' => json_encode([
                    'kind' => 'whatsapp_conversation',
                    'conversation_id' => $conversationId,
                    'contact_id' => $contactId,
                    'instance_name' => $instanceName,
                ]),
                'status' => 'todo',
                'priority' => 'medium',
                'assignee_agent_id' => $agentId,
                'origin_kind' => 'whatsapp_inbound',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $id;
        } catch (\Throwable $e) {
            Log::error('Bridge create-issue failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Paperclip create-issue failed: ' . $e->getMessage());
        }
    }

    private function reopenIssueIfDone(string $issueId): void
    {
        try {
            DB::connection(self::PAPERCLIP_DB)
                ->table('issues')
                ->where('id', $issueId)
                ->update(['status' => 'in_progress', 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Bridge reopen-issue failed', ['error' => $e->getMessage()]);
        }
    }

    private function addCommentToIssue(string $issueId, array $data): void
    {
        try {
            $instanceName = $data['instance_name'] ?? 'unknown';
            $whatsappMessageId = $data['whatsapp_message_id'] ?? 'N/A';
            $contentType = $data['content_type'] ?? 'text';
            $text = $data['text'] ?? '';

            $body = "---\n**WhatsApp incoming message [{$instanceName}]**\n";
            $body .= "conversation_id: `{$data['conversation_id']}`\n";
            $body .= "contact_id: `{$data['contact_id']}`\n";
            $body .= "whatsapp_message_id: `{$whatsappMessageId}`\n";
            $body .= "content_type: `{$contentType}`\n";
            $body .= "instance: `{$instanceName}`\n\n";
            $body .= "> {$text}\n";

            // Get company_id from the issue
            $issue = DB::connection(self::PAPERCLIP_DB)
                ->table('issues')
                ->where('id', $issueId)
                ->value('company_id');

            if ($issue) {
                $commentId = (string) \Ramsey\Uuid\Uuid::uuid4();
                DB::connection(self::PAPERCLIP_DB)->table('issue_comments')->insert([
                    'id' => $commentId,
                    'issue_id' => $issueId,
                    'company_id' => $issue,
                    'body' => $body,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Bridge add-comment failed', ['error' => $e->getMessage()]);
        }
    }
}
