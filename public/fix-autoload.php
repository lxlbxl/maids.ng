<?php
/**
 * Deep Laravel Diagnostic - Maids.ng
 * Attempts to fully boot Laravel and identifies the exact error.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "=== Maids.ng Deep Diagnostic ===\n\n";

$basePath = realpath(dirname(__FILE__) . '/..');
echo "Laravel root: $basePath\n\n";

// Step 1: Check for recent error logs
echo "1. Recent server error_log entries...\n";
$errorLogPaths = [
    $basePath . '/error_log',
    $basePath . '/public/error_log',
    dirname($basePath) . '/error_log',
];

foreach ($errorLogPaths as $logPath) {
    if (file_exists($logPath)) {
        echo "   Found: $logPath\n";
        $lines = file($logPath);
        $recent = array_slice($lines, -10);
        foreach ($recent as $line) {
            echo "   " . trim($line) . "\n";
        }
        echo "\n";
    }
}

// Step 2: Check Laravel log
echo "2. Recent Laravel log entries...\n";
$laravelLog = $basePath . '/storage/logs/laravel.log';
if (file_exists($laravelLog)) {
    $content = file_get_contents($laravelLog);
    $size = filesize($laravelLog);
    echo "   Log size: " . number_format($size) . " bytes\n";
    
    // Get last 3000 chars
    $tail = substr($content, -3000);
    // Find the start of a log entry
    $pos = strpos($tail, '[20');
    if ($pos !== false) {
        $tail = substr($tail, $pos);
    }
    echo "   --- Last entries ---\n";
    echo "   " . str_replace("\n", "\n   ", $tail) . "\n";
    echo "   --- End ---\n\n";
} else {
    echo "   No laravel.log found\n\n";
}

// Step 3: Try to fully boot Laravel and handle a request
echo "3. Attempting full Laravel boot...\n";
try {
    require_once $basePath . '/vendor/autoload.php';
    echo "   ✅ Autoloader loaded\n";
    
    $app = require_once $basePath . '/bootstrap/app.php';
    echo "   ✅ Application bootstrapped\n";
    
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    echo "   ✅ HTTP Kernel resolved\n";
    
    // Try to handle a fake request to the home page
    echo "   Attempting to handle a test request to '/'...\n";
    
    $request = Illuminate\Http\Request::create('/', 'GET');
    
    try {
        $response = $kernel->handle($request);
        $statusCode = $response->getStatusCode();
        echo "   Response status: $statusCode\n";
        
        if ($statusCode >= 500) {
            echo "   ❌ Server error! Response content (first 2000 chars):\n";
            $content = $response->getContent();
            echo "   " . str_replace("\n", "\n   ", substr($content, 0, 2000)) . "\n";
        } elseif ($statusCode >= 400) {
            echo "   ⚠️ Client error ($statusCode)\n";
            $content = $response->getContent();
            echo "   " . str_replace("\n", "\n   ", substr($content, 0, 1000)) . "\n";
        } else {
            echo "   ✅ Request handled successfully (status $statusCode)\n";
        }
    } catch (\Throwable $e) {
        echo "   ❌ Exception during request handling:\n";
        echo "   Class: " . get_class($e) . "\n";
        echo "   Message: " . $e->getMessage() . "\n";
        echo "   File: " . $e->getFile() . "\n";
        echo "   Line: " . $e->getLine() . "\n";
        echo "   Trace (first 15 frames):\n";
        $trace = $e->getTrace();
        foreach (array_slice($trace, 0, 15) as $i => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? '?';
            $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
            echo "     #$i $file:$line $func()\n";
        }
        
        // Check for previous exception
        if ($prev = $e->getPrevious()) {
            echo "\n   Previous exception:\n";
            echo "   Class: " . get_class($prev) . "\n";
            echo "   Message: " . $prev->getMessage() . "\n";
            echo "   File: " . $prev->getFile() . "\n";
            echo "   Line: " . $prev->getLine() . "\n";
        }
    }
    
} catch (\Throwable $e) {
    echo "   ❌ Fatal error during boot:\n";
    echo "   Class: " . get_class($e) . "\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    echo "   Trace (first 15 frames):\n";
    $trace = $e->getTrace();
    foreach (array_slice($trace, 0, 15) as $i => $frame) {
        $file = $frame['file'] ?? 'unknown';
        $line = $frame['line'] ?? '?';
        $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
        echo "     #$i $file:$line $func()\n";
    }
    
    if ($prev = $e->getPrevious()) {
        echo "\n   Previous exception:\n";
        echo "   Class: " . get_class($prev) . "\n";
        echo "   Message: " . $prev->getMessage() . "\n";
        echo "   File: " . $prev->getFile() . "\n";
        echo "   Line: " . $prev->getLine() . "\n";
    }
}

// Step 4: Check for common issues
echo "\n4. Additional checks...\n";

// Check .htaccess
$htaccess = $basePath . '/public/.htaccess';
if (file_exists($htaccess)) {
    echo "   ✅ public/.htaccess exists (" . filesize($htaccess) . " bytes)\n";
} else {
    echo "   ❌ public/.htaccess MISSING\n";
}

// Check index.php
$indexPhp = $basePath . '/public/index.php';
if (file_exists($indexPhp)) {
    echo "   ✅ public/index.php exists\n";
    $indexContent = file_get_contents($indexPhp);
    echo "   Contents:\n";
    echo "   " . str_replace("\n", "\n   ", $indexContent) . "\n";
} else {
    echo "   ❌ public/index.php MISSING\n";
}

// Check storage symlink
$storageLink = $basePath . '/public/storage';
if (is_link($storageLink) || is_dir($storageLink)) {
    echo "   ✅ public/storage link exists\n";
} else {
    echo "   ⚠️ public/storage link missing (not critical)\n";
}

// Check key files exist
$keyFiles = [
    'app/Http/Controllers/Controller.php',
    'app/Providers/AppServiceProvider.php',
    'routes/web.php',
    'config/app.php',
    'config/database.php',
];

echo "\n   Key files check:\n";
foreach ($keyFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file MISSING\n";
    }
}

// Check for SettingsServiceProvider mentioned in logs
echo "\n   Service providers check:\n";
$providerFiles = glob($basePath . '/app/Providers/*.php');
foreach ($providerFiles as $pf) {
    echo "   - " . basename($pf) . " (" . filesize($pf) . " bytes)\n";
}

echo "\n=== Done ===\n";
echo "⚠️  DELETE this file after debugging!\n";
echo "</pre>";
