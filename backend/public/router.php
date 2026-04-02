<?php
/**
 * Router for Maids.ng Frontend
 * Handles:
 * - Static file serving
 * - Trailing slash normalization
 * - Dynamic routes (helper profiles)
 * - SPA fallback for client-side routing
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$baseDir = __DIR__;
$fullPath = realpath($baseDir . $requestUri);

// 1. If the request maps to an existing file, serve it directly
if ($fullPath && is_file($fullPath)) {
    return false; // Let web server handle (or we can readfile)
}

// Normalize request: trim trailing slashes (except root)
$normalized = rtrim($requestUri, '/');
if ($normalized === '') {
    $normalized = '/';
}

// 2. Handle dynamic helper profile: /household/helpers/{id}.html
if (preg_match('#^/household/helpers/(\d+)\.html$#', $requestUri, $matches)) {
    $profileFile = $baseDir . '/household/helpers/profile.html';
    if (file_exists($profileFile)) {
        // Make the helper ID available to JS via a query param or just let JS parse path
        // We'll serve the file as is; JS will parse window.location.pathname
        readfile($profileFile);
        exit;
    }
}

// 3. Handle directory index files with/without trailing slash
// If request ends with slash or is a known directory, try to serve index.html in that directory
$segments = explode('/', trim($requestUri, '/'));
$firstSegment = $segments[0] ?? '';

$knownDirs = ['household', 'helper', 'agency', 'admin', 'common'];
if (in_array($firstSegment, $knownDirs) && $requestUri !== '/' && substr($requestUri, -1) === '/') {
    $indexFile = $baseDir . '/' . $firstSegment . '/index.html';
    if (file_exists($indexFile)) {
        // Redirect to canonical URL without trailing slash (or keep with slash? We'll keep as requested)
        // For consistency, we'll serve the index file directly without redirect
        readfile($indexFile);
        exit;
    }
}

// 4. Handle specific known routes without trailing slash (serve index.html in that directory)
// E.g., /household → household/index.html
$routeMap = [
    '/household' => '/household/index.html',
    '/helper' => '/helper/dashboard.html',
    '/agency' => '/agency/dashboard.html',
    '/admin' => '/admin/dashboard.html',
];
if (isset($routeMap[$normalized])) {
    $file = $baseDir . $routeMap[$normalized];
    if (file_exists($file)) {
        readfile($file);
        exit;
    }
}

// 5. SPA fallback: For any other route that doesn't match a file, serve root index.html
// This allows HTML5 pushState to work (client-side router)
// But we must ensure we're not serving API requests
if (strpos($requestUri, '/api/') === 0) {
    // Let the API handler (backend) deal with this
    return false;
}

$indexFile = $baseDir . '/index.html';
if (file_exists($indexFile)) {
    readfile($indexFile);
    exit;
}

// 6. Not found
header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
echo '404 Not Found';
?>
