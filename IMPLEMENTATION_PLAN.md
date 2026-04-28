# Maids.ng — Complete Implementation Plan

> **Goal**: Transform the existing codebase from "code written but not functional" into a fully working, production-ready system covering wallets, assignments, salary management, smart notifications, and AI matching.

---

## Table of Contents

1. [Current State Audit](#1-current-state-audit)
2. [Phase 1 — Database Foundation](#2-phase-1--database-foundation)
3. [Phase 2 — Artisan Commands & Cron Wiring](#3-phase-2--artisan-commands--cron-wiring)
4. [Phase 3 — Controllers & API Routes](#4-phase-3--controllers--api-routes)
5. [Phase 4 — Employer UI](#5-phase-4--employer-ui)
6. [Phase 5 — Maid UI](#6-phase-5--maid-ui)
7. [Phase 6 — Admin Dashboard](#7-phase-6--admin-dashboard)
8. [Phase 7 — SMS Integration (Production)](#8-phase-7--sms-integration-production)
9. [Phase 8 — Events, Listeners & Queue](#9-phase-8--events-listeners--queue)
10. [Phase 9 — Testing & QA](#10-phase-9--testing--qa)
11. [Phase 10 — Production Deployment](#11-phase-10--production-deployment)
12. [Acceptance Criteria](#12-acceptance-criteria)

---

## 1. Current State Audit

| Layer | Status | Notes |
|---|---|---|
| **Migrations (8 new tables)** | Written, NOT run | All pending in `migrate:status` |
| **Models (8)** | Complete | Rich with relations, scopes, business logic |
| **Services (5)** | Complete | Wallet, Salary, Assignment, Notification, SmartNotification |
| **Cron config** | Partial | `console.php` references 4 commands that don't exist as classes |
| `salary_reminders` table | **Missing** | No migration file |
| **Controllers** | Missing | No wallet/salary/assignment controllers |
| **API routes** | Missing | No endpoints for the new features |
| **UI / Views** | Missing | No Blade templates for employer, maid, or admin |
| **Events/Listeners** | Partial | `AssignmentRejected` event referenced but file existence unconfirmed |
| **SMS providers** | Stub only | Termii/Twilio/AfricaTalking code exists but uses mock in prod |
| **Tests** | Missing | No feature/unit tests for new code |

---

## 2. Phase 1 — Database Foundation

### 2.1 Create missing migration: `salary_reminders`

**File**: `database/migrations/2026_04_27_000009_create_salary_reminders_table.php`

```
Columns:
- id
- schedule_id → salary_schedules FK
- reminder_type: enum('upcoming', 'overdue', 'escalated')
- sent_to_employer_at: timestamp nullable
- employer_response: string nullable
- escalated_to_admin_at: timestamp nullable
- created_at, updated_at
- indexes: schedule_id, reminder_type, sent_to_employer_at
```

### 2.2 Fix column name mismatches between models and migrations

Several models reference columns that differ from migration definitions:

| Model Field | Migration Column | Fix |
|---|---|---|
| `SalarySchedule.monthly_amount` | `monthly_salary` | Update model fillable to match migration (`monthly_salary`) |
| `SalarySchedule.start_date` | `employment_start_date` | Align model to migration column names |
| `SalarySchedule.payment_day` | `salary_day` | Align model |
| `SalarySchedule.next_payment_due` | `next_salary_due_date` | Align model |
| `SalarySchedule.status` | `payment_status` + `is_active` | Align model |
| `SalarySchedule.last_payment_at` | not present | Add to migration or remove from model |
| `NotificationLog.user_id` | `recipient_id` | Migrations use `user_id`, SmartNotificationService uses `recipient_id` — **must unify** |
| `NotificationLog.type` | `notification_type` | Migrations use `type`, model uses `notification_type` — **must unify** |
| `NotificationLog.content` | `message` | Migrations use `content`, model uses `message` — **must unify** |
| `NotificationLog.context_json` | `context` | Migrations use `context_json`, NotificationService uses `context` — **must unify** |
| `NotificationLog.sent_at` | `sent_at` | OK |
| `NotificationLog.delivery_status` | `delivery_status` | OK |
| `NotificationLog.ai_generated` | `ai_generated` | OK |
| `NotificationLog.follow_up_sequence` | `follow_up_sequence` | OK |
| `NotificationLog.parent_notification_id` | `parent_notification_id` | OK |
| `NotificationLog.timezone` | `timezone` | OK |
| `NotificationLog.local_time_sent` | missing | Add to migration or remove from model |

**Action**: Reconcile ALL column names between migrations and models. The model is the source of truth — update migrations to match model fillable arrays.

### 2.3 Run migrations

```bash
php artisan migrate
```

### 2.4 Seed base data

- Create seeder for admin user with `admin` role
- Create seeder for sample employer + maid users for testing
- Create seeder for system settings (SMS provider, matching fee amount, etc.)

---

## 3. Phase 2 — Artisan Commands & Cron Wiring

### 3.1 Create command classes

| Command | Class | Frequency | Purpose |
|---|---|---|---|
| `ai:process-notifications` | `app/Console/Commands/ProcessNotifications.php` | Every minute | Process pending notifications in queue, respect work hours, send via SMS/email/push |
| `ai:process-matching-queue` | `app/Console/Commands/ProcessMatchingQueue.php` | Every 5 min | Process `ai_matching_queue` jobs, run ScoutAgent matching, create assignments |
| `ai:process-assignment-status` | `app/Console/Commands/ProcessAssignmentStatus.php` | Every 15 min | Check assignment timeouts, send follow-ups, detect completions |
| `ai:process-salary-reminders` | `app/Console/Commands/ProcessSalaryReminders.php` | Daily 09:00 | Send 3-day, 1-day, due-date salary reminders; escalate overdue |

### 3.2 Each command structure

```php
class ProcessNotifications extends Command
{
    protected $signature = 'ai:process-notifications';
    protected $description = 'Process pending notifications respecting work hours';

    public function handle(SmartNotificationService $notificationService): int
    {
        $processed = $notificationService->processPendingNotifications(batchSize: 100);
        $this->info("Processed {$processed} notifications.");
        return Command::SUCCESS;
    }
}
```

### 3.3 Verify `routes/console.php`

Confirm all 4 scheduled commands match the new class names and signatures.

---

## 4. Phase 3 — Controllers & API Routes

### 4.1 Create Controllers

| Controller | Route Prefix | Methods |
|---|---|---|
| `EmployerWalletController` | `/api/employer/wallet` | `balance`, `deposit`, `transactions`, `topup` |
| `MaidWalletController` | `/api/maid/wallet` | `balance`, `transactions`, `requestWithdrawal` |
| `AssignmentController` | `/api/assignments` | `index`, `show`, `accept`, `reject`, `complete`, `cancel` |
| `SalaryController` | `/api/salary` | `schedule`, `payments`, `upcoming`, `history` |
| `AdminWalletController` | `/api/admin/wallets` | `overview`, `approveWithdrawal`, `rejectWithdrawal`, `adjustBalance` |
| `AdminAssignmentController` | `/api/admin/assignments` | `overview`, `statistics`, `forceAssign`, `escalate` |
| `AdminSalaryController` | `/api/admin/salary` | `overview`, `overdue`, `escalate`, `markPaid` |
| `AdminNotificationController` | `/api/admin/notifications` | `logs`, `retryFailed`, `statistics` |
| `AdminMatchingQueueController` | `/api/admin/matching-queue` | `jobs`, `jobDetail`, `retry`, `cancel`, `review` |

### 4.2 API Route Definitions (`routes/api.php`)

```php
// Employer routes (auth:employer)
Route::middleware('auth:sanctum')->prefix('employer')->group(function () {
    Route::get('wallet/balance', [EmployerWalletController::class, 'balance']);
    Route::post('wallet/deposit', [EmployerWalletController::class, 'deposit']);
    Route::get('wallet/transactions', [EmployerWalletController::class, 'transactions']);
    
    Route::get('assignments', [AssignmentController::class, 'employerIndex']);
    Route::get('assignments/{id}', [AssignmentController::class, 'show']);
    Route::post('assignments/{id}/accept', [AssignmentController::class, 'accept']);
    Route::post('assignments/{id}/reject', [AssignmentController::class, 'reject']);
    
    Route::get('salary/schedule', [SalaryController::class, 'employerSchedule']);
    Route::get('salary/payments', [SalaryController::class, 'employerPayments']);
    Route::get('salary/upcoming', [SalaryController::class, 'upcoming']);
});

// Maid routes (auth:maid)
Route::middleware('auth:sanctum')->prefix('maid')->group(function () {
    Route::get('wallet/balance', [MaidWalletController::class, 'balance']);
    Route::get('wallet/transactions', [MaidWalletController::class, 'transactions']);
    Route::post('wallet/withdraw', [MaidWalletController::class, 'requestWithdrawal']);
    
    Route::get('assignments', [AssignmentController::class, 'maidIndex']);
    Route::get('salary/history', [SalaryController::class, 'maidHistory']);
    Route::get('salary/earnings', [SalaryController::class, 'maidEarnings']);
});

// Admin routes (auth:admin)
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('wallets/overview', [AdminWalletController::class, 'overview']);
    Route::post('wallets/{id}/adjust', [AdminWalletController::class, 'adjustBalance']);
    Route::post('wallets/withdrawals/{txnId}/approve', [AdminWalletController::class, 'approveWithdrawal']);
    Route::post('wallets/withdrawals/{txnId}/reject', [AdminWalletController::class, 'rejectWithdrawal']);
    
    Route::get('assignments/overview', [AdminAssignmentController::class, 'overview']);
    Route::get('assignments/statistics', [AdminAssignmentController::class, 'statistics']);
    Route::post('assignments/{id}/force-assign', [AdminAssignmentController::class, 'forceAssign']);
    
    Route::get('salary/overview', [AdminSalaryController::class, 'overview']);
    Route::get('salary/overdue', [AdminSalaryController::class, 'overdue']);
    Route::post('salary/{scheduleId}/escalate', [AdminSalaryController::class, 'escalate']);
    Route::post('salary/{paymentId}/mark-paid', [AdminSalaryController::class, 'markPaid']);
    
    Route::get('notifications/logs', [AdminNotificationController::class, 'logs']);
    Route::post('notifications/retry-failed', [AdminNotificationController::class, 'retryFailed']);
    Route::get('notifications/statistics', [AdminNotificationController::class, 'statistics']);
    
    Route::get('matching-queue/jobs', [AdminMatchingQueueController::class, 'jobs']);
    Route::get('matching-queue/jobs/{jobId}', [AdminMatchingQueueController::class, 'jobDetail']);
    Route::post('matching-queue/jobs/{jobId}/retry', [AdminMatchingQueueController::class, 'retry']);
    Route::post('matching-queue/jobs/{jobId}/review', [AdminMatchingQueueController::class, 'review']);
});
```

### 4.3 Web Routes (`routes/web.php`)

Add web routes for Blade-rendered employer/maid/admin dashboards if not using SPA:

```php
// Employer
Route::middleware(['auth', 'role:employer'])->prefix('employer')->group(function () {
    Route::get('dashboard', [EmployerDashboardController::class, 'index'])->name('employer.dashboard');
    Route::get('wallet', [EmployerDashboardController::class, 'wallet'])->name('employer.wallet');
    Route::get('assignments', [EmployerDashboardController::class, 'assignments'])->name('employer.assignments');
    Route::get('salary', [EmployerDashboardController::class, 'salary'])->name('employer.salary');
});

// Maid
Route::middleware(['auth', 'role:maid'])->prefix('maid')->group(function () {
    Route::get('dashboard', [MaidDashboardController::class, 'index'])->name('maid.dashboard');
    Route::get('wallet', [MaidDashboardController::class, 'wallet'])->name('maid.wallet');
    Route::get('earnings', [MaidDashboardController::class, 'earnings'])->name('maid.earnings');
    Route::get('assignments', [MaidDashboardController::class, 'assignments'])->name('maid.assignments');
});

// Admin
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('wallets', [AdminDashboardController::class, 'wallets'])->name('admin.wallets');
    Route::get('assignments', [AdminDashboardController::class, 'assignments'])->name('admin.assignments');
    Route::get('salary', [AdminDashboardController::class, 'salary'])->name('admin.salary');
    Route::get('notifications', [AdminDashboardController::class, 'notifications'])->name('admin.notifications');
    Route::get('matching-queue', [AdminDashboardController::class, 'matchingQueue'])->name('admin.matching-queue');
});
```

---

## 5. Phase 4 — Employer UI

### 5.1 Wallet Page

- Current balance card (available + escrow)
- Deposit/top-up button with payment gateway integration (Paystack/Flutterwave)
- Transaction history table with filters (type, date range)
- Low balance warning banner

### 5.2 Assignments Page

- Pending acceptance cards with maid profile preview
- Accept/Reject actions with confirmation modals
- Active assignment status tracker
- Assignment history with status badges

### 5.3 Salary Page

- Upcoming salary calendar view
- Salary schedule details (amount, due date, period)
- Payment history table
- Wallet auto-funding toggle

---

## 6. Phase 5 — Maid UI

### 6.1 Wallet Page

- Current balance card
- Total earned / total withdrawn stats
- Withdrawal request form (bank details pre-filled)
- Pending withdrawal status

### 6.2 Earnings Dashboard

- Monthly earnings chart
- Salary payment timeline
- Upcoming payments list
- Bank details management

### 6.3 Assignments Page

- Current assignment status
- Assignment history

---

## 7. Phase 6 — Admin Dashboard

### 7.1 Overview Dashboard

- KPI cards: total employers, maids, active assignments, pending payments
- Charts: revenue, assignments over time, salary payments
- Alert list: overdue salaries, failed notifications, pending withdrawals

### 7.2 Wallet Management

- All employer wallets table with balances
- All maid wallets table with balances
- Approve/reject withdrawal requests
- Manual balance adjustment with audit log

### 7.3 Assignment Management

- Filterable assignments table (status, employer, maid, date)
- Force-assign maid to employer
- View AI match reasoning
- Trigger replacement search

### 7.4 Salary Oversight

- Overdue salary list with escalation buttons
- Salary schedule overview
- Manual mark-as-paid action
- Escalation level tracker

### 7.5 Notification Logs Viewer

- Full log table with filters (type, channel, status, date)
- View context JSON
- Retry failed notifications
- Delivery rate statistics

### 7.6 AI Queue Monitor

- Job queue table with status, priority, attempts
- View job payload and results
- Retry failed jobs
- Review pending AI matches

---

## 8. Phase 7 — SMS Integration (Production)

### 8.1 Configure SMS Provider

- Add Termii/Twilio/AfricaTalking credentials to `.env`
- Add to `config/services.php`:

```php
'termii' => [
    'api_key' => env('TERMII_API_KEY'),
    'sender_id' => env('TERMII_SENDER_ID', 'MaidsNG'),
],
```

### 8.2 Implement SMS Provider Interface

Create `app/Services/Sms/SmsProviderInterface.php` and implementations:

```
- TermiiProvider
- TwilioProvider
- AfricaTalkingProvider
```

Add provider switching logic to `NotificationService` or `SmartNotificationService`.

### 8.3 SMS Template System

- Create `config/sms_templates.php` with parameterized templates
- Templates for: assignment notification, salary reminder (3d/1d/due), payment received, insufficient balance, withdrawal processed

### 8.4 Delivery Tracking

- Update `NotificationLog` with actual provider response
- Implement webhook endpoint for delivery receipts
- Update `delivery_status` based on webhook callbacks

---

## 9. Phase 8 — Events, Listeners & Queue

### 9.1 Create Event Classes

| Event | Trigger | Listeners |
|---|---|---|
| `AssignmentAccepted` | Employer accepts assignment | `CreateSalarySchedule`, `NotifyMaid`, `UpdateMaidAvailability` |
| `AssignmentRejected` | Employer rejects assignment | `ProcessRefund`, `TriggerReplacementSearch`, `NotifyAdmin` |
| `AssignmentCompleted` | Assignment marked complete | `FinalizeSalary`, `UpdateMaidAvailability`, `NotifyBothParties` |
| `SalaryPaymentProcessed` | Salary payment completed | `NotifyMaid`, `NotifyEmployer`, `UpdateSchedule` |
| `SalaryOverdue` | Salary past due date | `EscalateToAdmin`, `NotifyEmployer` |
| `WithdrawalRequested` | Maid requests withdrawal | `NotifyAdmin` |
| `WithdrawalApproved` | Admin approves withdrawal | `ProcessBankTransfer`, `NotifyMaid` |
| `MatchingJobCompleted` | AI matching completes | `CreateAssignment`, `NotifyEmployer` |

### 9.2 EventServiceProvider Registration

Register all event-listener pairs in `app/Providers/EventServiceProvider.php` (or `app.php` for Laravel 11+).

### 9.3 Queue Configuration

- Set queue driver to `database` or `redis` in `.env`
- Run `php artisan queue:table && php artisan migrate` if using database driver
- Configure supervisor/systemd for queue worker

---

## 10. Phase 9 — Testing & QA

### 10.1 Feature Tests

| Test File | Coverage |
|---|---|
| `WalletTest.php` | Credit, debit, escrow hold/release, transfer to maid, insufficient balance |
| `AssignmentTest.php` | Create, accept, reject, complete, cancel, replacement search |
| `SalaryTest.php` | Create schedule, process reminders, auto-pay, manual pay, overdue escalation |
| `NotificationTest.php` | Schedule, send, work-hours enforcement, follow-up chains, retry |
| `MatchingQueueTest.php` | Job creation, processing, retry, completion, failure |

### 10.2 Unit Tests

| Test File | Coverage |
|---|---|
| `MaidWalletModelTest.php` | Scopes, calculated attributes, bank details validation |
| `EmployerWalletModelTest.php` | Scopes, balance calculations, escrow logic |
| `SalaryScheduleModelTest.php` | Due date calculation, reminder logic, period advancement |

### 10.3 Manual QA Checklist

- [ ] Register employer, deposit funds, view wallet
- [ ] Register maid, set bank details
- [ ] AI matches maid to employer, employer accepts → salary schedule created
- [ ] 3-day salary reminder sent to employer
- [ ] Employer wallet debited, maid wallet credited on due date
- [ ] Maid requests withdrawal, admin approves
- [ ] Overdue salary escalates to admin
- [ ] Notification logs show full context and delivery status
- [ ] Admin dashboard shows all KPIs correctly

---

## 11. Phase 10 — Production Deployment

### 11.1 Environment Setup

```env
# Queue
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# SMS
SMS_PROVIDER=termii
TERMII_API_KEY=your_key_here
TERMII_SENDER_ID=MaidsNG

# AI
AI_PROVIDER=openrouter
OPENROUTER_API_KEY=your_key_here
OPENROUTER_MODEL=google/gemini-2.0-flash-001

# App
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=daily
```

### 11.2 Cron Configuration

```cron
* * * * * cd /path/to/maids.ng && php artisan schedule:run >> /dev/null 2>&1
```

### 11.3 Queue Worker (Supervisor)

```ini
[program:maids-ng-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/maids.ng/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/maids.ng/storage/logs/worker.log
```

### 11.4 Deployment Steps

1. `git pull origin main`
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan migrate --force`
5. `php artisan db:seed --class=ProductionSeeder --force`
6. `php artisan config:cache`
7. `php artisan route:cache`
8. `php artisan view:cache`
9. `php artisan queue:restart`
10. Verify supervisor workers running

---

## 12. Acceptance Criteria

### Functional Requirements

- [ ] **Wallet System**: Employer can deposit, view balance, auto-pay salary. Maid can view balance, request withdrawal to bank.
- [ ] **Assignment Lifecycle**: AI creates match → employer receives notification → accepts/rejects → salary schedule auto-created on acceptance.
- [ ] **Salary Automation**: 3-day reminder → 1-day reminder → due-day auto-debit → maid credited → both notified. Overdue escalates to admin.
- [ ] **Smart Notifications**: All notifications respect work hours (8 AM - 8 PM user timezone). Follow-up chains use AI-generated context-aware messages. Notification logs capture full context.
- [ ] **AI Matching Queue**: Jobs are processed by cron, retry on failure, support priority, store AI reasoning, allow admin review.
- [ ] **Admin Dashboard**: Full visibility into wallets, assignments, salary, notifications, and AI queue. Can manually intervene (adjust balances, force assign, mark paid, retry notifications).

### Non-Functional Requirements

- [ ] All database transactions use `DB::beginTransaction()` / `commit()` / `rollBack()` for atomicity
- [ ] No N+1 queries in list views (use eager loading)
- [ ] All API endpoints return consistent JSON responses with proper HTTP status codes
- [ ] Sensitive data (bank details, API keys) never logged or exposed in responses
- [ ] Queue workers handle failures gracefully with retry logic
- [ ] Tests pass: `php artisan test` with > 80% coverage on new code

---

## Implementation Order & Dependencies

```
Phase 1 (DB) 
  → Phase 2 (Commands)
    → Phase 8 (Events/Listeners)
      → Phase 3 (Controllers & Routes)
        → Phase 9 (Tests)
          → Phase 4 (Employer UI) + Phase 5 (Maid UI) + Phase 6 (Admin UI) [parallel]
            → Phase 7 (SMS Prod)
              → Phase 10 (Deploy)
```

**Estimated effort**: ~40-60 hours for a senior Laravel developer.

---

## File Checklist — What Needs to Be Created

### Migrations
- [ ] `2026_04_27_000009_create_salary_reminders_table.php`
- [ ] Fix all 8 existing migrations for column name alignment

### Commands
- [ ] `app/Console/Commands/ProcessNotifications.php`
- [ ] `app/Console/Commands/ProcessMatchingQueue.php`
- [ ] `app/Console/Commands/ProcessAssignmentStatus.php`
- [ ] `app/Console/Commands/ProcessSalaryReminders.php`

### Controllers (9)
- [ ] `app/Http/Controllers/Api/Employer/EmployerWalletController.php`
- [ ] `app/Http/Controllers/Api/Maid/MaidWalletController.php`
- [ ] `app/Http/Controllers/Api/AssignmentController.php`
- [ ] `app/Http/Controllers/Api/SalaryController.php`
- [ ] `app/Http/Controllers/Api/Admin/AdminWalletController.php`
- [ ] `app/Http/Controllers/Api/Admin/AdminAssignmentController.php`
- [ ] `app/Http/Controllers/Api/Admin/AdminSalaryController.php`
- [ ] `app/Http/Controllers/Api/Admin/AdminNotificationController.php`
- [ ] `app/Http/Controllers/Api/Admin/AdminMatchingQueueController.php`

### Events (8)
- [ ] `app/Events/AssignmentAccepted.php`
- [ ] `app/Events/AssignmentRejected.php` (verify existence)
- [ ] `app/Events/AssignmentCompleted.php`
- [ ] `app/Events/SalaryPaymentProcessed.php`
- [ ] `app/Events/SalaryOverdue.php`
- [ ] `app/Events/WithdrawalRequested.php`
- [ ] `app/Events/WithdrawalApproved.php`
- [ ] `app/Events/MatchingJobCompleted.php`

### Listeners (12+)
- [ ] `app/Listeners/CreateSalarySchedule.php`
- [ ] `app/Listeners/NotifyMaid.php`
- [ ] `app/Listeners/UpdateMaidAvailability.php`
- [ ] `app/Listeners/ProcessRefund.php`
- [ ] `app/Listeners/TriggerReplacementSearch.php`
- [ ] `app/Listeners/NotifyAdmin.php`
- [ ] `app/Listeners/FinalizeSalary.php`
- [ ] `app/Listeners/NotifyBothParties.php`
- [ ] `app/Listeners/EscalateToAdmin.php`
- [ ] `app/Listeners/NotifyEmployer.php`
- [ ] `app/Listeners/ProcessBankTransfer.php`
- [ ] `app/Listeners/CreateAssignment.php`

### SMS
- [ ] `app/Services/Sms/SmsProviderInterface.php`
- [ ] `app/Services/Sms/TermiiProvider.php`
- [ ] `app/Services/Sms/TwilioProvider.php`
- [ ] `app/Services/Sms/AfricaTalkingProvider.php`
- [ ] `config/sms_templates.php`

### Seeds
- [ ] `database/seeders/AdminUserSeeder.php`
- [ ] `database/seeders/TestDataSeeder.php` (expand)
- [ ] `database/seeders/ProductionSeeder.php`

### Views (Blade)
- [ ] `resources/views/employer/dashboard.blade.php`
- [ ] `resources/views/employer/wallet.blade.php`
- [ ] `resources/views/employer/assignments.blade.php`
- [ ] `resources/views/employer/salary.blade.php`
- [ ] `resources/views/maid/dashboard.blade.php`
- [ ] `resources/views/maid/wallet.blade.php`
- [ ] `resources/views/maid/earnings.blade.php`
- [ ] `resources/views/maid/assignments.blade.php`
- [ ] `resources/views/admin/dashboard.blade.php`
- [ ] `resources/views/admin/wallets.blade.php`
- [ ] `resources/views/admin/assignments.blade.php`
- [ ] `resources/views/admin/salary.blade.php`
- [ ] `resources/views/admin/notifications.blade.php`
- [ ] `resources/views/admin/matching-queue.blade.php`

### Tests
- [ ] `tests/Feature/WalletTest.php`
- [ ] `tests/Feature/AssignmentTest.php`
- [ ] `tests/Feature/SalaryTest.php`
- [ ] `tests/Feature/NotificationTest.php`
- [ ] `tests/Feature/MatchingQueueTest.php`
- [ ] `tests/Unit/MaidWalletModelTest.php`
- [ ] `tests/Unit/EmployerWalletModelTest.php`
- [ ] `tests/Unit/SalaryScheduleModelTest.php`

**Total new files to create: ~60+**
