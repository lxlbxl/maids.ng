<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SeoPage;
use App\Models\Setting;
use App\Services\SeoContentMatrix;
use Illuminate\Support\Facades\File;

class GenerateStaticSeoPages extends Command
{
    protected $signature = 'seo:generate-static
                            {--dry-run : Show what would be generated without writing files}';

    protected $description = 'Generate static HTML SEO pages for all city × service combinations using unique, non-swappable content';

    private string $template;
    private string $outputDir;

    public function handle(): int
    {
        $this->outputDir = public_path('seo');
        $this->template = file_get_contents(public_path('seo/template.html'));

        $pages = SeoPage::where('page_type', 'service_city')
            ->whereIn('page_status', ['published', 'draft'])
            ->with(['location', 'service'])
            ->get();

        if ($pages->isEmpty()) {
            $this->warn('No service_city pages found in database.');
            return 1;
        }

        $this->info("Found {$pages->count()} pages to generate.");

        $generated = 0;
        foreach ($pages as $page) {
            if ($this->generatePage($page)) {
                $generated++;
            }
        }

        $this->info("Done. Generated: {$generated} pages.");

        if ($this->option('dry-run')) {
            $this->warn('Dry run - no files were written.');
        }

        return 0;
    }

    private function generatePage(SeoPage $page): bool
    {
        $location = $page->location;
        $service = $page->service;

        if (!$location || !$service) {
            $this->warn("  Skipping page {$page->id} - missing location or service");
            return false;
        }

        $citySlug = $location->slug;
        $serviceSlug = $service->slug;
        $salary = $service->getSalaryForCity($citySlug);

        $pageDir = "{$this->outputDir}/{$serviceSlug}";
        $outputPath = "{$pageDir}/{$citySlug}.html";

        $urlPath = $page->url_path ?? "/find/{$serviceSlug}-in-{$citySlug}/";
        $canonicalUrl = "https://maids.ng" . $urlPath;

        $h1 = $page->h1 ?? "{$service->name} in {$location->name}";
        $metaTitle = $page->meta_title ?? "{$service->name} in {$location->name} | Maids.ng";
        $servicePlural = $service->plural ?? $service->name . 's';
        $metaDesc = $page->meta_description ?? "Find verified {$servicePlural} in {$location->name}. NIN-verified, AI-matched, backed by a 10-day money-back guarantee.";

        $matchingFee = number_format((int) Setting::get('matching_fee_amount', 20000));

        // Get genuinely unique content from the content matrix
        $cityData = SeoContentMatrix::cityData($citySlug);
        $serviceData = SeoContentMatrix::serviceData($serviceSlug);
        $faqs = SeoContentMatrix::serviceCityFaqs($serviceSlug, $citySlug);
        $localProof = SeoContentMatrix::buildLocalProof($serviceSlug, $citySlug);
        $proximity = SeoContentMatrix::buildProximity($serviceSlug, $citySlug);
        $hiringContext = SeoContentMatrix::buildHiringContext($serviceSlug, $citySlug);

        $pillarOne = $this->renderPillarOne($localProof, $cityData, $serviceData);
        $pillarTwo = $this->renderPillarTwo($faqs, $serviceData, $cityData);
        $pillarThree = $this->renderPillarThree($proximity, $cityData);
        $nearbySection = $this->buildNearbySection($page, $location, $service);

        $partTimeRow = '';
        if ($service->part_time_available) {
            $partTimeRow = '<tr><td>Part-time</td><td>&#8358;' . number_format((int) round($salary['min'] * 0.5)) . ' &#8211; &#8358;' . number_format((int) round($salary['min'] * 0.8)) . '</td></tr>';
        }

        $search = [
            '{{META_TITLE}}',
            '{{META_DESCRIPTION}}',
            '{{CANONICAL_URL}}',
            '{{OG_IMAGE}}',
            '{{SCHEMAS}}',
            '{{H1}}',
            '{{SERVICE_HUB_URL}}',
            '{{SERVICE_NAME}}',
            '{{SERVICE_PLURAL}}',
            '{{CITY_NAME}}',
            '{{CITY_FULL_NAME}}',
            '{{INTRO}}',
            '{{SHORT_DESC}}',
            '{{DUTIES}}',
            '{{LOCAL_CONTEXT}}',
            '{{PILLAR_ONE}}',
            '{{PILLAR_TWO}}',
            '{{PILLAR_THREE}}',
            '{{SALARY_SECTION}}',
            '{{SALARY_MIN}}',
            '{{SALARY_MAX}}',
            '{{SALARY_MAX_LIVE_IN}}',
            '{{SALARY_MIN_LIVE_OUT}}',
            '{{PART_TIME_ROW}}',
            '{{MATCHING_FEE}}',
            '{{WHY_MAIDS_NG}}',
            '{{VERIFICATION_CONTENT}}',
            '{{CTA_TEXT}}',
            '{{QUIZ_URL}}',
            '{{NEARBY_SECTION}}',
        ];

        $replace = [
            htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8'),
            $canonicalUrl,
            'https://maids.ng/maids-logo.png',
            $this->buildSchemas($page, $location, $service, $salary, $faqs),
            $h1,
            "https://maids.ng/find/{$serviceSlug}/",
            $service->name,
            $servicePlural,
            $location->name,
            $location->full_name,
            "Find verified {$servicePlural} in {$location->name} through Maids.ng. AI-matched, NIN-verified, backed by a 10-day money-back guarantee.",
            $service->short_description ?? $serviceData['short_description'],
            $service->duties ?? $serviceData['duties'],
            $hiringContext,
            $pillarOne,
            $pillarTwo,
            $pillarThree,
            "Salary ranges depend on experience, duties, and whether the position is live-in or live-out. Below are current market rates for {$location->full_name}.",
            number_format($salary['min']),
            number_format($salary['max']),
            number_format((int) round($salary['max'] * 0.7)),
            number_format((int) round($salary['min'] * 1.1)),
            $partTimeRow,
            $matchingFee,
            "Maids.ng uses AI matching and NIN verification to connect you with the right domestic staff. Every candidate is background-checked and verified.",
            "Always verify NIN, check references, and start with a trial period before committing to a full-time arrangement.",
            "Take our 5-minute quiz and get matched with verified {$servicePlural} in {$location->name} today.",
            "https://maids.ng/onboarding?utm_source=seo&service={$serviceSlug}&location={$citySlug}",
            $nearbySection,
        ];

        $html = str_replace($search, $replace, $this->template);

        if ($this->option('dry-run')) {
            $this->line("  [DRY] {$outputPath}");
            return true;
        }

        if (!File::isDirectory($pageDir)) {
            File::makeDirectory($pageDir, 0755, true);
        }

        File::put($outputPath, $html);
        $this->line("  Generated: {$outputPath}");

        return true;
    }

