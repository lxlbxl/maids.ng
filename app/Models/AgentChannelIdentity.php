<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgentChannelIdentity extends Model
{
    protected $fillable = [
        'channel',
        'external_id',
        'user_id',
        'display_name',
        'phone',
        'email',
        'otp',
        'otp_expires_at',
        'is_verified',
        'channel_meta',
        'last_seen_at',
    ];

    protected $casts = [
        'channel_meta' => 'array',
        'is_verified' => 'boolean',
        'otp_expires_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = ['otp'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AgentConversation::class, 'channel_identity_id');
    }

    public function lead(): HasOne
    {
        return $this->hasOne(AgentLead::class, 'channel_identity_id');
    }

    public function activeConversation(): HasOne
    {
        return $this->hasOne(AgentConversation::class, 'channel_identity_id')
            ->where('status', 'open')
            ->latestOfMany();
    }

    public function isOtpValid(string $otp): bool
    {
        return $this->otp === $otp
            && $this->otp_expires_at
            && $this->otp_expires_at->isFuture();
    }

    /** Determine the tier string for KnowledgeService */
    public function getTier(): string
    {
        if ($this->user_id && $this->is_verified) {
            return 'authenticated';
        }
        if ($this->channel === 'web') {
            return 'guest';
        }
        return 'lead';
    }
}