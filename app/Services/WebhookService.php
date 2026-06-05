<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Dispatch event to all listening webhooks.
     */
    public function dispatch(string $eventType, array $payload): void
    {
        $webhooks = Webhook::where('active', true)
            ->get()
            ->filter(fn($webhook) => $webhook->listensToEvent($eventType));

        foreach ($webhooks as $webhook) {
            $this->queueDelivery($webhook, $eventType, $payload);
        }
    }

    /**
     * Queue a webhook delivery.
     */
    private function queueDelivery(Webhook $webhook, string $eventType, array $payload): void
    {
        WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    /**
     * Process pending webhook deliveries.
     */
    public function processPendingDeliveries(): int
    {
        $deliveries = WebhookDelivery::with('webhook')
            ->where(function ($query) {
                $query->pending()
                    ->orWhere(function ($q) {
                        $q->needsRetry();
                    });
            })
            ->limit(100)
            ->get();

        $processed = 0;
        foreach ($deliveries as $delivery) {
            if ($this->deliver($delivery)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Deliver a webhook payload.
     */
    public function deliver(WebhookDelivery $delivery): bool
    {
        $webhook = $delivery->webhook;

        if (!$webhook || !$webhook->isHealthy()) {
            $delivery->markFailed('Webhook is inactive or unhealthy');
            return false;
        }

        $payload = $this->buildPayload($delivery, $webhook);
        $headers = $this->buildHeaders($payload, $webhook);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($webhook->timeout_seconds)
                ->verify($webhook->verify_ssl)
                ->post($webhook->url, $payload);

            if ($response->successful()) {
                $delivery->markSuccessful($response->status(), $response->body());
                $webhook->recordSuccess();

                Log::info('Webhook delivered successfully', [
                    'webhook_id' => $webhook->id,
                    'event_type' => $delivery->event_type,
                    'response_status' => $response->status(),
                ]);

                return true;
            } else {
                $delivery->markFailed(
                    "HTTP {$response->status()}: " . substr($response->body(), 0, 500),
                    $response->status()
                );
                $webhook->recordFailure("HTTP {$response->status()}");

                return false;
            }
        } catch (\Exception $e) {
            $delivery->markFailed($e->getMessage());
            $webhook->recordFailure($e->getMessage());

            Log::error('Webhook delivery failed', [
                'webhook_id' => $webhook->id,
                'event_type' => $delivery->event_type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build the payload for webhook delivery.
     */
    private function buildPayload(WebhookDelivery $delivery, Webhook $webhook): array
    {
        return [
            'id' => $delivery->id,
            'event' => $delivery->event_type,
            'timestamp' => now()->toIso8601String(),
            'data' => $delivery->payload,
        ];
    }

    /**
     * Build headers for webhook request including signature.
     */
    private function buildHeaders(array $payload, Webhook $webhook): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Webhook-Signature' => $this->generateSignature($payload, $webhook),
            'X-Webhook-Event' => $payload['event'],
            'X-Webhook-Timestamp' => $payload['timestamp'],
        ];

        return $headers;
    }

    /**
     * Generate HMAC signature for payload verification.
     */
    private function generateSignature(array $payload, Webhook $webhook): string
    {
        $secret = $webhook->decrypted_secret ?? config('app.key');
        $payloadString = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $payloadString, $secret);
    }

    /**
     * Verify webhook signature.
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get delivery statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_deliveries' => WebhookDelivery::count(),
            'successful' => WebhookDelivery::successful()->count(),
            'failed' => WebhookDelivery::failed()->count(),
            'pending' => WebhookDelivery::pending()->count(),
            'retrying' => WebhookDelivery::where('status', 'retrying')->count(),
            'success_rate' => WebhookDelivery::count() > 0
                ? round((WebhookDelivery::successful()->count() / WebhookDelivery::count()) * 100, 2)
                : 0,
        ];
    }

    /**
     * Retry a specific delivery.
     */
    public function retryDelivery(int $deliveryId): bool
    {
        $delivery = WebhookDelivery::with('webhook')->find($deliveryId);

        if (!$delivery) {
            return false;
        }

        $delivery->update([
            'status' => 'pending',
            'next_retry_at' => null,
            'attempt_count' => 0,
        ]);

        return $this->deliver($delivery);
    }
}