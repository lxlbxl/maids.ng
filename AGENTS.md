# Maids.ng — Codebase Map for AI Coding Assistants

> **Purpose:** This file exists so AI assistants (Cline, Antigravity, Opencode, OpenClaude, Claude Code, etc.) can orient quickly without reading the entire codebase every session.

## Project Overview

- **App:** Maids.ng — Nigerian domestic help marketplace connecting employers with verified maids
- **Backend:** Laravel 12.x (PHP 8.2+), MySQL
- **Frontend:** React 18 + Inertia.js + Tailwind CSS, Vite bundler
- **Real-time:** Laravel Echo + Pusher
- **Payments:** Paystack (matching fees, guarantee match)
- **SMS:** Multi-provider (Twilio, Termii, Africa's Talking, Log)
- **Auth:** Laravel session + Spatie Laravel Permission (roles: `admin`, `maid`, `employer`)

## Directory Structure

```
app/
  Console/Commands/     — Artisan commands (notifications, salary, matching queue, assignments)
  Events/               — Laravel events (Assignment*, Booking*, Salary*, Matching*, Wallet*)
  Http/Controllers/
    Admin/              — Admin panel controllers (dashboard, verifications, disputes, audit, settings)
    Api/                — REST API controllers (Admin, Assignment, Auth, Booking, Employer, Maid, Matching, Notification, Payment, Report, Salary, Wallet)
    Auth/               — Web auth (login, register, forgot/reset password)
    Employer/           — Employer web (dashboard)
    Maid/               — Maid web (dashboard)
    (root level)        — Booking, Matching, Payment, Review, Notification, Profile, Settings, Export
  Models/               — Eloquent models (see Data Model below)
  Services/
    Agents/             — Multi-agent system (Concierge, Gatekeeper, Referee, Scout, Sentinel, Treasurer)
    Ai/                 — AI providers (AiService, AiProvider, OpenAiDriver, OpenRouterDriver)
    Sms/                — SMS providers (Twilio, Termii, AfricasTalking, Log)
    (root level)        — Assignment, Matching, Notification, Salary, Wallet services
resources/
  js/
    Components/         — Shared React UI components (Toast, ui/* shadcn-style components)
    Layouts/            — Inertia layouts (AdminLayout, EmployerLayout, MaidLayout)
    Pages/              — Inertia page components grouped by role (Admin/, Auth/, Employer/, Maid/)
    lib/utils.js        — Shared utilities
    echo.js             — Laravel Echo / Pusher config
  css/                  — Tailwind CSS
routes/
  web.php               — Main web routes (Inertia SSR pages + form submissions)
  api.php               — API routes (if exists)
  console.php           — Scheduled commands
database/
  database.sql          — Full MySQL schema dump (authoritative table definitions)
  migrations/           — Laravel migrations (may be partial — trust database.sql for current schema)
  database.sqlite       — SQLite DB used in dev/testing
public/                 — Web root, Vite build output in public/build/
```

## Data Model (from database.sql)

| Table | Purpose |
|---|---|
| `users` | Auth users with role (admin/maid/employer), status, location |
| `maid_profiles` | Extended maid data — bio, skills, experience, location, verification, bank, availability |
| `employer_preferences` | Employer needs — help_types, schedule, urgency, budget, location, matching_status, guarantee_deadline |
| `bookings` | Employer-maid engagements — status, payment, dates, salary, schedule_type |
| `reviews` | Employer reviews of maids — rating, comment, flagged |
| `notifications` | In-app notifications per user — type, title, message, data (JSON), read_at |
| `wallet_transactions` | Financial ledger — user, type, amount, status, reference |
| `employer_wallet` | Employer wallet balances |
| `maid_wallet` | Maid wallet balances |
| `matching_fee_payments` | Paystack payments for matching/guarantee — reference, gateway, status |
| `salary_payments` | Maid salary tracking — status, amount, schedule, reminders |
| `salary_reminders` | Salary payment reminders |
| `salary_schedules` | Scheduled salary payments |
| `disputes` | Booking disputes — type, status, resolution |
| `support_tickets` | Escalation/support tickets |
| `ai_matching_queue` | AI-powered matching queue entries |
| `notifications_log` | Notification delivery log |
| `standalone_verifications` | Public verification service requests |
| `settings` | Key-value app settings (matching_fee_amount, deploy_secret, etc.) |
| `permissions`, `roles`, `role_has_permissions`, `model_has_roles`, `model_has_permissions` | Spatie Permission tables |
| `agent_activity_logs` | Multi-agent system activity log |
| `maid_assignments` | Maid-employer assignment tracking with accept/reject |

## Key Controllers & Responsibilities

| Controller | What it does |
|---|---|
| `MatchingController` | Find matches, create account from onboarding, guarantee match |
| `MaidSearchController` | Public maid browsing/searching/featured |
| `BookingController` | Booking lifecycle (create, manage) |
| `PaymentController` | Paystack integration, matching fee payments |
| `WalletController` | Wallet balances, transactions, withdrawals |
| `SalaryController` | Salary payments, reminders, scheduling |
| `NotificationController` | User notifications (list, mark read) |
| `ReviewController` | Create/manage reviews |
| `MatchingFeeController` | Matching fee management |
| `Admin/*` | Admin panel — maids, users, bookings, disputes, financials, verifications, settings, audit, notifications, reviews, escalations, assignments |
| `Api/*` | REST API endpoints for each domain |

## Key Services

| Service | Purpose |
|---|---|
| `MatchingService` | Core matching algorithm — scores maids against employer preferences |
| `AssignmentService` | Manages maid-employer assignment lifecycle |
| `WalletService` | Wallet transactions, balances, withdrawals |
| `SalaryManagementService` | Salary scheduling, payments, reminders |
| `NotificationService` / `SmartNotificationService` | In-app + SMS notification dispatch |
| `AiService` | AI provider abstraction for matching/scoring |
| `AgentService` | Orchestrates the multi-agent system |
| Agents: `Scout` (search), `Gatekeeper` (verify), `Sentinel` (monitor), `Concierge` (assist), `Referee` (dispute), `Treasurer` (finance) | |

## Frontend Pages (Inertia)

| Role | Pages |
|---|---|
| Public | Welcome, OnboardingQuiz, Maid Search (listing + detail), VerificationService |
| Employer | Dashboard, Bookings, Profile, Reviews, Wallet, Payments, AssignmentAcceptance, MatchingPayment, GuaranteeMatchPayment |
| Maid | Dashboard, Bookings, Profile, Earnings, Reviews |
| Admin | Dashboard, Maids, Users, Bookings, Disputes, Escalations, Financials, Earnings, Verifications, Reviews, Notifications, Settings, AuditLog, MatchingQueue, SalaryManagement |

## Conventions

- **Controllers** return Inertia responses for web routes, JSON for API routes
- **API** endpoints under `app/Http/Controllers/Api/`
- **Roles:** `admin`, `maid`, `employer` (Spatate Permission)
- **Events** fire for async actions (notifications, salary, matching)
- **Services** contain business logic; controllers are thin
- **React** components use Tailwind CSS + shadcn-style ui components in `resources/js/Components/ui/`
- **Vite** for asset building (`npm run dev` / `npm run build`)

## Important Notes

- `database/database.sql` is the authoritative schema — migrations may be partial
- `install.php` is the deployment installer
- `legacy-v1/` contains old v1 code — do not reference for new development
- `maids-ng-v2/` may contain old v2 source — the root directory IS the v2 codebase
- `.htaccess` configures URL rewriting
- Debug/fix scripts (`fix-vite.php`, `diagnose-settings.php`, etc.) are one-off tools — not part of the app

## graphify

This project has a graphify knowledge graph at graphify-out/.

Rules:
- Before answering architecture or codebase questions, read graphify-out/GRAPH_REPORT.md for god nodes and community structure
- If graphify-out/wiki/index.md exists, navigate it instead of reading raw files
- For cross-module "how does X relate to Y" questions, prefer `graphify query "<question>"`, `graphify path "<A>" "<B>"`, or `graphify explain "<concept>"` over grep — these traverse the graph's EXTRACTED + INFERRED edges instead of scanning files
- After modifying code files in this session, run `graphify update .` to keep the graph current (AST-only, no API cost)
