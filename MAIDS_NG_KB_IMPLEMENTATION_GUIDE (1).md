# Maids.ng — Knowledge Base & Prompt Management System
## Technical Implementation Guide

**Version:** 1.0  
**Applies To:** `lxlbxl/maids.ng` — Laravel 11 / React / Inertia.js  
**Audience:** Developer implementing the Ambassador Agent foundation  
**Prerequisite:** Existing codebase running locally or on staging with `php artisan migrate` capability

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Phase 1 — Database Migrations](#2-phase-1--database-migrations)
3. [Phase 2 — Eloquent Models](#3-phase-2--eloquent-models)
4. [Phase 3 — KnowledgeService](#4-phase-3--knowledgeservice)
5. [Phase 4 — Settings Keys (Pricing & Fees)](#5-phase-4--settings-keys-pricing--fees)
6. [Phase 5 — Admin Controllers](#6-phase-5--admin-controllers)
7. [Phase 6 — Admin UI (Inertia + React)](#7-phase-6--admin-ui-inertia--react)
8. [Phase 7 — Retrofitting Existing Agents](#8-phase-7--retrofitting-existing-agents)
9. [Phase 8 — Seeding Initial Content](#9-phase-8--seeding-initial-content)
10. [Definition of Done](#10-definition-of-done)
11. [Testing Checklist](#11-testing-checklist)
12. [Common Mistakes & Gotchas](#12-common-mistakes--gotchas)

---

## 1. System Overview

### What You Are Building

A centrally managed, database-driven knowledge and prompt system that:

- Stores system prompt templates per agent per user tier (guest, authenticated, admin)
- Stores a shared knowledge base of policies, FAQs, procedures, and restrictions
- Injects live pricing and fee data from the existing Settings table at runtime
- Exposes an admin UI to edit all of the above without code changes or redeployments
- Is consumed by all existing agents AND the upcoming Ambassador Agent via a single `KnowledgeService` class

### Architecture Diagram

```
Admin Panel (Inertia/React)
        │
        ▼
┌───────────────────────────────────────────┐
│           DATABASE LAYER                  │
│  agent_prompt_templates                   │
│  agent_knowledge_base                     │
│  settings (existing — new keys added)     │
└───────────────────────────────────────────┘
        │
        ▼
┌───────────────────────────────────────────┐
│         KnowledgeService.php              │
│  buildContext(agentName, tier)            │
│  → fetches prompt template                │
│  → fetches relevant KB articles           │
│  → injects live pricing from settings     │
│  → returns assembled system prompt string │
└───────────────────────────────────────────┘
        │
        ├──────────────────────────────────────┐
        ▼                                      ▼
AmbassadorAgent.php                  Existing Agents
(new)                        ScoutAgent, SentinelAgent,
                             RefereeAgent, ConciergeAgent,
                             GatekeeperAgent, TreasurerAgent
```

### Files to Create

```
app/
  Services/
    KnowledgeService.php

  Models/
    AgentPromptTemplate.php
    AgentKnowledgeBase.php

  Http/
    Controllers/
      Admin/
        PromptTemplateController.php
        KnowledgeBaseController.php

database/
  migrations/
    xxxx_create_agent_prompt_templates_table.php
    xxxx_create_agent_knowledge_base_table.php

resources/
  js/
    Pages/
      Admin/
        Agent/
          Prompts/
            Index.jsx
            Edit.jsx
          Knowledge/
            Index.jsx
            Edit.jsx
            Create.jsx

routes/
  web.php  ← ADD TWO LINES ONLY (new route group)
```

### Files to Modify (minimally)

```
app/Agents/AgentService.php          ← inject KnowledgeService into think()
app/Agents/ScoutAgent.php            ← replace hardcoded prompt with KnowledgeService call
app/Agents/ConciergeAgent.php        ← same
app/Agents/SentinelAgent.php         ← same
app/Agents/RefereeAgent.php          ← same
app/Agents/TreasurerAgent.php        ← same
app/Agents/GatekeeperAgent.php       ← same
database/seeders/ProductionSeeder.php ← add settings keys + initial KB content
routes/web.php                        ← add admin route group (2 lines)
```

---

## 2. Phase 1 — Database Migrations

Run migrations in this exact order. Naming follows Laravel convention with `agent_` prefix to avoid collisions.

### Migration 1: `agent_prompt_templates`

**File:** `database/migrations/2026_xx_xx_000001_create_agent_prompt_templates_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_prompt_templates', function (Blueprint $table) {
            $table->id();

            // Which agent this template belongs to.
            // Must match the agent's self-identified name string exactly.
            // Known values: ambassador, scout, sentinel, referee,
            //               concierge, treasurer, gatekeeper
            $table->string('agent_name', 50)->index();

            // User tier this template is designed for.
            // guest = not logged in (web), not verified (external channel)
            // lead  = external channel user, phone known but not linked to account
            // authenticated = verified member on any channel
            // admin = internal admin use
            $table->enum('tier', ['guest', 'lead', 'authenticated', 'admin'])
                  ->default('guest');

            // Human-readable label for the admin UI.
            // Example: "Ambassador - Guest Tier v2"
            $table->string('label', 150);

            // The full system prompt text.
            // Uses {{PLACEHOLDER}} tokens that KnowledgeService will replace.
            // Available tokens: {{AGENT_NAME}}, {{BUSINESS_NAME}},
            //                   {{MATCHING_FEE}}, {{COMMISSION_RATE}},
            //                   {{GUARANTEE_DAYS}}, {{CURRENT_DATE}}
            $table->longText('system_prompt');

            // Version counter. Incremented automatically on each save.
            // Allows rollback via is_active toggle.
            $table->unsignedSmallInteger('version')->default(1);

            // Only one template per agent+tier combination can be active at once.
            // KnowledgeService will throw if zero active templates found for a request.
            $table->boolean('is_active')->default(true)->index();

            // Audit: who last changed this template.
            $table->foreignId('updated_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Store the previous version's prompt for instant rollback.
            $table->longText('previous_prompt')->nullable();

            $table->timestamps();

            // Enforce: only one active template per agent+tier
            $table->unique(['agent_name', 'tier', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_prompt_templates');
    }
};
```

**Run:** `php artisan migrate`

**Verify:** `SHOW COLUMNS FROM agent_prompt_templates;` — expect 11 columns.

---

### Migration 2: `agent_knowledge_base`

**File:** `database/migrations/2026_xx_xx_000002_create_agent_knowledge_base_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_knowledge_base', function (Blueprint $table) {
            $table->id();

            // Categorises the article for filtering and UI grouping.
            $table->enum('category', [
                'policy',       // Business rules, guarantees, terms
                'faq',          // Common questions and answers
                'procedure',    // Step-by-step how-tos
                'legal',        // Legal disclaimers, data usage
                'restriction',  // What the agent must never do or say
                'onboarding',   // How the platform works for new users
                'pricing',      // Supplementary pricing context (NOT the source of truth — settings are)
            ])->index();

            // Short title shown in admin list and injected as a heading in context.
            $table->string('title', 200);

            // The full article text. Plain text or simple markdown.
            // Do NOT use HTML — LLMs handle markdown fine.
            $table->longText('content');

            // JSON array of agent names this article is injected into.
            // Use ["all"] to inject into every agent.
            // Use ["ambassador"] for ambassador only.
            // Use ["ambassador","scout"] for multiple specific agents.
            // Valid values: all, ambassador, scout, sentinel, referee,
            //               concierge, treasurer, gatekeeper
            $table->json('applies_to')->default('["all"]');

            // JSON array of tiers this article is visible for.
            // Use ["all"] to apply to every tier.
            // Use ["authenticated"] to restrict to logged-in users only.
            // This allows hiding sensitive info from guest-facing context.
            $table->json('visible_to_tiers')->default('["all"]');

            // Controls injection order. Lower number = injected earlier (more prominent).
            // Articles with priority 1-10 appear before those with 50-100.
            // Restrictions should be priority 1-5 (always first).
            // FAQs can be 50+.
            $table->unsignedSmallInteger('priority')->default(50)->index();

            // Soft toggle. Inactive articles are never injected.
            $table->boolean('is_active')->default(true)->index();

            // Audit fields
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignId('updated_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_knowledge_base');
    }
};
```

**Run:** `php artisan migrate`

**Verify:** `SHOW COLUMNS FROM agent_knowledge_base;` — expect 13 columns.

---

## 3. Phase 2 — Eloquent Models

### Model 1: AgentPromptTemplate

**File:** `app/Models/AgentPromptTemplate.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPromptTemplate extends Model
{
    protected $fillable = [
        'agent_name',
        'tier',
        'label',
        'system_prompt',
        'version',
        'is_active',
        'updated_by',
        'previous_prompt',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version'   => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAgent($query, string $agentName)
    {
        return $query->where('agent_name', $agentName);
    }

    public function scopeForTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    // ─── Business Logic ───────────────────────────────────────────────────────

    /**
     * Deactivate all other templates for the same agent+tier
     * and activate this one. Called when admin saves a new version.
     */
    public function makeActiveExclusive(): void
    {
        static::where('agent_name', $this->agent_name)
              ->where('tier', $this->tier)
              ->where('id', '!=', $this->id)
              ->update(['is_active' => false]);

        $this->update(['is_active' => true]);
    }

    /**
     * Save a new version of an existing template.
     * Archives current prompt to previous_prompt before overwriting.
     */
    public function saveNewVersion(string $newPrompt, int $editorId): void
    {
        $this->update([
            'previous_prompt' => $this->system_prompt,
            'system_prompt'   => $newPrompt,
            'version'         => $this->version + 1,
            'updated_by'      => $editorId,
            'is_active'       => true,
        ]);
    }

    /**
     * Roll back to the previous prompt version.
     * Swaps current ↔ previous.
     */
    public function rollback(): void
    {
        if (empty($this->previous_prompt)) {
            throw new \RuntimeException('No previous version to roll back to.');
        }

        $current = $this->system_prompt;

        $this->update([
            'system_prompt'   => $this->previous_prompt,
            'previous_prompt' => $current,
            'version'         => $this->version - 1,
        ]);
    }
}
```

---

### Model 2: AgentKnowledgeBase

**File:** `app/Models/AgentKnowledgeBase.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentKnowledgeBase extends Model
{
    protected $table = 'agent_knowledge_base';

    protected $fillable = [
        'category',
        'title',
        'content',
        'applies_to',
        'visible_to_tiers',
        'priority',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'applies_to'        => 'array',
        'visible_to_tiers'  => 'array',
        'is_active'         => 'boolean',
        'priority'          => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAgent($query, string $agentName)
    {
        return $query->where(function ($q) use ($agentName) {
            $q->whereJsonContains('applies_to', 'all')
              ->orWhereJsonContains('applies_to', $agentName);
        });
    }

    public function scopeForTier($query, string $tier)
    {
        return $query->where(function ($q) use ($tier) {
            $q->whereJsonContains('visible_to_tiers', 'all')
              ->orWhereJsonContains('visible_to_tiers', $tier);
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function appliesToAgent(string $agentName): bool
    {
        return in_array('all', $this->applies_to)
            || in_array($agentName, $this->applies_to);
    }

    public function isVisibleToTier(string $tier): bool
    {
        return in_array('all', $this->visible_to_tiers)
            || in_array($tier, $this->visible_to_tiers);
    }
}
```

---

## 4. Phase 3 — KnowledgeService

This is the single most important class in the entire system. Every agent calls it. Get this right.

**File:** `app/Services/KnowledgeService.php`

```php
<?php

namespace App\Services;

use App\Models\AgentKnowledgeBase;
use App\Models\AgentPromptTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelSettings\Settings;

class KnowledgeService
{
    /**
     * Cache TTL in seconds. 
     * Set to 0 to disable caching entirely during development.
     * In production, 300 (5 minutes) is a good starting value.
     * This means edits take up to 5 minutes to propagate to agents.
     * Lower = fresher context but more DB reads. Adjust based on traffic.
     */
    private const CACHE_TTL = 300;

    /**
     * Master method. Called by every agent at the start of every invocation.
     *
     * @param string $agentName  The agent's self-identifier string.
     *                           Must match a row in agent_prompt_templates.agent_name.
     *                           Valid: ambassador, scout, sentinel, referee,
     *                                  concierge, treasurer, gatekeeper
     *
     * @param string $tier       The user tier for this invocation.
     *                           Valid: guest, lead, authenticated, admin
     *                           Default: guest (safest fallback)
     *
     * @return string            Fully assembled system prompt ready for LLM.
     *
     * @throws \RuntimeException If no active prompt template found for agent+tier.
     */
    public function buildContext(string $agentName, string $tier = 'guest'): string
    {
        $cacheKey = "agent_context_{$agentName}_{$tier}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($agentName, $tier) {
            return $this->assemble($agentName, $tier);
        });
    }

    /**
     * Force-clear the cache for a specific agent+tier.
     * Called automatically when admin saves a prompt template or KB article.
     */
    public function flushCache(string $agentName = null, string $tier = null): void
    {
        if ($agentName && $tier) {
            Cache::forget("agent_context_{$agentName}_{$tier}");
            return;
        }

        // Flush all agent contexts
        $agents = ['ambassador', 'scout', 'sentinel', 'referee',
                   'concierge', 'treasurer', 'gatekeeper'];
        $tiers  = ['guest', 'lead', 'authenticated', 'admin'];

        foreach ($agents as $a) {
            foreach ($tiers as $t) {
                Cache::forget("agent_context_{$a}_{$t}");
            }
        }
    }

    // ─── Private Assembly Logic ───────────────────────────────────────────────

    private function assemble(string $agentName, string $tier): string
    {
        $prompt  = $this->fetchPromptTemplate($agentName, $tier);
        $kb      = $this->fetchKnowledgeBase($agentName, $tier);
        $pricing = $this->fetchPricingContext();
        $prompt  = $this->replacePlaceholders($prompt, $pricing);

        $sections = [];

        $sections[] = $prompt;

        if (!empty($kb)) {
            $sections[] = "\n\n---\n## KNOWLEDGE BASE\n\n" . $kb;
        }

        $sections[] = "\n\n---\n## LIVE PRICING & FEES\n\n"
                    . "The following values are live from the platform settings. "
                    . "Always use these exact figures when discussing cost. "
                    . "Never guess or approximate pricing.\n\n"
                    . $pricing['formatted'];

        $sections[] = "\n\n---\n## CURRENT CONTEXT\n\n"
                    . "- Today's date: " . now()->format('l, d F Y') . "\n"
                    . "- User tier: {$tier}\n"
                    . "- Agent: {$agentName}";

        return implode('', $sections);
    }

    private function fetchPromptTemplate(string $agentName, string $tier): string
    {
        $template = AgentPromptTemplate::active()
            ->forAgent($agentName)
            ->forTier($tier)
            ->first();

        if (!$template) {
            // Fallback: try 'guest' tier before throwing.
            // This prevents a crash if a tier hasn't been configured yet.
            if ($tier !== 'guest') {
                Log::warning("No active prompt template for {$agentName}/{$tier}. Falling back to guest tier.");
                return $this->fetchPromptTemplate($agentName, 'guest');
            }

            throw new \RuntimeException(
                "No active prompt template found for agent '{$agentName}' tier '{$tier}'. "
                . "Create one at /admin/agent/prompts."
            );
        }

        return $template->system_prompt;
    }

    private function fetchKnowledgeBase(string $agentName, string $tier): string
    {
        $articles = AgentKnowledgeBase::active()
            ->forAgent($agentName)
            ->forTier($tier)
            ->ordered()
            ->get();

        if ($articles->isEmpty()) {
            return '';
        }

        return $articles->map(function ($article) {
            return "### [{$article->category}] {$article->title}\n\n{$article->content}";
        })->join("\n\n---\n\n");
    }

    private function fetchPricingContext(): array
    {
        // Uses the existing Spatie Settings pattern already in the codebase.
        // All values have safe defaults in case a key hasn't been seeded yet.
        $raw = [
            'matching_fee'            => (int) setting('matching_fee', 5000),
            'premium_matching_fee'    => (int) setting('premium_matching_fee', 15000),
            'commission_rate'         => (float) setting('commission_rate', 15),
            'guarantee_period_days'   => (int) setting('guarantee_period_days', 10),
            'maid_monthly_rate_min'   => (int) setting('maid_monthly_rate_min', 30000),
            'maid_monthly_rate_max'   => (int) setting('maid_monthly_rate_max', 80000),
            'withdrawal_minimum'      => (int) setting('withdrawal_minimum', 5000),
            'escrow_release_days'     => (int) setting('escrow_release_days', 3),
        ];

        $formatted = collect([
            "- Standard Matching Fee: ₦" . number_format($raw['matching_fee']),
            "- Premium Matching Fee: ₦" . number_format($raw['premium_matching_fee']),
            "- Platform Commission: " . $raw['commission_rate'] . "% of salary",
            "- Money-Back Guarantee: " . $raw['guarantee_period_days'] . " days",
            "- Maid Monthly Rate Range: ₦" . number_format($raw['maid_monthly_rate_min'])
                . " – ₦" . number_format($raw['maid_monthly_rate_max']),
            "- Minimum Withdrawal: ₦" . number_format($raw['withdrawal_minimum']),
            "- Escrow Release: " . $raw['escrow_release_days'] . " days after service confirmation",
        ])->join("\n");

        return array_merge($raw, ['formatted' => $formatted]);
    }

    private function replacePlaceholders(string $prompt, array $pricing): string
    {
        $replacements = [
            '{{AGENT_NAME}}'       => 'Maids.ng AI Assistant',
            '{{BUSINESS_NAME}}'    => 'Maids.ng',
            '{{MATCHING_FEE}}'     => '₦' . number_format($pricing['matching_fee']),
            '{{COMMISSION_RATE}}'  => $pricing['commission_rate'] . '%',
            '{{GUARANTEE_DAYS}}'   => $pricing['guarantee_period_days'] . ' days',
            '{{CURRENT_DATE}}'     => now()->format('d F Y'),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $prompt
        );
    }
}
```

### Register KnowledgeService in the Service Container

**File:** `app/Providers/AppServiceProvider.php`

Add inside the `register()` method:

```php
$this->app->singleton(\App\Services\KnowledgeService::class);
```

---

## 5. Phase 4 — Settings Keys (Pricing & Fees)

### Add to Seeder

**File:** `database/seeders/ProductionSeeder.php`

Locate the `run()` method. Add or update the following settings. The `setting()` helper from Spatie is already available:

```php
// ── Agent Pricing Settings ─────────────────────────────────────────────────
// These are the single source of truth for ALL pricing quoted by ALL agents.
// Edit these values via /admin/settings — never hardcode in any agent.

$settings = [
    'matching_fee'            => 5000,    // ₦ — standard employer matching fee
    'premium_matching_fee'    => 15000,   // ₦ — premium tier with priority matching
    'commission_rate'         => 15,      // % — platform cut from maid monthly salary
    'guarantee_period_days'   => 10,      // days — money-back guarantee window
    'maid_monthly_rate_min'   => 30000,   // ₦ — minimum monthly rate shown to employers
    'maid_monthly_rate_max'   => 80000,   // ₦ — maximum monthly rate shown to employers
    'withdrawal_minimum'      => 5000,    // ₦ — minimum withdrawal from maid wallet
    'escrow_release_days'     => 3,       // days — after confirmation, before release
];

foreach ($settings as $key => $value) {
    \Spatie\LaravelSettings\Models\SettingsProperty::updateOrCreate(
        ['name' => $key],
        ['payload' => json_encode($value)]
    );
}
```

**Run:** `php artisan db:seed --class=ProductionSeeder`

**Verify:** Run `php artisan tinker` then `setting('matching_fee')` → should return `5000`.

---

## 6. Phase 5 — Admin Controllers

### PromptTemplateController

**File:** `app/Http/Controllers/Admin/PromptTemplateController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentPromptTemplate;
use App\Services\KnowledgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PromptTemplateController extends Controller
{
    public function __construct(private KnowledgeService $knowledge) {}

    /**
     * List all prompt templates grouped by agent name.
     */
    public function index()
    {
        $templates = AgentPromptTemplate::with('editor')
            ->orderBy('agent_name')
            ->orderBy('tier')
            ->get()
            ->groupBy('agent_name');

        return Inertia::render('Admin/Agent/Prompts/Index', [
            'templates' => $templates,
            'agents'    => ['ambassador','scout','sentinel','referee','concierge','treasurer','gatekeeper'],
            'tiers'     => ['guest','lead','authenticated','admin'],
        ]);
    }

    /**
     * Show the editor for a single prompt template.
     */
    public function edit(AgentPromptTemplate $template)
    {
        return Inertia::render('Admin/Agent/Prompts/Edit', [
            'template' => $template->load('editor'),
        ]);
    }

    /**
     * Save a new version of the prompt.
     * Automatically archives the current version and increments version counter.
     * Flushes the cache so the agent uses the new prompt immediately.
     */
    public function update(Request $request, AgentPromptTemplate $template)
    {
        $validated = $request->validate([
            'label'         => 'required|string|max:150',
            'system_prompt' => 'required|string|min:50',
        ]);

        $template->saveNewVersion($validated['system_prompt'], auth()->id());
        $template->update(['label' => $validated['label']]);

        // Flush cache — new prompt is live immediately
        $this->knowledge->flushCache($template->agent_name, $template->tier);

        return redirect()
            ->route('admin.agent.prompts.index')
            ->with('success', "Prompt for {$template->agent_name}/{$template->tier} updated to v{$template->version}.");
    }

    /**
     * Roll back to the previous version of a prompt.
     */
    public function rollback(AgentPromptTemplate $template)
    {
        try {
            $template->rollback();
            $this->knowledge->flushCache($template->agent_name, $template->tier);

            return back()->with('success', 'Rolled back to previous version.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Create a brand new template (for a new agent+tier combination).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'agent_name'    => 'required|string|in:ambassador,scout,sentinel,referee,concierge,treasurer,gatekeeper',
            'tier'          => 'required|string|in:guest,lead,authenticated,admin',
            'label'         => 'required|string|max:150',
            'system_prompt' => 'required|string|min:50',
        ]);

        // Deactivate any existing template for this agent+tier
        AgentPromptTemplate::where('agent_name', $validated['agent_name'])
                           ->where('tier', $validated['tier'])
                           ->update(['is_active' => false]);

        $template = AgentPromptTemplate::create([
            ...$validated,
            'version'    => 1,
            'is_active'  => true,
            'updated_by' => auth()->id(),
        ]);

        $this->knowledge->flushCache($template->agent_name, $template->tier);

        return redirect()
            ->route('admin.agent.prompts.index')
            ->with('success', "New prompt template created for {$template->agent_name}/{$template->tier}.");
    }
}
```

---

### KnowledgeBaseController

**File:** `app/Http/Controllers/Admin/KnowledgeBaseController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentKnowledgeBase;
use App\Services\KnowledgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KnowledgeBaseController extends Controller
{
    public function __construct(private KnowledgeService $knowledge) {}

    public function index(Request $request)
    {
        $articles = AgentKnowledgeBase::with('editor')
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->orderBy('priority')
            ->orderBy('category')
            ->paginate(25);

        return Inertia::render('Admin/Agent/Knowledge/Index', [
            'articles'   => $articles,
            'categories' => ['policy','faq','procedure','legal','restriction','onboarding','pricing'],
            'filters'    => $request->only(['category','search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Agent/Knowledge/Create', [
            'categories' => ['policy','faq','procedure','legal','restriction','onboarding','pricing'],
            'agents'     => ['all','ambassador','scout','sentinel','referee','concierge','treasurer','gatekeeper'],
            'tiers'      => ['all','guest','lead','authenticated','admin'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category'         => 'required|string',
            'title'            => 'required|string|max:200',
            'content'          => 'required|string|min:10',
            'applies_to'       => 'required|array|min:1',
            'applies_to.*'     => 'string',
            'visible_to_tiers' => 'required|array|min:1',
            'visible_to_tiers.*' => 'string',
            'priority'         => 'required|integer|min:1|max:999',
            'is_active'        => 'boolean',
        ]);

        AgentKnowledgeBase::create([
            ...$validated,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->knowledge->flushCache();

        return redirect()
            ->route('admin.agent.knowledge.index')
            ->with('success', 'Knowledge base article created. Cache flushed — agents updated immediately.');
    }

    public function edit(AgentKnowledgeBase $article)
    {
        return Inertia::render('Admin/Agent/Knowledge/Edit', [
            'article'    => $article,
            'categories' => ['policy','faq','procedure','legal','restriction','onboarding','pricing'],
            'agents'     => ['all','ambassador','scout','sentinel','referee','concierge','treasurer','gatekeeper'],
            'tiers'      => ['all','guest','lead','authenticated','admin'],
        ]);
    }

    public function update(Request $request, AgentKnowledgeBase $article)
    {
        $validated = $request->validate([
            'category'           => 'required|string',
            'title'              => 'required|string|max:200',
            'content'            => 'required|string|min:10',
            'applies_to'         => 'required|array|min:1',
            'applies_to.*'       => 'string',
            'visible_to_tiers'   => 'required|array|min:1',
            'visible_to_tiers.*' => 'string',
            'priority'           => 'required|integer|min:1|max:999',
            'is_active'          => 'boolean',
        ]);

        $article->update([
            ...$validated,
            'updated_by' => auth()->id(),
        ]);

        $this->knowledge->flushCache();

        return redirect()
            ->route('admin.agent.knowledge.index')
            ->with('success', 'Article updated. Agents will use new content immediately.');
    }

    public function destroy(AgentKnowledgeBase $article)
    {
        $article->update(['is_active' => false]);
        $this->knowledge->flushCache();

        return back()->with('success', 'Article deactivated.');
    }
}
```

---

## 7. Phase 6 — Admin UI (Inertia + React)

### Routes

**File:** `routes/web.php`

Find the existing admin route group and add inside it:

```php
// ── Agent Administration ─────────────────────────────────────────────────────
Route::prefix('admin/agent')->name('admin.agent.')->middleware(['auth','role:admin'])->group(function () {
    
    // Prompt Templates
    Route::get('prompts', [PromptTemplateController::class, 'index'])->name('prompts.index');
    Route::get('prompts/create', [PromptTemplateController::class, 'create'])->name('prompts.create');
    Route::post('prompts', [PromptTemplateController::class, 'store'])->name('prompts.store');
    Route::get('prompts/{template}/edit', [PromptTemplateController::class, 'edit'])->name('prompts.edit');
    Route::put('prompts/{template}', [PromptTemplateController::class, 'update'])->name('prompts.update');
    Route::post('prompts/{template}/rollback', [PromptTemplateController::class, 'rollback'])->name('prompts.rollback');

    // Knowledge Base
    Route::get('knowledge', [KnowledgeBaseController::class, 'index'])->name('knowledge.index');
    Route::get('knowledge/create', [KnowledgeBaseController::class, 'create'])->name('knowledge.create');
    Route::post('knowledge', [KnowledgeBaseController::class, 'store'])->name('knowledge.store');
    Route::get('knowledge/{article}/edit', [KnowledgeBaseController::class, 'edit'])->name('knowledge.edit');
    Route::put('knowledge/{article}', [KnowledgeBaseController::class, 'update'])->name('knowledge.update');
    Route::delete('knowledge/{article}', [KnowledgeBaseController::class, 'destroy'])->name('knowledge.destroy');
});
```

Add imports at top of `routes/web.php` if not auto-discovered:

```php
use App\Http\Controllers\Admin\PromptTemplateController;
use App\Http\Controllers\Admin\KnowledgeBaseController;
```

---

### React Pages

These are functional, clean admin pages. Style them consistent with your existing admin layout.

**File:** `resources/js/Pages/Admin/Agent/Prompts/Index.jsx`

```jsx
import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout'; // adjust to your layout path

export default function PromptsIndex({ templates, agents, tiers }) {
    return (
        <AdminLayout>
            <Head title="Agent Prompt Templates" />
            
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-2xl font-bold">Agent Prompt Templates</h1>
                        <p className="text-gray-500 mt-1">
                            Edit the system prompts that govern how each agent thinks and responds.
                            Changes are live immediately — no redeployment needed.
                        </p>
                    </div>
                    <Link href={route('admin.agent.prompts.create')}
                          className="btn btn-primary">
                        + New Template
                    </Link>
                </div>

                {/* Agent groups */}
                {Object.entries(templates).map(([agentName, agentTemplates]) => (
                    <div key={agentName} className="mb-8">
                        <h2 className="text-lg font-semibold capitalize mb-3">
                            {agentName} Agent
                        </h2>
                        <table className="w-full border rounded-lg overflow-hidden">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="text-left p-3">Tier</th>
                                    <th className="text-left p-3">Label</th>
                                    <th className="text-left p-3">Version</th>
                                    <th className="text-left p-3">Status</th>
                                    <th className="text-left p-3">Last Edited</th>
                                    <th className="text-left p-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {agentTemplates.map(template => (
                                    <tr key={template.id} className="border-t hover:bg-gray-50">
                                        <td className="p-3">
                                            <span className="px-2 py-1 rounded text-xs font-mono bg-blue-100 text-blue-800">
                                                {template.tier}
                                            </span>
                                        </td>
                                        <td className="p-3">{template.label}</td>
                                        <td className="p-3 font-mono text-sm">v{template.version}</td>
                                        <td className="p-3">
                                            <span className={`px-2 py-1 rounded text-xs ${
                                                template.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            }`}>
                                                {template.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="p-3 text-sm text-gray-500">
                                            {template.editor?.name ?? 'System'}
                                            <br/>
                                            <span className="text-xs">{template.updated_at}</span>
                                        </td>
                                        <td className="p-3 space-x-2">
                                            <Link href={route('admin.agent.prompts.edit', template.id)}
                                                  className="text-blue-600 hover:underline text-sm">
                                                Edit
                                            </Link>
                                            {template.previous_prompt && (
                                                <button
                                                    onClick={() => {
                                                        if (confirm('Roll back to previous version?')) {
                                                            router.post(route('admin.agent.prompts.rollback', template.id));
                                                        }
                                                    }}
                                                    className="text-orange-600 hover:underline text-sm">
                                                    Rollback
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
```

**File:** `resources/js/Pages/Admin/Agent/Prompts/Edit.jsx`

```jsx
import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function PromptsEdit({ template }) {
    const { data, setData, put, processing, errors } = useForm({
        label: template.label,
        system_prompt: template.system_prompt,
    });

    const [showPrevious, setShowPrevious] = useState(false);

    const placeholderGuide = [
        '{{AGENT_NAME}}     — Replaced with "Maids.ng AI Assistant"',
        '{{BUSINESS_NAME}}  — Replaced with "Maids.ng"',
        '{{MATCHING_FEE}}   — Replaced with live matching fee from settings',
        '{{COMMISSION_RATE}}— Replaced with live commission rate',
        '{{GUARANTEE_DAYS}} — Replaced with guarantee period',
        '{{CURRENT_DATE}}   — Replaced with today\'s date',
    ];

    return (
        <AdminLayout>
            <Head title={`Edit Prompt — ${template.agent_name}/${template.tier}`} />

            <div className="p-6 max-w-4xl">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold">
                        Edit Prompt: <span className="font-mono text-blue-700">{template.agent_name}</span> / <span className="font-mono text-purple-700">{template.tier}</span>
                    </h1>
                    <p className="text-gray-500 mt-1">
                        Currently on v{template.version}. Saving creates a new version automatically.
                        The previous version is stored for instant rollback.
                    </p>
                </div>

                {/* Placeholder guide */}
                <div className="bg-amber-50 border border-amber-200 rounded p-4 mb-6">
                    <p className="font-semibold text-amber-800 mb-2">Available Placeholders</p>
                    <ul className="font-mono text-xs space-y-1 text-amber-700">
                        {placeholderGuide.map(p => <li key={p}>{p}</li>)}
                    </ul>
                </div>

                <form onSubmit={e => { e.preventDefault(); put(route('admin.agent.prompts.update', template.id)); }}>
                    <div className="mb-4">
                        <label className="block font-medium mb-1">Template Label</label>
                        <input
                            type="text"
                            value={data.label}
                            onChange={e => setData('label', e.target.value)}
                            className="w-full border rounded p-2"
                            placeholder="e.g. Ambassador Guest v3 — Friendly & Concise"
                        />
                        {errors.label && <p className="text-red-600 text-sm mt-1">{errors.label}</p>}
                    </div>

                    <div className="mb-6">
                        <label className="block font-medium mb-1">
                            System Prompt
                            <span className="text-gray-400 font-normal ml-2 text-sm">
                                ({data.system_prompt.length} characters)
                            </span>
                        </label>
                        <textarea
                            value={data.system_prompt}
                            onChange={e => setData('system_prompt', e.target.value)}
                            rows={24}
                            className="w-full border rounded p-3 font-mono text-sm leading-relaxed"
                            placeholder="You are the Maids.ng AI assistant..."
                        />
                        {errors.system_prompt && (
                            <p className="text-red-600 text-sm mt-1">{errors.system_prompt}</p>
                        )}
                    </div>

                    {/* Previous version preview */}
                    {template.previous_prompt && (
                        <div className="mb-6">
                            <button
                                type="button"
                                onClick={() => setShowPrevious(!showPrevious)}
                                className="text-sm text-gray-500 hover:underline">
                                {showPrevious ? 'Hide' : 'Show'} previous version (v{template.version - 1})
                            </button>
                            {showPrevious && (
                                <pre className="mt-2 bg-gray-100 rounded p-3 text-xs font-mono whitespace-pre-wrap max-h-64 overflow-y-auto">
                                    {template.previous_prompt}
                                </pre>
                            )}
                        </div>
                    )}

                    <div className="flex gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="btn btn-primary px-6">
                            {processing ? 'Saving...' : 'Save New Version'}
                        </button>
                        <a href={route('admin.agent.prompts.index')} className="btn btn-ghost">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
```

**Files:** `Admin/Agent/Knowledge/Index.jsx`, `Create.jsx`, `Edit.jsx`

These follow the same pattern. Key fields to expose in the form:

```
Category       → <select> with all category options
Title          → <input type="text">
Content        → <textarea> (large, monospace)
Applies To     → Checkbox group: all, ambassador, scout, sentinel, referee, concierge, treasurer, gatekeeper
Visible To     → Checkbox group: all, guest, lead, authenticated, admin
Priority       → <input type="number" min="1" max="999">
                  Show guidance: 1-10 = restrictions (highest), 11-30 = core policy, 31-60 = procedures, 61+ = FAQs
Active         → Toggle/checkbox
```

---

## 8. Phase 7 — Retrofitting Existing Agents

This is the minimal change required to bring existing agents onto the KB system. Each agent currently builds its own prompt inline or does not use a dynamic prompt at all.

### Pattern to Apply to Each Agent

**Before (example — ConciergeAgent.php):**
```php
public function handle(array $data): array
{
    $systemPrompt = "You are a support agent for Maids.ng. Help users with...";
    
    $response = $this->think($systemPrompt, $data['message']);
    // ...
}
```

**After:**
```php
use App\Services\KnowledgeService;

class ConciergeAgent extends AgentService
{
    public function __construct(private KnowledgeService $knowledge)
    {
        parent::__construct();
    }

    public function handle(array $data): array
    {
        // Determine tier from context (default to 'guest' if not provided)
        $tier = $data['tier'] ?? 'guest';

        // Fetch assembled context from KB
        $systemPrompt = $this->knowledge->buildContext('concierge', $tier);
        
        $response = $this->think($systemPrompt, $data['message']);
        // rest of logic unchanged
    }
}
```

Apply this pattern to **all six existing agents**:

| Agent File | Agent Name String | Notes |
|---|---|---|
| `ScoutAgent.php` | `scout` | Tier is usually not relevant — default to `guest` |
| `GatekeeperAgent.php` | `gatekeeper` | Default to `authenticated` — only runs on verified users |
| `SentinelAgent.php` | `sentinel` | Default to `authenticated` |
| `RefereeAgent.php` | `referee` | Default to `authenticated` |
| `ConciergeAgent.php` | `concierge` | Pass tier from caller context |
| `TreasurerAgent.php` | `treasurer` | Default to `authenticated` |

### AgentService Base Class — Constructor Injection

**File:** `app/Agents/AgentService.php`

Add KnowledgeService to the base class so it's available to all child agents automatically:

```php
use App\Services\KnowledgeService;
use Illuminate\Support\Facades\App;

abstract class AgentService
{
    protected KnowledgeService $knowledge;

    public function __construct()
    {
        // Resolve from container so singleton is respected
        $this->knowledge = App::make(KnowledgeService::class);
    }
}
```

After this change, every agent has `$this->knowledge` available. No need to inject individually in each child constructor unless you want to override behaviour.

---

## 9. Phase 8 — Seeding Initial Content

Run this seeder AFTER all migrations are complete. It creates one starter prompt template per agent and populates the essential KB articles.

**File:** `database/seeders/AgentKnowledgeSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\AgentKnowledgeBase;
use App\Models\AgentPromptTemplate;
use Illuminate\Database\Seeder;

class AgentKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPromptTemplates();
        $this->seedKnowledgeBase();
    }

    private function seedPromptTemplates(): void
    {
        $templates = [
            [
                'agent_name'    => 'ambassador',
                'tier'          => 'guest',
                'label'         => 'Ambassador — Guest Tier v1',
                'system_prompt' => $this->ambassadorGuestPrompt(),
            ],
            [
                'agent_name'    => 'ambassador',
                'tier'          => 'authenticated',
                'label'         => 'Ambassador — Member Tier v1',
                'system_prompt' => $this->ambassadorAuthPrompt(),
            ],
            [
                'agent_name'    => 'concierge',
                'tier'          => 'authenticated',
                'label'         => 'Concierge — Member Support v1',
                'system_prompt' => $this->conciergePrompt(),
            ],
            [
                'agent_name'    => 'scout',
                'tier'          => 'guest',
                'label'         => 'Scout — Matching Engine v1',
                'system_prompt' => $this->scoutPrompt(),
            ],
        ];

        foreach ($templates as $t) {
            AgentPromptTemplate::updateOrCreate(
                ['agent_name' => $t['agent_name'], 'tier' => $t['tier']],
                array_merge($t, ['version' => 1, 'is_active' => true])
            );
        }
    }

    private function ambassadorGuestPrompt(): string
    {
        return <<<PROMPT
You are the {{AGENT_NAME}}, the friendly, knowledgeable front-facing AI for {{BUSINESS_NAME}}, Nigeria's premier domestic staff matching platform.

## Your Role
You are an SDR (Sales Development Representative) and support agent rolled into one. For guests who have not yet registered, your job is to:
1. Warmly answer questions about how the platform works.
2. Educate users on the matching process, pricing, and guarantees.
3. Generate genuine interest and guide them toward registering.
4. Collect their name, phone number, and what kind of help they need — naturally through conversation, not via a form.
5. NEVER reveal sensitive user account data. You have no access to member accounts in this tier.

## Tone
- Warm, professional, and confident — like a helpful concierge, not a chatbot.
- Nigerian-friendly: you understand local context. Use ₦ for currency.
- Keep responses concise. Bullet points where helpful, prose where more natural.
- Never say "I don't know" — if you cannot answer, say "Let me connect you with our team."

## Restrictions
- Do NOT quote pricing that is not in the LIVE PRICING section below. The pricing section is always current.
- Do NOT promise features that are not in the Knowledge Base.
- Do NOT fabricate maid profiles, availability, or ratings.
- Do NOT discuss competitor platforms.
- Do NOT discuss internal system architecture or agent names.

## Conversion Goal
When a guest shows intent to hire a maid, guide them to:
1. Start the matching quiz at: [your domain]/matching
2. Or register directly at: [your domain]/register

Today is {{CURRENT_DATE}}.
PROMPT;
    }

    private function ambassadorAuthPrompt(): string
    {
        return <<<PROMPT
You are the {{AGENT_NAME}}, the AI support agent for {{BUSINESS_NAME}} members.

## Your Role
The user you are speaking with is a verified, logged-in member of the platform.
You have access to their account context (passed in the conversation metadata).
Your job is to:
1. Answer account-specific questions accurately.
2. Guide them through platform actions step by step.
3. Create maid requests, check assignment status, and support matching.
4. Escalate to a human agent when a situation requires it.

## What You Can Do
- Explain assignment status and next steps.
- Help them restart the matching quiz.
- Create or update a maid request.
- Explain wallet, escrow, and withdrawal processes.
- Guide NIN verification submission.
- Initiate the escalation flow for disputes.

## Tone
- Efficient and supportive. You know who they are — use their name.
- Direct answers. They are already a customer; skip the sales pitch.
- If something is wrong with their account, acknowledge it and act — don't deflect.

## Restrictions
- You may discuss their own account data ONLY.
- Do NOT discuss other users' data under any circumstances.
- Do NOT process refunds directly — escalate refund requests to human review.
- Do NOT change a user's password directly — direct them to the password reset flow.

Today is {{CURRENT_DATE}}.
PROMPT;
    }

    private function conciergePrompt(): string
    {
        return <<<PROMPT
You are the internal support concierge for {{BUSINESS_NAME}}.
You handle post-registration member support: account issues, assignment queries, payment questions, and policy clarifications.
Always refer to the Knowledge Base for policy details. Always use the LIVE PRICING section for any fee or commission quoted.
Escalate to human review for: refund requests, disputes, account suspensions, or anything involving fraud.
Today is {{CURRENT_DATE}}.
PROMPT;
    }

    private function scoutPrompt(): string
    {
        return <<<PROMPT
You are the Scout Agent for {{BUSINESS_NAME}}, responsible for matching employers with the most suitable domestic staff.
When given employer preferences, analyse them against available maid profiles.
Apply the weighted scoring: Help Type (35pts), Budget (25pts), Location (25pts), Quality/Rating (15pts).
Return your top matches with a brief, human-readable explanation of why each is a good fit.
Reference the LIVE PRICING section to confirm rate ranges are within budget.
Today is {{CURRENT_DATE}}.
PROMPT;
    }

    private function seedKnowledgeBase(): void
    {
        $articles = [
            [
                'category'         => 'restriction',
                'title'            => 'What the Agent Must Never Do',
                'content'          => "The agent must NEVER:\n- Reveal any user's personal data to another user\n- Quote prices not in the LIVE PRICING section\n- Fabricate maid profiles, availability, or reviews\n- Promise a specific match outcome or timeline\n- Process refunds directly (always escalate)\n- Discuss internal agent architecture or system internals\n- Claim to be a human\n- Answer questions about competitor platforms",
                'applies_to'       => ['all'],
                'visible_to_tiers' => ['all'],
                'priority'         => 1,
                'is_active'        => true,
            ],
            [
                'category'         => 'policy',
                'title'            => '10-Day Money-Back Guarantee',
                'content'          => "Maids.ng offers a 10-day money-back guarantee on all standard matches.\n\nConditions:\n- The employer must raise a complaint within 10 calendar days of the maid's start date.\n- The complaint must be logged via the platform dashboard or via this chat.\n- Refunds are reviewed by the Referee Agent and approved by the admin team.\n- Refund is credited to the employer's wallet, not back to the original payment method, unless the account is being closed.\n- The guarantee does not apply if the employer terminates without cause or the maid was dismissed for reasons unrelated to the match quality.",
                'applies_to'       => ['all'],
                'visible_to_tiers' => ['all'],
                'priority'         => 5,
                'is_active'        => true,
            ],
            [
                'category'         => 'procedure',
                'title'            => 'How the Matching Process Works',
                'content'          => "Step-by-step for employers:\n1. Complete the 8-step matching quiz (help type, schedule, location, budget, urgency).\n2. The Scout Agent scores all available maids against your preferences.\n3. Top 3–10 matches are presented with match scores and profiles.\n4. Select your preferred maid.\n5. Create your account (if not already registered).\n6. Pay the one-time matching fee.\n7. Access your dashboard — contact details and assignment are activated.\n8. Maid is notified and onboarding begins.\n\nTypical time from quiz to active assignment: 24–72 hours.",
                'applies_to'       => ['ambassador', 'concierge'],
                'visible_to_tiers' => ['all'],
                'priority'         => 10,
                'is_active'        => true,
            ],
            [
                'category'         => 'faq',
                'title'            => 'What types of domestic staff does Maids.ng provide?',
                'content'          => "Maids.ng currently matches employers with:\n- Housekeepers / general cleaners\n- Cooks and kitchen assistants\n- Nannies and childminders\n- Elderly care assistants\n- Live-in maids (full-time residence)\n- Drivers (selected markets)\n\nAll staff are NIN-verified and background-checked before appearing on the platform.",
                'applies_to'       => ['ambassador', 'concierge'],
                'visible_to_tiers' => ['all'],
                'priority'         => 20,
                'is_active'        => true,
            ],
            [
                'category'         => 'policy',
                'title'            => 'NIN Verification Requirement',
                'content'          => "All domestic staff on Maids.ng must submit their National Identity Number (NIN) for verification.\n\nThe Gatekeeper Agent processes NIN verification automatically. High-confidence results are approved instantly. Borderline or suspicious cases are escalated to manual admin review.\n\nEmployers are matched only with verified maids. Unverified maids do not appear in match results.",
                'applies_to'       => ['all'],
                'visible_to_tiers' => ['all'],
                'priority'         => 15,
                'is_active'        => true,
            ],
            [
                'category'         => 'procedure',
                'title'            => 'Wallet, Escrow & Withdrawal Process',
                'content'          => "EMPLOYER WALLET:\n- Employer loads funds to their wallet via Paystack or Flutterwave.\n- When a salary payment is due, funds move from employer wallet to escrow.\n- Escrow holds the funds until the employer confirms the pay period (or the escrow release window expires).\n- Platform commission is deducted at release. Maid wallet is credited with net amount.\n\nMAID WITHDRAWAL:\n- Maids can request withdrawal to their registered bank account from their wallet.\n- Minimum withdrawal amount applies (see LIVE PRICING).\n- All withdrawals are reviewed by the Treasurer Agent and processed within 1–3 business days.",
                'applies_to'       => ['ambassador', 'concierge', 'treasurer'],
                'visible_to_tiers' => ['authenticated'],
                'priority'         => 25,
                'is_active'        => true,
            ],
            [
                'category'         => 'onboarding',
                'title'            => 'For Maids: Getting Your Profile Live',
                'content'          => "To appear in employer searches:\n1. Register as a maid at [domain]/register — select 'I am a domestic worker'.\n2. Complete your full profile: skills, experience, location, availability, rate.\n3. Upload your NIN and any relevant certificates.\n4. Wait for Gatekeeper Agent verification (usually within 24 hours).\n5. Once verified, your profile appears in match results.\n\nTips for a higher match rate:\n- Add a professional profile photo.\n- List all your skills specifically (e.g., 'Nigerian cuisine', 'infant care').\n- Set a realistic rate within the platform's standard range.",
                'applies_to'       => ['ambassador', 'concierge'],
                'visible_to_tiers' => ['all'],
                'priority'         => 30,
                'is_active'        => true,
            ],
        ];

        foreach ($articles as $article) {
            AgentKnowledgeBase::create($article);
        }
    }
}
```

**Register seeder** in `database/seeders/DatabaseSeeder.php`:

```php
$this->call([
    ProductionSeeder::class,
    AgentKnowledgeSeeder::class,
]);
```

**Run:** `php artisan db:seed --class=AgentKnowledgeSeeder`

---

## 10. Definition of Done

Each phase has its own acceptance criteria. A phase is NOT done until every criterion below is met.

---

### Phase 1 — Migrations ✓ Done When:

- [ ] `php artisan migrate` runs with zero errors
- [ ] `SHOW TABLES LIKE 'agent_%'` returns both `agent_prompt_templates` and `agent_knowledge_base`
- [ ] `DESCRIBE agent_prompt_templates` shows all 11 columns including `previous_prompt`
- [ ] `DESCRIBE agent_knowledge_base` shows all 13 columns including `visible_to_tiers`
- [ ] `php artisan migrate:rollback` runs without error (down methods work)

---

### Phase 2 — Models ✓ Done When:

- [ ] `php artisan tinker` — `AgentPromptTemplate::count()` returns without error
- [ ] `php artisan tinker` — `AgentKnowledgeBase::count()` returns without error
- [ ] `AgentPromptTemplate::active()->forAgent('ambassador')->forTier('guest')->first()` returns null (no data yet — not an error)
- [ ] `AgentKnowledgeBase::active()->forAgent('ambassador')->forTier('guest')->ordered()->get()` returns empty collection (not an exception)

---

### Phase 3 — KnowledgeService ✓ Done When:

- [ ] `KnowledgeService` is registered as singleton in `AppServiceProvider`
- [ ] Calling `app(KnowledgeService::class)->buildContext('ambassador', 'guest')` throws `RuntimeException` with a clear message (no template seeded yet — correct behaviour)
- [ ] After seeding one prompt template, the same call returns a non-empty string
- [ ] Calling `buildContext()` twice in the same request returns the cached version (add `Log::info()` inside `assemble()` — should only fire once)
- [ ] `flushCache()` runs without error
- [ ] Placeholder `{{MATCHING_FEE}}` in a template is replaced with the live value from settings in the returned string
- [ ] KB articles are appended after the system prompt in the returned string
- [ ] Pricing block is always present in the returned string, even if KB is empty

---

### Phase 4 — Settings Keys ✓ Done When:

- [ ] `php artisan tinker` — `setting('matching_fee')` returns `5000`
- [ ] `setting('commission_rate')` returns `15`
- [ ] `setting('guarantee_period_days')` returns `10`
- [ ] `setting('maid_monthly_rate_min')` returns `30000`
- [ ] Changing `matching_fee` in the database and calling `buildContext()` (with cache flushed) returns the updated value in the pricing block

---

### Phase 5 — Controllers ✓ Done When:

- [ ] `GET /admin/agent/prompts` returns HTTP 200 for admin user
- [ ] `GET /admin/agent/prompts` returns HTTP 403 (or redirect) for non-admin user
- [ ] `GET /admin/agent/knowledge` returns HTTP 200 for admin user
- [ ] `POST /admin/agent/prompts` with valid data creates a new template and redirects with success flash
- [ ] `PUT /admin/agent/prompts/{id}` saves new version, increments `version`, saves old to `previous_prompt`, and flushes cache
- [ ] `POST /admin/agent/prompts/{id}/rollback` swaps current ↔ previous and decrements version
- [ ] `DELETE /admin/agent/knowledge/{id}` sets `is_active = false` (does not hard delete)
- [ ] All routes return 405 for wrong HTTP methods

---

### Phase 6 — Admin UI ✓ Done When:

- [ ] `/admin/agent/prompts` page loads and shows grouped table of all templates
- [ ] Edit page loads a textarea pre-filled with the existing prompt
- [ ] Saving a prompt with fewer than 50 characters shows a validation error
- [ ] Saving a valid prompt reloads the index with a green success message
- [ ] "Rollback" button is hidden when `previous_prompt` is null
- [ ] "Show previous version" toggle works without page reload
- [ ] `/admin/agent/knowledge` shows paginated article list with category filter
- [ ] Create and Edit forms show correct checkboxes for `applies_to` and `visible_to_tiers`
- [ ] Priority field shows inline guidance about ranges (1-10 restrictions, etc.)
- [ ] Articles deactivated via Destroy still appear in the list with Inactive badge

---

### Phase 7 — Agent Retrofitting ✓ Done When:

- [ ] `ConciergeAgent` no longer contains any hardcoded prompt string
- [ ] `ScoutAgent` no longer contains any hardcoded prompt string
- [ ] All six existing agents call `$this->knowledge->buildContext(agentName, tier)` for their system prompt
- [ ] A test invocation of each agent (via `php artisan tinker` or unit test) returns a response without error
- [ ] Changing a prompt template in the admin UI and triggering the affected agent shows the updated persona in the response

---

### Phase 8 — Seeding ✓ Done When:

- [ ] `AgentPromptTemplate::count()` returns at least 4
- [ ] `AgentKnowledgeBase::count()` returns at least 7
- [ ] `buildContext('ambassador', 'guest')` returns a string containing:
  - The ambassador guest system prompt text
  - At least one KB article section under `## KNOWLEDGE BASE`
  - The pricing block under `## LIVE PRICING & FEES` with correct ₦ values
  - The current date under `## CURRENT CONTEXT`
- [ ] `buildContext('ambassador', 'authenticated')` returns a different, longer prompt than the guest version
- [ ] KB articles with `visible_to_tiers: ["authenticated"]` do NOT appear in the guest context
- [ ] The `restriction` category article appears FIRST in the KB block (priority 1)

---

### Full System ✓ Done When (Final Acceptance):

- [ ] Admin can log in, edit a KB article, and the next agent call reflects the change (within cache TTL or after manual flush)
- [ ] Admin can edit a prompt template and see version counter increment
- [ ] Admin can roll back a prompt and the previous text is restored
- [ ] Pricing values in `buildContext()` output match the values in the Settings table exactly
- [ ] No agent file contains any hardcoded pricing values
- [ ] No agent file contains any hardcoded system prompt strings
- [ ] `php artisan test` passes (no regressions introduced)
- [ ] `KnowledgeService` throws a clear, human-readable exception when called for an unconfigured agent/tier combination — it does not return empty string silently

---

## 11. Testing Checklist

Run these manually or add as feature tests in `tests/Feature/Agent/`.

```php
// tests/Feature/Agent/KnowledgeServiceTest.php

it('throws when no template exists', function () {
    expect(fn() => app(KnowledgeService::class)->buildContext('nonexistent', 'guest'))
        ->toThrow(\RuntimeException::class);
});

it('assembles context with all three sections', function () {
    // Seed one template + one KB article
    AgentPromptTemplate::factory()->create(['agent_name' => 'scout', 'tier' => 'guest', 'is_active' => true]);
    AgentKnowledgeBase::factory()->create(['applies_to' => ['all'], 'visible_to_tiers' => ['all'], 'is_active' => true]);

    $context = app(KnowledgeService::class)->buildContext('scout', 'guest');

    expect($context)
        ->toContain('KNOWLEDGE BASE')
        ->toContain('LIVE PRICING')
        ->toContain('CURRENT CONTEXT');
});

it('filters KB articles by tier', function () {
    AgentPromptTemplate::factory()->create(['agent_name' => 'ambassador', 'tier' => 'guest', 'is_active' => true]);
    AgentKnowledgeBase::factory()->create([
        'title'            => 'Secret Member Info',
        'applies_to'       => ['all'],
        'visible_to_tiers' => ['authenticated'],  // should NOT appear for guests
        'is_active'        => true,
    ]);

    $context = app(KnowledgeService::class)->buildContext('ambassador', 'guest');

    expect($context)->not->toContain('Secret Member Info');
});

it('replaces placeholders with live values', function () {
    AgentPromptTemplate::factory()->create([
        'agent_name'    => 'ambassador',
        'tier'          => 'guest',
        'system_prompt' => 'The fee is {{MATCHING_FEE}}.',
        'is_active'     => true,
    ]);
    setting(['matching_fee' => 7500]);

    app(KnowledgeService::class)->flushCache('ambassador', 'guest');
    $context = app(KnowledgeService::class)->buildContext('ambassador', 'guest');

    expect($context)->toContain('₦7,500');
});
```

---

## 12. Common Mistakes & Gotchas

**1. Cache not flushing after edits**
Both controllers call `$this->knowledge->flushCache()` after any save. If you add a new save path (e.g., a bulk update), ensure `flushCache()` is called there too. If in doubt during development, set `CACHE_TTL = 0` in `KnowledgeService`.

**2. Unique constraint on `agent_prompt_templates`**
The unique index is on `(agent_name, tier, is_active)`. MySQL allows multiple rows where `is_active = false` for the same agent+tier — only one `is_active = true` per combination is enforced. This is intentional for version history.

**3. `applies_to` JSON queries on MySQL 5.7 vs 8.0**
`whereJsonContains()` requires MySQL 8.0+ or MariaDB 10.2+. If your server is older, replace with a raw `LIKE` query as a temporary workaround: `->where('applies_to', 'like', '%"ambassador"%')`.

**4. `setting()` helper availability**
The `setting()` global helper comes from Spatie Laravel Settings. If it's not available in your version, use `\Spatie\LaravelSettings\Models\SettingsProperty::where('name', 'matching_fee')->value('payload')` and `json_decode()` it.

**5. Prompt template not found for a tier**
The service falls back to `guest` tier before throwing. This is intentional — if you haven't created an `authenticated` template for `scout` yet, it will use the `guest` one rather than crashing. Watch the logs for the fallback warning and create the missing template.

**6. KB article changes not reflected immediately**
Default cache TTL is 5 minutes. During development, set `CACHE_TTL = 0`. In production, call `$this->knowledge->flushCache()` after any KB change — both controllers already do this. If you bypass the controllers (e.g., DB edit directly), run `php artisan cache:clear` manually.

---

*End of Implementation Guide — v1.0*
*Next document: Ambassador Agent Build Guide*
