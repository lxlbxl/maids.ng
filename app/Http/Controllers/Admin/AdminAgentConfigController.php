<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminAgentConfigController extends Controller
{
    public function index()
    {
        $stats = ['active_agents' => 0, 'open_conversations' => 0, 'new_leads' => 0, 'messages_today' => 0];
        $conversations = [];
        $leads = [];
        $agentSettings = [];

        try {
            $settingKeys = DB::table('settings')
                ->where('key', 'like', 'agent_%')
                ->orWhere('key', 'like', 'channel_%')
                ->get();
            foreach ($settingKeys as $s) {
                $agentSettings[$s->key] = $s->value;
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('agent_conversations')) {
                $conversations = DB::table('agent_conversations')
                    ->join('agent_channel_identities', 'agent_conversations.channel_identity_id', '=', 'agent_channel_identities.id')
                    ->select('agent_conversations.*', 'agent_channel_identities.display_name', 'agent_channel_identities.phone', 'agent_channel_identities.email', 'agent_channel_identities.channel as ch')
                    ->where('agent_conversations.status', 'open')
                    ->orderByDesc('agent_conversations.last_message_at')
                    ->limit(50)->get()
                    ->map(fn($c) => [
                        'id' => $c->id,
                        'channel' => $c->ch,
                        'status' => $c->status,
                        'last_message_at' => $c->last_message_at,
                        'identity' => ['display_name' => $c->display_name, 'phone' => $c->phone, 'channel' => $c->ch],
                    ])->all();
                $stats['open_conversations'] = count($conversations);
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('agent_leads')) {
                $leads = DB::table('agent_leads')
                    ->join('agent_channel_identities', 'agent_leads.channel_identity_id', '=', 'agent_channel_identities.id')
                    ->select('agent_leads.*', 'agent_channel_identities.display_name', 'agent_channel_identities.phone', 'agent_channel_identities.channel as ch')
                    ->orderByDesc('agent_leads.created_at')
                    ->limit(100)->get()
                    ->map(fn($l) => [
                        'id' => $l->id,
                        'status' => $l->status,
                        'phone' => $l->phone,
                        'email' => $l->email,
                        'created_at' => $l->created_at,
                        'converted_at' => $l->converted_at ?? null,
                        'identity' => ['display_name' => $l->display_name, 'phone' => $l->phone, 'channel' => $l->ch],
                    ])->all();
                $stats['new_leads'] = DB::table('agent_leads')->where('status', 'new')->count();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('agent_messages')) {
                $stats['messages_today'] = DB::table('agent_messages')
                    ->where('created_at', '>=', now()->startOfDay())->count();
            }
            $stats['active_agents'] = 7;
        } catch (\Throwable $e) {
            Log::warning('Agent page load error: ' . $e->getMessage());
        }

        return Inertia::render('Admin/Agents', compact('stats', 'conversations', 'leads', 'agentSettings'));
    }

    public function toggleAgent(Request $request)
    {
        $agent = $request->input('agent');
        $enabled = $request->boolean('enabled');
        DB::table('settings')->updateOrInsert(
            ['key' => "agent_{$agent}_enabled"],
            ['value' => $enabled ? 'true' : 'false', 'group' => 'agents', 'updated_at' => now(), 'created_at' => now()]
        );
        return back()->with('success', "Agent updated.");
    }

    public function toggleChannel(Request $request)
    {
        $channel = $request->input('channel');
        $enabled = $request->boolean('enabled');
        DB::table('settings')->updateOrInsert(
            ['key' => "channel_{$channel}_enabled"],
            ['value' => $enabled ? 'true' : 'false', 'group' => 'agents', 'updated_at' => now(), 'created_at' => now()]
        );
        return back()->with('success', "Channel updated.");
    }

    public function closeConversation($id)
    {
        try {
            DB::table('agent_conversations')->where('id', $id)->update(['status' => 'closed', 'updated_at' => now()]);
            return back()->with('success', 'Conversation closed.');
        } catch (\Throwable $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }
}
