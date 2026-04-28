<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'notification_type',
        'channel',
        'subject',
        'content',
        'context_json',
        'reference_id',
        'reference_type',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'status',
        'delivery_status',
        'delivery_response',
        'follow_up_sequence',
        'parent_notification_id',
        'requires_follow_up',
        'follow_up_scheduled_at',
        'ai_generated',
        'ai_prompt_used',
        'local_time_sent',
        'timezone',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'context_json' => 'array',
        'delivery_response' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'follow_up_scheduled_at' => 'datetime',
        'local_time_sent' => 'datetime',
        'ai_generated' => 'boolean',
        'requires_follow_up' => 'boolean',
    ];

    /**
     * Get the user who received this notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the parent notification (for follow-up chains).
     */
    public function parentNotification(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_notification_id');
    }

    /**
     * Get all follow-up notifications.
     */
    public function followUpNotifications(): HasMany
    {
        return $this->hasMany(self::class, 'parent_notification_id');
    }

    /**
     * Get the assignment associated with this notification.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MaidAssignment::class, 'reference_id')
            ->where('reference_type', 'assignment');
    }

    /**
     * Get the preference associated with this notification.
     */
    public function preference(): BelongsTo
    {
        return $this->belongsTo(EmployerPreference::class, 'reference_id')
            ->where('reference_type', 'preference');
    }

    /**
     * Scope for pending notifications (scheduled but not sent).
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->whereNull('sent_at');
    }

    /**
     * Scope for scheduled notifications.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now());
    }

    /**
     * Scope for notifications ready to send (scheduled time passed).
     */
    public function scopeReadyToSend($query)
    {
        return $query->whereIn('status', ['pending', 'scheduled'])
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });
    }

    /**
     * Scope for sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent')
            ->whereNotNull('sent_at');
    }

    /**
     * Scope for delivered notifications.
     */
    public function scopeDelivered($query)
    {
        return $query->where('delivery_status', 'delivered')
            ->whereNotNull('delivered_at');
    }

    /**
     * Scope for failed notifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for notifications requiring follow-up.
     */
    public function scopeRequiresFollowUp($query)
    {
        return $query->where('requires_follow_up', true)
            ->whereNull('follow_up_scheduled_at');
    }

    /**
     * Scope for notifications by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for notifications by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Scope for notifications by channel.
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope for SMS notifications.
     */
    public function scopeSms($query)
    {
        return $query->where('channel', 'sms');
    }

    /**
     * Scope for email notifications.
     */
    public function scopeEmail($query)
    {
        return $query->where('channel', 'email');
    }

    /**
     * Scope for WhatsApp notifications.
     */
    public function scopeWhatsApp($query)
    {
        return $query->where('channel', 'whatsapp');
    }

    /**
     * Check if notification is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' && is_null($this->sent_at);
    }

    /**
     * Check if notification is sent.
     */
    public function isSent(): bool
    {
        return $this->status === 'sent' && !is_null($this->sent_at);
    }

    /**
     * Check if notification is delivered.
     */
    public function isDelivered(): bool
    {
        return $this->delivery_status === 'delivered' && !is_null($this->delivered_at);
    }

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if notification failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark notification as delivered.
     */
    public function markAsDelivered(array $response = []): void
    {
        $this->update([
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
            'delivery_response' => $response,
        ]);
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(string $reason, array $response = []): void
    {
        $this->update([
            'status' => 'failed',
            'delivery_status' => 'failed',
            'delivery_response' => array_merge($response, ['error' => $reason]),
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'read_at' => now(),
        ]);
    }

    /**
     * Schedule a follow-up notification.
     */
    public function scheduleFollowUp(int $sequence, \Carbon\Carbon $scheduledAt): self
    {
        return self::create([
            'user_id' => $this->user_id,
            'user_type' => $this->user_type,
            'notification_type' => $this->notification_type,
            'channel' => $this->channel,
            'subject' => "Follow-up: {$this->subject}",
            'content' => null, // Will be generated by AI
            'context_json' => array_merge($this->context_json ?? [], [
                'parent_notification_id' => $this->id,
                'follow_up_sequence' => $sequence,
                'original_sent_at' => $this->sent_at?->toIso8601String(),
            ]),
            'reference_id' => $this->reference_id,
            'reference_type' => $this->reference_type,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'follow_up_sequence' => $sequence,
            'parent_notification_id' => $this->id,
            'requires_follow_up' => false,
            'ai_generated' => true,
            'timezone' => $this->timezone,
        ]);
    }

    /**
     * Get context for AI follow-up.
     */
    public function getContextForAI(): array
    {
        $context = $this->context_json ?? [];

        // Add notification history
        if ($this->parent_notification_id) {
            $parent = $this->parentNotification;
            $context['parent_notification'] = [
                'id' => $parent->id,
                'sent_at' => $parent->sent_at?->toIso8601String(),
                'content' => $parent->content,
                'delivery_status' => $parent->delivery_status,
            ];
        }

        // Add follow-up history
        $followUps = $this->followUpNotifications()
            ->orderBy('follow_up_sequence')
            ->get(['id', 'sent_at', 'delivery_status', 'content']);

        if ($followUps->isNotEmpty()) {
            $context['follow_up_history'] = $followUps->toArray();
        }

        // Add user context
        if ($this->user) {
            $context['user'] = [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'user_type' => $this->user_type,
            ];
        }

        return $context;
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'scheduled' => 'Scheduled',
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get delivery status label.
     */
    public function getDeliveryStatusLabelAttribute(): string
    {
        return match ($this->delivery_status) {
            'pending' => 'Pending',
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
            'bounced' => 'Bounced',
            'rejected' => 'Rejected',
            default => ucfirst($this->delivery_status ?? 'unknown'),
        };
    }

    /**
     * Get channel label.
     */
    public function getChannelLabelAttribute(): string
    {
        return match ($this->channel) {
            'sms' => 'SMS',
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
            'push' => 'Push Notification',
            'in_app' => 'In-App',
            default => ucfirst($this->channel),
        };
    }

    /**
     * Check if notification is within work hours (8 AM - 8 PM).
     */
    public function isWithinWorkHours(): bool
    {
        $timezone = $this->timezone ?? config('app.timezone', 'Africa/Lagos');
        $localTime = $this->scheduled_at?->copy()->setTimezone($timezone);

        if (!$localTime) {
            return false;
        }

        $hour = (int) $localTime->format('H');
        return $hour >= 8 && $hour < 20;
    }

    /**
     * Schedule for next work hour if outside work hours.
     */
    public function scheduleForNextWorkHour(): \Carbon\Carbon
    {
        $timezone = $this->timezone ?? config('app.timezone', 'Africa/Lagos');
        $scheduledAt = $this->scheduled_at?->copy()->setTimezone($timezone) ?? now($timezone);

        $hour = (int) $scheduledAt->format('H');

        if ($hour < 8) {
            // Before 8 AM, schedule for 8 AM today
            return $scheduledAt->setTime(8, 0, 0);
        } elseif ($hour >= 20) {
            // After 8 PM, schedule for 8 AM next day
            return $scheduledAt->addDay()->setTime(8, 0, 0);
        }

        return $scheduledAt;
    }
}
