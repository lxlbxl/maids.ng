<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminWebhookController extends Controller
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Display the webhooks management page.
     */
    public function index(Request $request)
    {
        $webhooks = Webhook::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $statistics = $this->webhookService->getStatistics();

        return Inertia::render('Admin/Webhooks', [
            'initialWebhooks' => $webhooks,
            'statistics' => $statistics,
        ]);
    }
}