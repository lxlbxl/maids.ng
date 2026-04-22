<?php
/**
 * Router script for PHP built-in development server.
 * Usage: php -S localhost:8000 -t public public/router.php
 *
 * This serves static files directly and routes everything else to index.php.
 */

$url = parse_url($_SERVER['REQUEST_URI']);
$file = __DIR__ . $url['path'];

// Serve static files (CSS, JS, images, HTML) directly
if (is_file($file)) {
    // Let the built-in server handle known static file types
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $staticExtensions = ['html', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map', 'webp'];
    if (in_array(strtolower($ext), $staticExtensions, true)) {
        return false; // Let PHP built-in server handle it
    }
}

// For directories, check for index.html
if (is_dir($file)) {
    $indexFile = rtrim($file, '/') . '/index.html';
    if (is_file($indexFile)) {
        return false; // Let PHP built-in server serve index.html
    }
}

// Route everything else through the Slim application
require __DIR__ . '/index.php';
