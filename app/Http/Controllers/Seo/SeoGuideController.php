<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\{SeoLocation, SeoPage, SeoService};
use App\Services\SeoSchemaBuilder;
use Illuminate\Http\Response;

class SeoGuideController extends Controller
{
    public function hire(string $serviceSlug)
    {
        $service = SeoService::where('slug', $serviceSlug)->where('is_active', true)->first();

        if (!$service) {
            abort(404);
        }

        $page = SeoPage::where('page_type', 'hire_guide')
            ->where('service_id', $service->id)
            ->first();

        if (!$page || in_array($page->page_status, ['redirected'])) {
            abort(404);
        }

        $schemaBuilder = app(SeoSchemaBuilder::class);
        $page->schema_markup = $schemaBuilder->build($page);

        return response()->view('seo.guide-hire', compact('page', 'service'));
    }

    public function price(string $serviceSlug, string $locationSlug)
    {
        $service = SeoService::where('slug', $serviceSlug)->where('is_active', true)->first();
        $city = SeoLocation::where('type', 'city')->where('slug', $locationSlug)->where('is_active', true)->first();

        if (!$service || !$city) {
            abort(404);
        }

        $page = SeoPage::where('page_type', 'price_guide')
            ->where('service_id', $service->id)
            ->where('location_id', $city->id)
            ->first();

        if (!$page || in_array($page->page_status, ['redirected'])) {
            abort(404);
        }

        $salary = $service->getSalaryForCity($city->slug);
        $schemaBuilder = app(SeoSchemaBuilder::class);
        $page->schema_markup = $schemaBuilder->build($page);

        if ($page->page_status === 'noindex') {
            return response()->view('seo.guide-price', compact('page', 'service', 'city', 'salary'), 200)
                ->header('X-Robots-Tag', 'noindex');
        }

        return response()->view('seo.guide-price', compact('page', 'service', 'city', 'salary'));
    }

    public function salary(string $serviceSlug, string $locationSlug)
    {
        $service = SeoService::where('slug', $serviceSlug)->where('is_active', true)->first();
        $city = SeoLocation::where('type', 'city')->where('slug', $locationSlug)->where('is_active', true)->first();

        if (!$service || !$city) {
            abort(404);
        }

        $page = SeoPage::where('page_type', 'salary_guide')
            ->where('service_id', $service->id)
            ->where('location_id', $city->id)
            ->first();

        if (!$page || in_array($page->page_status, ['redirected'])) {
            abort(404);
        }

        $salary = $service->getSalaryForCity($city->slug);
        $schemaBuilder = app(SeoSchemaBuilder::class);
        $page->schema_markup = $schemaBuilder->build($page);

        if ($page->page_status === 'noindex') {
            return response()->view('seo.guide-salary', compact('page', 'service', 'city', 'salary'), 200)
                ->header('X-Robots-Tag', 'noindex');
        }

        return response()->view('seo.guide-salary', compact('page', 'service', 'city', 'salary'));
    }

    public function guide(string $slug)
    {
        $page = SeoPage::where('page_type', 'evergreen_guide')
            ->where('url_path', "/guide/{$slug}/")
            ->first();

        if (!$page || in_array($page->page_status, ['redirected'])) {
            abort(404);
        }

        return response()->view('seo.guide-evergreen', compact('page'));
    }
}
