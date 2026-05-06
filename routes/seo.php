<?php

use App\Http\Controllers\Seo\{
    SeoLocationController,
    SeoServiceController,
    SeoGuideController,
    SeoFaqController,
    SeoSitemapController,
    SeoStatsController,
};
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:120,1', 'cache.headers:public;max_age=86400,s-maxage=86400'])->group(function () {

    Route::get('/robots.txt', [SeoSitemapController::class, 'robots'])->name('robots');
    Route::get('/sitemap.xml',          [SeoSitemapController::class, 'index'])->name('sitemap.index');
    Route::get('/sitemap-main.xml',     [SeoSitemapController::class, 'mainPages'])->name('sitemap.main');
    Route::get('/sitemap-locations.xml',[SeoSitemapController::class, 'locations'])->name('sitemap.locations');
    Route::get('/sitemap-services.xml', [SeoSitemapController::class, 'services'])->name('sitemap.services');
    Route::get('/sitemap-guides.xml',   [SeoSitemapController::class, 'guides'])->name('sitemap.guides');
    Route::get('/sitemap-faqs.xml',     [SeoSitemapController::class, 'faqs'])->name('sitemap.faqs');

    Route::get('/locations',               [SeoLocationController::class, 'hub'])->name('seo.locations');
    Route::get('/locations/{city}',        [SeoLocationController::class, 'city'])->name('seo.location.city');
    Route::get('/locations/{city}/{area}', [SeoLocationController::class, 'area'])->name('seo.location.area');

    Route::get('/find/{serviceSlug}',                        [SeoServiceController::class, 'hub'])->name('seo.service')
        ->where('serviceSlug', '[a-z0-9-]+');
    Route::get('/find/{serviceSlug}-in-{locationSlug}',     [SeoServiceController::class, 'city'])->name('seo.service.city')
        ->where('serviceSlug', '[a-z0-9-]+')->where('locationSlug', '[a-z]+');
    Route::get('/find/{serviceSlug}-in-{areaSlug}-{citySlug}', [SeoServiceController::class, 'area'])->name('seo.service.area')
        ->where('serviceSlug', '[a-z0-9-]+')->where('areaSlug', '[a-z]+')->where('citySlug', '[a-z]+');

    Route::get('/hire/{serviceSlug}',                              [SeoGuideController::class, 'hire'])->name('seo.hire')
        ->where('serviceSlug', '[a-z0-9-]+');
    Route::get('/guide/how-much-does-a-{serviceSlug}-cost-in-{locationSlug}', [SeoGuideController::class, 'price'])->name('seo.price')
        ->where('serviceSlug', '[a-z0-9-]+')->where('locationSlug', '[a-z]+');
    Route::get('/salary/{serviceSlug}-in-{locationSlug}',          [SeoGuideController::class, 'salary'])->name('seo.salary')
        ->where('serviceSlug', '[a-z0-9-]+')->where('locationSlug', '[a-z]+');
    Route::get('/guide/{slug}',                                    [SeoGuideController::class, 'guide'])->name('seo.guide');

    Route::get('/faq',          [SeoFaqController::class, 'hub'])->name('seo.faq');
    Route::get('/faq/{slug}',   [SeoFaqController::class, 'show'])->name('seo.faq.show');

    Route::get('/api/seo/stats', [SeoStatsController::class, 'stats'])->name('seo.stats');
});
