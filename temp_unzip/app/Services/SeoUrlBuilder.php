<?php

namespace App\Services;

use App\Models\{SeoLocation, SeoService};

class SeoUrlBuilder
{
    public function locationCity(SeoLocation $city): string
    {
        return "/locations/{$city->slug}/";
    }

    public function locationArea(SeoLocation $area): string
    {
        return "/locations/{$area->parent->slug}/{$area->slug}/";
    }

    public function serviceHub(SeoService $service): string
    {
        return "/find/{$service->slug}/";
    }

    public function serviceCity(SeoService $service, SeoLocation $city): string
    {
        return "/find/{$service->slug}-in-{$city->slug}/";
    }

    public function serviceArea(SeoService $service, SeoLocation $area): string
    {
        $citySlug = $area->parent->slug;
        return "/find/{$service->slug}-in-{$area->slug}-{$citySlug}/";
    }

    public function priceGuide(SeoService $service, SeoLocation $city): string
    {
        return "/guide/how-much-does-a-{$service->slug}-cost-in-{$city->slug}/";
    }

    public function hireGuide(SeoService $service): string
    {
        return "/hire/{$service->slug}/";
    }

    public function salaryGuide(SeoService $service, SeoLocation $city): string
    {
        return "/salary/{$service->slug}-in-{$city->slug}/";
    }

    public function faq(string $slug): string
    {
        return "/faq/{$slug}/";
    }

    public function fullUrl(string $path): string
    {
        return rtrim(config('app.url'), '/') . $path;
    }
}
