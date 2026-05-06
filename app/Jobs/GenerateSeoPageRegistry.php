<?php

namespace App\Jobs;

use App\Models\{SeoLocation, SeoPage, SeoService};
use App\Services\SeoUrlBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class GenerateSeoPageRegistry implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 600;

    public function handle(SeoUrlBuilder $urls): void
    {
        $services  = SeoService::where('is_active', true)->get();
        $cities    = SeoLocation::where('type', 'city')->where('is_active', true)->get();
        $areas     = SeoLocation::where('type', 'area')->where('is_active', true)->with('parent')->get();

        $created = 0;

        // 1. City hub pages
        foreach ($cities as $city) {
            $this->upsert([
                'page_type'   => 'location_city',
                'url_path'    => $urls->locationCity($city),
                'location_id' => $city->id,
                'service_id'  => null,
                'h1'          => "Verified Domestic Staff in {$city->name}",
                'meta_title'  => "Hire Domestic Staff in {$city->name} | Maids.ng",
                'meta_description' => "Find verified housekeepers, nannies, cooks, and drivers in {$city->name}. NIN-verified staff. 10-day guarantee. Start free today.",
            ]);
            $created++;
        }

        // 2. Area hub pages
        foreach ($areas as $area) {
            $this->upsert([
                'page_type'   => 'location_area',
                'url_path'    => $urls->locationArea($area),
                'location_id' => $area->id,
                'service_id'  => null,
                'h1'          => "Domestic Staff in {$area->name}, {$area->parent->name}",
                'meta_title'  => "Hire Domestic Staff in {$area->name} | Maids.ng",
                'meta_description' => "Find verified housekeepers, nannies, and cooks in {$area->name}, {$area->parent->name}. Background-checked. 10-day guarantee.",
            ]);
            $created++;
        }

        // 3. Service hub pages
        foreach ($services as $service) {
            $this->upsert([
                'page_type'   => 'service_hub',
                'url_path'    => $urls->serviceHub($service),
                'location_id' => null,
                'service_id'  => $service->id,
                'h1'          => "Hire a Verified {$service->name} in Nigeria",
                'meta_title'  => "Hire a {$service->name} in Nigeria | Maids.ng",
                'meta_description' => "Find verified {$service->plural} near you. NIN-verified. Background checked. 10-day money-back guarantee. From ₦" . number_format($service->salary_min) . "/month.",
            ]);
            $created++;
        }

        // 4. Service × City pages
        foreach ($services as $service) {
            foreach ($cities as $city) {
                $salary = $service->getSalaryForCity($city->slug);
                $this->upsert([
                    'page_type'   => 'service_city',
                    'url_path'    => $urls->serviceCity($service, $city),
                    'location_id' => $city->id,
                    'service_id'  => $service->id,
                    'h1'          => "Hire a Verified {$service->name} in {$city->name}",
                    'meta_title'  => "Hire a {$service->name} in {$city->name} | Maids.ng",
                    'meta_description' => "Find NIN-verified {$service->plural} in {$city->name} from ₦" . number_format($salary['min']) . "/month. 10-day guarantee. Match in minutes.",
                ]);
                $created++;
            }
        }

        // 5. Service × Area pages (MONEY PAGES)
        foreach ($services as $service) {
            foreach ($areas as $area) {
                if ($area->parent->tier > 1) {
                    continue;
                }
                $salary = $service->getSalaryForCity($area->parent->slug);
                $this->upsert([
                    'page_type'   => 'service_area',
                    'url_path'    => $urls->serviceArea($service, $area),
                    'location_id' => $area->id,
                    'service_id'  => $service->id,
                    'h1'          => "Hire a {$service->name} in {$area->name}, {$area->parent->name}",
                    'meta_title'  => "{$service->name} in {$area->name}, {$area->parent->name} | Maids.ng",
                    'meta_description' => "Find a verified {$service->name} in {$area->name}. NIN-checked. From ₦" . number_format($salary['min']) . "/month. 10-day guarantee.",
                ]);
                $created++;
            }
        }

        // 6. Price guide pages
        foreach ($services as $service) {
            foreach ($cities as $city) {
                $salary = $service->getSalaryForCity($city->slug);
                $this->upsert([
                    'page_type'   => 'price_guide',
                    'url_path'    => (new SeoUrlBuilder)->priceGuide($service, $city),
                    'location_id' => $city->id,
                    'service_id'  => $service->id,
                    'h1'          => "How Much Does a {$service->name} Cost in {$city->name}? (2025)",
                    'meta_title'  => "{$service->name} Salary in {$city->name} 2025 | Maids.ng",
                    'meta_description' => "Complete guide to {$service->slug} salaries in {$city->name} 2025. Average: ₦" . number_format($salary['min']) . "–₦" . number_format($salary['max']) . "/month. Plus tips on fair pay.",
                ]);
                $created++;
            }
        }

        // 7. Hire guides
        foreach ($services as $service) {
            $this->upsert([
                'page_type'  => 'hire_guide',
                'url_path'   => $urls->hireGuide($service),
                'service_id' => $service->id,
                'h1'         => "How to Hire a {$service->name} in Nigeria (Complete Guide 2025)",
                'meta_title' => "How to Hire a {$service->name} in Nigeria | Maids.ng Guide",
                'meta_description' => "Step-by-step guide to hiring a {$service->slug} in Nigeria. What to look for, how much to pay, how to verify documents, and where to find trusted staff.",
            ]);
            $created++;
        }

        Log::info("SeoPageRegistry: created/updated {$created} pages.");
    }

    private function upsert(array $data): void
    {
        SeoPage::updateOrCreate(
            ['url_path' => $data['url_path']],
            array_merge($data, ['page_status' => 'draft'])
        );
    }
}
