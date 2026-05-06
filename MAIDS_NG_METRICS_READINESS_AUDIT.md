# Maids.ng — Metrics Readiness Audit
## Is the App Ready for the Outreach & SDR Agent?

**Version:** 1.0  
**Based on:** Full repo analysis (README, FEATURE_SUMMARY, AI_BACKEND_EXPLAINER, schema references)  
**Verdict:** Partially ready. The core transactional data exists but 9 critical tracking gaps will cause every metric-based campaign query to return empty results or silently fail.

---

## Executive Summary

The existing codebase has two confirmed tables that matter (`employer_preferences`, `matching_fee_payments`) and one partial logging table (`agent_activity_logs`). That is not enough. The outreach engine proposed in Phases 7 & 8 requires **precise event timestamps and state transitions** across the entire user journey. Without them, the `MetricsScanner` cannot distinguish between:

- A user who abandoned the quiz 2 hours ago vs. one who just started it
- An employer who paid but never got an assignment vs. one who paid and is actively working with a maid
- A maid who never uploaded documents vs. one whose NIN is pending review
- A user who has been inactive for 45 days vs. one who logged in yesterday

This document catalogues every gap, rates its severity, and provides the exact migrations, model changes, and event wiring needed to close each one — without touching any existing functionality.

---

## Gap Severity Legend

| Level | Meaning |
|---|---|
| 🔴 CRITICAL | Campaign cannot function at all without this. MetricsScanner returns zero results. |
| 🟡 IMPORTANT | Campaign fires but with reduced accuracy — may target wrong users or miss eligible ones. |
| 🟢 ENHANCEMENT | Campaign works, this improves personalisation or reporting quality. |

---

## Audit Table — Every Campaign vs. What It Needs

| Campaign | Metric Query | Required Data | Currently Exists? | Gap Level |
|---|---|---|---|---|
| Lead Warm-Up | `leads_never_registered` | `agent_leads.status`, `agent_channel_identities.last_seen_at` | ❌ Not yet (Phase 1 builds these) | Built in Phase 1 |
| Quiz Abandoned | `quiz_started_not_completed` | `employer_preferences.quiz_status`, `employer_preferences.quiz_started_at` | ❌ `employer_preferences` exists but has **no status or start timestamp** | 🔴 CRITICAL |
| Payment Pending × 2 | `matches_shown_no_payment` | `employer_preferences.matches_shown_at`, `matching_fee_payments.status = 'successful'` | 🟡 Payments table exists; **no `matches_shown_at` timestamp** | 🔴 CRITICAL |
| Payment Confirmed | `PaymentConfirmed` event | Event class + dispatch in webhook handler | ❌ No event class exists; webhook handler unclear | 🔴 CRITICAL |
| Post-Payment No Assignment | `paid_no_assignment_started` | `assignments` table with `employer_id`, `status` | 🟡 Assignments referenced in agents but **schema not confirmed** | 🔴 CRITICAL |
| Assignment Health Check | `AssignmentStarted` event | Event class + dispatch when assignment created | ❌ No event class exists | 🔴 CRITICAL |
| Assignment Expiring | `assignment_ending_within_days` | `assignments.end_date`, `assignments.status = 'active'` | 🟡 Likely exists but **`end_date` not confirmed** | 🟡 IMPORTANT |
| Win-Back Recent | `assignment_ended_no_return` | `assignments.status = 'completed'`, `assignments.end_date` | 🟡 Same as above | 🟡 IMPORTANT |
| Win-Back Lapsed | `user_inactive_days` | `users.last_login_at` | ❌ **`last_login_at` column does not exist on users table** | 🔴 CRITICAL |
| Maid Profile Incomplete | `maid_profile_incomplete` | `maid_profiles.is_complete` or completeness score | ❌ No completeness tracking on maid profiles | 🔴 CRITICAL |
| Maid NIN Pending | `maid_nin_pending_hours` | `nin_verifications` table with `status` + timestamps | ❌ GatekeeperAgent uses a **simulation layer** — no real NIN table confirmed | 🟡 IMPORTANT |
| All campaigns | Global cooldown | `agent_outreach_logs` table | ❌ Built in Phase 7 | Built in Phase 7 |

