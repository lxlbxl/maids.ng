<?php
/**
 * Unified Maids.ng Setup Script — Bulletproof Edition
 * Drop in public/, visit with ?token=setup-now
 * Handles: cache clear → migrations → seeders → SEO pages → final cache clear
 * DELETE THIS FILE AFTER FIRST USE.
 */

// Show ALL errors — no more silent 500s
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Security gate
$secret = $_GET['token'] ?? '';
if ($secret !== 'setup-now') {
    http_response_code(403);
    die('<pre>Forbidden. Use ?token=setup-now</pre>');
}

ob_start();

echo "=== Maids.ng Unified Setup ===\n\n";

// ──── STEP 0: NUKE CACHE FILES BEFORE BOOTING LARAVEL ────
// This is critical — stale route cache causes 500 during bootstrap
echo "[0/6] Deleting cache files (must happen before Laravel boots)...\n";

$baseDir = dirname(__DIR__);
$cacheDir = $baseDir . '/bootstrap/cache/';

if (!is_dir($cacheDir)) {
    echo "  WARNING: bootstrap/cache/ directory not found at: {$cacheDir}\n";
    echo "  Check your server structure.\n";
} else {
    $cacheFiles = [
        'routes-v7.php', 'routes-v84.php', 'routes-v82.php', 'routes-v83.php',
        'config.php', 'views.php', 'events.php', 'services.php',
        'packages.php',
    ];

    foreach ($cacheFiles as $f) {
        $path = $cacheDir . $f;
        if (file_exists($path)) {
            if (@unlink($path)) {
                echo "  Deleted: {$f}\n";
            } else {
                echo "  FAILED to delete: {$f} — check file permissions\n";
            }
        } else {
            echo "  Not found: {$f} (OK — already cleared)\n";
        }
    }

    // Also delete any other .php files in cache dir (catch future versions)
    foreach (glob($cacheDir . '*.php') as $file) {
        if (basename($file) !== '.gitignore' && !in_array(basename($file), $cacheFiles)) {
            @unlink($file);
            echo "  Deleted unexpected: " . basename($file) . "\n";
        }
    }
}

