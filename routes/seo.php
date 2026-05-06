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

Route::middleware(['web'])->group(function () {

    Route::get('/locations',               [SeoLocationController::class, 'hub'])->name('seo.locations');
    Route::get('/locations/{city}',        [SeoLocationController::class, 'city'])->name('seo.location.city');
    Route::get('/locations/{city}/{area}', [SeoLocationController::class, 'area'])->name('seo.location.area');

    Route::get('/find/{service}',                    [SeoServiceController::class, 'hub'])->name('seo.service');
    Route::get('/find/{service}-in-{city}',          [SeoServiceController::class, 'city'])->name('seo.service.city');
    Route::get('/find/{service}-in-{area}-{city}',   [SeoServiceController::class, 'area'])->name('seo.service.area');

    Route::get('/hire/{service}',                           [SeoGuideController::class, 'hire'])->name('seo.hire');
    Route::get('/guide/how-much-does-a-{service}-cost-in-{city}', [SeoGuideController::class, 'price'])->name('seo.price');
    Route::get('/salary/{service}-in-{city}',               [SeoGuideController::class, 'salary'])->name('seo.salary');
    Route::get('/guide/{slug}',                             [SeoGuideController::class, 'guide'])->name('seo.guide');

    Route::get('/faq',          [SeoFaqController::class, 'hub'])->name('seo.faq');
    Route::get('/faq/{slug}',   [SeoFaqController::class, 'show'])->name('seo.faq.show');

    Route::get('/sitemap.xml',          [SeoSitemapController::class, 'index'])->name('sitemap.index');
    Route::get('/sitemap-locations.xml',[SeoSitemapController::class, 'locations'])->name('sitemap.locations');
    Route::get('/sitemap-services.xml', [SeoSitemapController::class, 'services'])->name('sitemap.services');
    Route::get('/sitemap-guides.xml',   [SeoSitemapController::class, 'guides'])->name('sitemap.guides');
    Route::get('/sitemap-faqs.xml',     [SeoSitemapController::class, 'faqs'])->name('sitemap.faqs');

    Route::get('/robots.txt', [SeoSitemapController::class, 'robots'])->name('robots');

    Route::get('/api/seo/stats', [SeoStatsController::class, 'stats'])->name('seo.stats');
});
