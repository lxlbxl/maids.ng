<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agents\AmbassadorAgent;
use App\Services\Agents\DTOs\InboundMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AmbassadorChatController extends Controller
{
    public function __construct(
        private readonly AmbassadorAgent $ambassador,
    ) {
    }

    /**
     * POST /api/ambassador/chat
     * Process a chat message and return the AI response.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'session_id' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'metadata' => 'nullable|array',
        ]);

        // Build the inbound message DTO
        $inbound = InboundMessage::fromWeb([
            'message' => $validated['message'],
            'session_id' => $validated['session_id'] ?? $request->session()->getId(),
            'phone' => $validated['phone'] ?? Auth::user()?->phone,
            'email' => $validated['email'] ?? Auth::user()?->email,
            'message_id' => uniqid('msg_'),
            'metadata' => $validated['metadata'] ?? [],
        ]);

        // Process through Ambassador Agent
        $result = $this->ambassador->handle($inbound);

        return response()->json([
            'success' => true,
            'content' => $result['content'],
            'conversation_id' => $result['conversation_id'],
            'tool_calls' => $result['tool_calls'] ?? [],
        ]);
    }

    /**
     * GET /api/ambassador/conversation/{id}
     * Get conversation history for the current user.
     */
    public function history(Request $request, int $conversationId): JsonResponse
    {
        $conversation = \App\Models\AgentConversation::with('messages')
            ->where('id', $conversationId)
            ->where(function ($q) use ($request) {
                if (Auth::check()) {
                    $q->where('user_id', Auth::id());
                } else {
                    $q->whereHas('identity', function ($q2) use ($request) {
                        $q2->where('external_id', $request->session()->getId());
                    });
                }
            })
            ->firstOrFail();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $conversation->messages()
                ->whereIn('role', ['user', 'assistant'])
                ->orderBy('created_at')
                ->get(),
        ]);
    }
}