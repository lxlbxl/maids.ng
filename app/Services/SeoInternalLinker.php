<?php

namespace App\Services;

use App\Models\{SeoLocation, SeoPage, SeoService};

class SeoInternalLinker
{
    public function getNearbyPages(SeoPage $page, int $limit = 6): \Illuminate\Support\Collection
    {
        if (!$page->location || $page->location->type !== 'area') {
            return collect();
        }

        $city = $page->location->parent;

        return SeoPage::where('page_type', 'service_area')
            ->where('service_id', $page->service_id)
            ->where('id', '!=', $page->id)
            ->whereHas('location', fn($q) => $q->where('parent_id', $city->id))
            ->where('page_status', 'published')
            ->inRandomOrder()
            ->take($limit)
            ->with('location')
            ->get();
    }

    public function getCityServicePages(SeoLocation $city): \Illuminate\Support\Collection
    {
        return SeoPage::where('page_type', 'service_city')
            ->where('location_id', $city->id)
            ->where('page_status', 'published')
            ->with('service')
            ->get();
    }

    public function quizUrl(SeoPage $page): string
    {
        $params = [
            'utm_source'   => 'seo',
            'utm_medium'   => $page->page_type,
            'utm_campaign' => implode('-', array_filter([
                $page->service?->slug,
                $page->location?->slug,
            ])),
        ];

        if ($page->service) {
            $params['service'] = $page->service->slug;
        }
        if ($page->location) {
            $params['location'] = $page->location->slug;
        }

        return url('/') . '?' . http_build_query($params);
    }
}