**Summary: 7 out of 12 campaigns are completely broken on launch day without these fixes.**

---

## Detailed Gap Analysis & Remediation

### Gap 1 — `employer_preferences` Has No Quiz Lifecycle Tracking

**What the repo has:**
The `employer_preferences` table stores completed quiz responses and links to a selected maid. It was built to capture a *finished* quiz, not a quiz *in progress*.

**What is missing:**
- No `quiz_status` column (`in_progress` / `completed` / `abandoned`)
- No `quiz_started_at` timestamp (when they opened the quiz)
- No `matches_shown_at` timestamp (when results were displayed)
- No `selected_maid_at` timestamp (when they clicked a maid)

**Why it breaks campaigns:**
The `quiz_abandoned` campaign asks: *"who started the quiz more than 2 hours ago and hasn't finished?"* Without `quiz_status = 'in_progress'` and `quiz_started_at`, the query has nothing to filter on. Equally, `matches_shown_no_payment` needs to know *when* matches were shown, not just that a preference record exists.

**Fix — Migration:**

```php
// File: database/migrations/2026_04_29_000020_add_lifecycle_to_employer_preferences.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_preferences', function (Blueprint $table) {
            // Lifecycle status
            // 'in_progress' = quiz opened, not all steps completed
            // 'completed'   = all quiz steps answered, matches generated
            // 'abandoned'   = no activity for >6 hours after in_progress
            $table->enum('quiz_status', ['in_progress', 'completed', 'abandoned'])
                  ->default('in_progress')
                  ->after('id');

            // When the employer first opened/started the quiz
            $table->timestamp('quiz_started_at')->nullable()->after('quiz_status');

            // When the quiz was fully submitted and matches were generated
            $table->timestamp('quiz_completed_at')->nullable()->after('quiz_started_at');

            // When the match results page was displayed to the employer
            // This is the moment the payment nudge clock starts
            $table->timestamp('matches_shown_at')->nullable()->after('quiz_completed_at');

            // Which step of the quiz the user is currently on (1-8)
            // Allows the agent to tell the user exactly where they left off
            $table->unsignedTinyInteger('current_step')->default(1)->after('matches_shown_at');

            $table->index(['quiz_status', 'quiz_started_at']);
            $table->index('matches_shown_at');
        });
    }

    public function down(): void
    {
        Schema::table('employer_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'quiz_status', 'quiz_started_at', 'quiz_completed_at',
                'matches_shown_at', 'current_step',
            ]);
        });
    }
};
```

**Fix — Where to update the code:**

In `OnboardingQuiz.jsx` (frontend), the quiz saves progress to the backend. Add the corresponding backend calls:

```php
// In your MatchingController or OnboardingController:

// Called when quiz is first opened (step 1 render):
public function startQuiz(Request $request): JsonResponse
{
    $pref = EmployerPreference::firstOrCreate(
        ['employer_id' => auth()->id(), 'quiz_status' => 'in_progress'],
        ['quiz_started_at' => now(), 'current_step' => 1]
    );
    return response()->json(['preference_id' => $pref->id]);
}

// Called on every step advance:
public function updateStep(Request $request, EmployerPreference $pref): JsonResponse
{
    $pref->update([
        'current_step' => $request->step,
        // Merge step data into the preferences JSON column
    ]);
    return response()->json(['ok' => true]);
}

// Called when the final step is submitted and matches are generated:
public function completeQuiz(EmployerPreference $pref): void
{
    $pref->update([
        'quiz_status'        => 'completed',
        'quiz_completed_at'  => now(),
    ]);
}

// Called when the matches results page is rendered/viewed:
public function recordMatchesShown(EmployerPreference $pref): void
{
    if (!$pref->matches_shown_at) {
        $pref->update(['matches_shown_at' => now()]);
    }
}
```

---

### Gap 2 — `matching_fee_payments` Needs a Status Column

**What the repo has:**
The `matching_fee_payments` table tracks Paystack references and links to preferences. From FEATURE_SUMMARY it stores reference and links — but **the status field is not confirmed** as a dedicated enum column.

