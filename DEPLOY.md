# Maids.ng SEO Engine — Deployment Instructions

## The Problem

Your server has a **stale route cache** file. This means Laravel is serving old cached routes and ignoring all new route files (`routes/seo.php` and the updated `routes/web.php`).

This is why:
- `/run-setup` worked (it was in the old cache)
- `/deploy-all` gave 404 (it was inside auth middleware in the old cache)
- `/locations`, `/about`, etc. gave 404 (they are new routes not in the cache)

---

## Step 1: Clear the Route Cache via File Manager

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
