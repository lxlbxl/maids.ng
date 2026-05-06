<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentConversation;
use App\Models\AgentChannelIdentity;
use App\Models\AgentMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * AgentConversationController — Admin dashboard for managing agent conversations.
 *
 * Provides:
 * - List all conversations with filtering and pagination
 * - View conversation detail with full message history
 * - Escalate conversations to human agents
 * - Conversation analytics
 */
class AgentConversationController extends Controller
{
    /**
     * List all conversations with filtering.
     */
    public function index(Request $request)
    {
        $query = AgentConversation::with([
            'identity',
            'user',
            'assignedAdmin',
        ]);

        // Filter by channel
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by assigned admin
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Search by user email or phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('identity', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        // Only show conversations with messages
        $query->has('messages');

        $conversations = $query->orderByDesc('last_message_at')
            ->paginate(25)
            ->withQueryString();

        // Get stats
        $stats = [
            'total' => AgentConversation::count(),
            'open' => AgentConversation::where('status', 'open')->count(),
            'escalated' => AgentConversation::where('status', 'escalated')->count(),
            'closed' => AgentConversation::where('status', 'closed')->count(),
            'today' => AgentConversation::whereDate('created_at', today())->count(),
        ];

        return Inertia::render('Admin/Agent/Conversations/Index', [
            'conversations' => $conversations,
            'stats' => $stats,
            'filters' => $request->only(['channel', 'status', 'search', 'assigned_to']),
            'channels' => ['web', 'email', 'whatsapp', 'instagram', 'facebook'],
            'statuses' => ['open', 'escalated', 'closed'],
        ]);
    }

    /**
     * View a single conversation with full message history.
     */
    public function show(AgentConversation $conversation)
    {
        $conversation->load([
            'identity',
            'user',
            'assignedAdmin',
            'messages' => function ($query) {
                $query->orderBy('created_at');
            },
        ]);

        $messages = $conversation->messages->map(function ($message) {
            return [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'tool_calls' => $message->tool_calls,
                'created_at' => $message->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return Inertia::render('Admin/Agent/Conversations/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'channel' => $conversation->channel,
                'status' => $conversation->status,
                'intent_summary' => $conversation->intent_summary,
                'admin_note' => $conversation->admin_note,
                'created_at' => $conversation->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $conversation->updated_at->format('Y-m-d H:i:s'),
                'last_message_at' => $conversation->last_message_at?->format('Y-m-d H:i:s'),
                'identity' => $conversation->identity ? [
                    'id' => $conversation->identity->id,
                    'channel' => $conversation->identity->channel,
                    'display_name' => $conversation->identity->display_name,
                    'phone' => $conversation->identity->phone,
                    'email' => $conversation->identity->email,
                    'is_verified' => $conversation->identity->is_verified,
                    'tier' => $conversation->identity->getTier(),
                ] : null,
                'user' => $conversation->user ? [
                    'id' => $conversation->user->id,
                    'name' => $conversation->user->name,
                    'email' => $conversation->user->email,
                    'role' => $conversation->user->role,
                ] : null,
                'assigned_admin' => $conversation->assignedAdmin ? [
                    'id' => $conversation->assignedAdmin->id,
                    'name' => $conversation->assignedAdmin->name,
                ] : null,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Assign a conversation to an admin.
     */
    public function assign(Request $request, AgentConversation $conversation)
    {
        $validated = $request->validate([
            'admin_id' => 'required|exists:users,id',
        ]);

        $conversation->update([
            'assigned_to' => $validated['admin_id'],
            'status' => 'open',
        ]);

        return back()->with('success', 'Conversation assigned.');
    }

    /**
     * Escalate a conversation to human review.
     */
    public function escalate(Request $request, AgentConversation $conversation)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $conversation->update([
            'status' => 'escalated',
            'admin_note' => $validated['admin_note'] ?? $validated['reason'] ?? null,
            'assigned_to' => auth()->id(),
        ]);

        return back()->with('success', 'Conversation escalated to human review.');
    }

    /**
     * Close a conversation.
     */
    public function close(AgentConversation $conversation)
    {
        $conversation->update([
            'status' => 'closed',
        ]);

        return back()->with('success', 'Conversation closed.');
    }

    /**
     * Add an admin note to a conversation.
     */
    public function addNote(Request $request, AgentConversation $conversation)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $conversation->update([
            'admin_note' => $validated['note'],
        ]);

        return back()->with('success', 'Note added.');
    }

    /**
     * Get conversation analytics.
     */
    public function analytics(Request $request)
    {
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days);

        // Conversations by channel
        $byChannel = AgentConversation::where('created_at', '>=', $startDate)
            ->select('channel', DB::raw('count(*) as count'))
            ->groupBy('channel')
            ->get()
            ->pluck('count', 'channel')
            ->toArray();

        // Conversations by status
        $byStatus = AgentConversation::where('created_at', '>=', $startDate)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Messages per day (last 7 days)
        $messagesPerDay = AgentMessage::where('created_at', '>=', now()->subDays(7))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Average response time (time between user message and assistant response)
        $avgResponseTime = DB::table('agent_messages as user_msg')
            ->join('agent_messages as assistant_msg', function ($join) {
                $join->on('user_msg.conversation_id', '=', 'assistant_msg.conversation_id')
                    ->whereRaw('assistant_msg.created_at > user_msg.created_at')
                    ->where('user_msg.role', '=', 'user')
                    ->where('assistant_msg.role', '=', 'assistant');
            })
            ->where('user_msg.created_at', '>=', $startDate)
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, user_msg.created_at, assistant_msg.created_at)) as avg_seconds')
            ->value('avg_seconds');

        return Inertia::render('Admin/Agent/Conversations/Analytics', [
            'analytics' => [
                'by_channel' => $byChannel,
                'by_status' => $byStatus,
                'messages_per_day' => $messagesPerDay,
                'avg_response_time_seconds' => round($avgResponseTime ?? 0),
                'period_days' => $days,
            ],
        ]);
    }
}