**What is needed:**
A queryable `status` column with values `pending`, `successful`, `failed`, `refunded`. Without this the `matches_shown_no_payment` query cannot use a clean `WHERE status = 'successful'` check.

**Fix — Migration:**

```php
// File: database/migrations/2026_04_29_000021_add_status_to_matching_fee_payments.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_fee_payments', function (Blueprint $table) {
            // If status does not already exist as a proper enum, add/modify it
            if (!Schema::hasColumn('matching_fee_payments', 'status')) {
                $table->enum('status', ['pending', 'successful', 'failed', 'refunded'])
                      ->default('pending')
                      ->after('paystack_reference');
            }

            // When payment was confirmed by webhook
            if (!Schema::hasColumn('matching_fee_payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status');
            }

            // Payment type — needed for the Treasurer agent and outreach context
            if (!Schema::hasColumn('matching_fee_payments', 'payment_type')) {
                $table->enum('payment_type', ['matching_fee', 'premium_matching', 'renewal'])
                      ->default('matching_fee')
                      ->after('paid_at');
            }

            $table->index(['status', 'paid_at']);
            $table->index(['employer_id', 'status']); // adjust column name if different
        });
    }

    public function down(): void
    {
        // Only drop columns we added — do not drop status if it pre-existed
        Schema::table('matching_fee_payments', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'payment_type']);
        });
    }
};
```

---

### Gap 3 — `users` Table Has No `last_login_at`

**What the repo has:**
Standard Laravel users table with `created_at`, `updated_at`, and `email_verified_at`. No login timestamp.

**Why it breaks campaigns:**
The `win_back_lapsed` campaign (45-day inactivity) and the general user inactivity query both depend entirely on `last_login_at`. Without it, the query falls back to `created_at` which is wrong — a user created 60 days ago who logged in yesterday looks inactive.

**Fix — Migration:**

```php
// File: database/migrations/2026_04_29_000022_add_last_login_at_to_users.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('remember_token');

            // Also track login count — useful for segmentation
            // (e.g. "logged in only once" = low engagement)
            $table->unsignedInteger('login_count')->default(0)->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'login_count']);
        });
    }
};
```

**Fix — Update the Login Handler:**

```php
// In app/Http/Controllers/Auth/AuthenticatedSessionController.php
// Or in app/Http/Middleware/TrackLastLogin.php (create if preferred)

// After successful authentication:
auth()->user()->update([
    'last_login_at' => now(),
    'login_count'   => \DB::raw('login_count + 1'),
]);
```

If you prefer middleware (cleaner):

```php
// File: app/Http/Middleware/TrackLastLogin.php

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TrackLastLogin
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $user = auth()->user();

            // Only update once per session, not every request
            if (!$request->session()->has('login_tracked')) {
                $user->update([
                    'last_login_at' => now(),
                    'login_count'   => \DB::raw('login_count + 1'),
                ]);
                $request->session()->put('login_tracked', true);
            }
        }

        return $next($request);
    }
}
```

Register in `app/Http/Kernel.php` under `$middlewareGroups['web']`.

---

### Gap 4 — `assignments` Table Schema Not Confirmed

**What the repo has:**
Assignments are referenced extensively in the agents (ScoutAgent, RefereeAgent, TreasurerAgent) and in tests. The API docs reference `GET /api/v1/employer/assignments`. But the migration was not in the publicly visible repo files reviewed — the exact column set is unconfirmed.

**What is needed for the outreach engine:**

```
assignments.employer_id       — FK to users
assignments.maid_id           — FK to users
assignments.status            — ENUM: pending|active|completed|terminated|disputed
assignments.start_date        — DATE: when maid begins work (drives health check campaign)
assignments.end_date          — DATE: when contract is expected to end (drives expiry campaign)
assignments.actual_end_date   — DATE: when it actually ended (for win-back accuracy)
assignments.created_at        — standard
```

**Fix — Migration (additive only, does not drop existing columns):**

