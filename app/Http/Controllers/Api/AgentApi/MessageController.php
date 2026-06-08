<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Models\AgentChannelIdentity;
use App\Models\AgentOutreachLog;
use App\Services\Agents\ConversationManager;
use App\Services\Agents\IdentityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageController extends ApiController
{
    public function __construct(
        private readonly ConversationManager $conversationManager,
        private readonly IdentityResolver $identityResolver,
    ) {}

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'    => 'required|integer',
            'message'    => 'required|string|max:4096',
            'channel'    => 'required|string|in:whatsapp,sms,email',
            'phone'      => 'nullable|string|max:30',
            'email'      => 'nullable|email|max:255',
        ]);

        $channel = $validated['channel'];
        $sent    = false;
        $providerResponse = null;

        if ($channel === 'sms' && ($validated['phone'] ?? null)) {
            $sent = $this->sendSms($validated['phone'], $validated['message']);
        } elseif ($channel === 'whatsapp' && ($validated['phone'] ?? null)) {
            $sent = $this->sendWhatsApp($validated['phone'], $validated['message']);
        } elseif ($channel === 'email' && ($validated['email'] ?? null)) {
            $sent = $this->sendEmail($validated['email'], $validated['message'], 'Message from Maids.ng');
        }

        AgentOutreachLog::create([
            'channel'     => $channel,
            'message_content' => mb_substr($validated['message'], 0, 500),
            'status'      => $sent ? 'sent' : 'failed',
            'sent_at'     => $sent ? now() : null,
        ]);

        return $this->success([
            'sent'    => $sent,
            'channel' => $channel,
        ], $sent ? 'Message sent' : 'Message failed');
    }

    public function sms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'   => 'required|string|max:30',
            'message' => 'required|string|max:1600',
        ]);

        $sent = $this->sendSms($validated['phone'], $validated['message']);

        return $this->success(['sent' => $sent], $sent ? 'SMS sent' : 'SMS failed');
    }

    public function call(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'     => 'required|string|max:30',
            'call_type' => 'required|string|max:50',
            'context'   => 'nullable|string|max:5000',
            'metadata'  => 'nullable|array',
        ]);

        $vapiKey        = config('services.vapi.api_key') ?? env('VAPI_API_KEY');
        $vapiAssistantId = config('services.vapi.assistant_id') ?? env('VAPI_ASSISTANT_ID');
        $vapiPhoneId    = config('services.vapi.phone_number_id') ?? env('VAPI_PHONE_NUMBER_ID');

        if (! $vapiKey || ! $vapiAssistantId) {
            return $this->error('VAPI not configured on server', 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$vapiKey}",
                'Content-Type'  => 'application/json',
            ])->post('https://api.vapi.ai/call', [
                'assistantId' => $vapiAssistantId,
                'phoneNumberId' => $vapiPhoneId,
                'customer' => ['number' => $validated['phone']],
                'context'  => $validated['context'] ?? '',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->success([
                    'call_id' => $data['id'] ?? null,
                    'status'  => $data['status'] ?? 'queued',
                ], 'Call initiated');
            }

            Log::error('VAPI call failed', ['response' => $response->body()]);
            return $this->error('VAPI call initiation failed', 502, $response->json());
        } catch (\Throwable $e) {
            Log::error('VAPI call exception', ['error' => $e->getMessage()]);
            return $this->error('VAPI call failed: '.$e->getMessage(), 500);
        }
    }

    private function sendSms(string $phone, string $message): bool
    {
        $termiiKey = config('services.termii.api_key') ?? env('TERMII_API_KEY');
        if (! $termiiKey) {
            return false;
        }

        try {
            Http::withHeaders(['Content-Type' => 'application/json'])
                ->post('https://api.ng.termii.com/api/sms/send', [
                    'to'       => $phone,
                    'from'     => 'MaidsNG',
                    'sms'      => $message,
                    'type'     => 'plain',
                    'channel'  => 'generic',
                    'api_key'  => $termiiKey,
                ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('SMS send failed', ['to' => $phone, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendWhatsApp(string $phone, string $message): bool
    {
        $whatsappToken = config('services.whatsapp.access_token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (! $whatsappToken || ! $phoneNumberId) {
            return false;
        }

        try {
            Http::withHeaders([
                'Authorization' => "Bearer {$whatsappToken}",
                'Content-Type'  => 'application/json',
            ])->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $phone,
                'type'              => 'text',
                'text'              => ['body' => $message],
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed', ['to' => $phone, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendEmail(string $email, string $message, string $subject): bool
    {
        return false;
    }
}
