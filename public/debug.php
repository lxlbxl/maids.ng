<?php
/**
 * Maids.ng Server Diagnostic Tool
 * Upload this file to your server (either in your public_html folder or public folder)
 * and access it via https://maids.ng/debug.php
 */

// Enable raw PHP error reporting to catch errors before Laravel boots
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Maids.ng Server Diagnostic Tool</h1>";

// --- 1. PHP Environment ---
echo "<h2>1. PHP Environment</h2>";
echo "PHP Version: <strong>" . phpversion() . "</strong><br>";
if (version_compare(phpversion(), '8.2.0', '<')) {
    echo "<span style='color:red;'>ERROR: Laravel 10/11+ requires PHP 8.2+. Please upgrade your PHP version on the cPanel/hosting panel.</span><br>";
} else {
    echo "<span style='color:green;'>PHP Version OK.</span><br>";
}

// Find base path (works whether debug.php is in public/ or root/)
$basePath = __DIR__;
if (file_exists(__DIR__ . '/../artisan')) {
    $basePath = realpath(__DIR__ . '/../');
} elseif (file_exists(__DIR__ . '/artisan')) {
    $basePath = __DIR__;
} else {
    echo "<span style='color:orange;'>WARNING: Could not find 'artisan' file. Path might be incorrect. Current dir: $basePath</span><br>";
}
echo "Detected Base Path: <strong>" . $basePath . "</strong><br>";

// --- 2. Check Paths & Dependencies ---
echo "<h2>2. Directory & Dependency Checks</h2>";
$autoload = $basePath . '/vendor/autoload.php';
if (file_exists($autoload)) {
    echo "<span style='color:green;'>vendor/autoload.php found.</span><br>";
} else {
    echo "<span style='color:red;'>ERROR: vendor/autoload.php NOT FOUND. Did you upload the 'vendor' folder completely?</span><br>";
}

$storagePath = $basePath . '/storage';
if (is_dir($storagePath)) {
    if (is_writable($storagePath)) {
        echo "<span style='color:green;'>storage/ directory is writable.</span><br>";
    } else {
        echo "<span style='color:red;'>ERROR: storage/ directory is NOT writable. Run: chmod -R 775 storage</span><br>";
    }
} else {
    echo "<span style='color:red;'>ERROR: storage/ directory not found.</span><br>";
}

$bootstrapCache = $basePath . '/bootstrap/cache';
if (is_dir($bootstrapCache)) {
    if (is_writable($bootstrapCache)) {
        echo "<span style='color:green;'>bootstrap/cache/ directory is writable.</span><br>";
    } else {
        echo "<span style='color:red;'>ERROR: bootstrap/cache/ directory is NOT writable.</span><br>";
    }
}

// --- 3. Check .env ---
echo "<h2>3. Environment File (.env)</h2>";
$envFile = $basePath . '/.env';
if (file_exists($envFile)) {
    echo "<span style='color:green;'>.env file exists.</span><br>";
    $envContent = file_get_contents($envFile);
    if (strpos($envContent, 'APP_KEY=base64') === false) {
        echo "<span style='color:orange;'>WARNING: APP_KEY might be empty or invalid.</span><br>";
    }
} else {
    echo "<span style='color:red;'>ERROR: .env file NOT FOUND. Did you copy .env.example to .env?</span><br>";
}

// --- 4. Read Laravel Logs ---
echo "<h2>4. Recent Laravel Logs</h2>";
$logFile = $basePath . '/storage/logs/laravel.log';
if (file_exists($logFile)) {
    echo "Log file found. Last 20 lines:<br>";
    echo "<pre style='background:#f4f4f4; padding:10px; overflow:auto; max-height:400px; border:1px solid #ccc;'>";
    
    $fileContent = file($logFile);
    if ($fileContent && count($fileContent) > 0) {
        $lastLines = array_slice($fileContent, -20);
        echo htmlspecialchars(implode("", $lastLines));
    } else {
        echo "Log file is empty.";
    }
    echo "</pre>";
} else {
    echo "<span style='color:orange;'>laravel.log not found (might be empty or missing).</span><br>";
}

// --- 5. Application Boot Test ---
echo "<h2>5. Application Boot Test</h2>";
try {
    if (file_exists($autoload)) {
        require $autoload;
        echo "<span style='color:green;'>Autoloader loaded successfully.</span><br>";
        
        $appFile = $basePath . '/bootstrap/app.php';
        if (file_exists($appFile)) {
            $app = require_once $appFile;
            echo "<span style='color:green;'>bootstrap/app.php loaded successfully.</span><br>";
            
            // Try to make kernel
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            echo "<span style='color:green;'>Laravel Http Kernel resolved successfully. No syntax errors in core files!</span><br>";
        } else {
             echo "<span style='color:red;'>ERROR: bootstrap/app.php not found.</span><br>";
        }
    }
} catch (\Throwable $e) {
    echo "<span style='color:red;'><strong>FATAL EXCEPTION DURING BOOT:</strong></span><br>";
    echo "<strong>Message:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "<strong>Trace:</strong><br><pre style='background:#ffeeee; padding:10px; border:1px solid #f00;'>" . $e->getTraceAsString() . "</pre>";
}

// --- 6. Server Error Log ---
echo "<h2>6. Server error_log</h2>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    echo "Server error_log found at: $errorLog<br>";
    $errLines = file($errorLog);
    if ($errLines) {
        $lastErrLines = array_slice($errLines, -15);
        echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc;'>".htmlspecialchars(implode("", $lastErrLines))."</pre>";
    }
} else {
    echo "<span style='color:gray;'>Server error_log not accessible or not configured by PHP. (Check cPanel Error Logs section manually if needed)</span><br>";
}
?>