```php
// File: database/migrations/2026_04_29_000023_ensure_assignments_has_lifecycle_columns.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only runs if the assignments table exists
        if (!Schema::hasTable('assignments')) {
            // If assignments table doesn't exist at all, create it
            Schema::create('assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('maid_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('preference_id')->nullable()->constrained('employer_preferences')->nullOnDelete();
                $table->enum('status', ['pending', 'active', 'completed', 'terminated', 'disputed'])
                      ->default('pending');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->date('actual_end_date')->nullable();
                $table->text('termination_reason')->nullable();
                $table->timestamps();

                $table->index(['employer_id', 'status']);
                $table->index(['maid_id', 'status']);
                $table->index(['status', 'end_date']);
            });
            return;
        }

        // Table exists — add only missing columns
        Schema::table('assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('assignments', 'start_date')) {
                $table->date('start_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('assignments', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('assignments', 'actual_end_date')) {
                $table->date('actual_end_date')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('assignments', 'status')) {
                $table->enum('status', ['pending', 'active', 'completed', 'terminated', 'disputed'])
                      ->default('pending')->after('maid_id');
            }
        });

        // Ensure indexes exist
        try {
            Schema::table('assignments', function (Blueprint $table) {
                $table->index(['status', 'end_date'], 'assignments_status_end_date_idx');
            });
        } catch (\Exception $e) {
            // Index may already exist — safe to ignore
        }
    }

    public function down(): void
    {
        // Conservative — do not drop the table if it existed before
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['actual_end_date']);
        });
    }
};
```

---

### Gap 5 — No Laravel Events Dispatched for Key Moments

**What the repo has:**
Zero. There are no `app/Events/` files in the repo. The agents perform their logic internally but do not broadcast Laravel events that listeners can hook into.

**Why this is critical:**
The entire event-based campaign trigger system (`PaymentConfirmed`, `AssignmentStarted`, `AssignmentCompleted`) dispatches to `TriggerCampaignForEvent` listener. Without events being dispatched, none of the event campaigns ever fire.

**Fix — Create the Events:**

```php
// File: app/Events/PaymentConfirmed.php
namespace App\Events;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
class PaymentConfirmed {
    use Dispatchable;
    public function __construct(
        public readonly User $user,
        public readonly string $reference,
        public readonly int $amount,
        public readonly string $type
    ) {}
}

// File: app/Events/AssignmentStarted.php
namespace App\Events;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
class AssignmentStarted {
    use Dispatchable;
    public function __construct(
        public readonly User $employer,
        public readonly User $maid,
        public readonly int $assignmentId,
        public readonly \Carbon\Carbon $startDate,
    ) {}
}

// File: app/Events/AssignmentCompleted.php
namespace App\Events;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
class AssignmentCompleted {
    use Dispatchable;
    public function __construct(
        public readonly User $employer,
        public readonly User $maid,
        public readonly int $assignmentId,
    ) {}
}
```

**Fix — Dispatch the Events from Existing Code:**

**In your Paystack webhook handler** (wherever payment verification happens):

```php
// Find the file handling POST /payment/callback or /webhooks/paystack
// After you confirm the transaction is successful:

$user = User::find($payment->employer_id);

// Update the payment record
$payment->update(['status' => 'successful', 'paid_at' => now()]);

// Dispatch the event — this triggers the campaign chain
\App\Events\PaymentConfirmed::dispatch($user, $reference, $amount, 'matching_fee');
```

**In your assignment creation logic** (wherever an assignment row is created):

```php
// After Assignment::create():
\App\Events\AssignmentStarted::dispatch($employer, $maid, $assignment->id, $assignment->start_date);
```

**In your assignment status update** (wherever status → 'completed'):

```php
// When an admin or the system marks an assignment as completed:
\App\Events\AssignmentCompleted::dispatch($employer, $maid, $assignment->id);
```

---

### Gap 6 — Maid Profile Has No Completeness Tracking

**What the repo has:**
The FEATURE_SUMMARY shows maid profiles exist with photos, skills, experience, and location. The Gatekeeper references NIN documents. But there is no computed `is_complete` flag or completeness score.

**Why it breaks campaigns:**
The `maid_profile_incomplete` campaign cannot run without a field to query. It needs to find maids who registered but haven't completed their profile.

**Fix — Add Profile Completeness to Maid Profiles:**

