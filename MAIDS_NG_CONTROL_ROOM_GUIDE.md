# Maids.ng — Agent Control Room
## Complete Technical Implementation Guide

**Version:** 1.0  
**Codename:** Control Room  
**Prerequisite:** Phase 0 (Knowledge Base & Prompt Management) complete. Agents (Ambassador, Marketer, SEO Engine, Outreach Engine) may be at any stage — the Control Room is additive and reads from existing tables.  
**Stack:** Laravel 11, PHP 8.2+, MySQL, React 18, Inertia.js, Tailwind CSS, SSE (Server-Sent Events)  
**Principle:** Every action any AI agent can perform, a human must also be able to perform manually through this interface. AI agents and human operators are interchangeable at every step.

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Phase 1 — Database Foundation](#phase-1--database-foundation)
- [Phase 2 — Agent Logging Infrastructure](#phase-2--agent-logging-infrastructure)
- [Phase 3 — Human Override & Fallback System](#phase-3--human-override--fallback-system)
- [Phase 4 — SSE Real-Time Event Stream](#phase-4--sse-real-time-event-stream)
- [Phase 5 — Control Room Controllers](#phase-5--control-room-controllers)
- [Phase 6 — React Control Room UI (Five Panels)](#phase-6--react-control-room-ui-five-panels)
- [Phase 7 — Human Task Execution Interface](#phase-7--human-task-execution-interface)
- [Phase 8 — Agent Kill Switches & Override Controls](#phase-8--agent-kill-switches--override-controls)
- [Phase 9 — Routes & Registration](#phase-9--routes--registration)
- [Definition of Done](#definition-of-done)

---

## Architecture Overview

### The Core Principle: Parity

Every agent action has an exact human equivalent. The Control Room enforces this by design. When ScoutAgent is disabled, a human operator can open the matching interface and run the exact same scoring logic manually. When AmbassadorAgent is down, a human can send messages from the conversation view. When a campaign is paused, a human can manually trigger individual outreach to any identity.

This parity is enforced through a single abstraction called the **ActionDispatcher**. Both agent jobs AND human controller actions route through `ActionDispatcher`. The dispatcher checks: is this agent enabled? If yes, queue the agent job. If no, or if a human explicitly triggered it, execute the same underlying service method directly from the controller. The services themselves are unchanged.

### Data Flow

```
Agent Job fires (queue/cron/event)
    ↓
ActionDispatcher::check('agent_name') → is agent enabled?
    │                                           │
    YES → execute service → log to agent_events │
    NO  → create human_task_queue entry         │
              ↓                                 │
        Admin sees it in Panel 5 (HITL Queue)   │
        Human clicks Execute                    │
        Same service method runs                │
              ↓                                 ↓
        Both paths → agent_events row written
              ↓
        SSE stream pushes to Panel 1 (Live Feed)
              ↓
        Panel 2 (Queue Health) counters update
        Panel 3 (Campaigns) stats update
        Panel 4 (Token Cost) increments
```

### Agents Covered

| Agent | Type | Trigger Mode | Human Fallback |
|---|---|---|---|
| `ScoutAgent` | Matching | Queue job | Manual match runner in Panel 2 |
| `SentinelAgent` | Quality monitoring | Cron | Manual quality review |
| `RefereeAgent` | Dispute resolution | Event | Manual dispute form |
| `ConciergeAgent` | Support | Inbound message | Manual reply in conversation view |
| `TreasurerAgent` | Financial ops | Event/Cron | Manual payout approval |
| `GatekeeperAgent` | NIN verification | Event | Manual verification review |
| `AmbassadorAgent` | SDR/Support | Inbound message | Manual message compose |
| `MarketerAgent` | Social content | Scheduled | Manual post generator |
| `SeoContentAgent` | SEO content | Scheduled | Manual content regenerator |
| `OutreachEngine` | Campaigns | Scheduled/Event | Manual campaign trigger |

---

## Phase 1 — Database Foundation

### 1.1 — `agent_events` Table

This is the central nervous system. Every meaningful agent action writes here. The Control Room reads from here.

**File:** `database/migrations/2026_05_02_000001_create_agent_events_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_events', function (Blueprint $table) {
            $table->id();

            // Which agent fired this event
            // Must match agent name strings used throughout the codebase:
            // 'scout', 'sentinel', 'referee', 'concierge', 'treasurer',
            // 'gatekeeper', 'ambassador', 'marketer', 'seo_content', 'outreach'
            $table->string('agent_name', 50);

            // Structured event type — used for filtering and icons in the feed
            // Format: {noun}.{verb} e.g. 'match.scored', 'message.sent', 'post.generated'
            $table->string('event_type', 100);

            // Visual severity level
            // 'info'    = routine operation
            // 'success' = positive outcome (payment confirmed, match accepted)
            // 'warning' = something to watch (low match score, retry)
            // 'error'   = something failed (API error, tool error)
            // 'pending' = waiting for human approval
            $table->enum('severity', ['info', 'success', 'warning', 'error', 'pending'])
                  ->default('info');

            // One-line human-readable summary shown in the live feed
            // Example: "Matched Employer #89 with Maid #342 — 78% compatibility"
            $table->string('summary', 500);

            // Full structured context for the expandable detail view
            // Contains: inputs, outputs, scores, reasoning, tool calls, token counts
            $table->json('detail')->nullable();

            // Whether this event was triggered by a human operator vs an agent
            $table->boolean('triggered_by_human')->default(false);

            // FK to the admin user if triggered_by_human = true
            $table->foreignId('triggered_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // FK to the end user this event relates to (employer, maid, or lead)
            $table->foreignId('related_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Polymorphic link to the primary model this event concerns
            // e.g. AgentConversation, SocialPost, AgentOutreachLog, Assignment
            $table->string('related_model', 100)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            // For Human-In-The-Loop: this event is blocked pending human approval
            // When true, it appears in the HITL panel (Panel 5) and execution is paused
            $table->boolean('requires_approval')->default(false);

            // Approval state
            // null     = not yet reviewed (only relevant when requires_approval = true)
            // true     = approved by human
            // false    = rejected by human
            $table->boolean('approved')->nullable();

            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->text('approval_note')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Token usage for cost tracking (populated for LLM-calling agents)
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();

            // Estimated cost in USD (calculated from token counts + model pricing)
            $table->decimal('estimated_cost_usd', 8, 6)->nullable();

            // LLM model used for this call
            $table->string('llm_model', 100)->nullable();

            // Duration of the agent's work in milliseconds
            $table->unsignedInteger('duration_ms')->nullable();

            // Channel context (for Ambassador)
            $table->string('channel', 50)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes for the five panels
            $table->index(['agent_name', 'created_at']);           // Panel 1: per-agent feed
            $table->index(['created_at']);                          // Panel 1: global feed
            $table->index(['agent_name', 'event_type']);            // Panel 2: queue health
            $table->index(['requires_approval', 'approved']);       // Panel 5: HITL queue
            $table->index(['related_model', 'related_id']);         // Deep linking
            $table->index(['created_at', 'total_tokens']);          // Panel 4: cost tracking
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_events');
    }
};
```

---

### 1.2 — `human_task_queue` Table

When an agent is disabled or a human explicitly takes over a task, work items land here instead of (or in addition to) the agent queue.

**File:** `database/migrations/2026_05_02_000002_create_human_task_queue_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_task_queue', function (Blueprint $table) {
            $table->id();

            // The agent that would normally handle this
            $table->string('agent_name', 50);

            // Structured task type — maps to a human action form in the UI
            // 'match_employer'        → ScoutAgent replacement
            // 'send_message'          → AmbassadorAgent/ConciergeAgent replacement
            // 'verify_nin'            → GatekeeperAgent replacement
            // 'resolve_dispute'       → RefereeAgent replacement
            // 'process_payout'        → TreasurerAgent replacement
            // 'review_maid_quality'   → SentinelAgent replacement
            // 'generate_content'      → MarketerAgent replacement
            // 'generate_seo_content'  → SeoContentAgent replacement
            // 'send_outreach'         → OutreachEngine replacement
            // 'approve_hitl'          → Generic approval task
            $table->string('task_type', 100);

            // Why this task is in the human queue
            // 'agent_disabled'    = the agent was manually disabled
            // 'agent_error'       = agent failed with an unrecoverable error
            // 'hitl_required'     = agent determined human approval is needed
            // 'manual_override'   = admin explicitly assigned this to a human
            // 'ai_downtime'       = AI provider is unreachable
            $table->enum('reason', [
                'agent_disabled',
                'agent_error',
                'hitl_required',
                'manual_override',
                'ai_downtime',
            ]);

            // Full context payload needed to execute this task
            // Contains all data that would have been passed to the agent job
            $table->json('task_payload');

            // Human-readable description of what needs to be done
            $table->string('description', 500);

            // Priority for ordering in the queue
            // 1 = urgent (payment issue, escalated conversation)
            // 2 = high (pending match, awaiting verification)
            // 3 = normal
            // 4 = low (content review, SEO page generation)
            $table->unsignedTinyInteger('priority')->default(3);

            // Workflow status
            // 'pending'    = waiting for human to pick up
            // 'assigned'   = assigned to a specific human operator
            // 'in_progress' = human has started working on it
            // 'completed'  = human has completed the task
            // 'skipped'    = human decided to skip (agent came back online, etc.)
            // 'delegated'  = reassigned back to agent when it recovered
            $table->enum('status', [
                'pending', 'assigned', 'in_progress',
                'completed', 'skipped', 'delegated'
            ])->default('pending');

            // Assigned to a specific admin user
            $table->foreignId('assigned_to')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // The admin who completed this task
            $table->foreignId('completed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Notes added by the human operator when completing
            $table->text('completion_notes')->nullable();

            // Link to the original job that failed (for error/downtime reasons)
            $table->string('original_job_class', 255)->nullable();
            $table->json('original_job_payload')->nullable();

            // FK to the agent_event that triggered this (if applicable)
            $table->foreignId('triggered_by_event_id')
                  ->nullable()
                  ->constrained('agent_events')
                  ->nullOnDelete();

            // FK to the end user this task relates to
            $table->foreignId('related_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // SLA deadline — if not completed by this time, escalate
            $table->timestamp('due_by')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'created_at']); // Panel 5 sort order
            $table->index(['agent_name', 'status']);              // Per-agent filter
            $table->index(['assigned_to', 'status']);             // Per-operator view
            $table->index('due_by');                              // SLA breach detection
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_task_queue');
    }
};
```

---

### 1.3 — `agent_overrides` Table

Stores the current operational state of each agent — enabled/disabled, mode, and any override instructions.

**File:** `database/migrations/2026_05_02_000003_create_agent_overrides_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_overrides', function (Blueprint $table) {
            $table->id();

            // The agent this override applies to
            $table->string('agent_name', 50)->unique();

            // Operational mode
            // 'active'   = agent runs fully autonomously
            // 'supervised' = agent runs but all actions require HITL approval before executing
            // 'paused'   = agent is disabled; tasks route to human_task_queue
            // 'readonly' = agent can read/observe but not take actions
            $table->enum('mode', ['active', 'supervised', 'paused', 'readonly'])
                  ->default('active');

            // When mode is 'supervised', which action types require approval
            // null = all actions require approval
            // JSON array of event_types that need approval e.g. ["match.scored","message.sent"]
            $table->json('supervised_action_types')->nullable();

            // When mode is 'paused', should tasks automatically route to human queue?
            $table->boolean('auto_route_to_human')->default(true);

            // Global kill switch — overrides everything, immediately stops the agent
            // Use only in emergencies. Separate from 'paused' mode.
            $table->boolean('kill_switch')->default(false);

            // Human-readable reason for the current override state
            $table->string('override_reason', 500)->nullable();

            // Who set this override
            $table->foreignId('set_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // When a 'paused' agent should automatically re-activate (null = manual only)
            $table->timestamp('auto_resume_at')->nullable();

            // Per-agent rate limiting — max LLM calls per hour
            // null = no limit (uses global default)
            $table->unsignedInteger('max_calls_per_hour')->nullable();

            // Per-agent spending cap — max USD per day
            // null = no cap
            $table->decimal('daily_spend_cap_usd', 8, 2)->nullable();

            // Current daily spend tracking (reset daily by scheduler)
            $table->decimal('current_daily_spend_usd', 8, 4)->default(0);

            $table->timestamp('spend_reset_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_overrides');
    }
};
```

---

### 1.4 — Seed Default Agent Override Rows

**File:** `database/seeders/AgentOverrideSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\AgentOverride;
use Illuminate\Database\Seeder;

class AgentOverrideSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            'scout', 'sentinel', 'referee', 'concierge',
            'treasurer', 'gatekeeper', 'ambassador',
            'marketer', 'seo_content', 'outreach',
        ];

        foreach ($agents as $agent) {
            AgentOverride::firstOrCreate(
                ['agent_name' => $agent],
                [
                    'mode'                 => 'active',
                    'auto_route_to_human'  => true,
                    'kill_switch'          => false,
                    'daily_spend_cap_usd'  => 10.00,
                    'max_calls_per_hour'   => 200,
                ]
            );
        }

        $this->command->info('Agent override defaults seeded for ' . count($agents) . ' agents.');
    }
}
```

Run all migrations and seeder:

```bash
php artisan migrate
php artisan db:seed --class=AgentOverrideSeeder
```

---

### 1.5 — Eloquent Models

**File:** `app/Models/AgentEvent.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AgentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_name', 'event_type', 'severity', 'summary', 'detail',
        'triggered_by_human', 'triggered_by_user_id', 'related_user_id',
        'related_model', 'related_id', 'requires_approval', 'approved',
        'approved_by', 'approval_note', 'approved_at',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'estimated_cost_usd', 'llm_model', 'duration_ms', 'channel',
    ];

    protected $casts = [
        'detail'              => 'array',
        'triggered_by_human'  => 'boolean',
        'requires_approval'   => 'boolean',
        'approved'            => 'boolean',
        'approved_at'         => 'datetime',
        'created_at'          => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('requires_approval', true)->whereNull('approved');
    }

    public function scopeForAgent(Builder $query, string $agent): Builder
    {
        return $query->where('agent_name', $agent);
    }

    public function scopeRecent(Builder $query, int $minutes = 60): Builder
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    /** Cost in USD formatted as string */
    public function getCostStringAttribute(): string
    {
        if (!$this->estimated_cost_usd) {
            return '—';
        }
        return '$' . number_format($this->estimated_cost_usd, 4);
    }

    /** Severity to Tailwind colour class mapping */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'success' => 'green',
            'warning' => 'yellow',
            'error'   => 'red',
            'pending' => 'purple',
            default   => 'blue',
        };
    }
}
```

**File:** `app/Models/AgentOverride.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AgentOverride extends Model
{
    protected $fillable = [
        'agent_name', 'mode', 'supervised_action_types', 'auto_route_to_human',
        'kill_switch', 'override_reason', 'set_by', 'auto_resume_at',
        'max_calls_per_hour', 'daily_spend_cap_usd',
        'current_daily_spend_usd', 'spend_reset_at',
    ];

    protected $casts = [
        'supervised_action_types' => 'array',
        'auto_route_to_human'     => 'boolean',
        'kill_switch'             => 'boolean',
        'auto_resume_at'          => 'datetime',
        'spend_reset_at'          => 'datetime',
    ];

    /** Cached lookup — called on every agent invocation, must be fast */
    public static function forAgent(string $agentName): self
    {
        return Cache::remember("agent_override_{$agentName}", 60, function () use ($agentName) {
            return static::where('agent_name', $agentName)->firstOrFail();
        });
    }

    public function isActive(): bool
    {
        return !$this->kill_switch && $this->mode === 'active';
    }

    public function isPaused(): bool
    {
        return $this->kill_switch || $this->mode === 'paused';
    }

    public function isSupervised(): bool
    {
        return $this->mode === 'supervised';
    }

    public function requiresApprovalFor(string $eventType): bool
    {
        if (!$this->isSupervised()) {
            return false;
        }
        // null supervised_action_types = ALL actions require approval
        if ($this->supervised_action_types === null) {
            return true;
        }
        return in_array($eventType, $this->supervised_action_types);
    }

    /** Check if daily spend cap would be breached */
    public function wouldBreachSpendCap(float $estimatedCostUsd): bool
    {
        if (!$this->daily_spend_cap_usd) {
            return false;
        }
        return ($this->current_daily_spend_usd + $estimatedCostUsd) > $this->daily_spend_cap_usd;
    }

    public function clearCache(): void
    {
        Cache::forget("agent_override_{$this->agent_name}");
    }
}
```

**File:** `app/Models/HumanTask.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class HumanTask extends Model
{
    protected $table = 'human_task_queue';

    protected $fillable = [
        'agent_name', 'task_type', 'reason', 'task_payload', 'description',
        'priority', 'status', 'assigned_to', 'completed_by', 'completion_notes',
        'original_job_class', 'original_job_payload', 'triggered_by_event_id',
        'related_user_id', 'due_by', 'assigned_at', 'completed_at',
    ];

    protected $casts = [
        'task_payload'         => 'array',
        'original_job_payload' => 'array',
        'due_by'               => 'datetime',
        'assigned_at'          => 'datetime',
        'completed_at'         => 'datetime',
    ];

    public function assignedOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedByOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function triggerEvent(): BelongsTo
    {
        return $this->belongsTo(AgentEvent::class, 'triggered_by_event_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_by')
                     ->where('due_by', '<', now())
                     ->whereNotIn('status', ['completed', 'skipped', 'delegated']);
    }

    public function isOverdue(): bool
    {
        return $this->due_by && $this->due_by->isPast()
            && !in_array($this->status, ['completed', 'skipped', 'delegated']);
    }
}
```

---

## Phase 2 — Agent Logging Infrastructure

### 2.1 — AgentEventLogger Service

A single service all agents call to write to `agent_events`. Handles token cost calculation, HITL routing, and cache invalidation.

**File:** `app/Services/AgentEventLogger.php`

```php
<?php

namespace App\Services;

use App\Models\{AgentEvent, AgentOverride, HumanTask};
use Illuminate\Support\Facades\Log;

class AgentEventLogger
{
    // OpenAI pricing per 1M tokens as of 2025 (update as models change)
    private const TOKEN_COSTS = [
        'gpt-4o'              => ['input' => 5.00,  'output' => 15.00],
        'gpt-4o-mini'         => ['input' => 0.15,  'output' => 0.60],
        'gpt-4-turbo'         => ['input' => 10.00, 'output' => 30.00],
        'claude-opus-4-6'     => ['input' => 15.00, 'output' => 75.00],
        'claude-sonnet-4-6'   => ['input' => 3.00,  'output' => 15.00],
        'claude-haiku-4-5'    => ['input' => 0.25,  'output' => 1.25],
        'gpt-image-1'         => ['flat' => 0.04],  // per image
    ];

    /**
     * Log an agent event. Returns the created AgentEvent.
     * This is the primary method every agent calls.
     *
     * @param string $agentName       The agent identifier ('scout', 'ambassador', etc.)
     * @param string $eventType       Dot-notation type ('match.scored', 'message.sent')
     * @param string $severity        'info'|'success'|'warning'|'error'|'pending'
     * @param string $summary         One-line description for the live feed
     * @param array  $detail          Full structured context (scores, inputs, outputs, reasoning)
     * @param array  $options         Optional: related_user_id, related_model, related_id,
     *                                tokens (array), model, duration_ms, channel,
     *                                triggered_by_human, triggered_by_user_id
     */
    public function log(
        string $agentName,
        string $eventType,
        string $severity,
        string $summary,
        array  $detail = [],
        array  $options = []
    ): AgentEvent {
        $tokens = $options['tokens'] ?? null;
        $model  = $options['model'] ?? config('ambassador.model', 'gpt-4o');

        $cost = $tokens ? $this->calculateCost($tokens, $model) : null;

        $event = AgentEvent::create([
            'agent_name'            => $agentName,
            'event_type'            => $eventType,
            'severity'              => $severity,
            'summary'               => $summary,
            'detail'                => $detail,
            'triggered_by_human'    => $options['triggered_by_human'] ?? false,
            'triggered_by_user_id'  => $options['triggered_by_user_id'] ?? null,
            'related_user_id'       => $options['related_user_id'] ?? null,
            'related_model'         => $options['related_model'] ?? null,
            'related_id'            => $options['related_id'] ?? null,
            'requires_approval'     => $options['requires_approval'] ?? false,
            'prompt_tokens'         => $tokens['prompt'] ?? null,
            'completion_tokens'     => $tokens['completion'] ?? null,
            'total_tokens'          => $tokens ? (($tokens['prompt'] ?? 0) + ($tokens['completion'] ?? 0)) : null,
            'estimated_cost_usd'    => $cost,
            'llm_model'             => $model,
            'duration_ms'           => $options['duration_ms'] ?? null,
            'channel'               => $options['channel'] ?? null,
        ]);

        // Track daily spend on the override record
        if ($cost) {
            AgentOverride::where('agent_name', $agentName)
                ->increment('current_daily_spend_usd', $cost);
        }

        return $event;
    }

    /**
     * Log an event that requires human approval before execution continues.
     * Creates both an agent_events row (severity=pending) and a human_task_queue row.
     */
    public function logForApproval(
        string $agentName,
        string $eventType,
        string $summary,
        array  $detail,
        string $taskType,
        array  $taskPayload,
        array  $options = []
    ): array {
        $event = $this->log(
            $agentName, $eventType, 'pending', $summary, $detail,
            array_merge($options, ['requires_approval' => true])
        );

        $task = HumanTask::create([
            'agent_name'             => $agentName,
            'task_type'              => $taskType,
            'reason'                 => 'hitl_required',
            'task_payload'           => $taskPayload,
            'description'            => $summary,
            'priority'               => $options['priority'] ?? 2,
            'related_user_id'        => $options['related_user_id'] ?? null,
            'triggered_by_event_id'  => $event->id,
            'due_by'                 => $options['due_by'] ?? now()->addHours(4),
        ]);

        return compact('event', 'task');
    }

    /**
     * Log an agent error with exception context.
     */
    public function logError(
        string $agentName,
        string $eventType,
        string $summary,
        \Throwable $exception,
        array $options = []
    ): AgentEvent {
        return $this->log(
            $agentName,
            $eventType,
            'error',
            $summary,
            [
                'exception'   => get_class($exception),
                'message'     => $exception->getMessage(),
                'file'        => $exception->getFile(),
                'line'        => $exception->getLine(),
                'trace'       => collect(explode("\n", $exception->getTraceAsString()))
                                    ->take(10)
                                    ->toArray(),
            ],
            $options
        );
    }

    private function calculateCost(array $tokens, string $model): float
    {
        $pricing = self::TOKEN_COSTS[$model] ?? self::TOKEN_COSTS['gpt-4o'];

        if (isset($pricing['flat'])) {
            return $pricing['flat'];
        }

        $inputCost  = (($tokens['prompt'] ?? 0) / 1_000_000) * $pricing['input'];
        $outputCost = (($tokens['completion'] ?? 0) / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }
}
```

---

### 2.2 — ActionDispatcher — The Parity Enforcer

Every agent action, whether triggered by a job or a human, goes through this. It checks the override state and routes accordingly.

**File:** `app/Services/ActionDispatcher.php`

```php
<?php

namespace App\Services;

use App\Models\{AgentOverride, HumanTask};
use Illuminate\Support\Facades\Log;

class ActionDispatcher
{
    public function __construct(
        private AgentEventLogger $logger
    ) {}

    /**
     * Check whether an agent is permitted to execute a given action.
     * Returns: 'execute' | 'skip' | 'hitl' | 'killed'
     *
     * Usage in every agent job:
     *   $permission = $dispatcher->check('scout', 'match.score', estimatedCost: 0.002);
     *   if ($permission !== 'execute') return;
     */
    public function check(
        string $agentName,
        string $eventType,
        float  $estimatedCostUsd = 0.0
    ): string {
        try {
            $override = AgentOverride::forAgent($agentName);
        } catch (\Exception $e) {
            // Override record missing — default to allow
            Log::warning("ActionDispatcher: No override record for {$agentName}");
            return 'execute';
        }

        // Kill switch — hard stop
        if ($override->kill_switch) {
            return 'killed';
        }

        // Spend cap check
        if ($estimatedCostUsd > 0 && $override->wouldBreachSpendCap($estimatedCostUsd)) {
            $this->logger->log(
                $agentName, 'budget.cap_reached', 'warning',
                "Daily spend cap reached for {$agentName}. Task held.",
                ['estimated_cost' => $estimatedCostUsd, 'daily_cap' => $override->daily_spend_cap_usd]
            );
            return 'skip';
        }

        // Paused mode — route to human queue if auto_route_to_human
        if ($override->isPaused()) {
            return $override->auto_route_to_human ? 'hitl' : 'skip';
        }

        // Supervised mode — check if this specific action type needs approval
        if ($override->isSupervised() && $override->requiresApprovalFor($eventType)) {
            return 'hitl';
        }

        // Auto-resume check
        if ($override->mode === 'paused' && $override->auto_resume_at?->isPast()) {
            $override->update(['mode' => 'active', 'auto_resume_at' => null]);
            $override->clearCache();
        }

        return 'execute';
    }

    /**
     * Create a human task when an agent action is blocked.
     * Call this after check() returns 'hitl'.
     */
    public function routeToHuman(
        string $agentName,
        string $taskType,
        string $description,
        array  $payload,
        array  $options = []
    ): HumanTask {
        return HumanTask::create([
            'agent_name'      => $agentName,
            'task_type'       => $taskType,
            'reason'          => $options['reason'] ?? 'agent_disabled',
            'task_payload'    => $payload,
            'description'     => $description,
            'priority'        => $options['priority'] ?? 3,
            'related_user_id' => $options['related_user_id'] ?? null,
            'due_by'          => $options['due_by'] ?? now()->addHours(8),
        ]);
    }
}
```

---

### 2.3 — Agent Logging Trait

Add to every agent class — a single trait that provides `logEvent()`, `logError()`, and `checkPermission()` methods.

**File:** `app/Agents/Concerns/LogsEvents.php`

```php
<?php

namespace App\Agents\Concerns;

use App\Services\{ActionDispatcher, AgentEventLogger};

trait LogsEvents
{
    protected function getLogger(): AgentEventLogger
    {
        return app(AgentEventLogger::class);
    }

    protected function getDispatcher(): ActionDispatcher
    {
        return app(ActionDispatcher::class);
    }

    /**
     * Log a successful or informational agent event.
     */
    protected function logEvent(
        string $eventType,
        string $severity,
        string $summary,
        array  $detail = [],
        array  $options = []
    ): \App\Models\AgentEvent {
        return $this->getLogger()->log(
            $this->agentName,
            $eventType,
            $severity,
            $summary,
            $detail,
            $options
        );
    }

    /**
     * Check if this agent is permitted to proceed with an action.
     * Returns true if execution should continue, false if it should stop.
     * Handles routing to human queue automatically.
     */
    protected function canProceed(
        string $eventType,
        string $taskType,
        string $taskDescription,
        array  $taskPayload,
        array  $options = []
    ): bool {
        $permission = $this->getDispatcher()->check($this->agentName, $eventType);

        if ($permission === 'execute') {
            return true;
        }

        if ($permission === 'hitl') {
            $this->getDispatcher()->routeToHuman(
                $this->agentName,
                $taskType,
                $taskDescription,
                $taskPayload,
                array_merge($options, ['reason' => 'hitl_required'])
            );
        }

        if ($permission === 'killed') {
            $this->logEvent($eventType, 'error', "Kill switch active — {$this->agentName} is halted.", []);
        }

        return false;
    }
}
```

---

### 2.4 — Integrating Logging Into Each Agent

Apply the trait and add `logEvent()` calls to every agent. Examples for each:

#### ScoutAgent

```php
class ScoutAgent extends AgentService
{
    use LogsEvents;

    protected string $agentName = 'scout';

    public function findMatches(EmployerPreference $preference): array
    {
        // Check permission before proceeding
        if (!$this->canProceed(
            'match.score',
            'match_employer',
            "Find matches for Employer #{$preference->employer_id}",
            ['preference_id' => $preference->id, 'employer_id' => $preference->employer_id]
        )) {
            return [];
        }

        $startTime = now();

        // ... existing matching logic ...

        $results = $this->runScoringAlgorithm($preference, $candidates);

        $this->logEvent(
            'match.scored',
            'success',
            "Matched Employer #{$preference->employer_id} — top score: {$results[0]['score']}% ({$results[0]['name']})",
            [
                'employer_id'   => $preference->employer_id,
                'preference_id' => $preference->id,
                'help_type'     => $preference->help_type,
                'location'      => $preference->location,
                'budget'        => $preference->budget,
                'top_matches'   => array_slice($results, 0, 3),
                'total_candidates_evaluated' => count($candidates),
            ],
            [
                'related_user_id' => $preference->employer_id,
                'related_model'   => 'EmployerPreference',
                'related_id'      => $preference->id,
                'duration_ms'     => now()->diffInMilliseconds($startTime),
            ]
        );

        return $results;
    }
}
```

#### AmbassadorAgent

```php
// In AmbassadorAgent::reply() — after saving assistant message:

$this->logEvent(
    'message.sent',
    'info',
    "Replied on {$inbound->channel} to " . ($identity->display_name ?? $identity->external_id),
    [
        'channel'         => $inbound->channel,
        'user_message'    => substr($inbound->content, 0, 200),
        'reply_preview'   => substr($response['content'], 0, 200),
        'tools_called'    => $response['tool_calls'] ?? [],
        'conversation_id' => $conversation->id,
        'identity_id'     => $identity->id,
        'tier'            => $tier,
    ],
    [
        'related_user_id' => $identity->user_id,
        'related_model'   => 'AgentConversation',
        'related_id'      => $conversation->id,
        'tokens'          => $response['usage'] ?? null,
        'model'           => config('ambassador.model'),
        'channel'         => $inbound->channel,
    ]
);
```

#### TreasurerAgent

```php
// Before processing a payout > threshold:

if ($amount > 500000) {
    $this->canProceed(
        'payout.large',
        'process_payout',
        "Large payout ₦" . number_format($amount) . " for Maid #{$maidId} — requires approval",
        ['maid_id' => $maidId, 'amount' => $amount, 'assignment_id' => $assignmentId],
        ['priority' => 1, 'due_by' => now()->addHours(2)]
    );
    // canProceed returns false → routes to HITL queue → stops execution
}
```

#### GatekeeperAgent

```php
// After NIN verification result:

$severity = match(true) {
    $score >= 85  => 'success',
    $score >= 60  => 'warning',
    default       => 'error',
};

$action = match(true) {
    $score >= 85  => 'auto-approved',
    $score >= 60  => 'manual-review',
    default       => 'rejected',
};

$this->logEvent(
    'nin.verified',
    $severity,
    "NIN verification for Maid #{$maidId} — {$action} (score: {$score}%)",
    [
        'maid_id'     => $maidId,
        'score'       => $score,
        'action'      => $action,
        'api_ref'     => $externalReference,
        'confidence'  => $score,
    ],
    [
        'related_user_id' => $maidId,
        'related_model'   => 'User',
        'related_id'      => $maidId,
    ]
);
```

#### MarketerAgent (via GenerateSocialContent job)

```php
// After post is generated and images are ready:

$this->logger->log(
    'marketer',
    'post.generated',
    'info',
    "Generated {$post->format} post — {$post->theme->name} ({$post->funnel_stage})",
    [
        'post_id'     => $post->id,
        'theme'       => $post->theme->name,
        'funnel'      => $post->funnel_stage,
        'format'      => $post->format,
        'hook'        => $post->hook,
        'platforms'   => $accounts->pluck('platform')->toArray(),
        'slide_count' => $post->media->count(),
    ],
    [
        'related_model' => 'SocialPost',
        'related_id'    => $post->id,
        'tokens'        => $result['usage'] ?? null,
    ]
);
```

#### OutreachEngine (via DispatchOutreach job)

```php
// After outreach message is sent:

$this->logger->log(
    'outreach',
    'outreach.sent',
    'success',
    "Sent '{$campaign->name}' campaign to " . ($identity->display_name ?? 'Lead #' . $identity->id),
    [
        'campaign_slug' => $campaign->slug,
        'channel'       => $channel,
        'identity_id'   => $identity->id,
        'message_preview' => substr($message, 0, 200),
    ],
    [
        'related_user_id' => $identity->user_id,
        'related_model'   => 'AgentOutreachLog',
        'related_id'      => $outreachLog->id,
        'channel'         => $channel,
    ]
);
```

#### SeoContentAgent (via GenerateSeoContent job)

```php
// After page content is generated:

$this->logger->log(
    'seo_content',
    'content.generated',
    $page->page_status === 'published' ? 'success' : 'warning',
    "Generated content for {$page->url_path} — score: {$page->content_score}/100",
    [
        'page_id'      => $page->id,
        'url'          => $page->url_path,
        'page_type'    => $page->page_type,
        'score'        => $page->content_score,
        'status'       => $page->page_status,
        'faq_count'    => count($page->content_blocks['faqs'] ?? []),
    ],
    [
        'related_model' => 'SeoPage',
        'related_id'    => $page->id,
        'tokens'        => $result['usage'] ?? null,
    ]
);
```

---

### 2.5 — Daily Spend Reset Scheduler

```php
// In routes/console.php:
Schedule::call(function () {
    \App\Models\AgentOverride::query()->update([
        'current_daily_spend_usd' => 0,
        'spend_reset_at'          => now(),
    ]);
    \Illuminate\Support\Facades\Cache::flush(); // clear override cache
})->dailyAt('00:00')->name('reset-agent-daily-spend');
```

---

## Phase 3 — Human Override & Fallback System

### 3.1 — AgentOverrideService

The service human operators call from the Control Room UI to change agent states.

**File:** `app/Services/AgentOverrideService.php`

```php
<?php

namespace App\Services;

use App\Models\{AgentOverride, AgentEvent, HumanTask};
use App\Models\User;

class AgentOverrideService
{
    public function __construct(
        private AgentEventLogger $logger
    ) {}

    /**
     * Pause an agent and optionally route pending work to humans.
     */
    public function pause(
        string $agentName,
        User   $operator,
        string $reason,
        ?int   $autoResumeMinutes = null
    ): AgentOverride {
        $override = AgentOverride::where('agent_name', $agentName)->firstOrFail();

        $override->update([
            'mode'            => 'paused',
            'override_reason' => $reason,
            'set_by'          => $operator->id,
            'auto_resume_at'  => $autoResumeMinutes ? now()->addMinutes($autoResumeMinutes) : null,
        ]);

        $override->clearCache();

        $this->logger->log(
            $agentName, 'agent.paused', 'warning',
            "Agent paused by {$operator->name}: {$reason}",
            ['reason' => $reason, 'auto_resume_minutes' => $autoResumeMinutes],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );

        return $override;
    }

    /**
     * Resume a paused agent.
     */
    public function resume(string $agentName, User $operator): AgentOverride
    {
        $override = AgentOverride::where('agent_name', $agentName)->firstOrFail();

        $override->update([
            'mode'            => 'active',
            'override_reason' => null,
            'auto_resume_at'  => null,
        ]);

        $override->clearCache();

        $this->logger->log(
            $agentName, 'agent.resumed', 'success',
            "Agent resumed by {$operator->name}",
            [],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );

        return $override;
    }

    /**
     * Put an agent in supervised mode where specified actions require HITL approval.
     */
    public function supervise(
        string  $agentName,
        User    $operator,
        string  $reason,
        ?array  $actionTypes = null // null = all actions
    ): AgentOverride {
        $override = AgentOverride::where('agent_name', $agentName)->firstOrFail();

        $override->update([
            'mode'                     => 'supervised',
            'supervised_action_types'  => $actionTypes,
            'override_reason'          => $reason,
            'set_by'                   => $operator->id,
        ]);

        $override->clearCache();

        $this->logger->log(
            $agentName, 'agent.supervised', 'warning',
            "Agent put under supervision by {$operator->name}: {$reason}",
            ['reason' => $reason, 'supervised_actions' => $actionTypes ?? 'all'],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );

        return $override;
    }

    /**
     * Activate the kill switch. This is an emergency stop.
     */
    public function killSwitch(string $agentName, User $operator, string $reason): AgentOverride
    {
        $override = AgentOverride::where('agent_name', $agentName)->firstOrFail();

        $override->update([
            'kill_switch'     => true,
            'override_reason' => "[KILL SWITCH] {$reason}",
            'set_by'          => $operator->id,
        ]);

        $override->clearCache();

        $this->logger->log(
            $agentName, 'agent.kill_switch', 'error',
            "KILL SWITCH activated by {$operator->name}: {$reason}",
            ['reason' => $reason],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );

        return $override;
    }

    /**
     * Deactivate the kill switch.
     */
    public function releaseKillSwitch(string $agentName, User $operator): AgentOverride
    {
        $override = AgentOverride::where('agent_name', $agentName)->firstOrFail();

        $override->update([
            'kill_switch'     => false,
            'mode'            => 'active',
            'override_reason' => null,
        ]);

        $override->clearCache();

        $this->logger->log(
            $agentName, 'agent.kill_switch_released', 'success',
            "Kill switch released by {$operator->name} — agent is active",
            [],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );

        return $override;
    }

    /**
     * Update the daily spend cap for an agent.
     */
    public function updateSpendCap(string $agentName, User $operator, float $capUsd): void
    {
        AgentOverride::where('agent_name', $agentName)->update([
            'daily_spend_cap_usd' => $capUsd,
        ]);

        AgentOverride::forAgent($agentName)->clearCache(); // bust cache

        $this->logger->log(
            $agentName, 'agent.spend_cap_updated', 'info',
            "Daily spend cap updated to \${$capUsd} by {$operator->name}",
            ['new_cap' => $capUsd],
            ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
        );
    }
}
```

---

### 3.2 — HumanExecutionService

When a human operator executes a task from the HITL queue, this service runs the same underlying service methods that the agent would have run.

**File:** `app/Services/HumanExecutionService.php`

```php
<?php

namespace App\Services;

use App\Models\{HumanTask, User};
use App\Agents\{ScoutAgent, AmbassadorAgent};
use App\Agents\Tools\{MatchingTools, PaymentTools, AuthTools};
use Illuminate\Support\Facades\Log;

class HumanExecutionService
{
    public function __construct(
        private AgentEventLogger $logger
    ) {}

    /**
     * Execute a human task. Routes to the correct service based on task_type.
     * This is the single method the HITL controller calls.
     */
    public function execute(HumanTask $task, User $operator, array $inputs = []): array
    {
        $task->update([
            'status'      => 'in_progress',
            'assigned_to' => $operator->id,
            'assigned_at' => now(),
        ]);

        try {
            $result = match ($task->task_type) {
                'match_employer'      => $this->executeMatchEmployer($task, $operator, $inputs),
                'send_message'        => $this->executeSendMessage($task, $operator, $inputs),
                'verify_nin'          => $this->executeVerifyNin($task, $operator, $inputs),
                'process_payout'      => $this->executeProcessPayout($task, $operator, $inputs),
                'resolve_dispute'     => $this->executeResolveDispute($task, $operator, $inputs),
                'review_maid_quality' => $this->executeReviewMaidQuality($task, $operator, $inputs),
                'generate_content'    => $this->executeGenerateContent($task, $operator, $inputs),
                'generate_seo_content'=> $this->executeGenerateSeoContent($task, $operator, $inputs),
                'send_outreach'       => $this->executeSendOutreach($task, $operator, $inputs),
                'approve_hitl'        => $this->executeApproveHitl($task, $operator, $inputs),
                default               => throw new \InvalidArgumentException("Unknown task type: {$task->task_type}"),
            };

            $task->update([
                'status'           => 'completed',
                'completed_by'     => $operator->id,
                'completed_at'     => now(),
                'completion_notes' => $inputs['notes'] ?? null,
            ]);

            // Log the human execution as an agent event
            $this->logger->log(
                $task->agent_name,
                "human.{$task->task_type}.completed",
                'success',
                "Human operator {$operator->name} completed: {$task->description}",
                ['result' => $result, 'operator_id' => $operator->id],
                ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
            );

            return ['success' => true, 'result' => $result];

        } catch (\Exception $e) {
            $task->update(['status' => 'pending']); // Return to queue

            $this->logger->logError(
                $task->agent_name,
                "human.{$task->task_type}.failed",
                "Human execution failed: {$e->getMessage()}",
                $e,
                ['triggered_by_human' => true, 'triggered_by_user_id' => $operator->id]
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Task Type Executors ──────────────────────────────────────────────────

    private function executeMatchEmployer(HumanTask $task, User $operator, array $inputs): array
    {
        // Human manually runs the matching algorithm for the employer
        $preferenceId = $task->task_payload['preference_id'];
        $preference   = \App\Models\EmployerPreference::findOrFail($preferenceId);

        // Reuses the same ScoutAgent service that the automated job would use
        $matches = app(ScoutAgent::class)->findMatches($preference);

        return ['matches' => $matches, 'preference_id' => $preferenceId];
    }

    private function executeSendMessage(HumanTask $task, User $operator, array $inputs): array
    {
        // Human composes and sends a message in place of the agent
        // Uses the same ChannelSender as the Ambassador
        $conversation = \App\Models\AgentConversation::findOrFail($task->task_payload['conversation_id']);
        $message      = $inputs['message'] ?? throw new \InvalidArgumentException('Message content required');

        // Save as admin message (role = 'admin')
        \App\Models\AgentMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'admin',
            'content'         => $message,
            'created_at'      => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        app(\App\Services\ChannelSender::class)->send($conversation, $message);

        return ['sent' => true, 'channel' => $conversation->channel];
    }

    private function executeProcessPayout(HumanTask $task, User $operator, array $inputs): array
    {
        // Human approves and processes a payout held for HITL
        $assignmentId = $task->task_payload['assignment_id'];
        $amount       = $task->task_payload['amount'];
        $maidId       = $task->task_payload['maid_id'];

        // Uses the same WalletService as TreasurerAgent
        app(\App\Services\WalletService::class)->transferToMaid($maidId, $amount, $assignmentId);

        return ['processed' => true, 'amount' => $amount, 'maid_id' => $maidId];
    }

    private function executeVerifyNin(HumanTask $task, User $operator, array $inputs): array
    {
        // Human manually reviews and approves/rejects a NIN verification
        $maidId   = $task->task_payload['maid_id'];
        $decision = $inputs['decision'] ?? throw new \InvalidArgumentException('Decision required: approved|rejected');
        $notes    = $inputs['notes'] ?? null;

        \DB::table('nin_verifications')
            ->where('user_id', $maidId)
            ->update([
                'status'       => $decision,
                'review_notes' => "[Manual review by {$operator->name}] " . $notes,
                'reviewed_at'  => now(),
            ]);

        app(\App\Services\MaidProfileService::class)->recalculate(\App\Models\User::find($maidId));

        return ['decision' => $decision, 'maid_id' => $maidId];
    }

    private function executeResolveDispute(HumanTask $task, User $operator, array $inputs): array
    {
        // Human resolves a dispute in place of RefereeAgent
        $resolution = $inputs['resolution'] ?? throw new \InvalidArgumentException('Resolution required');

        // Uses the same AssignmentService
        return app(\App\Services\AssignmentService::class)->resolveDispute(
            $task->task_payload['assignment_id'],
            $resolution,
            $inputs['refund_amount'] ?? 0
        );
    }

    private function executeReviewMaidQuality(HumanTask $task, User $operator, array $inputs): array
    {
        // Human reviews maid quality in place of SentinelAgent
        $maidId = $task->task_payload['maid_id'];
        $action = $inputs['action'] ?? 'coaching'; // 'coaching' | 'suspend' | 'clear'
        $notes  = $inputs['notes'] ?? '';

        // Log the human quality decision
        return ['action' => $action, 'maid_id' => $maidId, 'notes' => $notes];
    }

    private function executeGenerateContent(HumanTask $task, User $operator, array $inputs): array
    {
        // Human manually triggers content generation for the marketing agent
        \App\Jobs\GenerateSocialContent::dispatch(
            $task->task_payload['funnel_stage'] ?? null,
            $task->task_payload['theme_id'] ?? null
        )->onQueue('marketing');

        return ['queued' => true];
    }

    private function executeGenerateSeoContent(HumanTask $task, User $operator, array $inputs): array
    {
        $pageId = $task->task_payload['page_id'];
        $page   = \App\Models\SeoPage::findOrFail($pageId);

        app(\App\Services\SeoContentGenerator::class)->generate($page);

        return ['page_id' => $pageId, 'status' => $page->fresh()->page_status];
    }

    private function executeSendOutreach(HumanTask $task, User $operator, array $inputs): array
    {
        // Human manually sends an outreach message from a campaign
        $campaign  = \App\Models\AgentCampaign::findOrFail($task->task_payload['campaign_id']);
        $identity  = \App\Models\AgentChannelIdentity::findOrFail($task->task_payload['identity_id']);
        $message   = $inputs['message_override'] ?? null; // Human can edit the message

        \App\Jobs\DispatchOutreach::dispatch($campaign, $identity)
            ->onQueue('outreach');

        return ['dispatched' => true, 'campaign' => $campaign->name];
    }

    private function executeApproveHitl(HumanTask $task, User $operator, array $inputs): array
    {
        // Generic approval — approves the pending agent_event and triggers the held action
        $eventId  = $task->task_payload['event_id'];
        $decision = $inputs['decision'] ?? throw new \InvalidArgumentException('Decision: approved|rejected');

        $event = \App\Models\AgentEvent::findOrFail($eventId);
        $event->update([
            'approved'     => $decision === 'approved',
            'approved_by'  => $operator->id,
            'approval_note'=> $inputs['note'] ?? null,
            'approved_at'  => now(),
        ]);

        if ($decision === 'approved' && isset($task->task_payload['callback_job'])) {
            // Re-dispatch the original job that was held
            $jobClass = $task->task_payload['callback_job'];
            $jobPayload = $task->task_payload['callback_payload'] ?? [];
            dispatch(new $jobClass(...$jobPayload));
        }

        return ['decision' => $decision, 'event_id' => $eventId];
    }
}
```

---

## Phase 4 — SSE Real-Time Event Stream

### 4.1 — SSE Controller

Streams agent events to the browser in real time without WebSockets.

**File:** `app/Http/Controllers/Admin/AgentControlRoom/EventStreamController.php`

```php
<?php

namespace App\Http\Controllers\Admin\AgentControlRoom;

use App\Http\Controllers\Controller;
use App\Models\AgentEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventStreamController extends Controller
{
    /**
     * SSE endpoint for the live feed panel.
     * Streams new agent_events rows as they are created.
     *
     * Client connects to: GET /admin/control-room/stream
     * Each event is a JSON object matching the AgentEvent shape.
     *
     * Reconnection: The browser sends the Last-Event-ID header on reconnect.
     * We use the AgentEvent ID as the event ID for reliable reconnection.
     */
    public function stream(Request $request): StreamedResponse
    {
        $lastEventId = (int) ($request->header('Last-Event-ID') ?? $request->query('last_id', 0));

        return new StreamedResponse(function () use ($lastEventId) {
            // Disable output buffering for true streaming
            if (ob_get_level()) {
                ob_end_flush();
            }

            // Send initial keep-alive
            echo "retry: 3000\n\n";
            flush();

            $lastSentId = $lastEventId;

            // Poll the database for new events every 2 seconds
            // This is simpler and more reliable than a WebSocket for this use case
            $iterations = 0;
            $maxIterations = 150; // 5 minutes max connection time (client will reconnect)

            while ($iterations < $maxIterations) {
                // Fetch new events since the last sent ID
                $newEvents = AgentEvent::where('id', '>', $lastSentId)
                    ->orderBy('id')
                    ->take(20) // Max 20 events per poll to avoid huge payloads
                    ->get([
                        'id', 'agent_name', 'event_type', 'severity', 'summary',
                        'triggered_by_human', 'related_user_id', 'related_model',
                        'related_id', 'requires_approval', 'total_tokens',
                        'estimated_cost_usd', 'channel', 'created_at',
                    ]);

                foreach ($newEvents as $event) {
                    $data = $event->toArray();
                    $data['created_at'] = $event->created_at->diffForHumans();

                    echo "id: {$event->id}\n";
                    echo "event: agent_event\n";
                    echo "data: " . json_encode($data) . "\n\n";

                    $lastSentId = $event->id;
                    flush();
                }

                // Also emit queue health counters every 10 seconds
                if ($iterations % 5 === 0) {
                    $health = $this->getQueueHealth();
                    echo "event: queue_health\n";
                    echo "data: " . json_encode($health) . "\n\n";
                    flush();
                }

                // Heartbeat to keep the connection alive
                if ($iterations % 3 === 0) {
                    echo ": heartbeat\n\n";
                    flush();
                }

                // Check if the connection is still open
                if (connection_aborted()) {
                    break;
                }

                sleep(2);
                $iterations++;
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no', // Disable Nginx buffering
            'Connection'        => 'keep-alive',
        ]);
    }

    private function getQueueHealth(): array
    {
        $agents = ['scout', 'sentinel', 'referee', 'concierge', 'treasurer',
                   'gatekeeper', 'ambassador', 'marketer', 'seo_content', 'outreach'];

        $health = [];
        $since  = now()->subHours(24);

        foreach ($agents as $agent) {
            $events = AgentEvent::where('agent_name', $agent)
                ->where('created_at', '>=', $since)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN severity = 'error' THEN 1 ELSE 0 END) as errors,
                    SUM(CASE WHEN severity = 'success' THEN 1 ELSE 0 END) as successes,
                    AVG(duration_ms) as avg_duration_ms,
                    SUM(total_tokens) as total_tokens,
                    SUM(estimated_cost_usd) as total_cost
                ")
                ->first();

            $health[$agent] = [
                'total'          => $events->total ?? 0,
                'errors'         => $events->errors ?? 0,
                'successes'      => $events->successes ?? 0,
                'avg_duration'   => round($events->avg_duration_ms ?? 0),
                'total_tokens'   => $events->total_tokens ?? 0,
                'total_cost'     => round($events->total_cost ?? 0, 4),
                'error_rate'     => $events->total > 0
                                        ? round(($events->errors / $events->total) * 100, 1)
                                        : 0,
            ];
        }

        // Queue depths from Laravel's jobs table
        $queuedJobs = \DB::table('jobs')
            ->selectRaw("queue, COUNT(*) as count")
            ->groupBy('queue')
            ->pluck('count', 'queue')
            ->toArray();

        $pendingHitl = \App\Models\HumanTask::pending()->count();

        return compact('health', 'queuedJobs', 'pendingHitl');
    }
}
```

---

## Phase 5 — Control Room Controllers

### 5.1 — Main Control Room Controller

**File:** `app/Http/Controllers/Admin/AgentControlRoom/ControlRoomController.php`

```php
<?php

namespace App\Http\Controllers\Admin\AgentControlRoom;

use App\Http\Controllers\Controller;
use App\Models\{AgentEvent, AgentOverride, HumanTask};
use App\Services\{AgentOverrideService, HumanExecutionService};
use Illuminate\Http\Request;
use Inertia\Inertia;

class ControlRoomController extends Controller
{
    public function __construct(
        private AgentOverrideService $overrides,
        private HumanExecutionService $executor,
    ) {}

    /**
     * Main Control Room page — renders all five panels.
     */
    public function index(): \Inertia\Response
    {
        $agentList = ['scout', 'sentinel', 'referee', 'concierge', 'treasurer',
                      'gatekeeper', 'ambassador', 'marketer', 'seo_content', 'outreach'];

        // Agent override states for Panel 2 status badges
        $overrideStates = AgentOverride::whereIn('agent_name', $agentList)
            ->get(['agent_name', 'mode', 'kill_switch', 'override_reason',
                   'daily_spend_cap_usd', 'current_daily_spend_usd'])
            ->keyBy('agent_name');

        // Recent events for Panel 1 initial load (SSE takes over after this)
        $recentEvents = AgentEvent::with('relatedUser:id,name')
            ->orderByDesc('id')
            ->take(50)
            ->get([
                'id', 'agent_name', 'event_type', 'severity', 'summary',
                'triggered_by_human', 'related_user_id', 'related_model',
                'related_id', 'requires_approval', 'total_tokens',
                'estimated_cost_usd', 'channel', 'created_at',
            ])
            ->map(fn($e) => array_merge($e->toArray(), [
                'created_at' => $e->created_at->diffForHumans(),
            ]));

        // HITL queue for Panel 5
        $hitlQueue = HumanTask::pending()
            ->with('relatedUser:id,name', 'triggerEvent:id,summary')
            ->orderBy('priority')
            ->orderBy('created_at')
            ->take(20)
            ->get();

        // Panel 4: cost summary for today
        $todayCost = AgentEvent::where('created_at', '>=', now()->startOfDay())
            ->selectRaw("
                SUM(estimated_cost_usd) as total_cost_usd,
                SUM(total_tokens) as total_tokens,
                agent_name,
                COUNT(*) as event_count
            ")
            ->groupBy('agent_name')
            ->get()
            ->keyBy('agent_name');

        // Panel 3: campaign stats
        $campaigns = \App\Models\AgentCampaign::with([
            'logs' => fn($q) => $q->where('sent_at', '>=', now()->subDays(7))
        ])
        ->orderBy('trigger_type')
        ->get(['id', 'name', 'slug', 'trigger_type', 'preferred_channel', 'is_active']);

        return Inertia::render('Admin/ControlRoom/Index', [
            'overrideStates' => $overrideStates,
            'recentEvents'   => $recentEvents,
            'hitlQueue'      => $hitlQueue,
            'todayCost'      => $todayCost,
            'campaigns'      => $campaigns,
            'agentList'      => $agentList,
            'lastEventId'    => $recentEvents->first()['id'] ?? 0,
        ]);
    }

    // ── Override Controls ────────────────────────────────────────────────────

    public function pauseAgent(Request $request, string $agentName): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'reason'               => 'required|string|max:500',
            'auto_resume_minutes'  => 'nullable|integer|min:5|max:1440',
        ]);

        $override = $this->overrides->pause(
            $agentName,
            auth()->user(),
            $request->reason,
            $request->auto_resume_minutes
        );

        return response()->json(['success' => true, 'mode' => $override->mode]);
    }

    public function resumeAgent(string $agentName): \Illuminate\Http\JsonResponse
    {
        $override = $this->overrides->resume($agentName, auth()->user());
        return response()->json(['success' => true, 'mode' => $override->mode]);
    }

    public function superviseAgent(Request $request, string $agentName): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'reason'       => 'required|string|max:500',
            'action_types' => 'nullable|array',
        ]);

        $override = $this->overrides->supervise(
            $agentName,
            auth()->user(),
            $request->reason,
            $request->action_types
        );

        return response()->json(['success' => true, 'mode' => $override->mode]);
    }

    public function killSwitch(Request $request, string $agentName): \Illuminate\Http\JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $this->overrides->killSwitch($agentName, auth()->user(), $request->reason);

        return response()->json(['success' => true, 'killed' => true]);
    }

    public function releaseKillSwitch(string $agentName): \Illuminate\Http\JsonResponse
    {
        $override = $this->overrides->releaseKillSwitch($agentName, auth()->user());
        return response()->json(['success' => true, 'mode' => $override->mode]);
    }

    public function updateSpendCap(Request $request, string $agentName): \Illuminate\Http\JsonResponse
    {
        $request->validate(['cap_usd' => 'required|numeric|min:0']);
        $this->overrides->updateSpendCap($agentName, auth()->user(), $request->cap_usd);
        return response()->json(['success' => true]);
    }

    // ── HITL Queue ───────────────────────────────────────────────────────────

    public function hitlQueue(Request $request): \Illuminate\Http\JsonResponse
    {
        $tasks = HumanTask::pending()
            ->with('relatedUser:id,name', 'triggerEvent:id,summary,agent_name')
            ->when($request->agent, fn($q, $a) => $q->where('agent_name', $a))
            ->orderBy('priority')
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json($tasks);
    }

    public function executeHitlTask(Request $request, HumanTask $task): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'inputs' => 'nullable|array',
        ]);

        $result = $this->executor->execute($task, auth()->user(), $request->inputs ?? []);

        return response()->json($result);
    }

    public function skipHitlTask(Request $request, HumanTask $task): \Illuminate\Http\JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $task->update([
            'status'           => 'skipped',
            'completed_by'     => auth()->id(),
            'completed_at'     => now(),
            'completion_notes' => 'Skipped: ' . ($request->reason ?? 'No reason given'),
        ]);

        return response()->json(['success' => true]);
    }

    public function reassignHitlTask(Request $request, HumanTask $task): \Illuminate\Http\JsonResponse
    {
        $request->validate(['assign_to_user_id' => 'required|exists:users,id']);

        $task->update([
            'assigned_to' => $request->assign_to_user_id,
            'status'      => 'assigned',
            'assigned_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    // ── Event Detail ─────────────────────────────────────────────────────────

    public function eventDetail(AgentEvent $event): \Illuminate\Http\JsonResponse
    {
        $event->load('triggeredBy:id,name', 'relatedUser:id,name', 'approvedByUser:id,name');
        return response()->json($event);
    }

    // ── Manual Agent Triggers ─────────────────────────────────────────────────

    /**
     * Human operator manually triggers a job that an agent would normally run.
     * This is the "push button" for human-initiated AI work.
     */
    public function triggerAgentJob(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'job_type'   => 'required|string',
            'parameters' => 'nullable|array',
        ]);

        $job = match ($request->job_type) {
            'scan_campaign_metrics'   => new \App\Jobs\ScanCampaignMetrics(),
            'generate_social_content' => new \App\Jobs\GenerateSocialContent(
                $request->parameters['funnel_stage'] ?? null,
                $request->parameters['theme_id'] ?? null
            ),
            'generate_seo_content'    => new \App\Jobs\GenerateSeoContentBatch(
                $request->parameters['page_ids'] ?? []
            ),
            'process_salary_reminders' => new \App\Jobs\ProcessSalaryReminders(),
            'revenue_leak_audit'       => new \App\Jobs\RevenueLeakAudit(),
            'sync_post_analytics'      => new \App\Jobs\SyncPostAnalytics(),
            default => throw new \InvalidArgumentException("Unknown job type: {$request->job_type}"),
        };

        dispatch($job)->onQueue('default');

        app(\App\Services\AgentEventLogger::class)->log(
            'system',
            'job.manually_triggered',
            'info',
            "Operator " . auth()->user()->name . " manually triggered: {$request->job_type}",
            ['job_type' => $request->job_type, 'parameters' => $request->parameters ?? []],
            ['triggered_by_human' => true, 'triggered_by_user_id' => auth()->id()]
        );

        return response()->json(['success' => true, 'job' => $request->job_type]);
    }

    // ── Token Cost Analytics ─────────────────────────────────────────────────

    public function costAnalytics(Request $request): \Illuminate\Http\JsonResponse
    {
        $range = $request->range ?? '7d';

        $since = match ($range) {
            '1d'  => now()->subDay(),
            '7d'  => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };

        $byAgent = AgentEvent::where('created_at', '>=', $since)
            ->whereNotNull('estimated_cost_usd')
            ->groupBy('agent_name')
            ->selectRaw("
                agent_name,
                COUNT(*) as call_count,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost_usd) as total_cost,
                AVG(estimated_cost_usd) as avg_cost_per_call,
                MAX(estimated_cost_usd) as max_single_call_cost
            ")
            ->orderByDesc('total_cost')
            ->get();

        $byDay = AgentEvent::where('created_at', '>=', $since)
            ->whereNotNull('estimated_cost_usd')
            ->groupBy(\DB::raw('DATE(created_at)'))
            ->selectRaw("DATE(created_at) as date, SUM(estimated_cost_usd) as cost, SUM(total_tokens) as tokens")
            ->orderBy('date')
            ->get();

        $byModel = AgentEvent::where('created_at', '>=', $since)
            ->whereNotNull('llm_model')
            ->groupBy('llm_model')
            ->selectRaw("llm_model, SUM(total_tokens) as tokens, SUM(estimated_cost_usd) as cost")
            ->orderByDesc('cost')
            ->get();

        return response()->json(compact('byAgent', 'byDay', 'byModel'));
    }
}
```

---

## Phase 6 — React Control Room UI (Five Panels)

### 6.1 — Main Control Room Page

**File:** `resources/js/Pages/Admin/ControlRoom/Index.jsx`

The Control Room is a single page with five collapsible/resizable panels. On desktop, panels 1 and 2 are side-by-side, with panels 3, 4, 5 stacked below. On mobile, all panels are stacked vertically.

```jsx
import { useState, useEffect, useRef } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import { usePage } from "@inertiajs/react";
import LiveFeedPanel from "./Panels/LiveFeedPanel";
import QueueHealthPanel from "./Panels/QueueHealthPanel";
import CampaignCommandPanel from "./Panels/CampaignCommandPanel";
import TokenCostPanel from "./Panels/TokenCostPanel";
import HumanTaskPanel from "./Panels/HumanTaskPanel";
import AgentControlBar from "./Components/AgentControlBar";

export default function ControlRoomIndex() {
    const { overrideStates, recentEvents, hitlQueue, todayCost,
            campaigns, agentList, lastEventId } = usePage().props;

    const [events, setEvents]           = useState(recentEvents);
    const [queueHealth, setQueueHealth] = useState({});
    const [hitlTasks, setHitlTasks]     = useState(hitlQueue);
    const [agents, setAgents]           = useState(overrideStates);
    const [pendingCount, setPending]    = useState(hitlQueue.data?.length ?? 0);
    const sseRef = useRef(null);

    // ── SSE Connection ───────────────────────────────────────────────────────
    useEffect(() => {
        let lastId = lastEventId;

        const connect = () => {
            const url = `/admin/control-room/stream?last_id=${lastId}`;
            const sse  = new EventSource(url);
            sseRef.current = sse;

            sse.addEventListener("agent_event", (e) => {
                const event = JSON.parse(e.data);
                lastId = event.id;
                setEvents(prev => [event, ...prev].slice(0, 200)); // Keep last 200

                // If this event requires approval, bump the HITL counter
                if (event.requires_approval && event.approved === null) {
                    setPending(p => p + 1);
                }
            });

            sse.addEventListener("queue_health", (e) => {
                const health = JSON.parse(e.data);
                setQueueHealth(health);
                setPending(health.pendingHitl ?? 0);
            });

            sse.onerror = () => {
                sse.close();
                // Exponential backoff reconnect
                setTimeout(connect, 3000);
            };
        };

        connect();
        return () => sseRef.current?.close();
    }, []);

    return (
        <AdminLayout title="Agent Control Room">
            {/* Agent Status Bar — always visible at top */}
            <AgentControlBar
                agents={agents}
                agentList={agentList}
                onAgentUpdate={setAgents}
            />

            {/* Panel Grid */}
            <div className="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-4">
                {/* Panel 1 — Live Feed */}
                <LiveFeedPanel events={events} />

                {/* Panel 2 — Queue Health */}
                <QueueHealthPanel health={queueHealth} agentList={agentList} />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-4">
                {/* Panel 3 — Campaign Command */}
                <CampaignCommandPanel campaigns={campaigns} />

                {/* Panel 4 — Token Cost */}
                <TokenCostPanel todayCost={todayCost} />

                {/* Panel 5 — Human Task Queue */}
                <HumanTaskPanel
                    tasks={hitlTasks}
                    pendingCount={pendingCount}
                    onTaskComplete={(taskId) => {
                        setHitlTasks(prev => ({
                            ...prev,
                            data: prev.data?.filter(t => t.id !== taskId)
                        }));
                        setPending(p => Math.max(0, p - 1));
                    }}
                />
            </div>
        </AdminLayout>
    );
}
```

---

### 6.2 — AgentControlBar Component

The always-visible agent status strip at the top of the Control Room.

**File:** `resources/js/Pages/Admin/ControlRoom/Components/AgentControlBar.jsx`

```jsx
import { useState } from "react";
import axios from "axios";

const AGENT_LABELS = {
    scout: "Scout", sentinel: "Sentinel", referee: "Referee",
    concierge: "Concierge", treasurer: "Treasurer", gatekeeper: "Gatekeeper",
    ambassador: "Ambassador", marketer: "Marketer", seo_content: "SEO Content",
    outreach: "Outreach",
};

const MODE_COLORS = {
    active:     "bg-green-500",
    supervised: "bg-yellow-400",
    paused:     "bg-gray-400",
    readonly:   "bg-blue-400",
};

const MODE_ICONS = {
    active: "●", supervised: "◑", paused: "■", readonly: "○",
};

export default function AgentControlBar({ agents, agentList, onAgentUpdate }) {
    const [selectedAgent, setSelectedAgent] = useState(null);
    const [showModal, setShowModal]         = useState(false);
    const [modalAction, setModalAction]     = useState(null);
    const [reason, setReason]               = useState("");
    const [loading, setLoading]             = useState(false);

    const openModal = (agent, action) => {
        setSelectedAgent(agent);
        setModalAction(action);
        setReason("");
        setShowModal(true);
    };

    const handleAction = async () => {
        setLoading(true);
        try {
            const endpoint = `/admin/control-room/agents/${selectedAgent}/${modalAction}`;
            const res = await axios.post(endpoint, { reason, auto_resume_minutes: null });

            onAgentUpdate(prev => ({
                ...prev,
                [selectedAgent]: {
                    ...prev[selectedAgent],
                    mode: res.data.mode,
                    kill_switch: res.data.killed ?? false,
                }
            }));
            setShowModal(false);
        } catch (e) {
            alert("Action failed: " + (e.response?.data?.message ?? e.message));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="bg-gray-900 rounded-xl p-3 flex flex-wrap gap-2 items-center">
            <span className="text-gray-400 text-xs font-semibold uppercase tracking-wider mr-2">
                Agents
            </span>

            {agentList.map(agent => {
                const state      = agents[agent] ?? {};
                const isKilled   = state.kill_switch;
                const mode       = isKilled ? "killed" : (state.mode ?? "active");
                const colorClass = isKilled ? "bg-red-600" : (MODE_COLORS[mode] ?? "bg-gray-400");

                return (
                    <div
                        key={agent}
                        className="relative group"
                        onClick={() => openModal(agent, null)}
                    >
                        <button className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-white text-xs font-medium cursor-pointer transition-all hover:scale-105 ${colorClass}`}>
                            <span>{MODE_ICONS[mode] ?? "◆"}</span>
                            <span>{AGENT_LABELS[agent]}</span>
                        </button>

                        {/* Context menu on click */}
                        <div className="hidden group-focus-within:block absolute top-8 left-0 z-50 bg-gray-800 rounded-lg shadow-xl border border-gray-700 w-44 py-1">
                            {mode !== "active" && !isKilled && (
                                <button
                                    onClick={(e) => { e.stopPropagation(); openModal(agent, "resume"); }}
                                    className="w-full text-left px-3 py-2 text-sm text-green-400 hover:bg-gray-700"
                                >
                                    ▶ Resume
                                </button>
                            )}
                            {(mode === "active" || mode === "supervised") && (
                                <button
                                    onClick={(e) => { e.stopPropagation(); openModal(agent, "pause"); }}
                                    className="w-full text-left px-3 py-2 text-sm text-yellow-400 hover:bg-gray-700"
                                >
                                    ⏸ Pause
                                </button>
                            )}
                            <button
                                onClick={(e) => { e.stopPropagation(); openModal(agent, "supervise"); }}
                                className="w-full text-left px-3 py-2 text-sm text-blue-400 hover:bg-gray-700"
                            >
                                👁 Supervise
                            </button>
                            <hr className="border-gray-700 my-1" />
                            {!isKilled ? (
                                <button
                                    onClick={(e) => { e.stopPropagation(); openModal(agent, "kill-switch"); }}
                                    className="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-gray-700"
                                >
                                    🔴 Kill Switch
                                </button>
                            ) : (
                                <button
                                    onClick={(e) => { e.stopPropagation(); openModal(agent, "release-kill-switch"); }}
                                    className="w-full text-left px-3 py-2 text-sm text-green-400 hover:bg-gray-700"
                                >
                                    🟢 Release Kill Switch
                                </button>
                            )}
                        </div>
                    </div>
                );
            })}

            {/* Override Modal */}
            {showModal && (
                <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center" onClick={() => setShowModal(false)}>
                    <div className="bg-gray-800 rounded-xl p-6 w-96 shadow-2xl border border-gray-700" onClick={e => e.stopPropagation()}>
                        <h3 className="text-white font-semibold mb-1 capitalize">
                            {modalAction?.replace("-", " ")} {AGENT_LABELS[selectedAgent]}
                        </h3>
                        <p className="text-gray-400 text-sm mb-4">
                            This action takes effect immediately and is logged in the event feed.
                        </p>
                        <textarea
                            className="w-full bg-gray-700 text-white rounded-lg p-3 text-sm mb-4 resize-none"
                            rows={3}
                            placeholder="Reason (required)..."
                            value={reason}
                            onChange={e => setReason(e.target.value)}
                        />
                        <div className="flex gap-3 justify-end">
                            <button onClick={() => setShowModal(false)} className="px-4 py-2 text-sm text-gray-400 hover:text-white">
                                Cancel
                            </button>
                            <button
                                onClick={handleAction}
                                disabled={loading || !reason.trim()}
                                className="px-4 py-2 text-sm bg-emerald-600 text-white rounded-lg disabled:opacity-40 hover:bg-emerald-500"
                            >
                                {loading ? "Applying..." : "Confirm"}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
```

---

### 6.3 — LiveFeedPanel

**File:** `resources/js/Pages/Admin/ControlRoom/Panels/LiveFeedPanel.jsx`

```jsx
import { useState } from "react";
import axios from "axios";

const SEVERITY_COLORS = {
    success: "border-l-green-500 bg-green-500/5",
    warning: "border-l-yellow-400 bg-yellow-400/5",
    error:   "border-l-red-500 bg-red-500/5",
    pending: "border-l-purple-500 bg-purple-500/5",
    info:    "border-l-blue-400 bg-blue-400/5",
};

const AGENT_COLORS = {
    scout: "text-emerald-400", sentinel: "text-yellow-400", referee: "text-orange-400",
    concierge: "text-blue-400", treasurer: "text-green-400", gatekeeper: "text-purple-400",
    ambassador: "text-pink-400", marketer: "text-cyan-400",
    seo_content: "text-lime-400", outreach: "text-indigo-400", system: "text-gray-400",
};

export default function LiveFeedPanel({ events }) {
    const [filter, setFilter]         = useState("all");
    const [expandedId, setExpandedId] = useState(null);
    const [detail, setDetail]         = useState({});

    const filtered = filter === "all" ? events : events.filter(e => e.agent_name === filter);

    const loadDetail = async (eventId) => {
        if (detail[eventId]) {
            setExpandedId(expandedId === eventId ? null : eventId);
            return;
        }
        const res = await axios.get(`/admin/control-room/events/${eventId}`);
        setDetail(prev => ({ ...prev, [eventId]: res.data }));
        setExpandedId(eventId);
    };

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 flex flex-col" style={{ height: "520px" }}>
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                <div className="flex items-center gap-2">
                    <span className="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
                    <h2 className="text-white font-semibold text-sm">Live Agent Feed</h2>
                    <span className="text-gray-500 text-xs">{filtered.length} events</span>
                </div>
                <select
                    value={filter}
                    onChange={e => setFilter(e.target.value)}
                    className="bg-gray-800 text-gray-300 text-xs rounded px-2 py-1 border border-gray-700"
                >
                    <option value="all">All Agents</option>
                    {["scout","sentinel","referee","concierge","treasurer",
                      "gatekeeper","ambassador","marketer","seo_content","outreach"].map(a => (
                        <option key={a} value={a}>{a}</option>
                    ))}
                </select>
            </div>

            {/* Feed */}
            <div className="flex-1 overflow-y-auto px-2 py-2 space-y-1">
                {filtered.map(event => (
                    <div key={event.id}>
                        <div
                            onClick={() => loadDetail(event.id)}
                            className={`border-l-2 px-3 py-2 rounded-r cursor-pointer hover:opacity-90 transition-opacity ${SEVERITY_COLORS[event.severity] ?? SEVERITY_COLORS.info}`}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-0.5">
                                        <span className={`text-xs font-semibold uppercase ${AGENT_COLORS[event.agent_name] ?? 'text-gray-400'}`}>
                                            {event.agent_name}
                                        </span>
                                        <span className="text-gray-600 text-xs">{event.event_type}</span>
                                        {event.triggered_by_human && (
                                            <span className="text-xs bg-orange-500/20 text-orange-400 px-1 rounded">HUMAN</span>
                                        )}
                                        {event.requires_approval && event.approved === null && (
                                            <span className="text-xs bg-purple-500/20 text-purple-400 px-1 rounded animate-pulse">PENDING APPROVAL</span>
                                        )}
                                    </div>
                                    <p className="text-gray-300 text-xs leading-tight truncate">{event.summary}</p>
                                </div>
                                <div className="flex-shrink-0 text-right">
                                    <div className="text-gray-500 text-xs">{event.created_at}</div>
                                    {event.estimated_cost_usd && (
                                        <div className="text-gray-600 text-xs">${parseFloat(event.estimated_cost_usd).toFixed(4)}</div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Expanded Detail */}
                        {expandedId === event.id && detail[event.id] && (
                            <div className="ml-3 bg-gray-800 rounded-b border border-gray-700 border-t-0 p-3">
                                <pre className="text-xs text-gray-300 overflow-x-auto whitespace-pre-wrap max-h-48">
                                    {JSON.stringify(detail[event.id].detail, null, 2)}
                                </pre>
                                {detail[event.id].related_model && (
                                    <a
                                        href={`/admin/${detail[event.id].related_model?.toLowerCase()}/${detail[event.id].related_id}`}
                                        className="mt-2 inline-block text-xs text-blue-400 hover:underline"
                                    >
                                        View {detail[event.id].related_model} #{detail[event.id].related_id} →
                                    </a>
                                )}
                            </div>
                        )}
                    </div>
                ))}

                {filtered.length === 0 && (
                    <div className="flex items-center justify-center h-full text-gray-600 text-sm">
                        No events yet
                    </div>
                )}
            </div>
        </div>
    );
}
```

---

### 6.4 — QueueHealthPanel

**File:** `resources/js/Pages/Admin/ControlRoom/Panels/QueueHealthPanel.jsx`

```jsx
import { useState } from "react";
import axios from "axios";

const JOB_TRIGGERS = [
    { label: "Scan Campaign Metrics",    job: "scan_campaign_metrics",    icon: "📊" },
    { label: "Generate Social Content",  job: "generate_social_content",  icon: "✍️" },
    { label: "Generate SEO Content",     job: "generate_seo_content",     icon: "🔍" },
    { label: "Salary Reminders",         job: "process_salary_reminders", icon: "💰" },
    { label: "Revenue Leak Audit",       job: "revenue_leak_audit",       icon: "🔎" },
    { label: "Sync Post Analytics",      job: "sync_post_analytics",      icon: "📈" },
];

export default function QueueHealthPanel({ health, agentList }) {
    const [triggering, setTriggering] = useState(null);

    const trigger = async (jobType, params = {}) => {
        setTriggering(jobType);
        try {
            await axios.post("/admin/control-room/trigger", { job_type: jobType, parameters: params });
            alert(`✓ ${jobType} queued`);
        } catch (e) {
            alert("Failed: " + e.response?.data?.message);
        } finally {
            setTriggering(null);
        }
    };

    const agents = health.health ?? {};
    const queues = health.queuedJobs ?? {};

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 flex flex-col" style={{ height: "520px" }}>
            <div className="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <h2 className="text-white font-semibold text-sm">Queue Health (24h)</h2>
                <span className="text-gray-500 text-xs">
                    {Object.values(queues).reduce((a, b) => a + b, 0)} jobs queued
                </span>
            </div>

            <div className="flex-1 overflow-y-auto">
                {/* Agent Health Table */}
                <table className="w-full text-xs">
                    <thead>
                        <tr className="text-gray-500 border-b border-gray-800">
                            <th className="px-3 py-2 text-left">Agent</th>
                            <th className="px-3 py-2 text-right">Events</th>
                            <th className="px-3 py-2 text-right">Errors</th>
                            <th className="px-3 py-2 text-right">Avg ms</th>
                            <th className="px-3 py-2 text-right">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        {agentList.map(agent => {
                            const d = agents[agent] ?? {};
                            const errorRate = d.error_rate ?? 0;
                            const rowColor = errorRate > 10 ? "bg-red-900/20" : errorRate > 3 ? "bg-yellow-900/20" : "";

                            return (
                                <tr key={agent} className={`border-b border-gray-800/50 hover:bg-gray-800/50 ${rowColor}`}>
                                    <td className="px-3 py-2 text-gray-300 font-medium capitalize">{agent}</td>
                                    <td className="px-3 py-2 text-right text-gray-400">{d.total ?? 0}</td>
                                    <td className={`px-3 py-2 text-right ${d.errors > 0 ? 'text-red-400 font-semibold' : 'text-gray-500'}`}>
                                        {d.errors ?? 0}
                                    </td>
                                    <td className="px-3 py-2 text-right text-gray-400">{d.avg_duration ?? '—'}</td>
                                    <td className="px-3 py-2 text-right text-gray-400">
                                        {d.total_cost ? `$${d.total_cost.toFixed(3)}` : '—'}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>

                {/* Manual Job Triggers */}
                <div className="px-4 py-3 border-t border-gray-800">
                    <p className="text-gray-500 text-xs mb-2 font-semibold uppercase tracking-wider">Manual Triggers</p>
                    <div className="grid grid-cols-2 gap-1.5">
                        {JOB_TRIGGERS.map(({ label, job, icon }) => (
                            <button
                                key={job}
                                onClick={() => trigger(job)}
                                disabled={triggering === job}
                                className="flex items-center gap-1.5 px-2 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs rounded border border-gray-700 disabled:opacity-50 transition-colors"
                            >
                                <span>{icon}</span>
                                <span className="truncate">{label}</span>
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
```

---

### 6.5 — HumanTaskPanel

**File:** `resources/js/Pages/Admin/ControlRoom/Panels/HumanTaskPanel.jsx`

```jsx
import { useState } from "react";
import axios from "axios";

const TASK_ICONS = {
    match_employer:      "🔍",
    send_message:        "💬",
    verify_nin:          "🪪",
    process_payout:      "💸",
    resolve_dispute:     "⚖️",
    review_maid_quality: "⭐",
    generate_content:    "✍️",
    generate_seo_content:"🔎",
    send_outreach:       "📣",
    approve_hitl:        "✅",
};

const PRIORITY_LABELS = { 1: "Urgent", 2: "High", 3: "Normal", 4: "Low" };
const PRIORITY_COLORS = {
    1: "text-red-400 bg-red-900/30",
    2: "text-orange-400 bg-orange-900/30",
    3: "text-blue-400 bg-blue-900/30",
    4: "text-gray-400 bg-gray-800",
};

export default function HumanTaskPanel({ tasks, pendingCount, onTaskComplete }) {
    const [selectedTask, setSelectedTask] = useState(null);
    const [inputs, setInputs]             = useState({});
    const [executing, setExecuting]       = useState(false);

    const taskList = Array.isArray(tasks) ? tasks : (tasks?.data ?? []);

    const executeTask = async (task) => {
        setExecuting(true);
        try {
            const res = await axios.post(`/admin/control-room/hitl/${task.id}/execute`, { inputs });
            if (res.data.success) {
                onTaskComplete(task.id);
                setSelectedTask(null);
            } else {
                alert("Execution failed: " + res.data.error);
            }
        } finally {
            setExecuting(false);
        }
    };

    const skipTask = async (task) => {
        await axios.post(`/admin/control-room/hitl/${task.id}/skip`, { reason: "Skipped by operator" });
        onTaskComplete(task.id);
    };

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 flex flex-col" style={{ height: "520px" }}>
            {/* Header */}
            <div className="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <h2 className="text-white font-semibold text-sm">Human Task Queue</h2>
                    {pendingCount > 0 && (
                        <span className="bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                            {pendingCount}
                        </span>
                    )}
                </div>
            </div>

            {/* Task List */}
            <div className="flex-1 overflow-y-auto">
                {taskList.length === 0 ? (
                    <div className="flex items-center justify-center h-full text-gray-600 text-sm">
                        ✓ No tasks pending
                    </div>
                ) : (
                    <div className="divide-y divide-gray-800">
                        {taskList.map(task => (
                            <div key={task.id} className="px-4 py-3">
                                <div className="flex items-start justify-between gap-2 mb-2">
                                    <div className="flex items-start gap-2 flex-1">
                                        <span className="text-lg">{TASK_ICONS[task.task_type] ?? "📋"}</span>
                                        <div>
                                            <p className="text-white text-xs font-medium">{task.description}</p>
                                            <div className="flex items-center gap-2 mt-0.5">
                                                <span className={`text-xs px-1.5 py-0.5 rounded ${PRIORITY_COLORS[task.priority]}`}>
                                                    {PRIORITY_LABELS[task.priority]}
                                                </span>
                                                <span className="text-gray-500 text-xs capitalize">{task.reason}</span>
                                                <span className="text-gray-600 text-xs">
                                                    Agent: {task.agent_name}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    {task.due_by && (
                                        <span className={`text-xs flex-shrink-0 ${new Date(task.due_by) < new Date() ? 'text-red-400' : 'text-gray-500'}`}>
                                            Due: {new Date(task.due_by).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}
                                        </span>
                                    )}
                                </div>

                                <div className="flex gap-2">
                                    <button
                                        onClick={() => setSelectedTask(selectedTask?.id === task.id ? null : task)}
                                        className="px-2 py-1 text-xs bg-emerald-700 hover:bg-emerald-600 text-white rounded transition-colors"
                                    >
                                        Execute
                                    </button>
                                    <button
                                        onClick={() => skipTask(task)}
                                        className="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors"
                                    >
                                        Skip
                                    </button>
                                </div>

                                {/* Execution Form — shown inline when task is selected */}
                                {selectedTask?.id === task.id && (
                                    <TaskExecutionForm
                                        task={task}
                                        inputs={inputs}
                                        onChange={setInputs}
                                        onExecute={() => executeTask(task)}
                                        onCancel={() => setSelectedTask(null)}
                                        executing={executing}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * Dynamic form rendered based on task_type.
 * Each task type shows only the fields needed for that specific action.
 */
function TaskExecutionForm({ task, inputs, onChange, onExecute, onCancel, executing }) {
    const set = (key, value) => onChange(prev => ({ ...prev, [key]: value }));

    const renderFields = () => {
        switch (task.task_type) {
            case "send_message":
            case "send_outreach":
                return (
                    <textarea
                        className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none"
                        rows={3}
                        placeholder="Message to send..."
                        value={inputs.message ?? ""}
                        onChange={e => set("message", e.target.value)}
                    />
                );
            case "verify_nin":
                return (
                    <div className="space-y-2">
                        <select
                            className="w-full bg-gray-700 text-white text-xs p-2 rounded"
                            value={inputs.decision ?? "approved"}
                            onChange={e => set("decision", e.target.value)}
                        >
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                            <option value="manual_review">Refer for Manual Review</option>
                        </select>
                        <textarea className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none"
                            rows={2} placeholder="Review notes (optional)"
                            value={inputs.notes ?? ""} onChange={e => set("notes", e.target.value)} />
                    </div>
                );
            case "process_payout":
                return (
                    <div className="bg-gray-700/50 rounded p-2 text-xs text-gray-300 space-y-1">
                        <div>Amount: <strong className="text-white">₦{Number(task.task_payload?.amount || 0).toLocaleString()}</strong></div>
                        <div>Maid ID: <strong className="text-white">#{task.task_payload?.maid_id}</strong></div>
                        <textarea className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none mt-2"
                            rows={2} placeholder="Approval notes..."
                            value={inputs.notes ?? ""} onChange={e => set("notes", e.target.value)} />
                    </div>
                );
            case "resolve_dispute":
                return (
                    <div className="space-y-2">
                        <textarea className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none"
                            rows={3} placeholder="Resolution decision..."
                            value={inputs.resolution ?? ""} onChange={e => set("resolution", e.target.value)} />
                        <input type="number" placeholder="Refund amount (₦), 0 if none"
                            className="w-full bg-gray-700 text-white text-xs p-2 rounded"
                            value={inputs.refund_amount ?? ""} onChange={e => set("refund_amount", e.target.value)} />
                    </div>
                );
            case "approve_hitl":
                return (
                    <div className="space-y-2">
                        <select className="w-full bg-gray-700 text-white text-xs p-2 rounded"
                            value={inputs.decision ?? "approved"} onChange={e => set("decision", e.target.value)}>
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                        </select>
                        <textarea className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none"
                            rows={2} placeholder="Decision note..."
                            value={inputs.note ?? ""} onChange={e => set("note", e.target.value)} />
                    </div>
                );
            default:
                // Generic: just show the payload and a notes field
                return (
                    <div className="space-y-2">
                        <pre className="text-xs text-gray-400 bg-gray-700/50 p-2 rounded overflow-x-auto max-h-20">
                            {JSON.stringify(task.task_payload, null, 2)}
                        </pre>
                        <textarea className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none"
                            rows={2} placeholder="Completion notes..."
                            value={inputs.notes ?? ""} onChange={e => set("notes", e.target.value)} />
                    </div>
                );
        }
    };

    return (
        <div className="mt-2 bg-gray-800 rounded border border-gray-700 p-3 space-y-2">
            {renderFields()}
            <div className="flex gap-2">
                <button onClick={onExecute} disabled={executing}
                    className="px-3 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-500 text-white rounded font-medium disabled:opacity-50">
                    {executing ? "Executing..." : "Execute Task"}
                </button>
                <button onClick={onCancel} className="px-3 py-1.5 text-xs text-gray-400 hover:text-white">
                    Cancel
                </button>
            </div>
        </div>
    );
}
```

---

### 6.6 — CampaignCommandPanel

**File:** `resources/js/Pages/Admin/ControlRoom/Panels/CampaignCommandPanel.jsx`

```jsx
import { useState } from "react";
import axios from "axios";

export default function CampaignCommandPanel({ campaigns }) {
    const [toggling, setToggling] = useState(null);

    const toggleCampaign = async (campaign) => {
        setToggling(campaign.id);
        try {
            await axios.patch(`/admin/agent/campaigns/${campaign.id}/toggle`);
            // Refresh handled by parent via Inertia router.reload()
            window.location.reload();
        } finally {
            setToggling(null);
        }
    };

    const runNow = async (campaign) => {
        await axios.post(`/admin/agent/campaigns/${campaign.id}/run-now`);
        alert(`✓ Campaign "${campaign.name}" queued`);
    };

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 flex flex-col" style={{ height: "240px" }}>
            <div className="px-4 py-3 border-b border-gray-800">
                <h2 className="text-white font-semibold text-sm">Campaign Command</h2>
            </div>

            <div className="flex-1 overflow-y-auto divide-y divide-gray-800">
                {campaigns.map(c => (
                    <div key={c.id} className="px-3 py-2 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${c.is_active ? 'bg-green-500' : 'bg-gray-600'}`} />
                            <span className="text-gray-300 text-xs truncate max-w-36">{c.name}</span>
                            <span className="text-gray-600 text-xs">{c.trigger_type}</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <button onClick={() => runNow(c)}
                                className="text-xs text-blue-400 hover:text-blue-300 px-1">
                                ▶ Run
                            </button>
                            <button onClick={() => toggleCampaign(c)} disabled={toggling === c.id}
                                className={`text-xs px-1 ${c.is_active ? 'text-yellow-400 hover:text-yellow-300' : 'text-green-400 hover:text-green-300'}`}>
                                {c.is_active ? '⏸ Pause' : '▶ Start'}
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
```

---

### 6.7 — TokenCostPanel

**File:** `resources/js/Pages/Admin/ControlRoom/Panels/TokenCostPanel.jsx`

```jsx
import { useState, useEffect } from "react";
import axios from "axios";

export default function TokenCostPanel({ todayCost }) {
    const [analytics, setAnalytics] = useState(null);
    const [range, setRange]         = useState("7d");

    useEffect(() => {
        axios.get(`/admin/control-room/cost-analytics?range=${range}`)
            .then(res => setAnalytics(res.data));
    }, [range]);

    const todayTotal = Object.values(todayCost).reduce((sum, d) => sum + (d.total_cost_usd ?? 0), 0);
    const todayTokens = Object.values(todayCost).reduce((sum, d) => sum + (d.total_tokens ?? 0), 0);

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 flex flex-col" style={{ height: "240px" }}>
            <div className="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <h2 className="text-white font-semibold text-sm">Token Cost Tracker</h2>
                <select value={range} onChange={e => setRange(e.target.value)}
                    className="bg-gray-800 text-gray-300 text-xs rounded px-2 py-1 border border-gray-700">
                    <option value="1d">Today</option>
                    <option value="7d">7 Days</option>
                    <option value="30d">30 Days</option>
                </select>
            </div>

            <div className="flex-1 overflow-y-auto p-3">
                {/* Today summary */}
                <div className="grid grid-cols-2 gap-2 mb-3">
                    <div className="bg-gray-800 rounded p-2 text-center">
                        <div className="text-white font-bold text-sm">${todayTotal.toFixed(3)}</div>
                        <div className="text-gray-500 text-xs">Today's Cost</div>
                    </div>
                    <div className="bg-gray-800 rounded p-2 text-center">
                        <div className="text-white font-bold text-sm">{(todayTokens / 1000).toFixed(1)}k</div>
                        <div className="text-gray-500 text-xs">Tokens Used</div>
                    </div>
                </div>

                {/* Per-agent cost breakdown */}
                {analytics && (
                    <div className="space-y-1">
                        {analytics.byAgent?.map(a => (
                            <div key={a.agent_name} className="flex items-center gap-2">
                                <span className="text-gray-400 text-xs w-24 truncate capitalize">{a.agent_name}</span>
                                <div className="flex-1 bg-gray-700 rounded-full h-1.5">
                                    <div
                                        className="bg-emerald-500 h-1.5 rounded-full"
                                        style={{ width: `${Math.min(100, (a.total_cost / (analytics.byAgent[0]?.total_cost || 1)) * 100)}%` }}
                                    />
                                </div>
                                <span className="text-gray-400 text-xs w-14 text-right">${parseFloat(a.total_cost).toFixed(3)}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
```

---

## Phase 7 — Human Task Execution Interface

### 7.1 — Dedicated HITL Detail Page

For complex tasks that don't fit in the panel's inline form, a full-page view.

**File:** `resources/js/Pages/Admin/ControlRoom/HumanTask/Show.jsx`

This page renders a full task execution interface with:
- The complete `task_payload` displayed in a readable format
- The original agent event that triggered it (if any)
- Full execution form with all relevant fields
- The related user's profile if applicable
- History of similar completed tasks for reference

The controller for this page is `ControlRoomController::showHitlTask(HumanTask $task)` which returns:

```php
return Inertia::render('Admin/ControlRoom/HumanTask/Show', [
    'task'         => $task->load('relatedUser', 'triggerEvent', 'assignedOperator'),
    'similarTasks' => HumanTask::where('task_type', $task->task_type)
                         ->where('status', 'completed')
                         ->latest('completed_at')
                         ->take(3)
                         ->with('completedByOperator:id,name')
                         ->get(['id', 'description', 'completion_notes', 'completed_at', 'completed_by']),
]);
```

---

## Phase 8 — Agent Kill Switches & Override Controls

### 8.1 — Emergency All-Stop Command

For complete AI shutdown — every agent paused simultaneously.

**File:** `app/Console/Commands/EmergencyStopAllAgents.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\AgentOverride;
use App\Services\AgentEventLogger;
use Illuminate\Console\Command;

class EmergencyStopAllAgents extends Command
{
    protected $signature   = 'agents:emergency-stop {reason}';
    protected $description = 'Immediately pause all agents and route all tasks to humans';

    public function handle(AgentEventLogger $logger): int
    {
        $reason = $this->argument('reason');

        AgentOverride::query()->update([
            'mode'            => 'paused',
            'kill_switch'     => true,
            'override_reason' => "[EMERGENCY] {$reason}",
        ]);

        // Bust all caches
        foreach (AgentOverride::all() as $override) {
            $override->clearCache();
        }

        $logger->log(
            'system', 'system.emergency_stop', 'error',
            "EMERGENCY STOP: All agents halted. Reason: {$reason}",
            ['reason' => $reason, 'triggered_by' => 'CLI']
        );

        $this->error("All agents halted. Reason: {$reason}");
        $this->line("To resume all agents: php artisan agents:resume-all");

        return Command::SUCCESS;
    }
}
```

```php
// Companion resume command
// php artisan agents:resume-all

class ResumeAllAgents extends Command
{
    protected $signature = 'agents:resume-all';

    public function handle(AgentEventLogger $logger): int
    {
        AgentOverride::query()->update([
            'mode'            => 'active',
            'kill_switch'     => false,
            'override_reason' => null,
        ]);

        foreach (AgentOverride::all() as $override) {
            $override->clearCache();
        }

        $logger->log('system', 'system.all_resumed', 'success', 'All agents resumed', []);
        $this->info('All agents resumed.');

        return Command::SUCCESS;
    }
}
```

---

### 8.2 — AI Downtime Auto-Detection Job

Runs every 5 minutes. If an AI provider is unreachable, automatically routes all pending tasks to humans and notifies the admin.

**File:** `app/Jobs/CheckAiProviderHealth.php`

```php
<?php

namespace App\Jobs;

use App\Models\AgentOverride;
use App\Services\AgentEventLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckAiProviderHealth implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 15;

    public function handle(AgentEventLogger $logger): void
    {
        $openAiHealthy    = $this->pingOpenAi();
        $anthropicHealthy = $this->pingAnthropic();

        $allDown = !$openAiHealthy && !$anthropicHealthy;

        if ($allDown) {
            // Set all active agents to supervised mode
            AgentOverride::where('mode', 'active')->update([
                'mode'            => 'supervised',
                'supervised_action_types' => null, // All actions need approval
                'override_reason' => 'AI provider unreachable — auto-supervised',
            ]);

            foreach (AgentOverride::all() as $o) {
                $o->clearCache();
            }

            $logger->log(
                'system', 'system.ai_downtime', 'error',
                'AI providers unreachable — all agents switched to supervised mode',
                ['openai' => $openAiHealthy, 'anthropic' => $anthropicHealthy]
            );

            // Notify admin
            \Mail::to(config('mail.admin_address'))
                 ->send(new \App\Mail\AiProviderDownAlert());

        } elseif ($openAiHealthy || $anthropicHealthy) {
            // If at least one provider is up and some agents were auto-supervised, log recovery
            $autoSupervised = AgentOverride::where('override_reason', 'LIKE', '%AI provider unreachable%')
                ->where('mode', 'supervised')
                ->count();

            if ($autoSupervised > 0) {
                $logger->log(
                    'system', 'system.ai_recovered', 'success',
                    'AI provider recovered — manually review and resume agents if needed',
                    ['openai' => $openAiHealthy, 'anthropic' => $anthropicHealthy]
                );
            }
        }
    }

    private function pingOpenAi(): bool
    {
        try {
            $res = Http::timeout(8)->withToken(config('services.openai.api_key'))
                ->get('https://api.openai.com/v1/models');
            return $res->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function pingAnthropic(): bool
    {
        try {
            $res = Http::timeout(8)->withHeaders(['x-api-key' => config('services.anthropic.api_key')])
                ->get('https://api.anthropic.com/v1/models');
            return $res->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

Schedule it:

```php
Schedule::job(new CheckAiProviderHealth)->everyFiveMinutes()->name('check-ai-health');
```

---

## Phase 9 — Routes & Registration

### 9.1 — Routes

**File:** `routes/control_room.php`

```php
<?php

use App\Http\Controllers\Admin\AgentControlRoom\{ControlRoomController, EventStreamController};
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin/control-room')
    ->name('admin.control_room.')
    ->group(function () {

    // Main page
    Route::get('/',                    [ControlRoomController::class, 'index'])->name('index');

    // SSE stream — note: no 'web' session middleware on this route
    Route::get('/stream',              [EventStreamController::class, 'stream'])->name('stream')
         ->withoutMiddleware(['web']); // SSE cannot have session middleware — it blocks streaming

    // Agent override controls
    Route::post('/agents/{agent}/pause',               [ControlRoomController::class, 'pauseAgent'])->name('agents.pause');
    Route::post('/agents/{agent}/resume',              [ControlRoomController::class, 'resumeAgent'])->name('agents.resume');
    Route::post('/agents/{agent}/supervise',           [ControlRoomController::class, 'superviseAgent'])->name('agents.supervise');
    Route::post('/agents/{agent}/kill-switch',         [ControlRoomController::class, 'killSwitch'])->name('agents.kill');
    Route::post('/agents/{agent}/release-kill-switch', [ControlRoomController::class, 'releaseKillSwitch'])->name('agents.release');
    Route::patch('/agents/{agent}/spend-cap',          [ControlRoomController::class, 'updateSpendCap'])->name('agents.spend_cap');

    // HITL queue
    Route::get('/hitl',                    [ControlRoomController::class, 'hitlQueue'])->name('hitl.index');
    Route::post('/hitl/{task}/execute',    [ControlRoomController::class, 'executeHitlTask'])->name('hitl.execute');
    Route::post('/hitl/{task}/skip',       [ControlRoomController::class, 'skipHitlTask'])->name('hitl.skip');
    Route::patch('/hitl/{task}/reassign',  [ControlRoomController::class, 'reassignHitlTask'])->name('hitl.reassign');
    Route::get('/hitl/{task}',             [ControlRoomController::class, 'showHitlTask'])->name('hitl.show');

    // Event detail
    Route::get('/events/{event}',  [ControlRoomController::class, 'eventDetail'])->name('events.show');

    // Manual triggers
    Route::post('/trigger',        [ControlRoomController::class, 'triggerAgentJob'])->name('trigger');

    // Cost analytics
    Route::get('/cost-analytics',  [ControlRoomController::class, 'costAnalytics'])->name('cost_analytics');
});
```

Add to `routes/web.php`:

```php
require __DIR__ . '/control_room.php';
```

---

### 9.2 — Service Container Bindings

Add to `AppServiceProvider::register()`:

```php
$this->app->singleton(\App\Services\AgentEventLogger::class);
$this->app->singleton(\App\Services\ActionDispatcher::class);
$this->app->singleton(\App\Services\AgentOverrideService::class);
$this->app->singleton(\App\Services\HumanExecutionService::class);
```

---

### 9.3 — Admin Navigation Link

In your admin sidebar navigation component, add:

```jsx
{/* In AdminLayout sidebar */}
<NavLink href="/admin/control-room" icon="🎛️" label="Control Room" badge={pendingHitlCount} />
```

The `pendingHitlCount` is shared via `HandleInertiaRequests.php`:

```php
// In HandleInertiaRequests::share():
'controlRoom' => [
    'pendingHitl' => \App\Models\HumanTask::pending()->count(),
    'agentErrors' => \App\Models\AgentEvent::where('severity', 'error')
                         ->where('created_at', '>=', now()->subHour())
                         ->count(),
],
```

---

## Definition of Done

### Database & Models

- [ ] All 3 migrations run cleanly. `php artisan migrate:status` shows all as `Ran`.
- [ ] `AgentOverride` seeder populates one row per agent, all `mode = 'active'`.
- [ ] `AgentEvent::create()` writes a row that is immediately readable via `AgentEvent::orderByDesc('id')->first()`.
- [ ] `AgentOverride::forAgent('scout')` hits cache on second call — confirmed by checking query log count.

### Agent Logging Integration

- [ ] Every agent class has `use LogsEvents` trait applied.
- [ ] `ScoutAgent::findMatches()` writes an `agent_events` row with `event_type = 'match.scored'` on every call.
- [ ] `AmbassadorAgent::reply()` writes an `agent_events` row with `event_type = 'message.sent'` on every reply.
- [ ] `TreasurerAgent` writes a `pending` event for payouts >₦500,000 and creates a `human_task_queue` row.
- [ ] `GatekeeperAgent` writes events for all three NIN verdict paths (approved, manual_review, rejected).
- [ ] `MarketerAgent` (GenerateSocialContent job) logs `post.generated` events with token counts.
- [ ] `OutreachEngine` (DispatchOutreach job) logs `outreach.sent` events with channel and campaign.
- [ ] `SeoContentAgent` logs `content.generated` events with score and page type.
- [ ] Token costs are calculated and stored. Confirmed by: `AgentEvent::whereNotNull('estimated_cost_usd')->exists()` returns true after one LLM call.

### ActionDispatcher

- [ ] `ActionDispatcher::check('scout', 'match.score')` returns `'execute'` when agent mode is `active`.
- [ ] Returns `'hitl'` when mode is `paused` and `auto_route_to_human = true`.
- [ ] Returns `'killed'` when `kill_switch = true`.
- [ ] Returns `'skip'` when daily spend cap would be breached.
- [ ] A `HumanTask` row is created whenever `check()` returns `'hitl'` and `routeToHuman()` is called.

### Override Controls

- [ ] Admin clicks Pause on an agent in the Control Bar → `agent_overrides.mode` changes to `paused` → cache is cleared → next agent job checks and routes to human queue.
- [ ] Admin clicks Resume → `mode` returns to `active` → next job executes normally.
- [ ] Kill switch activation stops an agent mid-campaign — confirmed by checking that no new `agent_events` are created for that agent after activation.
- [ ] `php artisan agents:emergency-stop "reason"` sets all agents to `kill_switch = true` within 1 second.
- [ ] `php artisan agents:resume-all` restores all agents to `active` within 1 second.
- [ ] Spend cap breach is detected before the LLM call is made — no cost incurred for a blocked call.

### SSE Stream

- [ ] Opening `/admin/control-room/stream` in a browser returns `Content-Type: text/event-stream`.
- [ ] A new `AgentEvent` created in the DB appears in the browser feed within 4 seconds.
- [ ] The browser reconnects automatically after the connection is closed (retry mechanism works).
- [ ] `queue_health` events are emitted every 10 seconds and the Queue Health panel counters update without page reload.

### Human Task Execution

- [ ] A task in the `human_task_queue` with `task_type = 'send_message'` can be executed from the HITL panel — the message is sent to the user on the correct channel and logged as a `human.send_message.completed` agent event.
- [ ] A task with `task_type = 'verify_nin'` shows the decision dropdown and updates `nin_verifications.status` on execution.
- [ ] A task with `task_type = 'process_payout'` calls `WalletService::transferToMaid()` — the maid's wallet balance increases.
- [ ] Skipping a task sets `status = 'skipped'` and removes it from the pending queue.
- [ ] Executing a task that fails returns an error to the UI and resets the task status to `pending` (does not mark as completed).

### AI Downtime Detection

- [ ] `CheckAiProviderHealth` job runs every 5 minutes (confirmed via `php artisan schedule:list`).
- [ ] When OpenAI and Anthropic endpoints are both unreachable (test by temporarily setting wrong API keys), all `active` agents are switched to `supervised` mode automatically.
- [ ] An `agent_events` row with `event_type = 'system.ai_downtime'` is created during downtime.
- [ ] An admin email notification is sent during downtime.

### Control Room UI

- [ ] `/admin/control-room` loads without JavaScript errors.
- [ ] The Agent Control Bar shows the correct mode colour for every agent.
- [ ] Pausing an agent from the Control Bar updates the badge colour without page reload.
- [ ] The Live Feed panel shows new events within 4 seconds of them being created.
- [ ] Clicking "Expand" on a Live Feed event shows the full `detail` JSON without additional page load.
- [ ] The Queue Health panel shows correct per-agent event counts and error rates for the past 24 hours.
- [ ] The HITL panel shows the correct pending count badge in the sidebar nav.
- [ ] The inline task execution form renders different fields based on `task_type`.
- [ ] Executing a task from the inline form updates the HITL panel count without page reload.
- [ ] The Token Cost panel shows today's total cost and a breakdown by agent.
- [ ] The Campaign Command panel correctly pauses and resumes campaigns via toggle buttons.
- [ ] All admin routes return 403 for non-admin authenticated users.

---

*End of Agent Control Room Implementation Guide.*
