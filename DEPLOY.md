# Maids.ng — Deployment Guide

## Quick Deploy: Control Room (Single Script)

Upload `public/deploy-control-room.php` to your server, then visit:

```
https://maids.ng/deploy-control-room.php?token=setup-now
```

This runs: migrations → seeder → cache clear → verification. Output shown inline.

**Then upload** the frontend build:
```bash
npm run build              # run locally
# Upload public/build/ to server
rm public/deploy-control-room.php # delete after done
```

The Control Room is at `/admin/control-room` (admin login required).

---

## Full File Upload List — Control Room

If the single-script deploy fails or you prefer manual upload, here is every new file:

### Database
```
database/migrations/2026_05_02_000001_create_agent_events_table.php
database/migrations/2026_05_02_000002_create_human_task_queue_table.php
database/migrations/2026_05_02_000003_create_agent_overrides_table.php
database/migrations/2026_05_07_000001_create_agent_campaigns_social_tables.php
database/seeders/AgentOverrideSeeder.php
```

### Models (NEW)
```
app/Models/AgentEvent.php
app/Models/AgentOverride.php
app/Models/HumanTask.php
app/Models/AgentCampaign.php
app/Models/AgentOutreachLog.php
app/Models/SocialPost.php
app/Models/SocialTheme.php
app/Models/SocialPostMedia.php
```

### Services (NEW + UPDATED)
```
app/Services/AgentEventLogger.php            ← NEW
app/Services/ActionDispatcher.php            ← NEW
app/Services/AgentOverrideService.php        ← NEW
app/Services/HumanExecutionService.php       ← NEW
app/Services/ChannelSender.php               ← NEW
app/Services/AgentService.php                ← UPDATED (LogsEvents trait + bridge)
app/Services/WalletService.php               ← UPDATED (transferToMaid)
app/Services/AssignmentService.php           ← UPDATED (resolveDispute)
app/Services/Ai/OpenAiDriver.php             ← UPDATED (.env fallback)
app/Services/Ai/OpenRouterDriver.php         ← UPDATED (.env fallback)
```

### Agents (NEW + UPDATED)
```
app/Agents/Concerns/LogsEvents.php           ← NEW
app/Services/Agents/MarketerAgent.php        ← NEW
app/Services/Agents/SeoContentAgent.php      ← NEW
app/Services/Agents/OutreachEngine.php       ← NEW
app/Services/Agents/ScoutAgent.php           ← UPDATED (findMatches + logging)
app/Services/Agents/AmbassadorAgent.php      ← UPDATED (LogsEvents + model fix)
app/Services/Agents/GatekeeperAgent.php      ← UPDATED ($agentName)
app/Services/Agents/TreasurerAgent.php       ← UPDATED ($agentName)
app/Services/Agents/ConciergeAgent.php       ← UPDATED ($agentName)
app/Services/Agents/RefereeAgent.php         ← UPDATED ($agentName)
app/Services/Agents/SentinelAgent.php        ← UPDATED ($agentName)
```

### Controllers (NEW)
```
app/Http/Controllers/Admin/AgentControlRoom/ControlRoomController.php
app/Http/Controllers/Admin/AgentControlRoom/EventStreamController.php
```

### Commands & Jobs (NEW)
```
app/Console/Commands/EmergencyStopAllAgents.php
app/Console/Commands/ResumeAllAgents.php
app/Jobs/CheckAiProviderHealth.php
```

### Mail (NEW)
```
app/Mail/AiProviderDownAlert.php
resources/views/emails/ai-provider-down.blade.php
```

### Routes (UPDATED)
```
routes/control_room.php                     ← NEW
routes/web.php                              ← UPDATED (includes control_room.php)
routes/console.php                          ← UPDATED (daily spend reset + AI health)
```

### Middleware & Providers (UPDATED)
```
app/Http/Middleware/HandleInertiaRequests.php  ← UPDATED (controlRoom shared data)
app/Providers/AppServiceProvider.php           ← UPDATED (6 new singletons)
```

