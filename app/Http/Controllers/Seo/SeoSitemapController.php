<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use Illuminate\Http\Response;

class SeoSitemapController extends Controller
{
    public function index(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        $sitemaps = [
            route('sitemap.main'),
            route('sitemap.locations'),
            route('sitemap.services'),
            route('sitemap.guides'),
            route('sitemap.faqs'),
        ];

        foreach ($sitemaps as $url) {
            $xml .= "<sitemap><loc>{$url}</loc><lastmod>" . now()->format('Y-m-d') . "</lastmod></sitemap>";
        }

        $xml .= '</sitemapindex>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function mainPages(): Response
    {
        $now = now()->format('Y-m-d');

        $staticPages = [
            ['url' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['url' => url('/about'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['url' => url('/contact'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['url' => url('/blog'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['url' => url('/terms'), 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => url('/privacy'), 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => url('/verify-service'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['url' => url('/maids'), 'priority' => '0.8', 'changefreq' => 'daily'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($staticPages as $page) {
            $xml .= '<url>';
            $xml .= '<loc>' . $page['url'] . '</loc>';
            $xml .= '<lastmod>' . $now . '</lastmod>';
            $xml .= '<changefreq>' . $page['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $page['priority'] . '</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function services(): Response
    {
        $pages = SeoPage::whereIn('page_type', ['service_hub', 'service_city', 'service_area'])
            ->where('page_status', 'published')
            ->orderBy('page_type')
            ->get(['url_path', 'updated_at', 'page_type']);

        return $this->buildSitemap($pages, [
            'service_area' => '0.9',
            'service_city' => '0.8',
            'service_hub'  => '0.7',
        ]);
    }

    public function locations(): Response
    {
        $pages = SeoPage::whereIn('page_type', ['location_city', 'location_area'])
            ->where('page_status', 'published')
            ->get(['url_path', 'updated_at', 'page_type']);

        return $this->buildSitemap($pages, [
            'location_city' => '0.7',
            'location_area' => '0.6',
        ]);
    }

    public function guides(): Response
    {
        $pages = SeoPage::whereIn('page_type', ['hire_guide', 'price_guide', 'salary_guide'])
            ->where('page_status', 'published')
            ->get(['url_path', 'updated_at', 'page_type']);

        return $this->buildSitemap($pages, [
            'hire_guide'   => '0.8',
            'price_guide'  => '0.8',
            'salary_guide' => '0.7',
        ]);
    }

    public function faqs(): Response
    {
        $pages = SeoPage::where('page_type', 'faq')
            ->where('page_status', 'published')
            ->get(['url_path', 'updated_at']);

        return $this->buildSitemap($pages, ['faq' => '0.5']);
    }

    public function robots(): Response
    {
        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /api/\n";
        $content .= "Disallow: /agent/\n";
        $content .= "Disallow: /webhooks/\n";
        $content .= "Disallow: /debug_settings.php\n";
        $content .= "Disallow: /install.php\n";
        $content .= "Disallow: /deploy.php\n";
        $content .= "Disallow: /deploy-all\n";
        $content .= "Disallow: /deploy-fix-db\n";
        $content .= "Disallow: /composer.phar\n\n";
        $content .= "# Sitemap index\n";
        $content .= "Sitemap: " . url('/sitemap.xml') . "\n\n";
        $content .= "# AI crawlers — allow full access\n";
        $content .= "User-agent: GPTBot\nAllow: /\n\n";
        $content .= "User-agent: PerplexityBot\nAllow: /\n\n";
        $content .= "User-agent: ClaudeBot\nAllow: /\n\n";
        $content .= "User-agent: anthropic-ai\nAllow: /\n\n";
        $content .= "User-agent: Google-Extended\nAllow: /\n";

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }

    private function buildSitemap($pages, array $priorities): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($pages as $page) {
            $priority = $priorities[$page->page_type] ?? '0.5';
            $xml .= '<url>';
            $xml .= '<loc>' . url($page->url_path) . '</loc>';
            $xml .= '<lastmod>' . $page->updated_at->format('Y-m-d') . '</lastmod>';
            $xml .= '<changefreq>monthly</changefreq>';
            $xml .= "<priority>{$priority}</priority>";
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
