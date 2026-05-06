<?php
/**
 * Emergency Route Cache Clearer — Standalone (no Laravel boot needed)
 * Drop in public/ and visit with ?token=setup-now
 * DELETE THIS FILE AFTER USE.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$secret = $_GET['token'] ?? '';
if ($secret !== 'setup-now') {
    http_response_code(403);
    die('<pre>Forbidden. Use ?token=setup-now</pre>');
}

$baseDir = dirname(__DIR__);
$cacheDir = $baseDir . '/bootstrap/cache/';
$deleted = [];
$errors = [];

if (!is_dir($cacheDir)) {
    die("<pre>ERROR: bootstrap/cache/ directory not found at:\n{$cacheDir}\n\nCheck your server's file structure.</pre>");
}

$cacheFiles = [
    'routes-v7.php', 'routes-v84.php', 'routes-v82.php', 'routes-v83.php',
    'config.php', 'views.php', 'events.php', 'services.php', 'packages.php',
];

foreach ($cacheFiles as $f) {
    $path = $cacheDir . $f;
    if (file_exists($path)) {
        if (@unlink($path)) {
            $deleted[] = $f;
        } else {
            $errors[] = "Could not delete {$f} — check file permissions (needs write access)";
        }
    }
}

// Also delete any other .php cache files
foreach (glob($cacheDir . '*.php') as $file) {
    $name = basename($file);
    if ($name !== '.gitignore' && !in_array($name, $cacheFiles)) {
        if (@unlink($file)) {
            $deleted[] = $name . " (unexpected)";
        }
    }
}

// Clear storage cache too
foreach (['storage/framework/cache', 'storage/framework/views', 'storage/framework/sessions'] as $sub) {
    $dir = $baseDir . '/' . $sub . '/';
    if (is_dir($dir)) {
        foreach (glob($dir . '*') as $file) {
            if (is_file($file)) @unlink($file);
        }
        $deleted[] = "{$sub}/*";
    }
}

echo "<pre>=== Route Cache Cleared ===\n\n";
echo "Deleted files:\n";
foreach ($deleted as $f) {
    echo "  ✓ {$f}\n";
}

if (!empty($errors)) {
    echo "\nERRORS:\n";
    foreach ($errors as $e) {
        echo "  ✗ {$e}\n";
    }
}

echo "\n─────────────────────────────────────\n";
echo "Next step: visit https://maids.ng/setup.php?token=setup-now\n";
echo "─────────────────────────────────────\n\n";

if (empty($deleted)) {
    echo "Note: No cache files were found. This means either:\n";
    echo "  1. Caches were already cleared (good!)\n";
    echo "  2. Your cache directory is in a different location\n";
    echo "  3. Your host uses a different path structure\n\n";
    echo "Try visiting /setup.php?token=setup-now anyway — it will show\n";
    echo "detailed error output if something is wrong.\n";
}

echo "\nIMPORTANT: Delete this file after use.\n</pre>";
