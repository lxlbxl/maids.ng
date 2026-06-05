<?php

namespace App\Jobs;

use App\Models\SeoPage;
use App\Services\SeoContentGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class RefreshSeoContent implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 1800;

    public function handle(SeoContentGenerator $generator): void
    {
        $pages = SeoPage::where('page_status', '!=', 'redirected')
            ->where(function ($q) {
                $q->whereNull('content_generated_at')
                  ->orWhere('content_generated_at', '<', now()->subDays(90));
            })
            ->orderByRaw("FIELD(page_type, 'service_area', 'service_city', 'hire_guide', 'price_guide', 'salary_guide', 'location_city', 'location_area')")
            ->limit(50)
            ->get();

        $processed = 0;

        foreach ($pages as $page) {
            try {
                $generator->generate($page);
                $processed++;
            } catch (\Throwable $e) {
                Log::error("SeoContentRefresh failed for page {$page->id}: " . $e->getMessage());
            }
        }

        Log::info("SeoContentRefresh: processed {$processed} pages.");
    }
}
