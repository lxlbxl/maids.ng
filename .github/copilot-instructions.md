# Maids.ng — GitHub Copilot Instructions

Read `AGENTS.md` for the full codebase map (directory structure, data model, controllers, services, conventions).

## Stack

- Laravel 12.x (PHP 8.2+) with MySQL
- React 18 + Inertia.js + Tailwind CSS + Vite
- Roles: admin, maid, employer (Spatie Permission)
- Paystack payments, multi-provider SMS, Laravel Echo + Pusher

## Key Conventions

- Database schema is in `database/database.sql` (authoritative)
- Business logic in `app/Services/`, controllers are thin
- Inertia SSR for web pages, JSON responses for API routes
- Tailwind CSS with shadcn-style components in `resources/js/Components/ui/`
- Ignore `legacy-v1/` and `maids-ng-v2/` — the root directory is the active v2 codebase