### Frontend (NEW)
```
resources/js/Pages/Admin/ControlRoom/Index.jsx
resources/js/Pages/Admin/ControlRoom/Components/AgentControlBar.jsx
resources/js/Pages/Admin/ControlRoom/Panels/LiveFeedPanel.jsx
resources/js/Pages/Admin/ControlRoom/Panels/QueueHealthPanel.jsx
resources/js/Pages/Admin/ControlRoom/Panels/HumanTaskPanel.jsx
resources/js/Pages/Admin/ControlRoom/Panels/CampaignCommandPanel.jsx
resources/js/Pages/Admin/ControlRoom/Panels/TokenCostPanel.jsx
resources/js/Pages/Admin/ControlRoom/HumanTask/Show.jsx
```

### Frontend (UPDATED)
```
resources/js/Layouts/AdminLayout.jsx          ← UPDATED (Control Room nav + badge)
```

### Models (UPDATED)
```
app/Models/AgentChannelIdentity.php           ← UPDATED (outreachLogs relation)
```

---

## Manual Deploy Steps (No Script)

### 1. Clear route cache
```
# Via cPanel File Manager: delete everything in bootstrap/cache/ except .gitignore
# Or visit: https://maids.ng/clear-route-cache.php?token=setup-now
```

### 2. Upload ALL files listed above
Overwrite existing files. Create directories as needed:
- `app/Agents/Concerns/`
- `app/Http/Controllers/Admin/AgentControlRoom/`
- `resources/js/Pages/Admin/ControlRoom/Components/`
- `resources/js/Pages/Admin/ControlRoom/Panels/`
- `resources/js/Pages/Admin/ControlRoom/HumanTask/`

### 3. Run deployment
```
https://maids.ng/deploy-control-room.php?token=setup-now
```

### 4. Build and upload frontend
```bash
npm install && npm run build   # run locally
# Upload public/build/ folder to server
```

### 5. Verify
Visit `/admin/control-room` as admin. You should see:
- Agent Control Bar with 10 color-coded agent pills
- Live Agent Feed panel (empty until agents run)
- Queue Health panel with per-agent stats
- Human Task Queue panel
- Token Cost Tracker
- Campaign Command panel

### 6. Clean up
```
rm public/deploy-control-room.php
```

---

## Post-Deploy: Scheduler Setup

Add these entries to your hosting cron (every 5 min recommended):

