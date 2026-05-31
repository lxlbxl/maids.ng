<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WebhookController extends ApiController
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * List all webhooks for admin.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return $this->forbidden('Admin access required.');
        }

        $query = Webhook::with('creator')->orderBy('created_at', 'desc');

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
            });
        }

        $webhooks = $query->paginate($request->per_page ?? 20);

        return $this->paginated($webhooks, 'Webhooks retrieved successfully');
    }

    /**
     * Get webhook detail.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return $this->forbidden('Admin access required.');
        }

        $webhook = Webhook::with([
            'creator',
            'deliveries' => function ($q) {
                $q->latest()->limit(10);
            }
        ])->findOrFail($id);

        return $this->success($webhook, 'Webhook details retrieved successfully');
    }

    /**
     * Create a new webhook.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return $this->forbidden('Admin access required.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'secret' => 'nullable|string|max:255',
            'events' => 'required|array|min:1',
            'events.*' => 'required|string|in:' . implode(',', array_keys(Webhook::availableEvents())),
            'active' => 'boolean',
            'verify_ssl' => 'boolean',
            'timeout_seconds' => 'integer|min:5|max:120',
            'max_retries' => 'integer|min:1|max:10',
        ]);

        $webhook = Webhook::create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $validated['secret'] ?? Str::random(32),
            'events' => $validated['events'],
            'active' => $validated['active'] ?? true,
            'verify_ssl' => $validated['verify_ssl'] ?? true,
            'timeout_seconds' => $validated['timeout_seconds'] ?? 30,
            'max_retries' => $validated['max_retries'] ?? 3,
            'created_by' => $user->id,
        ]);

        return $this->success($webhook->fresh(), 'Webhook created successfully', [], 201);
    }

    /**
     * Update a webhook.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return $this->forbidden('Admin access required.');
        }

        $webhook = Webhook::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'url' => 'sometimes|required|url|max:2048',
            'secret' => 'nullable|string|max:255',
            'events' => 'sometimes|required|array|min:1',
            'events.*' => 'required|string|in:' . implode(',', array_keys(Webhook::availableEvents())),
            'active' => 'boolean',
            'verify_ssl' => 'boolean',
            'timeout_seconds' => 'integer|min:5|max:120',
            'max_retries' => 'integer|min:1|max:10',
        ]);

        $webhook->update($validated);

        return $this->success($webhook->fresh(), 'Webhook updated successfully');
    }

    /**
     * Delete a webhook.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return $this->forbidden('Admin access required.');
        }

        $webhook = Webhook::findOrFail($id);
        $webhook->delete();

        return $this->success(null, 'Webhook deleted successfully');
    }

    /**
     * Test webhook connection.
     */
    public function test(int $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return $this->forbidden('Admin access required.');
        }

        $webhook = Webhook::findOrFail($id);

        $testPayload = [
            'event' => 'webhook.test',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'message' => 'This is a test webhook from Maids.ng',
                'webhook_id' => $webhook->id,
                'webhook_name' => $webhook->name,
            ],
        ];

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_type' => 'webhook.test',
            'payload' => $testPayload,
            'status' => 'pending',
        ]);

        $success = $this->webhookService->deliver($delivery);

        if ($success) {
            return $this->success([
                'delivery' => $delivery->fresh(),
            ], 'Webhook test successful');
        }

        return $this->error($delivery->error_message ?? 'Webhook test failed', 400);
    }

    /**
     * Get webhook deliveries.
     */
    public function deliveries(int $id, Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return $this->forbidden('Admin access required.');
        }

        $webhook = Webhook::findOrFail($id);

        $query = WebhookDelivery::where('webhook_id', $webhook->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        $deliveries = $query->paginate($request->per_page ?? 20);

        return $this->paginated($deliveries, 'Deliveries retrieved successfully');
    }

    /**
     * Retry a failed delivery.
     */
    public function retryDelivery(int $deliveryId): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return $this->forbidden('Admin access required.');
        }

        $success = $this->webhookService->retryDelivery($deliveryId);

        if ($success) {
            return $this->success(null, 'Delivery retried successfully');
        }

        return $this->error('Failed to retry delivery', 400);
    }

    /**
     * Get webhook statistics.
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return $this->forbidden('Admin access required.');
        }

        return $this->success($this->webhookService->getStatistics(), 'Statistics retrieved successfully');
    }

    /**
     * Get available events.
     */
    public function availableEvents(): JsonResponse
    {
        return $this->success(Webhook::availableEvents(), 'Available events retrieved successfully');
    }
}