```php
// File: database/migrations/2026_04_29_000024_add_completeness_to_maid_profiles.php
// (adjust table name to match your actual maid profile table)

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Try 'maid_profiles' first; adjust to your actual table name
        $table = Schema::hasTable('maid_profiles') ? 'maid_profiles' : 'users';

        Schema::table($table, function (Blueprint $table) {
            if (!Schema::hasColumn($table->getTable() ?? 'users', 'profile_completeness')) {
                $table->unsignedTinyInteger('profile_completeness')->default(0)->after('id');
                // 0 = just registered, 100 = fully complete + NIN verified
            }
            if (!Schema::hasColumn($table->getTable() ?? 'users', 'is_profile_complete')) {
                $table->boolean('is_profile_complete')->default(false);
            }
            if (!Schema::hasColumn($table->getTable() ?? 'users', 'profile_completed_at')) {
                $table->timestamp('profile_completed_at')->nullable();
            }
        });
    }

    public function down(): void {}
};
```

**Fix — Profile Completeness Calculator:**

```php
// File: app/Services/MaidProfileService.php

<?php

namespace App\Services;

use App\Models\User;

class MaidProfileService
{
    /**
     * Recalculate and save the completeness score for a maid.
     * Call this after every profile update, document upload, or NIN submission.
     */
    public function recalculate(User $maid): int
    {
        $score = 0;

        // Each criterion worth points that sum to 100
        if ($maid->name)                                  $score += 10;
        if ($maid->phone)                                 $score += 10;
        if ($maid->email)                                 $score += 5;
        if ($maid->maidProfile?->photo_path)              $score += 15;
        if (!empty($maid->maidProfile?->skills))          $score += 15;
        if ($maid->maidProfile?->location)                $score += 10;
        if ($maid->maidProfile?->expected_monthly_rate)   $score += 5;
        if ($maid->maidProfile?->experience_years !== null) $score += 5;
        if ($maid->ninVerification?->status === 'approved') $score += 20;
        if (!empty($maid->maidProfile?->bio))             $score += 5;

        $isComplete = $score >= 80;

        $maid->update([
            'profile_completeness'  => $score,
            'is_profile_complete'   => $isComplete,
            'profile_completed_at'  => $isComplete && !$maid->profile_completed_at ? now() : $maid->profile_completed_at,
        ]);

        return $score;
    }
}
```

Call `MaidProfileService::recalculate()` in the observer or after any profile save:

```php
// In a MaidProfile observer or in the profile update controller:
app(MaidProfileService::class)->recalculate($user);
```

---

### Gap 7 — NIN Verifications Have No Proper Status Table

**What the repo has:**
The GatekeeperAgent references NIN verification but the explainer explicitly says it uses a **simulation layer** for the QoreID API. The actual `nin_verifications` table may not exist or may lack status tracking.

**Fix — Migration:**

```php
// File: database/migrations/2026_04_29_000025_create_nin_verifications_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nin_verifications')) {
            // Table exists — ensure required columns are present
            Schema::table('nin_verifications', function (Blueprint $table) {
                if (!Schema::hasColumn('nin_verifications', 'status')) {
                    $table->enum('status', ['pending', 'approved', 'rejected', 'manual_review'])
                          ->default('pending')->after('user_id');
                }
                if (!Schema::hasColumn('nin_verifications', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('nin_verifications', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
                }
                if (!Schema::hasColumn('nin_verifications', 'confidence_score')) {
                    $table->unsignedTinyInteger('confidence_score')->nullable()->after('reviewed_at');
                }
            });
            return;
        }

        Schema::create('nin_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // NIN number stored hashed — never store plaintext NIN in logs
            $table->string('nin_hash', 64)->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'manual_review'])
                  ->default('pending');

            // 0-100 confidence score returned by the verification API
            $table->unsignedTinyInteger('confidence_score')->nullable();

            // API response reference
            $table->string('external_reference', 255)->nullable();

            // Reason for rejection or manual review flag
            $table->text('review_notes')->nullable();

            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('user_id'); // One verification record per user
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        // Do not drop if it already existed
    }
};
```

---