// Delete storage framework cache files too
$storageCache = $baseDir . '/storage/framework/cache/';
if (is_dir($storageCache)) {
    foreach (glob($storageCache . '*') as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    echo "  Cleared storage/framework/cache/\n";
}

$storageViews = $baseDir . '/storage/framework/views/';
if (is_dir($storageViews)) {
    foreach (glob($storageViews . '*') as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    echo "  Cleared storage/framework/views/\n";
}

echo "\n";

// ──── STEP 1: BOOT LARAVEL (cache is gone now) ────
echo "[1/6] Booting Laravel...\n";
try {
    require $baseDir . '/vendor/autoload.php';
    $app = require_once $baseDir . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    echo "  Laravel booted successfully.\n";
} catch (\Throwable $e) {
    echo "  FATAL: Laravel failed to bootstrap!\n";
    echo "  Error: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . " line " . $e->getLine() . "\n\n";
    echo "This usually means:\n";
    echo "  - vendor/ files were not uploaded (run: composer install)\n";
    echo "  - .env file is missing or has syntax errors\n";
    echo "  - bootstrap/app.php is missing or corrupted\n";
    die("\nFix the error above and try again.\n");
}

echo "\n";

// ──── STEP 2: Run artisan clear commands ────
echo "[2/6] Running artisan cache commands...\n";
$kernel->call('route:clear');
$kernel->call('config:clear');
$kernel->call('view:clear');
$kernel->call('cache:clear');
echo "  All caches cleared.\n\n";

// ──── STEP 3: Run migrations ────
echo "[3/6] Running migrations...\n";

// Create migration repository if it doesn't exist
try {
    $repo = $app->make('migration.repository');
    if (!$repo->repositoryExists()) {
        $repo->createRepository();
        echo "  Created migrations tracking table.\n";
    }
} catch (\Throwable $e) {
    echo "  Warning: " . $e->getMessage() . "\n";
}

// Run SEO migrations by checking table existence and running only if needed
$seoMigrations = [
    '2026_05_05_000001_create_seo_locations_table' => 'seo_locations',
    '2026_05_05_000002_create_seo_services_table' => 'seo_services',
    '2026_05_05_000003_create_seo_pages_table' => 'seo_pages',
    '2026_05_05_000004_create_seo_faqs_table' => 'seo_faqs',
];

$seoCount = 0;
foreach ($seoMigrations as $className => $tableName) {
    $tableExists = false;
    $alreadyRan = false;
    
    try {
        $tableExists = \Illuminate\Support\Facades\Schema::hasTable($tableName);
    } catch (\Throwable $e) {
        echo "  [check] {$tableName} — could not check ({$e->getMessage()})\n";
        $tableExists = true; // assume exists if we can't check
    }
    
    if ($tableExists) {
        echo "  [skip] {$className} — table '{$tableName}' already exists\n";
        // Mark as run in migration repository so Laravel doesn't try again
        try {
            $repo->log($className, 'default');
            echo "    Marked as migrated.\n";
        } catch (\Throwable $e) {
            // Already logged, that's fine
        }
        $seoCount++;
    } else {
        try {
            ob_start();
            $result = $kernel->call('migrate', ['--path' => 'database/migrations/' . $className . '.php', '--force' => true]);
            $out = trim(ob_get_clean());
            echo "  [OK] {$className} — {$tableName} created\n";
            $seoCount++;
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "  [skip] {$className} — {$tableName} already exists\n";
                try { $repo->log($className, 'default'); } catch (\Throwable $e2) {}
            } else {
                echo "  [FAIL] {$className} — " . $e->getMessage() . "\n";
            }
        }
    }
}

// Also try running full migrate to catch any other pending non-SEO migrations
try {
    ob_start();
    $kernel->call('migrate', ['--force' => true]);
    $fullOut = trim(ob_get_clean());
    if ($fullOut && strpos($fullOut, 'Nothing to migrate') === false) {
        echo "  Other migrations: {$fullOut}\n";
    }
} catch (\Throwable $e) {
    // Expected — some tables exist, ignore since we only care about SEO tables
}

echo "  SEO tables ready: {$seoCount}/4\n";
echo "\n";

// ──── STEP 4: Seed data ────
echo "[4/6] Seeding data...\n";
$seeders = ['AgentKnowledgeSeeder', 'SeoLocationSeeder', 'SeoServiceSeeder'];
foreach ($seeders as $seeder) {
    try {
        // Check if seeder class exists in autoloader
        if (class_exists("Database\\Seeders\\{$seeder}")) {
            $result = $kernel->call('db:seed', ['--class' => "Database\\Seeders\\{$seeder}", '--force' => true]);
            $output = trim(\Illuminate\Support\Facades\Artisan::output());
            echo "  {$seeder} => OK" . ($output ? " ({$output})" : "") . "\n";
        } else {
            echo "  {$seeder} => Class not found in autoloader\n";
            echo "    Run 'composer dump-autoload' on your server, OR\n";
            echo "    Ensure database/seeders/{$seeder}.php was uploaded\n";
        }
    } catch (\Throwable $e) {
        echo "  {$seeder} => Error: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// ──── STEP 5: Generate SEO page registry ────
echo "[5/6] Generating SEO page registry...\n";
try {
    if (class_exists('\\App\\Jobs\\GenerateSeoPageRegistry')) {
        $countBefore = \App\Models\SeoPage::count();
        \App\Jobs\GenerateSeoPageRegistry::dispatchSync();
        $countAfter = \App\Models\SeoPage::count();
        echo "  Pages generated: {$countAfter} (was {$countBefore})\n";
    } else {
        echo "  GenerateSeoPageRegistry class not found.\n";
        echo "  Ensure app/Jobs/GenerateSeoPageRegistry.php was uploaded.\n";
        echo "  Run 'composer dump-autoload' if needed.\n";
    }
} catch (\Throwable $e) {
    echo "  Error: " . $e->getMessage() . "\n";
    echo "  at " . $e->getFile() . ":" . $e->getLine() . "\n";
}
echo "\n";

// ──── STEP 6: Final cache clear ────
echo "[6/6] Final cache clear...\n";
$kernel->call('route:clear');
$kernel->call('config:clear');
$kernel->call('view:clear');
echo "  Done.\n\n";

// ──── SUMMARY ────
echo "=== Setup Complete ===\n\n";
echo "Verify these URLs work:\n";
echo "  https://maids.ng/                         → Home\n";
echo "  https://maids.ng/about                     → About page\n";
echo "  https://maids.ng/locations                 → SEO locations hub\n";
echo "  https://maids.ng/find/housekeeper-in-lekki-lagos/  → Money page\n";
echo "  https://maids.ng/robots.txt                → AI crawler rules\n";
echo "  https://maids.ng/sitemap.xml               → Sitemap index\n";
echo "  https://maids.ng/admin/seo/                → Admin dashboard (login required)\n\n";
echo "IMPORTANT: Delete public/setup.php after confirming everything works!\n";

$output = ob_get_clean();
echo "<pre>{$output}</pre>";
