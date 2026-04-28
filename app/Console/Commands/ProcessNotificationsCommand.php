<?php

namespace App\Console\Commands;

use App\Services\SmartNotificationService;
use Illuminate\Console\Command;

class ProcessNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:process-notifications
                            {--batch=100 : Number of notifications to process per run}
                            {--retry : Also retry failed notifications}';

    /**
     * The console command description.
     */
    protected $description = 'Process pending notifications scheduled for delivery';

    protected SmartNotificationService $notificationService;

    public function __construct(SmartNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Processing pending notifications...');

        $batchSize = $this->option('batch');

        // Process pending notifications
        $processed = $this->notificationService->processPendingNotifications($batchSize);
        $this->info("Processed {$processed} notifications.");

        // Retry failed notifications if requested
        if ($this->option('retry')) {
            $this->info('Retrying failed notifications...');
            $retried = $this->notificationService->retryFailedNotifications(50);
            $this->info("Retried {$retried} failed notifications.");
        }

        // Show statistics
        $stats = $this->notificationService->getNotificationStatistics();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total', $stats['total']],
                ['Pending', $stats['pending']],
                ['Sent', $stats['sent']],
                ['Failed', $stats['failed']],
            ]
        );

        return Command::SUCCESS;
    }
}
