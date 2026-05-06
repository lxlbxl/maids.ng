<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\{SeoLocation, SeoPage, SeoService};
use App\Services\{SeoSchemaBuilder, SeoInternalLinker};
use Illuminate\Http\Response;

class SeoServiceController extends Controller
{
    public function hub(string $serviceSlug)
    {
        $service = SeoService::where('slug', $serviceSlug)->where('is_active', true)->first();

        if (!$service) {
            abort(404);
        }

        $page = SeoPage::where('page_type', 'service_hub')
            ->where('service_id', $service->id)
            ->first();

        if (!$page || $page->page_status === 'redirected') {
            abort(404);
        }

        $schemaBuilder = app(SeoSchemaBuilder::class);
        $page->schema_markup = $schemaBuilder->build($page);

        $cityPages = SeoPage::where('page_type', 'service_city')
            ->where('service_id', $service->id)
            ->where('page_status', 'published')
            ->with('location')
            ->get();

        return response()->view('seo.service-hub', compact('page', 'service', 'cityPages'));
    }

    public function city(string $serviceSlug, string $locationSlug)
    {
        $service = SeoService::where('slug', $serviceSlug)->where('is_active', true)->first();
        $city = SeoLocation::where('type', 'city')->where('slug', $locationSlug)->where('is_active', true)->first();

        if (!$service || !$city) {
            abort(404);
        }

        $page = SeoPage::where('page_type', 'service_city')
            ->where('service_id', $service->id)
            ->where('location_id', $city->id)
            ->first();

        if (!$page || $page->page_status === 'redirected') {
            abort(404);
        }

        $salary = $service->getSalaryForCity($city->slug);
        $schemaBuilder = app(SeoSchemaBuilder::class);
        $internalLinker = app(SeoInternalLinker::class);

        $page->schema_markup = $schemaBuilder->build($page);

        $nearbyPages = collect();
        if (!empty($page->content_blocks)) {
            $nearbyPages = $internalLinker->getNearbyPages($page);
        }

        $quizUrl = $internalLinker->quizUrl($page);

        if ($page->page_status === 'noindex') {
            return response()->view('seo.service-area', compact('page', 'service', 'city', 'salary', 'nearbyPages', 'quizUrl'), 200)
                ->header('X-Robots-Tag', 'noindex');
        }

        return response()->view('seo.service-area', compact('page', 'service', 'city', 'salary', 'nearbyPages', 'quizUrl'));
    }

    public function area(string $serviceSlug, string $areaSlug, string $citySlug)
    {
        $service = SeoService::where('slug', $serviceSlug)->where('is_active', true)->first();
        $area = SeoLocation::where('type', 'area')
            ->where('slug', $areaSlug)
            ->whereHas('parent', fn($q) => $q->where('slug', $citySlug))
            ->where('is_active', true)
            ->with('parent')
            ->first();

        if (!$service || !$area) {
            abort(404);
        }

        $page = SeoPage::where('page_type', 'service_area')
            ->where('service_id', $service->id)
            ->where('location_id', $area->id)
            ->first();

        if (!$page || $page->page_status === 'redirected') {
            abort(404);
        }

        $salary = $service->getSalaryForCity($area->parent->slug);
        $schemaBuilder = app(SeoSchemaBuilder::class);
        $internalLinker = app(SeoInternalLinker::class);

        $page->schema_markup = $schemaBuilder->build($page);

        $nearbyPages = collect();
        if (!empty($page->content_blocks)) {
            $nearbyPages = $internalLinker->getNearbyPages($page);
        }

        $quizUrl = $internalLinker->quizUrl($page);

        if ($page->page_status === 'noindex') {
            return response()->view('seo.service-area', compact('page', 'service', 'area', 'salary', 'nearbyPages', 'quizUrl'), 200)
                ->header('X-Robots-Tag', 'noindex');
        }

        return response()->view('seo.service-area', compact('page', 'service', 'area', 'salary', 'nearbyPages', 'quizUrl'));
    }
}
