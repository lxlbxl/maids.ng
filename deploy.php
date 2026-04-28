<?php

/**
 * Maids.ng Shared Hosting Deployment Utility
 * This script runs independently of the Laravel routing engine to fix 404/500 errors
 * after uploading new code.
 */

// 1. Boot Laravel (Check if we are in public/ or root directory)
if (file_exists(__DIR__.'/vendor/autoload.php')) {
    $basePath = __DIR__;
} elseif (file_exists(__DIR__.'/../vendor/autoload.php')) {
    $basePath = __DIR__.'/..';
} else {
    die("Error: Could not find vendor/autoload.php. Please ensure deploy.php is in the project root or public directory.");
}

require $basePath.'/vendor/autoload.php';
$app = require_once $basePath.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

header('Content-Type: text/html');
echo "<h2>Maids.ng Deployment Utility</h2>";

try {
    echo "<li>Clearing route cache... ";
    $kernel->call('route:clear');
    echo "Done.</li>";

    echo "<li>Clearing config cache... ";
    $kernel->call('config:clear');
    echo "Done.</li>";

    echo "<li>Clearing application cache... ";
    $kernel->call('cache:clear');
    echo "Done.</li>";

    echo "<li>Clearing view cache... ";
    $kernel->call('view:clear');
    echo "Done.</li>";

    echo "<li>Clearing PHP OPcache... ";
    if (function_exists('opcache_reset')) {
        opcache_reset();
        echo "Done.</li>";
    } else {
        echo "Not available.</li>";
    }

    echo "<li>Running optimize:clear... ";
    $kernel->call('optimize:clear');
    echo "Done.</li>";

    echo "<li>Ensuring storage symlink... ";
    try {
        $kernel->call('storage:link');
        echo "Done.</li>";
    } catch (Exception $linkError) {
        echo "Skipped (already exists or permission denied).</li>";
    }

    echo "<li>Ensuring migration table exists... ";
    $kernel->call('migrate:install');
    echo "Done.</li>";

    echo "<li>Running migrations... ";
    try {
        $kernel->call('migrate', ['--force' => true]);
        echo "Done.</li>";
    } catch (Exception $migError) {
        if (strpos($migError->getMessage(), 'already exists') !== false) {
            echo "<span style='color: orange;'>Skipped (some tables already exist). Attempting to run only new migrations...</span> ";
            // Try to run only the specific new migration if the general one fails
            try {
                $kernel->call('migrate', [
                    '--force' => true,
                    '--path' => 'database/migrations/2026_04_26_085150_create_standalone_verifications_table.php'
                ]);
                echo "Latest migration forced successfully.</li>";
            } catch (Exception $e2) {
                echo "Failed: " . $e2->getMessage() . "</li>";
            }
        } else {
            throw $migError;
        }
    }

    echo "<br><h3 style='color: green;'>Success! Your application is now updated.</h3>";
    echo "<p>You can now visit <a href='/'>your homepage</a> or <a href='/admin/settings'>Admin Settings</a>.</p>";
    echo "<p><b>Security Note:</b> Please delete this file (<code>deploy.php</code>) from your server now.</p>";

} catch (Exception $e) {
    echo "<br><h3 style='color: red;'>Error during deployment:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
