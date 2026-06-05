<?php

namespace App\Console\Commands;

use App\Services\QoreIDService;
use Illuminate\Console\Command;

class CheckQoreIdHealth extends Command
{
    protected $signature = 'qoreid:health';
    protected $description = 'Check QoreID API connectivity and NIN Premium product availability';

    public function handle(QoreIDService $qoreid): int
    {
        $this->info('Checking QoreID connectivity...');

        $result = $qoreid->healthCheck();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Healthy', $result['healthy'] ? '<info>Yes</info>' : '<error>No</error>'],
                ['Product Available', $result['product_available'] === true ? '<info>Yes</info>' : ($result['product_available'] === false ? '<error>No</error>' : '<comment>Unknown</comment>')],
                ['HTTP Status', (string) $result['status_code']],
                ['Error', $result['error'] ?? '<comment>None</comment>'],
                ['Client ID', substr(config('services.qoreid.client_id', ''), 0, 8) . '...' ?: '<error>Not set</error>'],
            ]
        );

        if (!$result['healthy']) {
            $this->warn('');
            $this->warn('QoreID is NOT healthy. Standalone verifications will be marked as "service_unavailable".');
            $this->warn('Fix: ensure QoreID client ID/secret are valid and NIN Premium product is subscribed.');
            return Command::FAILURE;
        }

        if (!$result['product_available']) {
            $this->warn('');
            $this->warn('QoreID is reachable but NIN Premium product is NOT available for this account.');
            $this->warn('Verifications will fail with 403/404. Verify your product subscription at https://dashboard.qoreid.com');
            return Command::FAILURE;
        }

        $this->info('');
        $this->info('QoreID is healthy and NIN Premium product is available.');
        return Command::SUCCESS;
    }
}
