<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\{SeoLocation, SeoPage};
use App\Services\{SeoSchemaBuilder, SeoInternalLinker};
use Illuminate\Http\Request;

class SeoLocationController extends Controller
{
    public function hub()
    {
        $cities = SeoLocation::where('type', 'city')
            ->where('is_active', true)
            ->orderBy('tier')
            ->orderBy('name')
            ->get();

        return response()->view('seo.locations.hub', compact('cities'));
    }

    public function city(string $citySlug)
    {
        $city = SeoLocation::where('type', 'city')
            ->where('slug', $citySlug)
            ->where('is_active', true)
            ->first();

        if (!$city) {
            abort(404);
        }

        $page = SeoPage::where('page_type', 'location_city')
            ->where('location_id', $city->id)
            ->first();

        if (!$page || $page->page_status === 'redirected') {
            abort(404);
        }

        $schemaBuilder = app(SeoSchemaBuilder::class);
        $page->schema_markup = $schemaBuilder->build($page);

        $areas = SeoLocation::where('type', 'area')
            ->where('parent_id', $city->id)
            ->where('is_active', true)
            ->get();

        $servicePages = SeoPage::where('page_type', 'service_city')
            ->where('location_id', $city->id)
            ->whereIn('page_status', ['published', 'draft'])
            ->with('service')
            ->get();

        if ($page->page_status === 'noindex') {
            return response()->view('seo.locations.city', compact('page', 'city', 'areas', 'servicePages'), 200)
                ->header('X-Robots-Tag', 'noindex');
        }

        return response()->view('seo.locations.city', compact('page', 'city', 'areas', 'servicePages'));
    }

    public function area(string $citySlug, string $areaSlug)
    {
        $area = SeoLocation::where('type', 'area')
            ->where('slug', $areaSlug)
            ->whereHas('parent', fn($q) => $q->where('slug', $citySlug))
            ->where('is_active', true)
            ->with('parent')
            ->first();

        if (!$area) {
            abort(404);
        }

        $page = SeoPage::where('page_type', 'location_area')
            ->where('location_id', $area->id)
            ->first();

        if (!$page || $page->page_status === 'redirected') {
            abort(404);
        }

        $schemaBuilder = app(SeoSchemaBuilder::class);
        $page->schema_markup = $schemaBuilder->build($page);

        $nearbyAreas = SeoLocation::where('type', 'area')
            ->where('parent_id', $area->parent->id)
            ->where('id', '!=', $area->id)
            ->where('is_active', true)
            ->take(6)
            ->get();

        if ($page->page_status === 'noindex') {
            return response()->view('seo.locations.area', compact('page', 'area', 'nearbyAreas'), 200)
                ->header('X-Robots-Tag', 'noindex');
        }

        return response()->view('seo.locations.area', compact('page', 'area', 'nearbyAreas'));
    }
}