### Gap 8 — No Page/Session Activity Tracking for Quiz Abandonment (Frontend)

**What the repo has:**
The `OnboardingQuiz.jsx` is a React component. When a user closes the tab or navigates away mid-quiz, there is currently no mechanism to record the abandonment.

**Why it matters:**
Even with `quiz_started_at` added in Gap 1, the scanner will find users where `quiz_started_at` is old AND `quiz_status = 'in_progress'`. But it will also find users who are currently on the quiz right now. A simple check: if `quiz_started_at` was > 2 hours ago AND status is still `in_progress`, they have abandoned it. That logic is in `ScanCampaignMetrics` already. **No additional frontend tracking is strictly required** — the timestamp comparison handles it.

However, for improved accuracy, add a `beforeunload` handler in the quiz component that POSTs the current step:

```jsx
// In OnboardingQuiz.jsx — add to useEffect:
useEffect(() => {
    const handleUnload = () => {
        if (currentStep < totalSteps) {
            // Use sendBeacon for reliability on page unload
            navigator.sendBeacon('/api/quiz/step', JSON.stringify({
                preference_id: preferenceId,
                step: currentStep,
                _token: csrfToken,
            }));
        }
    };

    window.addEventListener('beforeunload', handleUnload);
    return () => window.removeEventListener('beforeunload', handleUnload);
}, [currentStep, preferenceId]);
```

---

### Gap 9 — `agent_activity_logs` Is Too Coarse for Campaign Targeting

**What the repo has:**
The `agent_activity_logs` table exists for basic agent decision logging. But it logs **agent system decisions** (matching scores, verification results) not **user funnel events** (quiz step reached, payment page viewed, dashboard opened).

**Why this matters:**
This is a 🟢 ENHANCEMENT, not critical. But it becomes valuable once the platform has volume — you can use activity logs to build smarter segments. For now, the gaps above (timestamps on key tables) are sufficient.

**Improvement — Add a lightweight user event log:**

```php
// File: database/migrations/2026_04_29_000026_create_user_events_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Session ID for pre-registration tracking
            $table->string('session_id', 255)->nullable();

            // The event name — use a consistent naming convention:
            // 'quiz.started', 'quiz.step.3', 'quiz.completed',
            // 'matches.viewed', 'payment.initiated', 'payment.confirmed',
            // 'dashboard.opened', 'assignment.created', 'profile.updated'
            $table->string('event', 100);

            // Any structured data about the event
            $table->json('properties')->nullable();

            // Source: 'web', 'api', 'agent', 'system'
            $table->string('source', 50)->default('web');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'event', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_events');
    }
};
```

**Usage in controllers/services:**

```php
// Wherever a key moment happens — add one line:

// Quiz step saved:
UserEvent::create(['user_id' => auth()->id(), 'event' => 'quiz.step.' . $step]);

// Matches shown:
UserEvent::create(['user_id' => auth()->id(), 'event' => 'matches.viewed', 
    'properties' => ['preference_id' => $preference->id, 'match_count' => count($matches)]]);

// Payment initiated:
UserEvent::create(['user_id' => auth()->id(), 'event' => 'payment.initiated',
    'properties' => ['reference' => $reference, 'amount' => $amount]]);

// Or use a helper service to make it one line everywhere:
app(UserEventService::class)->track('matches.viewed', ['preference_id' => $pref->id]);
```

---

## Complete List of Migrations to Run

In this exact order:

```
2026_04_29_000020_add_lifecycle_to_employer_preferences
2026_04_29_000021_add_status_to_matching_fee_payments
2026_04_29_000022_add_last_login_at_to_users
2026_04_29_000023_ensure_assignments_has_lifecycle_columns
2026_04_29_000024_add_completeness_to_maid_profiles
2026_04_29_000025_create_nin_verifications_table
2026_04_29_000026_create_user_events_table
```

```bash
php artisan migrate
php artisan migrate:status
```

All 7 should show as `Ran`.

---

## Code Touch Map — What to Update Where

This is a summary of every existing file that needs a change. Each change is additive (no rewrites).

