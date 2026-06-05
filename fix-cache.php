<?php
/**
 * Clear All Laravel Caches - Fix for 500 Error
 * Upload this to your hosting root and visit it in browser
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Maids.ng - Clear Cache Fix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }

        h1 {
            color: #333;
        }

        .pass {
            color: green;
        }

        .fail {
            color: red;
        }

        .info {
            background: #f0f7ff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        pre {
            background: #f5f5f5;
            padding: 15px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f5f5f5;
        }
    </style>
</head>

<body>
    <h1>🗑️ Maids.ng Cache Clearer</h1>

    <?php
    $basePath = __DIR__;
    $results = [];

    // Step 1: Delete bootstrap cache files
    echo "<h2>Step 1: Clearing Bootstrap Cache</h2>";
    $cacheFiles = [
        'bootstrap/cache/config.php',
        'bootstrap/cache/routes-v7.php',
        'bootstrap/cache/packages.php',
        'bootstrap/cache/services.php',
        'bootstrap/cache/events.php',
    ];

    foreach ($cacheFiles as $file) {
        $path = $basePath . '/' . $file;
        if (file_exists($path)) {
            if (unlink($path)) {
                echo "<p class='pass'>✓ Deleted: {$file}</p>";
            } else {
                echo "<p class='fail'>✗ Could not delete: {$file} (check permissions)</p>";
            }
        } else {
            echo "<p>{$file} - not found (OK)</p>";
        }
    }

    // Step 2: Clear storage framework cache
    echo "<h2>Step 2: Clearing Storage Cache</h2>";
    $storageDirs = [
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
    ];

    foreach ($storageDirs as $dir) {
        $path = $basePath . '/' . $dir;
        if (is_dir($path)) {
            $files = glob($path . '/*');
            $count = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $count++;
                }
            }
            echo "<p class='pass'>✓ Cleared {$count} files from {$dir}/</p>";
        } else {
            echo "<p>{$dir}/ - not found</p>";
        }
    }

    // Step 3: Ensure directories exist with correct permissions
    echo "<h2>Step 3: Creating Required Directories</h2>";
    $requiredDirs = [
        'storage/app',
        'storage/app/public',
        'storage/framework/cache',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'bootstrap/cache',
    ];

    foreach ($requiredDirs as $dir) {
        $path = $basePath . '/' . $dir;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            echo "<p class='pass'>✓ Created: {$dir}/</p>";
        } else {
            chmod($path, 0755);
            echo "<p>✓ Exists: {$dir}/</p>";
        }
    }

    // Step 4: Check composer autoload
    echo "<h2>Step 4: Checking Composer Autoload</h2>";
    $autoloadFile = $basePath . '/vendor/autoload.php';
    if (file_exists($autoloadFile)) {
        echo "<p class='pass'>✓ vendor/autoload.php exists</p>";

        // Try to require it to check for errors
        try {
            require $autoloadFile;
            echo "<p class='pass'>✓ Autoload loads successfully</p>";
        } catch (Exception $e) {
            echo "<p class='fail'>✗ Autoload error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>Fix:</strong> Re-upload the vendor/ folder or run <code>composer install</code> on your server.</p>";
        }
    } else {
        echo "<p class='fail'>✗ vendor/autoload.php NOT FOUND!</p>";
        echo "<p><strong>This is the problem!</strong> You need to upload the vendor/ folder.</p>";
        echo "<div class='info'><h3>How to fix:</h3><ol>";
        echo "<li>On your local machine, run: <code>composer install --no-dev</code></li>";
        echo "<li>Upload the entire <code>vendor/</code> folder to your hosting</li>";
        echo "</ol></div>";
    }

    // Step 5: Check .env
    echo "<h2>Step 5: Checking .env File</h2>";
    $envFile = $basePath . '/.env';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        $checks = [
            'APP_KEY' => strpos($envContent, 'APP_KEY=base64:') !== false,
            'APP_ENV' => strpos($envContent, 'APP_ENV=') !== false,
            'DB_CONNECTION' => strpos($envContent, 'DB_CONNECTION=') !== false,
            'DB_DATABASE' => strpos($envContent, 'DB_DATABASE=') !== false,
            'DB_USERNAME' => strpos($envContent, 'DB_USERNAME=') !== false,
        ];

        foreach ($checks as $name => $ok) {
            echo "<p class='" . ($ok ? 'pass' : 'fail') . "'>" . ($ok ? '✓' : '✗') . " {$name}</p>";
        }
    } else {
        echo "<p class='fail'>✗ .env file not found!</p>";
    }

    // Step 6: Test Laravel boot
    echo "<h2>Step 6: Testing Laravel Bootstrap</h2>";
    try {
        if (file_exists($autoloadFile)) {
            require $autoloadFile;
            $app = require $basePath . '/bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            echo "<p class='pass'>✓ Laravel bootstrap successful!</p>";
            echo "<p class='pass'>✓ Your application should now work.</p>";
            echo "<p><a href='/' style='color:blue;font-size:18px;'>→ Visit Homepage</a></p>";
        }
    } catch (Exception $e) {
        echo "<p class='fail'>✗ Bootstrap error: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }

    echo "<h2>Summary</h2>";
    echo "<div class='info'>";
    echo "<h3>If you still get a 500 error:</h3>";
    echo "<ol>";
    echo "<li><strong>Missing vendor/ folder:</strong> Run <code>composer install --no-dev</code> locally and upload vendor/</li>";
    echo "<li><strong>PHP version:</strong> Ensure PHP 8.2+ is selected in cPanel</li>";
    echo "<li><strong>PHP extensions:</strong> Enable all required extensions in cPanel</li>";
    echo "<li><strong>Check error log:</strong> Visit <code>/check.php</code> to see the latest errors</li>";
    echo "</ol>";
    echo "</div>";

    echo "<p style='margin-top:30px;padding-top:20px;border-top:1px solid #ddd;'>";
    echo "<small>Cache clearer ran: " . date('Y-m-d H:i:s') . "</small>";
    echo "</p>";
    ?>
</body>

</html>