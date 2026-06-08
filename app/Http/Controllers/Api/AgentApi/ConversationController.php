<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Models\AgentChannelIdentity;
use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Services\Agents\AmbassadorAgent;
use App\Services\Agents\ConversationManager;
use App\Services\Agents\IdentityResolver;
use App\Services\Agents\DTOs\InboundMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends ApiController
{
    public function __construct(
        private readonly ConversationManager $conversationManager,
        private readonly IdentityResolver $identityResolver,
        private readonly AmbassadorAgent $ambassador,
    ) {}

    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel'     => 'required|string|in:whatsapp,sms,email,web,phone,vapi',
            'from_phone'  => 'nullable|string|max:30',
            'from_email'  => 'nullable|email|max:255',
            'from_name'   => 'nullable|string|max:255',
            'content'     => 'required|string|max:5000',
            'role'        => 'nullable|string|in:user,assistant,system',
            'user_id'     => 'nullable|integer',
            'external_message_id' => 'nullable|string|max:255',
        ]);

        $channel = $validated['channel'];
        $externalId = $validated['from_phone'] ?? $validated['from_email'] ?? 'unknown';

        $identity = $this->identityResolver->resolve($channel, $externalId, [
            'phone' => $validated['from_phone'] ?? null,
            'email' => $validated['from_email'] ?? null,
            'name'  => $validated['from_name'] ?? null,
        ]);

        $conversation = $this->conversationManager->getOrCreateConversation($identity, $channel);

        if ($validated['user_id'] ?? null) {
            $user = User::find($validated['user_id']);
            if ($user) {
                $conversation->update(['user_id' => $user->id]);
                $identity->update(['user_id' => $user->id]);
            }
        }

        $role = $validated['role'] ?? 'user';

        if ($role === 'user') {
            $this->conversationManager->storeUserMessage(
                $conversation,
                $validated['content'],
                $validated['external_message_id'] ?? null,
            );
        } else {
            $this->conversationManager->storeAssistantMessage(
                $conversation,
                $validated['content'],
            );
        }

        $this->conversationManager->updateConversationActivity($conversation);

        return $this->success([
            'conversation_id' => $conversation->id,
            'identity_id'     => $identity->id,
            'user_id'         => $conversation->user_id,
            'channel'         => $channel,
        ], 'Message stored');
    }

    public function show(int $id): JsonResponse
    {
        $conversation = AgentConversation::with(['identity', 'messages' => fn($q) => $q->latest()->limit(50)])->findOrFail($id);

        return $this->success($conversation);
    }

    public function messages(int $id): JsonResponse
    {
        $messages = AgentMessage::where('conversation_id', $id)
            ->latest()
            ->limit(50)
            ->get();

        return $this->success($messages);
    }

    public function index(Request $request): JsonResponse
    {
        $query = AgentConversation::query()->with('identity');

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $conversations = $query->latest()->limit(50)->get();

        return $this->success($conversations);
    }

    public function identityLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string|in:whatsapp,sms,email,web,phone,vapi',
            'phone'   => 'nullable|string|max:30',
            'email'   => 'nullable|email|max:255',
        ]);

        $query = AgentChannelIdentity::query()->where('channel', $validated['channel']);

        if ($phone = $validated['phone'] ?? null) {
            $query->where('phone', 'like', '%'.preg_replace('/[^\d]/', '', $phone).'%');
        }
        if ($email = $validated['email'] ?? null) {
            $query->where('email', $email);
        }

        $identity = $query->with('user')->first();

        if (! $identity) {
            return $this->success(null, 'No identity found');
        }

        return $this->success([
            'identity' => $identity,
            'user' => $identity->user,
        ]);
    }

    public function ambassadorMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel'    => 'required|string|in:whatsapp,sms,email,web,phone,vapi',
            'from_phone' => 'nullable|string|max:30',
            'from_email' => 'nullable|email|max:255',
            'from_name'  => 'nullable|string|max:255',
            'content'    => 'required|string|max:5000',
        ]);

        $channel    = $validated['channel'];
        $externalId = $validated['from_phone'] ?? $validated['from_email'] ?? 'unknown';

        $identity = $this->identityResolver->resolve($channel, $externalId, [
            'phone' => $validated['from_phone'] ?? null,
            'email' => $validated['from_email'] ?? null,
            'name'  => $validated['from_name'] ?? null,
        ]);

        $conversation = $this->conversationManager->getOrCreateConversation($identity, $channel);

        $this->conversationManager->storeUserMessage($conversation, $validated['content']);

        $inbound = new InboundMessage(
            channel: $channel,
            externalId: $externalId,
            content: $validated['content'],
            phone: $validated['from_phone'] ?? null,
            metadata: [
                'profile_name'  => $validated['from_name'] ?? null,
                'identity_id'   => $identity->id,
                'user_id'       => $identity->user_id,
            ],
        );

        $response = $this->ambassador->handle($inbound);

        $this->conversationManager->storeAssistantMessage(
            $conversation,
            $response['content'] ?? '',
        );

        $this->conversationManager->updateConversationActivity($conversation);

        return $this->success([
            'conversation_id' => $conversation->id,
            'identity_id'     => $identity->id,
            'user_id'         => $conversation->user_id,
            'reply'           => $response['content'] ?? '',
            'actions'         => $response['actions'] ?? [],
        ], 'Ambassador processed');
    }
}
