<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentLead extends Model
{
    protected $fillable = [
        'channel_identity_id',
        'user_id',
        'name',
        'phone',
        'email',
        'intent',
        'status',
        'notes',
    ];

    protected $casts = [
        'intent' => 'array',
    ];

    public function identity(): BelongsTo
    {
        return $this->belongsTo(AgentChannelIdentity::class, 'channel_identity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the lead as registered (user account created).
     */
    public function markRegistered(int $userId): void
    {
        $this->update([
            'user_id' => $userId,
            'status' => 'registered',
        ]);
    }

    /**
     * Mark the lead as lost (no longer interested).
     */
    public function markLost(string $reason = ''): void
    {
        $this->update([
            'status' => 'lost',
            'notes' => $reason,
        ]);
    }
}