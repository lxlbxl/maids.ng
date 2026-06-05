<?php
/**
 * Aggressive Cache Clearer - Clears ALL Laravel caches
 * Upload to hosting root and visit in browser
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Maids.ng - Clear All Caches</title>
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

        .success-box {
            background: #d1fae5;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
        }

        pre {
            background: #f5f5f5;
            padding: 15px;
            overflow-x: auto;
            border-radius: 5px;
        }

        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>

<body>
    <h1>🗑️ Clear ALL Laravel Caches</h1>

    <?php
    $basePath = __DIR__;
    $deleted = 0;
    $errors = 0;

    // 1. Delete ALL compiled views
    echo "<h2>1. Compiled Views Cache</h2>";
    $viewsDir = $basePath . '/storage/framework/views';
    if (is_dir($viewsDir)) {
        $files = glob($viewsDir . '/*.php');
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            } else {
                $errors++;
                echo "<p class='fail'>✗ Could not delete: " . basename($file) . "</p>";
            }
        }
        echo "<p class='pass'>✓ Deleted {$deleted} compiled view files</p>";
    } else {
        echo "<p>Views directory not found</p>";
    }

    // 2. Delete config cache
    echo "<h2>2. Config Cache</h2>";
    $configCache = $basePath . '/bootstrap/cache/config.php';
    if (file_exists($configCache)) {
        if (unlink($configCache)) {
            echo "<p class='pass'>✓ Deleted config.php cache</p>";
        }
    } else {
        echo "<p>Config cache not found (OK)</p>";
    }

    // 3. Delete routes cache
    echo "<h2>3. Routes Cache</h2>";
    $routesCache = $basePath . '/bootstrap/cache/routes-v7.php';
    if (file_exists($routesCache)) {
        if (unlink($routesCache)) {
            echo "<p class='pass'>✓ Deleted routes cache</p>";
        }
    } else {
        echo "<p>Routes cache not found (OK)</p>";
    }

    // 4. Delete services cache
    echo "<h2>4. Services Cache</h2>";
    $servicesCache = $basePath . '/bootstrap/cache/services.php';
    if (file_exists($servicesCache)) {
        if (unlink($servicesCache)) {
            echo "<p class='pass'>✓ Deleted services.php cache</p>";
        }
    } else {
        echo "<p>Services cache not found (OK)</p>";
    }

    // 5. Delete packages cache
    echo "<h2>5. Packages Cache</h2>";
    $packagesCache = $basePath . '/bootstrap/cache/packages.php';
    if (file_exists($packagesCache)) {
        if (unlink($packagesCache)) {
            echo "<p class='pass'>✓ Deleted packages.php cache</p>";
        }
    } else {
        echo "<p>Packages cache not found (OK)</p>";
    }

    // 6. Delete events cache
    echo "<h2>6. Events Cache</h2>";
    $eventsCache = $basePath . '/bootstrap/cache/events.php';
    if (file_exists($eventsCache)) {
        if (unlink($eventsCache)) {
            echo "<p class='pass'>✓ Deleted events.php cache</p>";
        }
    } else {
        echo "<p>Events cache not found (OK)</p>";
    }

    // 7. Clear storage cache
    echo "<h2>7. Storage Cache</h2>";
    $cacheDir = $basePath . '/storage/framework/cache';
    if (is_dir($cacheDir)) {
        clearDir($cacheDir);
        echo "<p class='pass'>✓ Cleared storage cache</p>";
    }

    // 8. Clear sessions
    echo "<h2>8. Sessions</h2>";
    $sessionsDir = $basePath . '/storage/framework/sessions';
    if (is_dir($sessionsDir)) {
        clearDir($sessionsDir);
        echo "<p class='pass'>✓ Cleared sessions</p>";
    }

    // 9. Clear logs
    echo "<h2>9. Logs</h2>";
    $logsDir = $basePath . '/storage/logs';
    if (is_dir($logsDir)) {
        clearDir($logsDir);
        echo "<p class='pass'>✓ Cleared logs</p>";
    }

    // 10. Verify Vite manifest is readable
    echo "<h2>10. Vite Manifest Check</h2>";
    $manifestFile = $basePath . '/public/build/manifest.json';
    if (file_exists($manifestFile)) {
        $manifest = json_decode(file_get_contents($manifestFile), true);
        if ($manifest && isset($manifest['resources/js/app.jsx'])) {
            $appEntry = $manifest['resources/js/app.jsx'];
            echo "<p class='pass'>✓ manifest.json is valid and readable</p>";
            echo "<p>App entry: <code>" . ($appEntry['file'] ?? 'N/A') . "</code></p>";
            if (isset($appEntry['css'])) {
                foreach ($appEntry['css'] as $css) {
                    echo "<p>CSS: <code>{$css}</code></p>";
                }
            }
        }
    }

    // 11. Test that @vite will use production assets
    echo "<h2>11. Testing Vite Production Mode</h2>";
    try {
        require $basePath . '/vendor/autoload.php';
        $app = require $basePath . '/bootstrap/app.php';

        // Check if Vite manifest is being read correctly
        $vite = $app->make(\Illuminate\Foundation\Vite::class);
        $tags = $vite->__invoke(['resources/js/app.jsx']);

        if (strpos($tags, 'localhost:5173') !== false) {
            echo "<p class='fail'>✗ Vite is still using dev server!</p>";
            echo "<p>Output: <code>" . htmlspecialchars($tags) . "</code></p>";
        } else {
            echo "<p class='pass'>✓ Vite is using production assets!</p>";
            echo "<p>Output: <code>" . htmlspecialchars($tags) . "</code></p>";
        }
    } catch (Exception $e) {
        echo "<p class='fail'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<div class='success-box' style='margin-top:30px;'>";
    echo "<h3>✅ All caches cleared!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Hard refresh your browser:</strong> <code>Ctrl+Shift+R</code> (or <code>Cmd+Shift+R</code> on Mac)</li>";
    echo "<li><strong>Visit your homepage:</strong> <a href='/'>https://maids.ng/</a></li>";
    echo "<li><strong>If still showing localhost:5173:</strong> The compiled views were cached before. The first page load after clearing will recompile them correctly.</li>";
    echo "</ol>";
    echo "</div>";

    function clearDir($dir)
    {
        if (!is_dir($dir))
            return;
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                clearDir($file);
                rmdir($file);
            }
        }
    }
    ?>
</body>

</html>