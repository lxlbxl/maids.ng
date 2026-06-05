<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'active',
        'verify_ssl',
        'timeout_seconds',
        'max_retries',
        'last_triggered_at',
        'last_success_at',
        'last_failure_at',
        'consecutive_failures',
        'last_error',
        'created_by',
    ];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'verify_ssl' => 'boolean',
        'timeout_seconds' => 'integer',
        'max_retries' => 'integer',
        'consecutive_failures' => 'integer',
        'last_triggered_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    /**
     * Get the user who created the webhook.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get webhook deliveries.
     */
    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Get encrypted secret.
     */
    public function getDecryptedSecretAttribute(): ?string
    {
        if (!$this->secret) {
            return null;
        }
        try {
            return Crypt::decryptString($this->secret);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted secret.
     */
    public function setSecretAttribute($value): void
    {
        if ($value) {
            $this->attributes['secret'] = Crypt::encryptString($value);
        }
    }

    /**
     * Check if webhook listens to a specific event.
     */
    public function listensToEvent(string $event): bool
    {
        return in_array($event, $this->events);
    }

    /**
     * Check if webhook is healthy (not exceeding max failures).
     */
    public function isHealthy(): bool
    {
        return $this->active && $this->consecutive_failures < $this->max_retries;
    }

    /**
     * Record successful delivery.
     */
    public function recordSuccess(): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'last_success_at' => now(),
            'consecutive_failures' => 0,
            'last_error' => null,
        ]);
    }

    /**
     * Record failed delivery.
     */
    public function recordFailure(string $error): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'last_failure_at' => now(),
            'consecutive_failures' => $this->consecutive_failures + 1,
            'last_error' => $error,
            'active' => $this->consecutive_failures + 1 >= $this->max_retries ? false : $this->active,
        ]);
    }

    /**
     * Available webhook events.
     */
    public static function availableEvents(): array
    {
        return [
            // Assignment events
            'assignment.created' => 'Assignment Created',
            'assignment.accepted' => 'Assignment Accepted',
            'assignment.rejected' => 'Assignment Rejected',
            'assignment.completed' => 'Assignment Completed',
            'assignment.cancelled' => 'Assignment Cancelled',

            // Booking events
            'booking.created' => 'Booking Created',
            'booking.started' => 'Booking Started',
            'booking.completed' => 'Booking Completed',
            'booking.cancelled' => 'Booking Cancelled',

            // Payment events
            'payment.successful' => 'Payment Successful',
            'payment.failed' => 'Payment Failed',
            'payment.refunded' => 'Payment Refunded',

            // Salary events
            'salary.paid' => 'Salary Paid',
            'salary.overdue' => 'Salary Overdue',
            'salary.reminder_sent' => 'Salary Reminder Sent',

            // Matching events
            'matching.completed' => 'Matching Completed',
            'matching.failed' => 'Matching Failed',

            // User events
            'user.registered' => 'User Registered',
            'user.verified' => 'User Verified',

            // Review events
            'review.created' => 'Review Created',
            'review.flagged' => 'Review Flagged',

            // Dispute events
            'dispute.created' => 'Dispute Created',
            'dispute.resolved' => 'Dispute Resolved',

            // Withdrawal events
            'withdrawal.requested' => 'Withdrawal Requested',
            'withdrawal.approved' => 'Withdrawal Approved',
            'withdrawal.rejected' => 'Withdrawal Rejected',
        ];
    }
}