    private function renderPillarOne(string $localProof, array $cityData, array $serviceData): string
    {
        $neighborhoods = implode(', ', array_slice($cityData['neighborhoods'], 0, 3));
        $estates = implode(', ', array_slice($cityData['estates'], 0, 3));

        return '
    <section class="seo-section">
        <div class="container">
            <h2>Why Families in ' . $cityData['name'] . ' Choose Maids.ng</h2>
            <div class="local-proof">
                <h3>Trusted Local Service in ' . $cityData['name'] . '</h3>
                <p>' . $localProof . '</p>
                <ul>
                    <li>We serve homes in <strong>' . $neighborhoods . '</strong> and <strong>' . $estates . '</strong></li>
                    <li>Every ' . $serviceData['name'] . ' is NIN-verified and reference-checked before placement</li>
                    <li>We understand ' . $cityData['housing'] . '</li>
                </ul>
            </div>
        </div>
    </section>';
    }

    private function renderPillarTwo(array $faqs, array $serviceData, array $cityData): string
    {
        $faqItems = '';
        foreach (array_slice($faqs, 0, 5) as $faq) {
            $faqItems .= '
            <div class="faq-item" itemscope itemtype="https://schema.org/Question" itemprop="mainEntity">
                <h3 itemprop="name">' . htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8') . '</h3>
                <div itemscope itemtype="https://schema.org/Answer" itemprop="acceptedAnswer">
                    <p class="faq-short-answer" itemprop="text">' . htmlspecialchars($faq['short_answer'], ENT_QUOTES, 'UTF-8') . '</p>
                    <div class="faq-full-answer">
                        <p>' . htmlspecialchars($faq['full_answer'] ?? $faq['short_answer'], ENT_QUOTES, 'UTF-8') . '</p>
                    </div>
                </div>
            </div>';
        }

