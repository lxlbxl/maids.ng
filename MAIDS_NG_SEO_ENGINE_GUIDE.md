# Maids.ng — Programmatic SEO & AI-SEO Domination
## Technical Implementation Guide

**Version:** 1.0  
**Codename:** SEO Engine  
**Architecture:** Fully parallel to existing app — zero touches to quiz, dashboard, agents, or payments  
**Goal:** Own every high-intent search query and AI citation for domestic staffing in Nigeria  
**Stack:** Laravel 11, Blade (not Inertia — SEO pages must be server-rendered), MySQL

---

## Table of Contents

- [Strategy Overview](#strategy-overview)
- [The Four Layers of Modern Search Domination](#the-four-layers-of-modern-search-domination)
- [Page Type Architecture](#page-type-architecture)
- [Location & Service Data Model](#location--service-data-model)
- [Phase 1 — Data Foundation & URL Architecture](#phase-1--data-foundation--url-architecture)
- [Phase 2 — Page Templates & Content Generation](#phase-2--page-templates--content-generation)
- [Phase 3 — Schema.org & Structured Data](#phase-3--schemaorg--structured-data)
- [Phase 4 — Internal Linking Strategy](#phase-4--internal-linking-strategy)
- [Phase 5 — AI-SEO & GEO Optimisation](#phase-5--ai-seo--geo-optimisation)
- [Phase 6 — Sitemap, Indexing & Crawl Management](#phase-6--sitemap-indexing--crawl-management)
- [Phase 7 — Performance Monitoring & Iteration](#phase-7--performance-monitoring--iteration)
- [Global Definition of Done](#global-definition-of-done)

---

## Strategy Overview

### The Opportunity

Nobody owns "domestic staff hiring in Nigeria" on search. The space is fragmented between informal Facebook groups, unreliable agency sites, and Jiji listings. Maids.ng's window to dominate is now — before a better-funded competitor does it first.

### Volume Estimate

The programmatic engine will generate approximately **2,400+ unique, indexable pages** from the combination of:
- 6 major cities × 80+ neighbourhoods = ~480 location pages
- 8 service types × 480 locations = ~3,840 location+service pages (filtered to realistic combinations)
- 12 intent templates × 8 services = ~96 guide/how-to pages
- 8 services × 6 cities = ~48 price guide pages
- 200+ FAQ pages targeting PAA (People Also Ask)

**These pages do not replace your main site.** They live at `/find/`, `/hire/`, `/guide/`, `/locations/` — distinct URL namespaces that link into your quiz, registration, and profile pages.

### Why This Works for AI-SEO

AI models (ChatGPT, Perplexity, Google AI Overviews, Claude) are trained to cite **authoritative, specific, structured sources** when answering queries. When someone asks Perplexity "how much does a housekeeper cost in Lekki Lagos?", it will cite the page that best answers that question with structured data, specific pricing, and clear entity information. That page should be yours.

The key insight: **AI citation is won by depth and specificity, not volume.** A thin page with just a city name and a CTA will never be cited. A page that comprehensively answers every question a human might ask about hiring a housekeeper in Lekki Lagos — with structured FAQ, schema markup, specific pricing, and genuine local context — will be cited repeatedly.

---

## The Four Layers of Modern Search Domination

### Layer 1 — Traditional SEO (Rankings)

Rank pages 1–3 on Google for high-intent queries.
- Targets: `[service] in [city/area]`, `hire [service] [city]`, `how much does [service] cost in [city]`
- Signals: on-page content, internal links, schema, page speed, Core Web Vitals

### Layer 2 — AEO (Answer Engine Optimisation)

Get cited in Google AI Overviews and featured snippets.
- Targets: `how to hire a maid in Lagos`, `what is NIN verification for domestic staff`, `what is the average salary for a housekeeper in Abuja`
- Signals: FAQ schema, concise 40–60 word answer blocks above the fold, structured headings, E-E-A-T

### Layer 3 — GEO (Generative Engine Optimisation)

Get cited in ChatGPT, Perplexity, Claude, Gemini responses.
- Targets: Conversational queries, comparison queries, research queries
- Signals: Entity clarity, topical authority, citation-worthy statistics, consistent NAP (Name/Address/Phone), Wikipedia-level factual writing, structured data

### Layer 4 — Local SEO

Dominate Google Maps and local pack results for "domestic staff near me" in Lagos, Abuja, PH.
- Signals: Google Business Profile, local schema, location-specific content depth, reviews

---

## Page Type Architecture

### URL Namespace Design (All parallel — no conflict with existing routes)

```
/locations/                     → All cities hub
/locations/{city}/              → City hub page
/locations/{city}/{area}/       → Neighbourhood hub

/find/{service}/                → Service hub (nationwide)
/find/{service}-in-{city}/      → Service × City
/find/{service}-in-{area}-{city}/ → Service × Area × City  ← MONEY PAGES

/hire/                          → Employer guide hub
/hire/{service}/                → How to hire {service} guide

/guide/                         → Resource hub
/guide/how-much-does-{service}-cost-in-{city}/   → Price guide
/guide/how-to-hire-{service}-in-{city}/          → Process guide
/guide/what-is-nin-verification-domestic-staff/  → Educational
/guide/{topic}/                                   → Evergreen guides

/salary/                        → Salary guide hub
/salary/{service}/              → Salary guide per service
/salary/{service}-in-{city}/    → Salary × City

/faq/                           → FAQ hub
/faq/{question-slug}/           → Individual FAQ page
```

### Page Type Definitions

| Page Type | Target Keyword Pattern | Volume | Priority |
|---|---|---|---|
| **Service × Area × City** | "housekeeper in lekki lagos" | ~3,800 pages | 🔴 Highest |
| **Service × City** | "hire nanny abuja" | ~48 pages | 🔴 Highest |
| **City Hub** | "domestic staff lagos" | ~6 pages | 🟡 High |
| **Price Guide** | "housekeeper salary in lagos 2025" | ~48 pages | 🟡 High |
| **How-to Guide** | "how to hire a maid in nigeria" | ~96 pages | 🟡 High |
| **Salary Guide** | "maid salary abuja" | ~48 pages | 🟢 Medium |
| **FAQ Page** | "what does a housekeeper do" | ~200 pages | 🟢 Medium |
| **Neighbourhood Hub** | "domestic staff lekki" | ~480 pages | 🟢 Medium |

---

## Location & Service Data Model

### Nigerian Locations (Full Dataset)

```
TIER 1 CITIES (Full neighbourhood coverage)
├── Lagos
│   ├── Island: Victoria Island, Ikoyi, Lagos Island, Oniru
│   ├── Lekki Corridor: Lekki Phase 1, Lekki Phase 2, Chevron, Ajah, Sangotedo, Badore, Awoyaya
│   ├── Mainland Premium: Ikeja GRA, Maryland, Magodo, Ojodu, Berger, Omole
│   ├── Mainland Mid: Surulere, Yaba, Gbagada, Shomolu, Ketu, Mile 12
│   └── Other: Festac, Isolo, Oshodi, Apapa, Badagry, Epe, Ikorodu
│
├── Abuja (FCT)
│   ├── Premium: Maitama, Asokoro, Wuse 2, Wuse, Garki
│   ├── Mid: Jabi, Kado, Durumi, Apo, Gudu, Lokogoma
│   └── Other: Gwarinpa, Kubwa, Lugbe, Karu, Nyanya, Bwari, Gwagwalada
│
└── Port Harcourt
    ├── GRA, Old GRA, New GRA
    ├── Rumuola, Rumuigbo, Diobu, Eliozu
    └── Woji, Mgbuoba, Forces Avenue, Peter Odili Road

TIER 2 CITIES (City-level pages only, some areas)
├── Ibadan (Bodija, Jericho, Oluyole)
├── Kano (Nasarawa GRA, Bompai)
├── Enugu (GRA, Independence Layout)
├── Benin City (GRA, Ugbowo)
├── Calabar (GRA, Satellite Town)
├── Warri (GRA, Effurun)
├── Owerri (GRA, New Owerri)
└── Uyo (Shelter Afrique, Ring Road)
```

### Service Types

```php
// The 8 core service types
$services = [
    [
        'name'        => 'Housekeeper',
        'slug'        => 'housekeeper',
        'plural'      => 'Housekeepers',
        'also_known'  => ['house help', 'maid', 'house cleaner', 'domestic worker'],
        'duties'      => 'Cleaning, laundry, cooking, general home maintenance',
        'salary_min'  => 30000,  // ₦/month
        'salary_max'  => 80000,
        'salary_city_modifier' => ['lagos' => 1.3, 'abuja' => 1.2, 'ph' => 1.1],
        'live_in_available' => true,
        'part_time_available' => true,
        'monthly_demand_index' => 95, // 0-100 popularity
    ],
    [
        'name'        => 'Nanny',
        'slug'        => 'nanny',
        'plural'      => 'Nannies',
        'also_known'  => ['babysitter', 'childminder', 'au pair'],
        'duties'      => 'Childcare, feeding, school runs, light housework',
        'salary_min'  => 40000,
        'salary_max'  => 100000,
        'live_in_available' => true,
        'part_time_available' => true,
        'monthly_demand_index' => 88,
    ],
    [
        'name'        => 'Cook',
        'slug'        => 'cook',
        'plural'      => 'Cooks',
        'also_known'  => ['chef', 'house cook', 'personal chef'],
        'duties'      => 'Meal preparation, kitchen management, grocery shopping',
        'salary_min'  => 45000,
        'salary_max'  => 120000,
        'live_in_available' => true,
        'part_time_available' => true,
        'monthly_demand_index' => 72,
    ],
    [
        'name'        => 'Driver',
        'slug'        => 'driver',
        'plural'      => 'Drivers',
        'also_known'  => ['chauffeur', 'personal driver', 'household driver'],
        'duties'      => 'School runs, errands, airport pickups, general driving',
        'salary_min'  => 50000,
        'salary_max'  => 130000,
        'live_in_available' => false,
        'part_time_available' => true,
        'monthly_demand_index' => 80,
    ],
    [
        'name'        => 'Elderly Carer',
        'slug'        => 'elderly-carer',
        'plural'      => 'Elderly Carers',
        'also_known'  => ['caregiver', 'aged care', 'home health aide'],
        'duties'      => 'Personal care, medication management, companionship, mobility support',
        'salary_min'  => 55000,
        'salary_max'  => 150000,
        'live_in_available' => true,
        'part_time_available' => false,
        'monthly_demand_index' => 60,
    ],
    [
        'name'        => 'Cleaner',
        'slug'        => 'cleaner',
        'plural'      => 'Cleaners',
        'also_known'  => ['house cleaner', 'cleaning lady', 'office cleaner'],
        'duties'      => 'Deep cleaning, post-event cleaning, move-in/move-out cleaning',
        'salary_min'  => 20000,
        'salary_max'  => 60000,
        'live_in_available' => false,
        'part_time_available' => true,
        'monthly_demand_index' => 85,
    ],
    [
        'name'        => 'Laundry Person',
        'slug'        => 'laundry-person',
        'plural'      => 'Laundry Staff',
        'also_known'  => ['laundress', 'washerman', 'laundry help'],
        'duties'      => 'Washing, ironing, folding, wardrobe management',
        'salary_min'  => 20000,
        'salary_max'  => 50000,
        'live_in_available' => false,
        'part_time_available' => true,
        'monthly_demand_index' => 55,
    ],
    [
        'name'        => 'Home Manager',
        'slug'        => 'home-manager',
        'plural'      => 'Home Managers',
        'also_known'  => ['house manager', 'estate manager', 'household manager'],
        'duties'      => 'Supervising other staff, budgeting, household admin, vendor management',
        'salary_min'  => 100000,
        'salary_max'  => 300000,
        'live_in_available' => true,
        'part_time_available' => false,
        'monthly_demand_index' => 40,
    ],
];
```

---

## Phase 1 — Data Foundation & URL Architecture

### 1.1 — Migrations

#### `seo_locations`

**File:** `database/migrations/2026_05_01_000001_create_seo_locations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_locations', function (Blueprint $table) {
            $table->id();

            // 'city' | 'area' (neighbourhood/district within a city)
            $table->enum('type', ['city', 'area']);

            // Human-readable name: "Lekki", "Victoria Island", "Lagos"
            $table->string('name', 255);

            // URL slug: "lekki", "victoria-island", "lagos"
            $table->string('slug', 100);

            // Parent city ID (null for cities, set for areas)
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('seo_locations')
                  ->nullOnDelete();

            // State/region for schema markup
            $table->string('state', 100)->nullable();

            // 1=Tier1(full), 2=Tier2(city-only), 3=Tier3(mention only)
            $table->unsignedTinyInteger('tier')->default(1);

            // Content for this location's pages
            $table->text('description')->nullable();     // 2–3 sentences about this area
            $table->text('demand_context')->nullable();  // Why domestic staff are needed here
            $table->json('notable_estates')->nullable(); // ["Lekki Phase 1","Chevron","Ikate"]
            $table->json('nearby_areas')->nullable();    // Nearby areas for internal linking

            // Approximate population/household count for schema
            $table->unsignedInteger('household_estimate')->nullable();

            // Lat/lng for LocalBusiness schema
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // SEO metadata overrides
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['slug', 'parent_id']);
            $table->index(['type', 'is_active']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_locations');
    }
};
```

---

#### `seo_services`

**File:** `database/migrations/2026_05_01_000002_create_seo_services_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_services', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);            // "Housekeeper"
            $table->string('slug', 100)->unique();  // "housekeeper"
            $table->string('plural', 100);          // "Housekeepers"
            $table->json('also_known_as');           // ["house help","maid","domestic worker"]

            // Content
            $table->text('short_description');      // 1–2 sentence definition
            $table->text('full_description');       // 300-word description
            $table->text('duties');                 // What they do
            $table->text('who_needs_this');         // Target audience
            $table->text('what_to_look_for');       // Hiring advice

            // Salary data
            $table->unsignedInteger('salary_min');   // ₦/month nationwide floor
            $table->unsignedInteger('salary_max');   // ₦/month nationwide ceiling
            $table->json('salary_by_city');          // {"lagos":{"min":40000,"max":100000}}

            // Service flags
            $table->boolean('live_in_available')->default(true);
            $table->boolean('part_time_available')->default(true);
            $table->boolean('nin_required')->default(true);

            // Schema.org service type mapping
            $table->string('schema_service_type', 255)->nullable();

            // SEO
            $table->string('meta_title_template', 70)->nullable();
            $table->string('meta_description_template', 160)->nullable();
            $table->unsignedTinyInteger('demand_index')->default(50); // 0-100 popularity score

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_services');
    }
};
```

---

#### `seo_pages`

Tracks every generated page — its URL, content status, last AI-generated content, and performance metrics.

**File:** `database/migrations/2026_05_01_000003_create_seo_pages_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_pages', function (Blueprint $table) {
            $table->id();

            // Page type determines template used
            // 'location_city'     → /locations/{city}/
            // 'location_area'     → /locations/{city}/{area}/
            // 'service_hub'       → /find/{service}/
            // 'service_city'      → /find/{service}-in-{city}/
            // 'service_area'      → /find/{service}-in-{area}-{city}/
            // 'price_guide'       → /guide/how-much-does-{service}-cost-in-{city}/
            // 'hire_guide'        → /hire/{service}/
            // 'salary_guide'      → /salary/{service}-in-{city}/
            // 'faq'               → /faq/{slug}/
            $table->string('page_type', 50);

            // The canonical URL path (without domain)
            $table->string('url_path', 500)->unique();

            $table->foreignId('location_id')
                  ->nullable()
                  ->constrained('seo_locations')
                  ->nullOnDelete();

            $table->foreignId('service_id')
                  ->nullable()
                  ->constrained('seo_services')
                  ->nullOnDelete();

            // AI-generated content blocks (stored as JSON)
            // Regenerated periodically without changing the page structure
            $table->json('content_blocks')->nullable();
            // Structure:
            // {
            //   "intro": "...",
            //   "why_maids_ng": "...",
            //   "local_context": "...",
            //   "hiring_tips": "...",
            //   "faqs": [{"q":"...","a":"..."}],
            //   "salary_context": "...",
            //   "cta_text": "..."
            // }

            // SEO fields
            $table->string('meta_title', 70);
            $table->string('meta_description', 160);
            $table->string('h1', 100);
            $table->string('canonical_url', 500)->nullable();

            // Schema.org JSON-LD (generated, stored for fast serving)
            $table->json('schema_markup')->nullable();

            // Page status
            // 'draft'     = generated but not published
            // 'published' = live and indexed
            // 'noindex'   = live but noindex (thin content pages)
            // 'redirected' = permanently redirected to another URL
            $table->enum('page_status', ['draft', 'published', 'noindex', 'redirected'])
                  ->default('draft');

            // Content quality score (set after content generation)
            // Used to determine noindex vs publish decision
            $table->unsignedTinyInteger('content_score')->default(0); // 0-100

            // Performance data (synced from Google Search Console API eventually)
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->float('avg_position')->nullable();
            $table->float('ctr')->nullable();

            $table->timestamp('content_generated_at')->nullable();
            $table->timestamp('last_indexed_at')->nullable();
            $table->timestamps();

            $table->index(['page_type', 'page_status']);
            $table->index(['location_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_pages');
    }
};
```

---

#### `seo_faqs`

**File:** `database/migrations/2026_05_01_000004_create_seo_faqs_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_faqs', function (Blueprint $table) {
            $table->id();

            $table->string('question', 500);
            $table->text('answer');        // Full answer (200-400 words for AI citation)
            $table->text('short_answer');  // 40-60 word version for featured snippets

            $table->string('slug', 500)->unique();

            // What this FAQ relates to
            $table->foreignId('service_id')->nullable()->constrained('seo_services')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('seo_locations')->nullOnDelete();

            // Category for grouping
            $table->enum('category', [
                'pricing', 'process', 'verification', 'legal',
                'service_type', 'platform', 'salary', 'general'
            ]);

            // Which pages embed this FAQ (JSON array of seo_page IDs)
            // FAQs also get their own standalone page at /faq/{slug}
            $table->json('embedded_on_page_types')->nullable();

            // For PAA (People Also Ask) targeting
            $table->boolean('targets_paa')->default(false);

            // Search volume estimate (manual or from keyword tool)
            $table->unsignedInteger('estimated_monthly_searches')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_faqs');
    }
};
```

Run all migrations:

```bash
php artisan migrate
```

---

### 1.2 — Models

**File:** `app/Models/SeoLocation.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class SeoLocation extends Model
{
    protected $table = 'seo_locations';

    protected $fillable = [
        'type', 'name', 'slug', 'parent_id', 'state', 'tier',
        'description', 'demand_context', 'notable_estates', 'nearby_areas',
        'household_estimate', 'latitude', 'longitude',
        'meta_title', 'meta_description', 'is_active',
    ];

    protected $casts = [
        'notable_estates' => 'array',
        'nearby_areas'    => 'array',
        'is_active'       => 'boolean',
        'latitude'        => 'float',
        'longitude'       => 'float',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SeoLocation::class, 'parent_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(SeoLocation::class, 'parent_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SeoPage::class, 'location_id');
    }

    /** Full URL-friendly path: "lekki-lagos" or "lagos" */
    public function getUrlSegmentAttribute(): string
    {
        if ($this->type === 'area' && $this->parent) {
            return $this->slug . '-' . $this->parent->slug;
        }
        return $this->slug;
    }

    /** Display name with city: "Lekki, Lagos" */
    public function getFullNameAttribute(): string
    {
        if ($this->type === 'area' && $this->parent) {
            return "{$this->name}, {$this->parent->name}";
        }
        return $this->name;
    }
}
```

**File:** `app/Models/SeoService.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoService extends Model
{
    protected $table = 'seo_services';

    protected $fillable = [
        'name', 'slug', 'plural', 'also_known_as', 'short_description',
        'full_description', 'duties', 'who_needs_this', 'what_to_look_for',
        'salary_min', 'salary_max', 'salary_by_city',
        'live_in_available', 'part_time_available', 'nin_required',
        'schema_service_type', 'meta_title_template', 'meta_description_template',
        'demand_index', 'is_active',
    ];

    protected $casts = [
        'also_known_as'       => 'array',
        'salary_by_city'      => 'array',
        'live_in_available'   => 'boolean',
        'part_time_available' => 'boolean',
        'nin_required'        => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function getSalaryForCity(string $citySlug): array
    {
        $cityData = $this->salary_by_city[$citySlug] ?? null;
        return [
            'min' => $cityData['min'] ?? $this->salary_min,
            'max' => $cityData['max'] ?? $this->salary_max,
        ];
    }
}
```

**File:** `app/Models/SeoPage.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class SeoPage extends Model
{
    protected $table = 'seo_pages';

    protected $fillable = [
        'page_type', 'url_path', 'location_id', 'service_id',
        'content_blocks', 'meta_title', 'meta_description', 'h1',
        'canonical_url', 'schema_markup', 'page_status', 'content_score',
        'impressions', 'clicks', 'avg_position', 'ctr',
        'content_generated_at', 'last_indexed_at',
    ];

    protected $casts = [
        'content_blocks'        => 'array',
        'schema_markup'         => 'array',
        'content_generated_at'  => 'datetime',
        'last_indexed_at'       => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(SeoLocation::class, 'location_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(SeoService::class, 'service_id');
    }
}
```

---

### 1.3 — SeoUrlBuilder Service

Generates canonical URLs for every page type. Single source of truth for all URL construction.

**File:** `app/Services/SeoUrlBuilder.php`

```php
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
```

---

### 1.4 — SeoPageFactory Job

Generates all `seo_pages` rows from the location × service matrix. Run once, then on-demand when new locations or services are added.

**File:** `app/Jobs/GenerateSeoPageRegistry.php`

```php
<?php

namespace App\Jobs;

use App\Models\{SeoLocation, SeoPage, SeoService};
use App\Services\SeoUrlBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class GenerateSeoPageRegistry implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 600;

    public function handle(SeoUrlBuilder $urls): void
    {
        $services  = SeoService::where('is_active', true)->get();
        $cities    = SeoLocation::where('type', 'city')->where('is_active', true)->get();
        $areas     = SeoLocation::where('type', 'area')->where('is_active', true)->with('parent')->get();

        $created = 0;

        // 1. City hub pages
        foreach ($cities as $city) {
            $this->upsert([
                'page_type'   => 'location_city',
                'url_path'    => $urls->locationCity($city),
                'location_id' => $city->id,
                'service_id'  => null,
                'h1'          => "Verified Domestic Staff in {$city->name}",
                'meta_title'  => "Hire Domestic Staff in {$city->name} | Maids.ng",
                'meta_description' => "Find verified housekeepers, nannies, cooks, and drivers in {$city->name}. NIN-verified staff. 10-day guarantee. Start free today.",
            ]);
            $created++;
        }

        // 2. Area hub pages
        foreach ($areas as $area) {
            $this->upsert([
                'page_type'   => 'location_area',
                'url_path'    => $urls->locationArea($area),
                'location_id' => $area->id,
                'service_id'  => null,
                'h1'          => "Domestic Staff in {$area->name}, {$area->parent->name}",
                'meta_title'  => "Hire Domestic Staff in {$area->name} | Maids.ng",
                'meta_description' => "Find verified housekeepers, nannies, and cooks in {$area->name}, {$area->parent->name}. Background-checked. 10-day guarantee.",
            ]);
            $created++;
        }

        // 3. Service hub pages (one per service, nationwide)
        foreach ($services as $service) {
            $this->upsert([
                'page_type'   => 'service_hub',
                'url_path'    => $urls->serviceHub($service),
                'location_id' => null,
                'service_id'  => $service->id,
                'h1'          => "Hire a Verified {$service->name} in Nigeria",
                'meta_title'  => "Hire a {$service->name} in Nigeria | Maids.ng",
                'meta_description' => "Find verified {$service->plural} near you. NIN-verified. Background checked. 10-day money-back guarantee. From ₦" . number_format($service->salary_min) . "/month.",
            ]);
            $created++;
        }

        // 4. Service × City pages
        foreach ($services as $service) {
            foreach ($cities as $city) {
                $salary = $service->getSalaryForCity($city->slug);
                $this->upsert([
                    'page_type'   => 'service_city',
                    'url_path'    => $urls->serviceCity($service, $city),
                    'location_id' => $city->id,
                    'service_id'  => $service->id,
                    'h1'          => "Hire a Verified {$service->name} in {$city->name}",
                    'meta_title'  => "Hire a {$service->name} in {$city->name} | Maids.ng",
                    'meta_description' => "Find NIN-verified {$service->plural} in {$city->name} from ₦" . number_format($salary['min']) . "/month. 10-day guarantee. Match in minutes.",
                ]);
                $created++;
            }
        }

        // 5. Service × Area pages (MONEY PAGES)
        foreach ($services as $service) {
            foreach ($areas as $area) {
                if ($area->parent->tier > 1) {
                    continue; // Only generate area pages for Tier 1 cities
                }
                $salary = $service->getSalaryForCity($area->parent->slug);
                $this->upsert([
                    'page_type'   => 'service_area',
                    'url_path'    => $urls->serviceArea($service, $area),
                    'location_id' => $area->id,
                    'service_id'  => $service->id,
                    'h1'          => "Hire a {$service->name} in {$area->name}, {$area->parent->name}",
                    'meta_title'  => "{$service->name} in {$area->name}, {$area->parent->name} | Maids.ng",
                    'meta_description' => "Find a verified {$service->name} in {$area->name}. NIN-checked. From ₦" . number_format($salary['min']) . "/month. 10-day guarantee.",
                ]);
                $created++;
            }
        }

        // 6. Price guide pages
        foreach ($services as $service) {
            foreach ($cities as $city) {
                $salary = $service->getSalaryForCity($city->slug);
                $this->upsert([
                    'page_type'   => 'price_guide',
                    'url_path'    => (new SeoUrlBuilder)->priceGuide($service, $city),
                    'location_id' => $city->id,
                    'service_id'  => $service->id,
                    'h1'          => "How Much Does a {$service->name} Cost in {$city->name}? (2025)",
                    'meta_title'  => "{$service->name} Salary in {$city->name} 2025 | Maids.ng",
                    'meta_description' => "Complete guide to {$service->slug} salaries in {$city->name} 2025. Average: ₦" . number_format($salary['min']) . "–₦" . number_format($salary['max']) . "/month. Plus tips on fair pay.",
                ]);
                $created++;
            }
        }

        // 7. Hire guides
        foreach ($services as $service) {
            $this->upsert([
                'page_type'  => 'hire_guide',
                'url_path'   => $urls->hireGuide($service),
                'service_id' => $service->id,
                'h1'         => "How to Hire a {$service->name} in Nigeria (Complete Guide 2025)",
                'meta_title' => "How to Hire a {$service->name} in Nigeria | Maids.ng Guide",
                'meta_description' => "Step-by-step guide to hiring a {$service->slug} in Nigeria. What to look for, how much to pay, how to verify documents, and where to find trusted staff.",
            ]);
            $created++;
        }

        Log::info("SeoPageRegistry: created/updated {$created} pages.");
    }

    private function upsert(array $data): void
    {
        SeoPage::updateOrCreate(
            ['url_path' => $data['url_path']],
            array_merge($data, ['page_status' => 'draft'])
        );
    }
}
```

Run with:

```bash
php artisan tinker
App\Jobs\GenerateSeoPageRegistry::dispatchSync();
```

---

## Phase 2 — Page Templates & Content Generation

### 2.1 — SeoContentGenerator Service

Uses the LLM to fill `content_blocks` for each page. Different page types get different prompts.

**File:** `app/Services/SeoContentGenerator.php`

```php
<?php

namespace App\Services;

use App\Models\{SeoPage, SeoFaq};
use App\Services\{AiService, KnowledgeService};

class SeoContentGenerator
{
    // Content quality threshold. Pages scoring below this are marked 'noindex'.
    private const MIN_QUALITY_SCORE = 65;

    public function __construct(
        private AiService $ai,
        private KnowledgeService $knowledge,
    ) {}

    /**
     * Generate content blocks for a single SeoPage.
     * Stores content in seo_pages.content_blocks.
     */
    public function generate(SeoPage $page): void
    {
        $systemPrompt = $this->buildSystemPrompt($page);
        $userPrompt   = $this->buildUserPrompt($page);

        $systemPrompt .= "\n\nRespond ONLY with valid JSON matching this schema:\n" . $this->getSchema($page);

        $result  = $this->ai->chatWithTools($systemPrompt, [
            ['role' => 'user', 'content' => $userPrompt]
        ], []);

        $content = json_decode($result['content'], true);

        if (!$content) {
            \Log::error("SeoContentGenerator: Invalid JSON for page {$page->id}");
            return;
        }

        // Score content quality
        $score = $this->scoreContent($content, $page);

        $page->update([
            'content_blocks'       => $content,
            'content_score'        => $score,
            'page_status'          => $score >= self::MIN_QUALITY_SCORE ? 'published' : 'noindex',
            'content_generated_at' => now(),
        ]);
    }

    private function buildSystemPrompt(SeoPage $page): string
    {
        $kb = $this->knowledge->buildContext('marketer', 'global');

        return $kb . "\n\n---\n## YOUR ROLE\n"
            . "You are an expert SEO content writer for Maids.ng, Nigeria's leading domestic staff matching platform. "
            . "You write content that ranks on Google AND gets cited by AI models like ChatGPT and Perplexity. "
            . "Your writing is:\n"
            . "- Factually accurate (use only data provided — never fabricate statistics)\n"
            . "- Genuinely helpful to someone considering hiring domestic staff in Nigeria\n"
            . "- Written in clear, warm, professional Nigerian-English\n"
            . "- Structured for both humans and AI crawlers (clear H2s, concise answers)\n"
            . "- Never keyword-stuffed or generic\n"
            . "- Always specific to the exact location and service type\n\n"
            . "CRITICAL RULES:\n"
            . "- Never fabricate testimonials or reviews\n"
            . "- Use only salary figures provided in the context\n"
            . "- Never mention competitor platforms by name\n"
            . "- Always reference the 10-day money-back guarantee when mentioning Maids.ng's offer\n"
            . "- Write as if you know this specific neighbourhood well\n";
    }

    private function buildUserPrompt(SeoPage $page): string
    {
        $service  = $page->service;
        $location = $page->location;

        $context = "Page type: {$page->page_type}\n";
        $context .= "H1: {$page->h1}\n";

        if ($service) {
            $salary = $location
                ? $service->getSalaryForCity($location->parent?->slug ?? $location->slug)
                : ['min' => $service->salary_min, 'max' => $service->salary_max];

            $context .= "Service: {$service->name}\n";
            $context .= "Also known as: " . implode(', ', $service->also_known_as ?? []) . "\n";
            $context .= "What they do: {$service->duties}\n";
            $context .= "Salary range for this location: ₦" . number_format($salary['min']) . " – ₦" . number_format($salary['max']) . " per month\n";
            $context .= "NIN verification required: " . ($service->nin_required ? 'Yes' : 'No') . "\n";
        }

        if ($location) {
            $context .= "Location: {$location->full_name}\n";
            $context .= "Location type: {$location->type}\n";
            if ($location->description) {
                $context .= "About this area: {$location->description}\n";
            }
            if ($location->demand_context) {
                $context .= "Why domestic staff are in demand here: {$location->demand_context}\n";
            }
            if ($location->notable_estates) {
                $context .= "Notable estates/areas: " . implode(', ', $location->notable_estates) . "\n";
            }
        }

        return "Generate SEO content for this page.\n\n{$context}\n\nGenerate the content blocks now. JSON only.";
    }

    private function getSchema(SeoPage $page): string
    {
        // All page types share a common schema with optional fields
        return json_encode([
            'intro'              => '2–3 opening sentences. Directly answers the search query. Mention the service, location, and what Maids.ng offers.',
            'what_is_this'       => 'For service pages: 1 paragraph explaining what a [service] does. For location pages: about hiring in this area.',
            'why_maids_ng'       => '1 paragraph on why to use Maids.ng specifically. Reference NIN verification, matching algorithm, 10-day guarantee.',
            'local_context'      => 'Specific paragraph about domestic staff demand in this exact location. Should reference local context, estates, types of families who hire.',
            'hiring_tips'        => '3–5 practical tips as an array of {tip, explanation} objects. Specific to the service type.',
            'salary_section'     => 'Paragraph explaining salary expectations. Use exact figures provided. Explain factors that affect pay.',
            'faqs'               => 'Array of 5–7 FAQ objects: {question: string, short_answer: string (under 60 words), full_answer: string (150-300 words)}. Target real user questions. Include at least one pricing question and one process question.',
            'cta_text'           => 'A single compelling sentence inviting the user to start the quiz. Under 20 words.',
            'what_to_check'      => 'What to verify before hiring (documents, references, trial period). 2–3 sentences.',
        ], JSON_PRETTY_PRINT);
    }

    private function scoreContent(array $content, SeoPage $page): int
    {
        $score = 0;

        // Check minimum content fields are present and non-empty
        $requiredFields = ['intro', 'why_maids_ng', 'faqs', 'cta_text'];
        foreach ($requiredFields as $field) {
            if (!empty($content[$field])) $score += 15;
        }

        // Check FAQ quality (should have 5+ questions)
        if (count($content['faqs'] ?? []) >= 5) $score += 10;

        // Check for location specificity
        if ($page->location && str_contains(
            strtolower(json_encode($content)),
            strtolower($page->location->name)
        )) {
            $score += 10;
        }

        // Check for salary figures
        if ($page->service && str_contains(json_encode($content), '₦')) {
            $score += 5;
        }

        return min(100, $score);
    }
}
```

---

### 2.2 — Blade Templates

SEO pages use **Blade, not Inertia**. This is critical — Inertia requires JS execution, which delays crawlers and AI scrapers. Blade renders pure HTML server-side.

**Master SEO layout:**

**File:** `resources/views/seo/layouts/master.blade.php`

```blade
<!DOCTYPE html>
<html lang="en-NG" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- SEO Meta --}}
    <title>{{ $page->meta_title }}</title>
    <meta name="description" content="{{ $page->meta_description }}">
    <link rel="canonical" href="{{ $page->canonical_url ?? url($page->url_path) }}">

    {{-- Open Graph --}}
    <meta property="og:title" content="{{ $page->meta_title }}">
    <meta property="og:description" content="{{ $page->meta_description }}">
    <meta property="og:url" content="{{ url($page->url_path) }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('images/seo/og-default.png') }}">
    <meta property="og:site_name" content="Maids.ng">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $page->meta_title }}">
    <meta name="twitter:description" content="{{ $page->meta_description }}">

    {{-- Schema.org JSON-LD --}}
    @if($page->schema_markup)
    @foreach($page->schema_markup as $schema)
    <script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
    @endforeach
    @endif

    {{-- Minimal CSS — SEO pages should be fast, not fancy --}}
    <link rel="stylesheet" href="{{ asset('css/seo.css') }}">

    {{-- Preconnect to main app for quiz CTA --}}
    <link rel="preconnect" href="{{ config('app.url') }}">
</head>
<body>
    {{-- Simplified header with link back to main site --}}
    @include('seo.partials.header')

    <main>
        @yield('content')
    </main>

    {{-- Breadcrumbs (also in schema) --}}
    @include('seo.partials.breadcrumbs')

    {{-- Simplified footer --}}
    @include('seo.partials.footer')

    {{-- Minimal JS only (no full app bundle) --}}
    <script src="{{ asset('js/seo.js') }}" defer></script>
</body>
</html>
```

**Service × Area page template (the MONEY PAGE):**

**File:** `resources/views/seo/service-area.blade.php`

```blade
@extends('seo.layouts.master')

@section('content')

{{-- ABOVE THE FOLD — This section is what AI and Google see first --}}
<section class="seo-hero">
    <div class="container">
        <h1>{{ $page->h1 }}</h1>

        {{-- 40-60 word answer block — targets AI Overviews and featured snippets --}}
        <div class="answer-block" itemprop="description">
            <p>{{ $content['intro'] }}</p>
        </div>

        {{-- Trust signals — above the fold --}}
        <div class="trust-bar">
            <span>✓ NIN-Verified Staff</span>
            <span>✓ Background Checked</span>
            <span>✓ 10-Day Money-Back Guarantee</span>
            <span>✓ AI-Matched in Minutes</span>
        </div>

        {{-- PRIMARY CTA — links to quiz on main app --}}
        <a href="{{ url('/') }}?from=seo&service={{ $service->slug }}&location={{ $location->slug }}"
           class="btn-primary cta-quiz">
            Find a {{ $service->name }} in {{ $location->name }} →
        </a>

        {{-- Price anchor --}}
        <p class="price-anchor">
            From <strong>₦{{ number_format($salary['min']) }}</strong> per month.
            Salary range: ₦{{ number_format($salary['min']) }} – ₦{{ number_format($salary['max']) }}.
        </p>
    </div>
</section>

{{-- WHAT DOES A [SERVICE] DO --}}
<section class="seo-section">
    <div class="container">
        <h2>What Does a {{ $service->name }} in {{ $location->name }} Do?</h2>
        <p>{{ $content['what_is_this'] }}</p>
        <p>{{ $service->duties }}</p>
    </div>
</section>

{{-- LOCAL CONTEXT — Location-specific demand --}}
<section class="seo-section">
    <div class="container">
        <h2>Hiring a {{ $service->name }} in {{ $location->name }}</h2>
        <p>{{ $content['local_context'] }}</p>
    </div>
</section>

{{-- SALARY TABLE — Highly citeable by AI --}}
<section class="seo-section" id="salary">
    <div class="container">
        <h2>{{ $service->name }} Salary in {{ $location->full_name }} (2025)</h2>
        <p>{{ $content['salary_section'] }}</p>

        {{-- Structured salary table for AI to extract --}}
        <table class="salary-table" itemscope itemtype="https://schema.org/PriceSpecification">
            <thead>
                <tr>
                    <th>Employment Type</th>
                    <th>Monthly Salary Range</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Full-time (Live-in)</td>
                    <td itemprop="price">₦{{ number_format($salary['min']) }} – ₦{{ number_format(round($salary['max'] * 0.7)) }}</td>
                </tr>
                <tr>
                    <td>Full-time (Live-out)</td>
                    <td>₦{{ number_format(round($salary['min'] * 1.1)) }} – ₦{{ number_format($salary['max']) }}</td>
                </tr>
                @if($service->part_time_available)
                <tr>
                    <td>Part-time</td>
                    <td>₦{{ number_format(round($salary['min'] * 0.5)) }} – ₦{{ number_format(round($salary['min'] * 0.8)) }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <p class="salary-note"><em>Salary ranges reflect market rates in {{ $location->full_name }} as of 2025. Exact pay depends on experience, duties, and working hours.</em></p>
    </div>
</section>

{{-- HOW MAIDS.NG WORKS — MoFU block --}}
<section class="seo-section seo-how-it-works">
    <div class="container">
        <h2>How to Find a {{ $service->name }} in {{ $location->name }} with Maids.ng</h2>
        <ol class="steps-list">
            <li><strong>Take the 5-minute quiz</strong> — Tell us what kind of help you need, your schedule, location, and budget.</li>
            <li><strong>See your top matches</strong> — Our AI matches you with verified {{ $service->plural }} in {{ $location->name }}.</li>
            <li><strong>Pay the matching fee</strong> — ₦{{ number_format((int) setting('matching_fee', 5000)) }} one-time fee, covered by our 10-day guarantee.</li>
            <li><strong>Connect and hire</strong> — Get the {{ $service->name }}'s contact details and arrange a start date.</li>
        </ol>
        <p>{{ $content['why_maids_ng'] }}</p>
        <a href="{{ url('/') }}?from=seo" class="btn-secondary">Start the Quiz Free →</a>
    </div>
</section>

{{-- HIRING TIPS --}}
<section class="seo-section">
    <div class="container">
        <h2>Tips for Hiring a {{ $service->name }} in {{ $location->name }}</h2>
        @if(isset($content['hiring_tips']) && is_array($content['hiring_tips']))
        @foreach($content['hiring_tips'] as $tip)
        <div class="tip-block">
            <h3>{{ $tip['tip'] }}</h3>
            <p>{{ $tip['explanation'] }}</p>
        </div>
        @endforeach
        @endif
    </div>
</section>

{{-- VERIFICATION SECTION — Trust/AI citation signal --}}
<section class="seo-section seo-verification">
    <div class="container">
        <h2>Why Verification Matters When Hiring {{ $service->plural }} in {{ $location->name }}</h2>
        <p>{{ $content['what_to_check'] }}</p>
        <p>All {{ $service->plural }} on Maids.ng are required to complete NIN (National Identification Number) verification before their profile appears in search results. This means you can see a government-verified identity for every match.</p>
    </div>
</section>

{{-- FAQ SECTION — AEO + Schema FAQPage --}}
<section class="seo-section seo-faq" id="faq">
    <div class="container">
        <h2>Frequently Asked Questions — {{ $service->name }} in {{ $location->name }}</h2>

        <div class="faq-list" itemscope itemtype="https://schema.org/FAQPage">
            @foreach($content['faqs'] ?? [] as $faq)
            <div class="faq-item" itemscope itemtype="https://schema.org/Question" itemprop="mainEntity">
                <h3 itemprop="name">{{ $faq['question'] }}</h3>
                <div itemscope itemtype="https://schema.org/Answer" itemprop="acceptedAnswer">
                    {{-- Short answer first — targets featured snippets --}}
                    <p class="faq-short-answer" itemprop="text">{{ $faq['short_answer'] }}</p>
                    {{-- Full answer — targets AI citation depth --}}
                    <div class="faq-full-answer">
                        <p>{{ $faq['full_answer'] }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- NEARBY AREAS INTERNAL LINK BLOCK --}}
@if($location->nearby_areas && count($location->nearby_areas) > 0)
<section class="seo-section seo-nearby">
    <div class="container">
        <h2>Also Hiring {{ $service->plural }} Near {{ $location->name }}</h2>
        <ul class="nearby-list">
            @foreach($nearbyPages as $nearby)
            <li><a href="{{ $nearby->url_path }}">{{ $service->name }} in {{ $nearby->location->name }}</a></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

{{-- FINAL CTA --}}
<section class="seo-cta-final">
    <div class="container">
        <h2>Ready to Find a {{ $service->name }} in {{ $location->name }}?</h2>
        <p>{{ $content['cta_text'] }}</p>
        <a href="{{ url('/') }}?from=seo&service={{ $service->slug }}&location={{ $location->slug }}"
           class="btn-primary btn-large">
            Find Your {{ $service->name }} Now →
        </a>
        <p class="guarantee-note">Protected by our 10-day money-back guarantee.</p>
    </div>
</section>

@endsection
```

---

### 2.3 — Routes (Parallel Namespace)

**File:** `routes/seo.php`

```php
<?php

use App\Http\Controllers\Seo\{
    SeoLocationController,
    SeoServiceController,
    SeoGuideController,
    SeoFaqController,
    SeoSitemapController,
};
use Illuminate\Support\Facades\Route;

// All SEO routes — no auth, no CSRF, just pure server-rendered HTML
Route::middleware(['web'])->group(function () {

    // Location hub pages
    Route::get('/locations',               [SeoLocationController::class, 'hub'])->name('seo.locations');
    Route::get('/locations/{city}',        [SeoLocationController::class, 'city'])->name('seo.location.city');
    Route::get('/locations/{city}/{area}', [SeoLocationController::class, 'area'])->name('seo.location.area');

    // Service pages
    Route::get('/find/{service}',                    [SeoServiceController::class, 'hub'])->name('seo.service');
    Route::get('/find/{service}-in-{city}',          [SeoServiceController::class, 'city'])->name('seo.service.city');
    Route::get('/find/{service}-in-{area}-{city}',   [SeoServiceController::class, 'area'])->name('seo.service.area');

    // Guides
    Route::get('/hire/{service}',                           [SeoGuideController::class, 'hire'])->name('seo.hire');
    Route::get('/guide/how-much-does-a-{service}-cost-in-{city}', [SeoGuideController::class, 'price'])->name('seo.price');
    Route::get('/salary/{service}-in-{city}',               [SeoGuideController::class, 'salary'])->name('seo.salary');
    Route::get('/guide/{slug}',                             [SeoGuideController::class, 'guide'])->name('seo.guide');

    // FAQ
    Route::get('/faq',          [SeoFaqController::class, 'hub'])->name('seo.faq');
    Route::get('/faq/{slug}',   [SeoFaqController::class, 'show'])->name('seo.faq.show');

    // Sitemaps
    Route::get('/sitemap.xml',          [SeoSitemapController::class, 'index'])->name('sitemap.index');
    Route::get('/sitemap-locations.xml',[SeoSitemapController::class, 'locations'])->name('sitemap.locations');
    Route::get('/sitemap-services.xml', [SeoSitemapController::class, 'services'])->name('sitemap.services');
    Route::get('/sitemap-guides.xml',   [SeoSitemapController::class, 'guides'])->name('sitemap.guides');
    Route::get('/sitemap-faqs.xml',     [SeoSitemapController::class, 'faqs'])->name('sitemap.faqs');

    // robots.txt
    Route::get('/robots.txt', [SeoSitemapController::class, 'robots'])->name('robots');
});
```

Add to `routes/web.php`:

```php
require __DIR__ . '/seo.php';
```

---

## Phase 3 — Schema.org & Structured Data

Schema markup is the single most important signal for AI citation. It's how ChatGPT, Perplexity, and Google AI Overviews understand what your page is about.

**File:** `app/Services/SeoSchemaBuilder.php`

```php
<?php

namespace App\Services;

use App\Models\{SeoPage, SeoLocation, SeoService};

class SeoSchemaBuilder
{
    /**
     * Build all schema.org JSON-LD blocks for a page.
     * Returns array of schema objects (multiple are supported).
     */
    public function build(SeoPage $page): array
    {
        $schemas = [];

        // Always add WebPage schema
        $schemas[] = $this->webPage($page);

        // Always add BreadcrumbList
        $schemas[] = $this->breadcrumb($page);

        // Always add Organization
        $schemas[] = $this->organization();

        // Page-type specific schemas
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
            'logo'            => url('/images/logo.png'),
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
                'email'       => config('ambassador.email.from_address'),
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
            'image'    => url('/images/logo.png'),
            'url'      => url($page->url_path),
            'telephone' => '+234-XXX-XXX-XXXX', // Your actual number
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

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'HowTo',
            'name'        => "How to Hire a {$service} in {$location}",
            'description' => "Step-by-step guide to finding and hiring a verified {$service} in {$location} using Maids.ng.",
            'step'        => [
                ['@type' => 'HowToStep', 'name' => 'Complete the matching quiz', 'text' => 'Take Maids.ng\'s 5-minute employer quiz. Describe the help you need, your schedule, location, and budget.'],
                ['@type' => 'HowToStep', 'name' => 'Review your matches', 'text' => 'The AI matching algorithm returns your top verified candidates ranked by compatibility score.'],
                ['@type' => 'HowToStep', 'name' => 'Pay the matching fee', 'text' => 'Pay the one-time matching fee (₦' . number_format((int) setting('matching_fee', 5000)) . ') to access contact details. Protected by a 10-day money-back guarantee.'],
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
```

---

## Phase 4 — Internal Linking Strategy

Internal links distribute PageRank and help AI crawlers understand the site's topic map.

### Linking Rules

**Rule 1 — Hub to Spoke:** Every city hub page (`/locations/lagos/`) links to every service × city page for Lagos (`/find/housekeeper-in-lagos/`, `/find/nanny-in-lagos/`, etc.)

**Rule 2 — Spoke to Spoke:** Every service × area page links to 4–6 nearby area pages for the same service.

**Rule 3 — Content to Conversion:** Every SEO page has exactly one primary CTA link pointing to the quiz on the main app with UTM parameters.

**Rule 4 — Guide to Service:** Every hire guide and price guide links to the relevant service hub and top 3 city pages for that service.

**Rule 5 — FAQ to Deep Content:** FAQ pages link to the most relevant service × city page for the query answered.

**File:** `app/Services/SeoInternalLinker.php`

```php
<?php

namespace App\Services;

use App\Models\{SeoLocation, SeoPage, SeoService};

class SeoInternalLinker
{
    /**
     * Get nearby service pages for a given service × area page.
     * Used in the "Also hiring in nearby areas" section.
     */
    public function getNearbyPages(SeoPage $page, int $limit = 6): \Illuminate\Support\Collection
    {
        if (!$page->location || $page->location->type !== 'area') {
            return collect();
        }

        $city = $page->location->parent;

        return SeoPage::where('page_type', 'service_area')
            ->where('service_id', $page->service_id)
            ->where('id', '!=', $page->id)
            ->whereHas('location', fn($q) => $q->where('parent_id', $city->id))
            ->where('page_status', 'published')
            ->inRandomOrder()
            ->take($limit)
            ->with('location')
            ->get();
    }

    /**
     * Get all service pages for a city (used in city hub page).
     */
    public function getCityServicePages(SeoLocation $city): \Illuminate\Support\Collection
    {
        return SeoPage::where('page_type', 'service_city')
            ->where('location_id', $city->id)
            ->where('page_status', 'published')
            ->with('service')
            ->get();
    }

    /**
     * Build UTM-tagged quiz URL for this SEO page's CTA.
     */
    public function quizUrl(SeoPage $page): string
    {
        $params = [
            'utm_source'   => 'seo',
            'utm_medium'   => $page->page_type,
            'utm_campaign' => implode('-', array_filter([
                $page->service?->slug,
                $page->location?->slug,
            ])),
        ];

        // Pre-fill quiz with known context so users don't start from scratch
        if ($page->service) {
            $params['service'] = $page->service->slug;
        }
        if ($page->location) {
            $params['location'] = $page->location->slug;
        }

        return url('/') . '?' . http_build_query($params);
    }
}
```

---

## Phase 5 — AI-SEO & GEO Optimisation

This phase covers what traditional SEO guides miss — how to ensure Maids.ng is cited by ChatGPT, Perplexity, Claude, and Gemini.

### 5.1 — The Five Signals AI Models Use for Citation

**Signal 1 — Entity Clarity.** AI models need to understand what Maids.ng IS as an entity. This means defining the company, its service, its location, and its differentiators in machine-readable formats (schema) AND in plain language on every page.

Add this to your main `app.blade.php` (the main site layout, not SEO pages):

```html
<!-- Entity Definition Block — place in <head> of main site -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "@id": "https://maids.ng/#organization",
  "name": "Maids.ng",
  "alternateName": ["Maids Nigeria", "MaidsNG"],
  "url": "https://maids.ng",
  "description": "Maids.ng is Nigeria's leading AI-powered domestic staff matching platform. We connect Nigerian families with NIN-verified housekeepers, nannies, cooks, drivers, and elderly carers. Our algorithm matches employers with verified domestic staff in Lagos, Abuja, and Port Harcourt. One-time matching fee of ₦5,000 with a 10-day money-back guarantee.",
  "foundingDate": "2024",
  "foundingLocation": "Lagos, Nigeria",
  "areaServed": "Nigeria",
  "knowsAbout": [
    "Domestic Staff Matching",
    "NIN Verification Nigeria",
    "Housekeeper Hiring Lagos",
    "Nanny Services Abuja",
    "Domestic Worker Salary Nigeria"
  ]
}
</script>
```

**Signal 2 — Statistics & Specific Facts.** AI models prefer citing sources with specific, verifiable statistics. Build a live stats API that your SEO pages can display:

**File:** `app/Http/Controllers/Seo/SeoStatsController.php`

```php
<?php

namespace App\Http\Controllers\Seo;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Cache, DB};

class SeoStatsController extends Controller
{
    /**
     * Public API endpoint that returns platform statistics.
     * Used on SEO pages to display real, citeable numbers.
     * Cached for 6 hours.
     */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('seo_public_stats', 21600, function () {
            return [
                'total_maids_registered'     => DB::table('users')->role('maid')->count(),
                'total_employers_registered' => DB::table('users')->role('employer')->count(),
                'total_successful_matches'   => DB::table('matching_fee_payments')->where('status', 'successful')->count(),
                'nin_verified_staff'         => DB::table('nin_verifications')->where('status', 'approved')->count(),
                'cities_covered'             => 3,    // Update as you expand
                'areas_covered'              => 80,
                'average_match_time_minutes' => 5,
                'guarantee_days'             => 10,
            ];
        });

        return response()->json($stats);
    }
}
```

Include these stats on high-value SEO pages in the template. AI models will cite: *"According to Maids.ng, the platform has matched over X employers with verified domestic staff across Lagos, Abuja, and Port Harcourt."*

**Signal 3 — E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness).** Add an "About This Guide" block to all hire guide and price guide pages:

```blade
<div class="eeat-block">
    <h3>About This Information</h3>
    <p>This salary and hiring guide is prepared by the Maids.ng team based on:</p>
    <ul>
        <li>Live platform data from thousands of employer-maid matches on Maids.ng</li>
        <li>Market research conducted across Lagos, Abuja, and Port Harcourt ({{ date('Y') }})</li>
        <li>Salary data reported by domestic workers and employers on our platform</li>
    </ul>
    <p>All pricing figures reflect market conditions as of {{ date('F Y') }}. We update this guide quarterly.</p>
</div>
```

**Signal 4 — Consistent NAP Across the Web.** Name, Address, Phone must be identical everywhere — your site, Google Business Profile, social media bios. AI models cross-reference these for trust signals. Establish it:

```
Name: Maids.ng
Address: [Your registered Lagos address]
Phone: [Your verified business phone]
Email: support@maids.ng
```

**Signal 5 — Topical Authority.** Publish a set of authoritative long-form guides (not programmatic — hand-crafted) that position Maids.ng as THE expert on domestic staffing in Nigeria:

```
/guide/domestic-staff-rights-and-legal-guide-nigeria/
/guide/nin-verification-guide-domestic-workers-nigeria/
/guide/how-to-calculate-fair-domestic-staff-salary-nigeria/
/guide/live-in-vs-live-out-domestic-staff-pros-cons/
/guide/domestic-worker-contract-template-nigeria/
/guide/how-to-manage-domestic-staff-professionally/
```

These 6 guides are the AI citation anchors. When Perplexity answers "what are domestic worker rights in Nigeria?", it should cite `/guide/domestic-staff-rights-and-legal-guide-nigeria/`. These pages should be 2,000+ words, cite real legislation (Labour Act Nigeria), and be updated annually.

---

### 5.2 — AI Citation Optimisation Checklist

Apply these rules to every SEO page:

```
□ Page has a concise (40-60 word) answer in the first visible paragraph
□ H1 is a question or matches a search query pattern exactly
□ H2s are questions (not marketing copy)
□ FAQ section has 5+ questions with short_answer (≤60 words) AND full_answer
□ Schema FAQ markup is present and validates clean
□ Statistics are specific numbers, not vague claims
□ Salary figures are in a data table (not buried in paragraphs)
□ Organization schema with @id is present
□ Service schema with offers + priceSpecification is present
□ HowTo schema is present on guide pages
□ All content is factual — no fabricated reviews or testimonials
□ Page loads in under 2 seconds (Core Web Vitals pass)
□ Breadcrumb schema is present
□ Canonical URL is set correctly
```

---

## Phase 6 — Sitemap, Indexing & Crawl Management

### 6.1 — Sitemap Controller

**File:** `app/Http/Controllers/Seo/SeoSitemapController.php`

```php
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

    public function services(): Response
    {
        $pages = SeoPage::whereIn('page_type', ['service_hub', 'service_city', 'service_area'])
            ->where('page_status', 'published')
            ->orderBy('page_type')
            ->get(['url_path', 'updated_at', 'page_type']);

        return $this->buildSitemap($pages, [
            'service_area' => '0.9',   // Highest priority — money pages
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
        $content .= "Disallow: /webhooks/\n\n";
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
```

### 6.2 — robots.txt Key Insight

The `robots.txt` file deliberately **allows** all major AI crawlers (`GPTBot`, `PerplexityBot`, `ClaudeBot`, `anthropic-ai`, `Google-Extended`). Many sites block these by default — which is why they never get cited. Maids.ng should be fully open to AI crawlers on all SEO pages.

---

## Phase 7 — Performance Monitoring & Iteration

### 7.1 — SEO Metrics to Track

**File:** `app/Jobs/SyncSeoPerformance.php`

Connects to Google Search Console API to pull impressions, clicks, position per page. Run weekly.

Key metrics to track per page:
- `impressions` — how many times the page appeared in search
- `clicks` — how many people actually visited
- `avg_position` — average ranking position
- `ctr` — click-through rate

Pages with `impressions > 0` and `clicks = 0` (position 5–20, low CTR) need meta title/description improvements.

Pages with `impressions = 0` after 60 days need either noindex or content improvement.

### 7.2 — Admin SEO Dashboard

Add to admin routes:

```
GET /admin/seo/                 → Overview: total pages, indexed/noindex breakdown, top performing
GET /admin/seo/pages            → Full page list with status, position, clicks filters
GET /admin/seo/pages/{id}       → Individual page — edit content_blocks, meta, regenerate
GET /admin/seo/locations        → Manage locations, add new areas
GET /admin/seo/services         → Manage services, update salary ranges
POST /admin/seo/generate/{id}   → Manually trigger content regeneration for a page
POST /admin/seo/bulk-generate   → Queue content generation for all draft pages
```

### 7.3 — Content Refresh Schedule

```php
// Refresh content for pages that haven't been updated in 90 days
Schedule::job(new RefreshSeoContent)
    ->monthly()
    ->name('refresh-seo-content');
```

Pages are refreshed in priority order: service_area first (highest revenue intent), then service_city, then guides, then FAQs.

---

## Global Definition of Done

### Database
1. All 4 migrations run cleanly.
2. Locations seeded: minimum 3 Tier 1 cities with all major areas, 5 Tier 2 cities.
3. All 8 services seeded with correct salary ranges.
4. `GenerateSeoPageRegistry` runs and produces at least 1,000 `seo_pages` rows.

### Content Generation
5. `SeoContentGenerator::generate()` produces valid `content_blocks` JSON for a service × area page.
6. Generated FAQs: minimum 5 per page, each with a `short_answer` under 60 words.
7. Pages scoring below 65 are automatically set to `noindex`.
8. Salary figures in generated content match the values in `seo_services.salary_by_city` — never hallucinated numbers.

### Templates & Rendering
9. All SEO pages render as pure HTML (Blade, not Inertia) — verified by `curl -s {url} | grep "<h1>"`.
10. Each service × area page contains: H1, intro, salary table, how-it-works steps, hiring tips, FAQ section, CTA.
11. All CTA links use UTM parameters and pre-fill the quiz with service/location context.
12. Pages load in under 2 seconds on a 4G mobile connection (measured via PageSpeed Insights).

### Schema Markup
13. Every published page has valid JSON-LD — verified via Google's Rich Results Test.
14. Service × area pages have: WebPage, Organization, LocalBusiness, Service, FAQPage, BreadcrumbList, HowTo schemas.
15. FAQ schema validates in Google's tool — questions and answers are correctly nested.
16. Organization schema with `@id` is present on every page.

### Internal Linking
17. Every service × area page has 4–6 "nearby area" links to related pages.
18. Every city hub page lists all service × city pages for that city.
19. Every price guide links to the corresponding service × city page.
20. No orphan pages — every SEO page is reachable within 3 clicks from either the main site or another SEO page.

### Sitemap & Crawling
21. `sitemap.xml` returns valid XML and lists all 4 child sitemaps.
22. All published pages appear in the relevant child sitemap.
23. `robots.txt` allows `GPTBot`, `PerplexityBot`, `ClaudeBot`, `anthropic-ai`, `Google-Extended`.
24. All SEO pages are submitted to Google Search Console via the sitemap.

### AI-SEO
25. The 6 authoritative long-form guides are published and minimum 2,000 words each.
26. Organization entity schema includes `knowsAbout` with correct topic list.
27. `E-E-A-T` block is present on all hire guide and price guide pages.
28. Public stats API at `/api/seo/stats` returns live platform numbers.
29. Manual test: ask Perplexity "how much does a housekeeper cost in Lagos" — within 90 days of launch, Maids.ng should appear in the sources panel.

### Parallel Operation
30. Zero lines of existing app code modified — confirmed by `git diff` showing only new files and `routes/web.php` additions.
31. SEO pages link INTO the main app (quiz, registration) but the main app does not depend on SEO pages.
32. SEO routes do not conflict with any existing route — confirmed by `php artisan route:list | grep seo`.

---

*End of Programmatic SEO & AI-SEO Implementation Guide.*
