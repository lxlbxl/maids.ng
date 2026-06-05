<?php

namespace App\Jobs;

use App\Services\EmailPoller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * PollInboundEmail Job
 * 
 * Periodically polls configured email inboxes for new messages.
 * Dispatched by the Laravel scheduler every 5 minutes.
 * 
 * Usage:
 *   - Schedule: $schedule->job(new PollInboundEmail())->everyFiveMinutes();
 *   - Manual: php artisan queue:work --queue=email-polling
 */
class PollInboundEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job on failure.
     */
    public int $tries = 2;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = [30, 60];

    /**
     * Maximum seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('email-polling');
    }

    /**
     * Execute the job.
     */
    public function handle(EmailPoller $poller): void
    {
        Log::info('Starting email poll job');

        try {
            $results = $poller->poll();

            Log::info('Email poll job completed', [
                'emails_processed' => $results['emails_processed'] ?? 0,
                'errors' => $results['errors'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('Email poll job failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}