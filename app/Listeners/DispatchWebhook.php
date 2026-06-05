<?php

namespace App\Listeners;

use App\Services\WebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DispatchWebhook implements ShouldQueue
{
    use InteractsWithQueue;

    protected WebhookService $webhookService;

    /**
     * Create the event listener.
     */
    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle the event.
     * 
     * The event should provide:
     * - getEventType(): string - The event type (e.g., 'assignment.created')
     * - getPayload(): array - The payload data to send
     */
    public function handle(object $event): void
    {
        if (!method_exists($event, 'getEventType') || !method_exists($event, 'getPayload')) {
            return;
        }

        $eventType = $event->getEventType();
        $payload = $event->getPayload();

        $this->webhookService->dispatch($eventType, $payload);
    }
}