<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentConversation extends Model
{
    protected $fillable = [
        'channel_identity_id',
        'user_id',
        'channel',
        'status',
        'intent_summary',
        'email_subject',
        'email_thread_id',
        'admin_note',
        'assigned_to',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function identity(): BelongsTo
    {
        return $this->belongsTo(AgentChannelIdentity::class, 'channel_identity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'conversation_id');
    }

    /**
     * Return the last N messages in LLM-ready format.
     * Excludes 'tool' and 'system' roles from history to reduce token usage.
     */
    public function getHistory(int $limit = 20): array
    {
        return $this->messages()
            ->whereIn('role', ['user', 'assistant', 'admin'])
            ->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->reverse()
            ->map(fn($m) => [
                'role' => $m->role === 'admin' ? 'assistant' : $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->toArray();
    }
}