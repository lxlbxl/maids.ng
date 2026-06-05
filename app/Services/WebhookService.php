<?php

namespace App\Services;

use App\Models\Webhook;
use App\Jobs\DispatchWebhookJob;

class WebhookService
{
    /**
     * Dispatch an event payload to all subscribed webhooks.
     *
     * @param string $event The event name (e.g., 'maid.hired')
     * @param array $payload The data payload for the event
     */
    public static function dispatch(string $event, array $payload): void
    {
        $webhooks = Webhook::active()
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($webhooks as $webhook) {
            DispatchWebhookJob::dispatch($webhook, $event, $payload);
        }
    }
}
