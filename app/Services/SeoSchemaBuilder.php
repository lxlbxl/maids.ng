<?php

namespace App\Services;

use App\Models\{SeoPage, SeoLocation, SeoService};

class SeoSchemaBuilder
{
    public function build(SeoPage $page): array
    {
        $schemas = [];

        $schemas[] = $this->webPage($page);
        $schemas[] = $this->breadcrumb($page);
        $schemas[] = $this->organization();

        match ($page->page_type) {
            'service_area', 'service_city' => array_push($schemas,
                $this->localBusiness($page),
                $this->service($page),
                $this->faqPage($page)
            ),
            'location_city', 'location_area' => array_push($schemas,
                $this->localBusiness($page)
            ),
            'price_guide', 'salary_guide' => array_push($schemas,
                $this->faqPage($page),
                $this->howTo($page)
            ),
            'hire_guide' => array_push($schemas,
                $this->howTo($page),
                $this->faqPage($page)
            ),
            'faq' => array_push($schemas, $this->faqPage($page)),
            default => null,
        };

        return array_filter($schemas);
    }

    private function webPage(SeoPage $page): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'WebPage',
            'name'        => $page->meta_title,
            'description' => $page->meta_description,
            'url'         => url($page->url_path),
            'inLanguage'  => 'en-NG',
            'publisher'   => [
                '@type' => 'Organization',
                'name'  => 'Maids.ng',
                'url'   => url('/'),
            ],
            'dateModified' => $page->updated_at->toIso8601String(),
        ];
    }

    private function organization(): array
    {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'Organization',
            'name'            => 'Maids.ng',
            'url'             => url('/'),
            'logo'            => url('/maids-logo.png'),
            'description'     => 'Nigeria\'s leading AI-powered domestic staff matching platform. Find verified housekeepers, nannies, cooks, and drivers with NIN verification and a 10-day money-back guarantee.',
            'foundingLocation' => [
                '@type'          => 'Place',
                'addressCountry' => 'NG',
                'addressLocality' => 'Lagos',
            ],
            'areaServed'  => 'Nigeria',
            'serviceType' => 'Domestic Staff Matching',
            'contactPoint' => [
                '@type'       => 'ContactPoint',
                'contactType' => 'customer support',
                'email'       => config('mail.from.address', 'hello@maids.ng'),
            ],
            'sameAs' => [
                'https://www.instagram.com/maids.ng',
                'https://www.facebook.com/maidsng',
            ],
        ];
    }

    private function localBusiness(SeoPage $page): ?array
    {
        $location = $page->location;
        if (!$location) return null;

        $city = $location->type === 'area' ? $location->parent : $location;

        return [
            '@context' => 'https://schema.org',
            '@type'    => 'LocalBusiness',
            'name'     => 'Maids.ng',
            'image'    => url('/maids-logo.png'),
            'url'      => url($page->url_path),
            'telephone' => \App\Models\Setting::get('contact_phone', '+234-801-234-5678'),
            'priceRange' => '₦₦',
            'areaServed' => [
                '@type'           => 'City',
                'name'            => $city->name,
                'containedIn'     => [
                    '@type'           => 'AdministrativeArea',
                    'name'            => $city->state ?? 'Lagos State',
                    'containedIn'     => ['@type' => 'Country', 'name' => 'Nigeria'],
                ],
            ],
            'address' => [
                '@type'           => 'PostalAddress',
                'addressLocality' => $city->name,
                'addressRegion'   => $city->state ?? '',
                'addressCountry'  => 'NG',
            ],
            'geo' => $location->latitude ? [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $location->latitude,
                'longitude' => $location->longitude,
            ] : null,
            'openingHours'     => 'Mo-Su 07:00-22:00',
            'serviceArea'      => $location->full_name,
            'hasOfferCatalog'  => [
                '@type' => 'OfferCatalog',
                'name'  => 'Domestic Staff Matching Services',
            ],
        ];
    }

    private function service(SeoPage $page): ?array
    {
        $service  = $page->service;
        $location = $page->location;
        if (!$service) return null;

        $salary = $location
            ? $service->getSalaryForCity($location->parent?->slug ?? $location->slug)
            : ['min' => $service->salary_min, 'max' => $service->salary_max];

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            'name'        => $service->name . ($location ? " in {$location->full_name}" : ' in Nigeria'),
            'description' => $service->short_description,
            'serviceType' => $service->name,
            'provider'    => ['@type' => 'Organization', 'name' => 'Maids.ng', 'url' => url('/')],
            'areaServed'  => $location ? $location->full_name : 'Nigeria',
            'offers'      => [
                '@type'         => 'Offer',
                'priceCurrency' => 'NGN',
                'price'         => $salary['min'],
                'priceSpecification' => [
                    '@type'         => 'PriceSpecification',
                    'priceCurrency' => 'NGN',
                    'minPrice'      => $salary['min'],
                    'maxPrice'      => $salary['max'],
                    'description'   => 'Monthly salary range',
                ],
            ],
        ];
    }

    private function faqPage(SeoPage $page): ?array
    {
        $faqs = $page->content_blocks['faqs'] ?? [];
        if (empty($faqs)) return null;

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(fn($faq) => [
                '@type'          => 'Question',
                'name'           => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $faq['short_answer'] . ' ' . ($faq['full_answer'] ?? ''),
                ],
            ], $faqs),
        ];
    }

    private function howTo(SeoPage $page): array
    {
        $service  = $page->service?->name ?? 'domestic staff';
        $location = $page->location?->full_name ?? 'Nigeria';
        $matchingFee = number_format((int) \App\Models\Setting::get('matching_fee_amount', 5000));

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'HowTo',
            'name'        => "How to Hire a {$service} in {$location}",
            'description' => "Step-by-step guide to finding and hiring a verified {$service} in {$location} using Maids.ng.",
            'step'        => [
                ['@type' => 'HowToStep', 'name' => 'Complete the matching quiz', 'text' => 'Take Maids.ng\'s 5-minute employer quiz. Describe the help you need, your schedule, location, and budget.'],
                ['@type' => 'HowToStep', 'name' => 'Review your matches', 'text' => 'The AI matching algorithm returns your top verified candidates ranked by compatibility score.'],
                ['@type' => 'HowToStep', 'name' => 'Pay the matching fee', 'text' => 'Pay the one-time matching fee (₦' . $matchingFee . ') to access contact details. Protected by a 10-day money-back guarantee.'],
                ['@type' => 'HowToStep', 'name' => 'Connect and agree terms', 'text' => 'Contact your matched candidate, discuss salary and duties, and agree on a start date.'],
            ],
        ];
    }

    private function breadcrumb(SeoPage $page): array
    {
        $items = [
            ['name' => 'Home', 'url' => url('/')],
        ];

        if ($page->location) {
            $city = $page->location->type === 'area' ? $page->location->parent : $page->location;
            $items[] = ['name' => $city->name, 'url' => url("/locations/{$city->slug}/")];
            if ($page->location->type === 'area') {
                $items[] = ['name' => $page->location->name, 'url' => url("/locations/{$city->slug}/{$page->location->slug}/")];
            }
        }

        if ($page->service) {
            $items[] = ['name' => $page->service->name, 'url' => url("/find/{$page->service->slug}/")];
        }

        if (count($items) > 1) {
            $items[] = ['name' => $page->h1, 'url' => url($page->url_path)];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => array_map(fn($item, $i) => [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ], $items, array_keys($items)),
        ];
    }
}
