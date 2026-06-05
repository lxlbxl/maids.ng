<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_id',
        'event_type',
        'payload',
        'response_status',
        'response_body',
        'error_message',
        'attempt_count',
        'status',
        'next_retry_at',
        'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_status' => 'integer',
        'attempt_count' => 'integer',
        'next_retry_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Get the webhook that owns this delivery.
     */
    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * Scope for pending deliveries.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for deliveries needing retry.
     */
    public function scopeNeedsRetry($query)
    {
        return $query->where('status', 'retrying')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    /**
     * Scope for successful deliveries.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed deliveries.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Mark delivery as successful.
     */
    public function markSuccessful(int $responseStatus, string $responseBody): void
    {
        $this->update([
            'status' => 'success',
            'response_status' => $responseStatus,
            'response_body' => $responseBody,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark delivery as failed.
     */
    public function markFailed(string $errorMessage, int $responseStatus = null): void
    {
        $webhook = $this->webhook;
        $maxRetries = $webhook ? $webhook->max_retries : 3;

        if ($this->attempt_count >= $maxRetries) {
            $this->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'response_status' => $responseStatus,
            ]);
        } else {
            // Schedule retry with exponential backoff
            $retryDelay = pow(2, $this->attempt_count) * 60; // 2, 4, 8 minutes etc.
            $this->update([
                'status' => 'retrying',
                'next_retry_at' => now()->addSeconds($retryDelay),
                'attempt_count' => $this->attempt_count + 1,
                'error_message' => $errorMessage,
                'response_status' => $responseStatus,
            ]);
        }
    }
}