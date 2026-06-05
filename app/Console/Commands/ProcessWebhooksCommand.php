<?php

namespace App\Console\Commands;

use App\Services\WebhookService;
use Illuminate\Console\Command;

class ProcessWebhooksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending webhook deliveries';

    protected WebhookService $webhookService;

    /**
     * Execute the console command.
     */
    public function handle(WebhookService $webhookService): int
    {
        $this->webhookService = $webhookService;

        $this->info('Processing pending webhook deliveries...');

        $processed = $this->webhookService->processPendingDeliveries();

        $this->info("Processed {$processed} webhook deliveries.");

        return Command::SUCCESS;
    }
}