        return '
    <section class="seo-section seo-faq" id="faq">
        <div class="container">
            <h2>Frequently Asked Questions &#8212; ' . $serviceData['name'] . ' in ' . $cityData['name'] . '</h2>
            <div class="faq-list" itemscope itemtype="https://schema.org/FAQPage">' . $faqItems . '
            </div>
        </div>
    </section>';
    }

    private function renderPillarThree(string $proximity, array $cityData): string
    {
        $nearby = implode(', ', array_slice($cityData['nearby_areas'], 0, 4));

        return '
    <section class="seo-section">
        <div class="container">
            <div class="proximity-signals">
                <h3>Service Area: ' . $cityData['name'] . ' and Surrounding Areas</h3>
                <p>' . $proximity . '</p>
                <ul>
                    <li>Primary service areas: <strong>' . $nearby . '</strong></li>
                    <li>' . $cityData['commute'] . '</li>
                    <li>Request a match from any neighborhood in ' . $cityData['name'] . ' and we will connect you with verified staff familiar with the area.</li>
                </ul>
            </div>
        </div>
    </section>';
    }

    private function buildNearbySection(SeoPage $page, $location, $service): string
    {
        $nearbyPages = SeoPage::where('page_type', 'service_city')
            ->where('service_id', $service->id)
            ->where('location_id', '!=', $location->id)
            ->whereIn('page_status', ['published', 'draft'])
            ->with('location')
            ->get()
            ->take(6);

        if ($nearbyPages->isEmpty()) {
            return '';
        }

        $items = '';
        foreach ($nearbyPages as $np) {
            $url = $np->url_path ?? "/find/{$service->slug}-in-{$np->location->slug}/";
            $items .= '<li><a href="https://maids.ng' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($service->name, ENT_QUOTES, 'UTF-8') . ' in ' . htmlspecialchars($np->location->name, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }

        return '
    <section class="seo-section seo-nearby">
        <div class="container">
            <h2>Also Hiring ' . htmlspecialchars($service->plural ?? $service->name . 's', ENT_QUOTES, 'UTF-8') . ' Near ' . htmlspecialchars($location->name, ENT_QUOTES, 'UTF-8') . '</h2>
            <ul class="nearby-list">' . $items . '
            </ul>
        </div>
    </section>';
    }

    private function buildSchemas(SeoPage $page, $location, $service, array $salary, array $faqs): string
    {
        $schemas = [];

        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $page->meta_title ?? ($service->name . ' in ' . $location->name),
            'description' => $page->meta_description ?? '',
            'url' => 'https://maids.ng' . ($page->url_path ?? ''),
            'inLanguage' => 'en-NG',
            'publisher' => ['@type' => 'Organization', 'name' => 'Maids.ng', 'url' => 'https://maids.ng/'],
            'dateModified' => $page->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ];

        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Maids.ng',
            'url' => 'https://maids.ng/',
            'logo' => 'https://maids.ng/maids-logo.png',
            'description' => 'Nigeria\'s leading AI-powered domestic staff matching platform.',
            'areaServed' => 'Nigeria',
        ];

        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => 'Maids.ng',
            'url' => 'https://maids.ng' . ($page->url_path ?? ''),
            'priceRange' => '₦₦',
            'areaServed' => [
                '@type' => 'City',
                'name' => $location->name,
                'containedIn' => [
                    '@type' => 'AdministrativeArea',
                    'name' => $location->state ?? 'Nigeria',
                    'containedIn' => ['@type' => 'Country', 'name' => 'Nigeria'],
                ],
            ],
        ];

        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $service->name . ' in ' . $location->full_name,
            'description' => $service->short_description ?? '',
            'serviceType' => $service->name,
            'provider' => ['@type' => 'Organization', 'name' => 'Maids.ng', 'url' => 'https://maids.ng/'],
            'areaServed' => $location->full_name,
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'NGN',
                'price' => $salary['min'],
                'priceSpecification' => [
                    '@type' => 'PriceSpecification',
                    'priceCurrency' => 'NGN',
                    'minPrice' => $salary['min'],
                    'maxPrice' => $salary['max'],
                    'description' => 'Monthly salary range',
                ],
            ],
        ];

        if (!empty($faqs)) {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn($faq) => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq['short_answer'] . ' ' . ($faq['full_answer'] ?? ''),
                    ],
                ], $faqs),
            ];
        }

        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://maids.ng/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $service->name . 's', 'item' => 'https://maids.ng/find/' . $service->slug . '/'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $location->name, 'item' => 'https://maids.ng' . ($page->url_path ?? '')],
            ],
        ];

        return htmlspecialchars(json_encode($schemas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
    }
}
