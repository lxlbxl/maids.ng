<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\{SeoLocation, SeoPage, SeoService};
use App\Services\SeoSchemaBuilder;

class SeoGuideController extends Controller
{
    public function hire($serviceSlug)
    {
        $service = SeoService::where('slug', $serviceSlug)->where('is_active', true)->firstOrFail();

        $page = SeoPage::where('page_type', 'hire_guide')
            ->where('service_id', $service->id)
            ->first();

        if (!$page) {
            abort(404);
        }

        $schemaBuilder = app(SeoSchemaBuilder::class);
        $page->schema_markup = $schemaBuilder->build($page);

        return response()->view('seo.guide-hire', compact('page', 'service'));
    }

    public function price($serviceSlug, $citySlug)
    {
        $service = SeoService::where('slug', $serviceSlug)->where('is_active', true)->firstOrFail();
        $city = SeoLocation::where('type', 'city')->where('slug', $citySlug)->where('is_active', true)->firstOrFail();

        $page = SeoPage::where('page_type', 'price_guide')
            ->where('service_id', $service->id)
            ->where('location_id', $city->id)
            ->first();

        if (!$page) {
            abort(404);
        }

        $salary = $service->getSalaryForCity($city->slug);
        $schemaBuilder = app(SeoSchemaBuilder::class);
        $page->schema_markup = $schemaBuilder->build($page);

        return response()->view('seo.guide-price', compact('page', 'service', 'city', 'salary'));
    }

    public function salary($serviceSlug, $citySlug)
    {
        $service = SeoService::where('slug', $serviceSlug)->where('is_active', true)->firstOrFail();
        $city = SeoLocation::where('type', 'city')->where('slug', $citySlug)->where('is_active', true)->firstOrFail();

        $page = SeoPage::where('page_type', 'salary_guide')
            ->where('service_id', $service->id)
            ->where('location_id', $city->id)
            ->first();

        if (!$page) {
            abort(404);
        }

        $salary = $service->getSalaryForCity($city->slug);
        $schemaBuilder = app(SeoSchemaBuilder::class);
        $page->schema_markup = $schemaBuilder->build($page);

        return response()->view('seo.guide-salary', compact('page', 'service', 'city', 'salary'));
    }

    public function guide($slug)
    {
        $page = SeoPage::where('page_type', 'evergreen_guide')
            ->where('url_path', "/guide/{$slug}/")
            ->first();

        if (!$page) {
            abort(404);
        }

        return response()->view('seo.guide-evergreen', compact('page'));
    }
}