| File | Change Required | Impact |
|---|---|---|
| `MatchingController.php` | Add `quiz_started_at` write on quiz open; `quiz_completed_at` on completion; `matches_shown_at` on results render | Fixes Gaps 1 campaign queries |
| `OnboardingQuiz.jsx` | Add `beforeunload` beacon handler; POST step progress on advance | Improves quiz abandonment accuracy |
| `AuthenticatedSessionController.php` OR new middleware | Write `last_login_at` on login | Fixes Gap 3 win-back campaigns |
| Paystack/Flutterwave webhook handler | Dispatch `PaymentConfirmed` event after verifying payment | Fixes Gap 5 event campaigns |
| Assignment creation controller/service | Dispatch `AssignmentStarted` after insert | Fixes Gap 5 health check campaign |
| Assignment status update logic | Dispatch `AssignmentCompleted` when status → completed | Fixes Gap 5 win-back scheduling |
| Maid profile update controller | Call `MaidProfileService::recalculate()` after save | Fixes Gap 6 profile campaign |
| GatekeeperAgent | Write to `nin_verifications` with proper status | Fixes Gap 7 NIN campaign |
| `EventServiceProvider.php` | Register `TriggerCampaignForEvent` listener for all 3 events | Required for event campaigns to fire |

---

## What Still Works Without These Fixes

To be fair — if you deploy Phases 1–6 (the reactive agent) before addressing these gaps, the following works perfectly:

- Web chat widget (guest + authenticated) — fully operational
- Email channel — fully operational
- WhatsApp, Instagram, Facebook — fully operational
- OTP identity verification — fully operational
- Admin conversation dashboard — fully operational
- All manual tool executions (register, match, pay, assign) — fully operational

The gaps only affect the **proactive outreach campaigns** (Phase 7). Phases 1–6 have zero dependency on the fixes above.

---

## Recommended Implementation Order

Do these gaps in this sequence, ideally before Phase 7 migrations run:

**Step 1** — Run Gap 1, 2, 3, 4 migrations (all are purely additive — zero downtime risk)

**Step 2** — Deploy the `TrackLastLogin` middleware

**Step 3** — Update `MatchingController` to write the quiz lifecycle timestamps

**Step 4** — Create the 3 Event classes

**Step 5** — Wire Events into the webhook handler and assignment service

**Step 6** — Deploy `MaidProfileService` and call it from profile update controller

**Step 7** — Run Gap 5, 6, 7 migrations

**Step 8** — Register listeners in `EventServiceProvider`

**Step 9** — Update `ScanCampaignMetrics` queries to use the new column names (they reference the correct column names already as written in Phase 7 guide)

**Step 10** — Run Phase 7 migrations (`agent_campaigns`, `agent_outreach_logs`)

**Step 11** — Seed campaigns

**Step 12** — Start queue worker and cron scheduler

**Step 13** — Run `ScanCampaignMetrics` manually once: `php artisan tinker` → `App\Jobs\ScanCampaignMetrics::dispatchSync()` and verify it returns eligible identities without errors

---

## Definition of Done — Metrics Readiness

The app is fully ready for the outreach engine when ALL of the following are true:

1. `employer_preferences.quiz_status` is being written as `in_progress` when the quiz is opened and `completed` when submitted.
2. `employer_preferences.matches_shown_at` is being written when the matches results view is rendered.
3. `matching_fee_payments.status` has a confirmed `successful` value written by the webhook handler.
4. `users.last_login_at` is updated on every login.
5. `assignments.start_date`, `assignments.end_date`, `assignments.status` are all present and populated.
6. `PaymentConfirmed`, `AssignmentStarted`, and `AssignmentCompleted` events are dispatched from the correct locations.
7. `maid_profiles.profile_completeness` is recalculated after every profile update.
8. `nin_verifications.status` is written as `pending` on submission and `approved`/`rejected` after review.
9. `php artisan tinker` → manual call to each `MetricsScanner` audience query returns a non-empty collection when test data matching the criteria exists in the DB.
10. A test payment via Paystack sandbox correctly dispatches `PaymentConfirmed` and the `payment_confirmed` campaign outreach appears in `agent_outreach_logs` within 5 minutes.

---

*End of Metrics Readiness Audit.*