```
# Reset agent daily spend at midnight
0 0 * * * cd /path/to/project && php artisan schedule:run

# Or set up a single cron entry if using Laravel scheduler:
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler handles: daily spend reset, AI provider health checks, notification processing, matching queue, salary reminders.

---

## One-Line SSH Deploy (if available)

```bash
git pull origin v2-refactor && composer install --no-dev -o && php artisan migrate --force && php artisan db:seed --class=AgentOverrideSeeder --force && php artisan cache:clear && php artisan config:clear && php artisan route:clear
```

---

## Command Reference

| Command | Purpose |
|---------|---------|
| `php artisan agents:emergency-stop "reason"` | Kill-switch all agents immediately |
| `php artisan agents:resume-all` | Restore all agents to active |
| `php artisan schedule:list` | Verify scheduler is configured |

---

## Troubleshooting

### 404 on /admin/control-room
- Route cache not cleared — delete `bootstrap/cache/routes-*.php`
- Confirm `require __DIR__ . '/control_room.php';` is at the bottom of `routes/web.php`
- Confirm `app/Http/Controllers/Admin/AgentControlRoom/ControlRoomController.php` exists

### Blank page or JS error
- Run `npm run build` locally and upload `public/build/`
- Check browser console for missing JS chunks

### SSE stream not connecting
- Shared hosting may buffer output — add `X-Accel-Buffering: no` header (already in controller)
- If using Nginx, add `proxy_buffering off;` to location block
- Try accessing `/admin/control-room/stream` directly to test

### "No override record" warnings in logs
- Run the seeder: `php artisan db:seed --class=AgentOverrideSeeder --force`
- Or visit the deploy script URL

### Migration fails
- Check `database/database.sqlite` permissions if using SQLite
- For MySQL, verify credentials in `.env`

---

---

## Legacy: SEO Engine Deployment

*The sections below cover the SEO engine deployment. These remain unchanged.*

### SEO Engine — Step 1: Clear the Route Cache via File Manager

### Method A — cPanel File Manager (Recommended)

1. Log into your hosting cPanel
2. Open **File Manager**
3. Navigate to: `public_html/bootstrap/cache/` (or just `bootstrap/cache/`)
4. **DELETE** any file matching:
   - `routes-v7.php`
   - `routes-v84.php`
   - `routes-v82.php`
   - `config.php`
   - `views.php`
   - `events.php`
   - `services.php`
5. **DO NOT DELETE** `.gitignore` or `packages.php`

### Method B — Via FTP (FileZilla, WinSCP, etc.)

1. Connect to your server
2. Navigate to `bootstrap/cache/`
3. Delete the same files listed in Method A

### Method C — Via the clear-route-cache.php script (No Laravel boot needed)

Upload `public/clear-route-cache.php` to your server, then visit:

```
https://maids.ng/clear-route-cache.php?token=setup-now
```

This script deletes cache files directly without booting Laravel. After it shows "Deleted files", proceed to Step 3.

**If this gives 500:** Your `public/` directory path may be wrong, or the file wasn't uploaded. Use your hosting file manager to confirm `public/clear-route-cache.php` exists on the server.

---

## Step 2: Upload ALL New and Changed Files

Upload these files to your server (overwrite existing where applicable):

### Backend (PHP)
```
app/Models/SeoLocation.php
app/Models/SeoService.php
app/Models/SeoPage.php
app/Models/SeoFaq.php
app/Services/SeoUrlBuilder.php
app/Services/SeoSchemaBuilder.php
app/Services/SeoInternalLinker.php
app/Services/SeoContentGenerator.php
app/Jobs/GenerateSeoPageRegistry.php
app/Jobs/RefreshSeoContent.php
app/Http/Controllers/Seo/SeoLocationController.php
app/Http/Controllers/Seo/SeoServiceController.php
app/Http/Controllers/Seo/SeoGuideController.php
app/Http/Controllers/Seo/SeoFaqController.php
app/Http/Controllers/Seo/SeoSitemapController.php
app/Http/Controllers/Seo/SeoStatsController.php
app/Http/Controllers/Admin/Seo/SeoDashboardController.php
```

### Database
```
database/migrations/2026_05_05_000001_create_seo_locations_table.php
database/migrations/2026_05_05_000002_create_seo_services_table.php
database/migrations/2026_05_05_000003_create_seo_pages_table.php
database/migrations/2026_05_05_000004_create_seo_faqs_table.php
database/seeders/SeoLocationSeeder.php
database/seeders/SeoServiceSeeder.php
```

### Routes (CRITICAL)
```
routes/seo.php          ← NEW FILE
routes/web.php          ← UPDATED (deploy endpoints + SEO routes)
routes/console.php      ← UPDATED (SEO schedule)
```

### Blade Templates
```
resources/views/seo/layouts/master.blade.php
resources/views/seo/partials/header.blade.php
resources/views/seo/partials/footer.blade.php
resources/views/seo/partials/breadcrumbs.blade.php
resources/views/seo/service-area.blade.php
resources/views/seo/service-hub.blade.php
resources/views/seo/locations/hub.blade.php
resources/views/seo/locations/city.blade.php
resources/views/seo/locations/area.blade.php
resources/views/seo/guide-hire.blade.php
resources/views/seo/guide-price.blade.php
resources/views/seo/guide-salary.blade.php
resources/views/seo/guide-evergreen.blade.php
resources/views/seo/faq-hub.blade.php
resources/views/seo/faq-show.blade.php
```

### Frontend (React/Inertia)
```
resources/js/Pages/About.jsx
resources/js/Pages/Contact.jsx
resources/js/Pages/Blog.jsx
resources/js/Pages/Terms.jsx
resources/js/Pages/Privacy.jsx
resources/js/Pages/Admin/Seo/Dashboard.jsx
resources/js/Pages/Admin/Seo/Pages.jsx
resources/js/Pages/Admin/Seo/PageDetail.jsx
resources/js/Pages/Admin/Seo/Locations.jsx
resources/js/Pages/Admin/Seo/Services.jsx
```

### CSS
```
public/css/seo.css
```

### Updated Existing Files
```
resources/views/app.blade.php    ← Added organization entity schema
```

### Setup Scripts (One-Time Use Only)
```
public/setup.php                ← Full setup (migrate + seed + generate)
public/clear-route-cache.php    ← Emergency cache clearer
```

### Build Frontend (run locally, then upload)
```bash
npm run build
```
Upload the new `public/build/` folder to server.

---

## Step 3: Run Full Setup

After clearing the route cache (Step 1) AND uploading all files (Step 2):

Visit in your browser:
```
https://maids.ng/setup.php?token=setup-now
```

This will run in order:
1. Delete all cache files (before booting Laravel)
2. Boot Laravel and clear artisan caches
3. Run SEO migrations — checks each table first, creates only if missing
4. Seed locations (36 locations) and services (8 services)
5. Generate SEO page registry (~428 pages)
6. Final cache clear

You will see a `<pre>` block showing output from each step.

**Note:** The migration step now checks if tables exist before creating them. 
If your app already has a `users` table and other existing tables, they are safely skipped.
Only the 4 new SEO tables (`seo_locations`, `seo_services`, `seo_pages`, `seo_faqs`) will be created.

---

## Step 4: Verify Everything Works

Visit these URLs:

| URL | Expected Result |
|---|---|
| `https://maids.ng/` | Home page (unchanged) |
| `https://maids.ng/about` | About Us page |
| `https://maids.ng/contact` | Contact page |
| `https://maids.ng/blog` | Blog home |
| `https://maids.ng/locations` | SEO locations hub |
| `https://maids.ng/find/housekeeper-in-lekki-lagos/` | Service × Area money page |
| `https://maids.ng/robots.txt` | robots.txt with AI crawler rules |
| `https://maids.ng/sitemap.xml` | Sitemap index |
| `https://maids.ng/api/seo/stats` | JSON with platform stats |
| `https://maids.ng/admin/seo/` | Admin SEO dashboard (must be logged in as admin) |

