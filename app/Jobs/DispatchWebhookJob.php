<?php

namespace App\Jobs;

use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $webhook;
    public $event;
    public $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(Webhook $webhook, string $event, array $payload)
    {
        $this->webhook = $webhook;
        $this->event = $event;
        $this->payload = $payload;

        // Set job requirements based on webhook settings
        $this->tries = $webhook->max_retries > 0 ? $webhook->max_retries : 1;
        $this->timeout = $webhook->timeout;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->webhook->is_active) {
            return; // Don't process if disabled
        }

        $payload = [
            'id' => Str::uuid()->toString(),
            'event' => $this->event,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload,
        ];

        $request = Http::timeout($this->webhook->timeout)
            ->withOptions([
                'verify' => $this->webhook->verify_ssl,
            ]);

        // Add signature if secret is provided
        if (!empty($this->webhook->secret)) {
            $signature = hash_hmac('sha256', json_encode($payload), $this->webhook->secret);
            $request = $request->withHeaders([
                'X-Signature' => $signature,
            ]);
        }

        try {
            $response = $request->post($this->webhook->url, $payload);

            if ($response->failed()) {
                Log::warning("Webhook dispatch failed for URL {$this->webhook->url} with status {$response->status()}");
                $this->release(60 * $this->attempts()); // Backoff strategy
            }
        } catch (\Exception $e) {
            Log::error("Webhook dispatch exception for URL {$this->webhook->url}: " . $e->getMessage());
            $this->release(60 * $this->attempts()); // Backoff strategy
        }
    }
}