---

## Step 5: Clean Up — DELETE Setup Files

After confirming everything works, **delete these files** from your server:

```
public/setup.php
public/clear-route-cache.php
```

These are security risks if left on the server.

---

## Step 6: Set a Real DEPLOY_SECRET (Optional but Recommended)

In your `.env` file on the server, add:

```
DEPLOY_SECRET=your-own-secret-string-here
```

Then the temporary token `setup-now` will no longer work. Only your secret will.

---

## Troubleshooting

### Getting 500 error on setup.php?

This means Laravel can't boot, usually because:
1. **Route cache still exists** — Clear it first via cPanel File Manager (Method A above), or visit `/clear-route-cache.php?token=setup-now`
2. **Files not uploaded** — Make sure ALL files from Step 2 are on the server, especially `routes/web.php` and `routes/seo.php`
3. **Autoloader outdated** — Run `composer dump-autoload` via SSH if available
4. **.env missing** — Check that `.env` exists in the project root on the server

The `setup.php` script now shows detailed error messages. If you see a specific PHP error in the output, fix that error and try again.

### Still getting 404 on SEO pages?

1. The route cache was not cleared. Go back to Step 1.
2. Check that `routes/seo.php` exists on the server
3. Check that `require __DIR__ . '/seo.php';` is at the bottom of `routes/web.php`
4. Visit `/clear-route-cache.php?token=setup-now` to force-clear, then retry setup.php

### Migration error "table already exists"?

This is fine — the migrations are safe to re-run. If you get errors, the tables were already created from a previous attempt.

### Seeder error "class not found"?

Run `composer dump-autoload` on the server if you have SSH access. If not, the `setup.php` script has fallback logic that runs seeders inline.

### Page registry shows 0 pages?

The `seo_locations` and `seo_services` tables need data first. Make sure the seeders ran successfully. You can re-run `/setup.php?token=setup-now` — it's safe to run multiple times.
