<?php
/**
 * Force Vite Production Mode
 * Removes hot file and forces manifest-based asset loading
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Maids.ng - Force Vite Production</title>
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

        .error-box {
            background: #fee2e2;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ef4444;
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
    <h1>🔧 Force Vite Production Mode</h1>

    <?php
    $basePath = __DIR__;

    // Check for .vite/hot file
    echo "<h2>1. Checking for Vite Hot File</h2>";
    $hotFiles = [
        $basePath . '/.vite/hot',
        $basePath . '/public/build/hot',
        $basePath . '/storage/.vite/hot',
    ];

    $foundHot = false;
    foreach ($hotFiles as $hotFile) {
        if (file_exists($hotFile)) {
            echo "<p class='fail'>✗ Found hot file: {$hotFile}</p>";
            echo "<p>Content: <code>" . htmlspecialchars(file_get_contents($hotFile)) . "</code></p>";
            if (unlink($hotFile)) {
                echo "<p class='pass'>✓ Deleted hot file</p>";
            }
            $foundHot = true;
        }
    }
    if (!$foundHot) {
        echo "<p class='pass'>✓ No hot files found</p>";
    }

    // Check .env for VITE settings
    echo "<h2>2. Checking .env Vite Configuration</h2>";
    $envFile = $basePath . '/.env';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);

        // Check for VITE_ASSET_URL or similar
        if (preg_match('/VITE_.*=.*/', $envContent, $matches)) {
            echo "<p>Vite env vars found: <code>" . htmlspecialchars($matches[0]) . "</code></p>";
        } else {
            echo "<p>No VITE_* environment variables found (OK)</p>";
        }

        // Check APP_ENV
        if (preg_match('/APP_ENV=(.+)/', $envContent, $m)) {
            $appEnv = trim($m[1]);
            echo "<p>APP_ENV: <code>{$appEnv}</code></p>";
            if ($appEnv !== 'production') {
                echo "<p class='fail'>✗ APP_ENV should be 'production'</p>";
            }
        }
    }

    // Check manifest.json
    echo "<h2>3. Checking manifest.json</h2>";
    $manifestFile = $basePath . '/public/build/manifest.json';
    if (file_exists($manifestFile)) {
        $manifest = json_decode(file_get_contents($manifestFile), true);
        if ($manifest) {
            echo "<p class='pass'>✓ manifest.json exists and is valid JSON</p>";
            echo "<p>Entries: " . count($manifest) . "</p>";

            // Show app.jsx entry
            if (isset($manifest['resources/js/app.jsx'])) {
                $app = $manifest['resources/js/app.jsx'];
                echo "<p>App JS: <code>" . ($app['file'] ?? 'N/A') . "</code></p>";
                if (isset($app['css'])) {
                    foreach ($app['css'] as $css) {
                        echo "<p>App CSS: <code>{$css}</code></p>";
                    }
                }
            }
        } else {
            echo "<p class='fail'>✗ manifest.json is invalid JSON</p>";
        }
    } else {
        echo "<p class='fail'>✗ manifest.json not found!</p>";
    }

    // Check if asset files actually exist
    echo "<h2>4. Checking Asset Files Exist</h2>";
    if (file_exists($manifestFile)) {
        $manifest = json_decode(file_get_contents($manifestFile), true);
        if (isset($manifest['resources/js/app.jsx'])) {
            $app = $manifest['resources/js/app.jsx'];
            $jsFile = $basePath . '/public/build/' . ($app['file'] ?? '');
            $cssFile = $basePath . '/public/build/' . ($app['css'][0] ?? '');

            echo "<p>JS file: <code>" . ($app['file'] ?? 'N/A') . "</code> - ";
            echo file_exists($jsFile) ? "<span class='pass'>✓ Exists</span>" : "<span class='fail'>✗ Missing</span>";
            echo "</p>";

            if (isset($app['css'][0])) {
                echo "<p>CSS file: <code>{$app['css'][0]}</code> - ";
                echo file_exists($cssFile) ? "<span class='pass'>✓ Exists</span>" : "<span class='fail'>✗ Missing</span>";
                echo "</p>";
            }
        }
    }

    // Force production mode by testing Vite class directly
    echo "<h2>5. Testing Vite Class</h2>";
    try {
        require $basePath . '/vendor/autoload.php';
        $app = require $basePath . '/bootstrap/app.php';

        // Force production mode
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['APP_ENV'] = 'production';

        $vite = $app->make(\Illuminate\Foundation\Vite::class);

        // Check the internal state
        $ref = new ReflectionClass($vite);

        // Get the manifest path
        $manifestPathProp = $ref->getProperty('manifestPath');
        $manifestPathProp->setAccessible(true);
        $manifestPath = $manifestPathProp->getValue($vite);
        echo "<p>Manifest path: <code>" . ($manifestPath ?? 'null') . "</code></p>";

        // Get the build directory
        $buildDirProp = $ref->getProperty('buildDirectory');
        $buildDirProp->setAccessible(true);
        $buildDir = $buildDirProp->getValue($vite);
        echo "<p>Build directory: <code>" . ($buildDir ?? 'null') . "</code></p>";

        // Check if hot file detection is active
        $hotFileProp = $ref->getProperty('hotFile');
        $hotFileProp->setAccessible(true);
        $hotFile = $hotFileProp->getValue($vite);
        echo "<p>Hot file path: <code>" . ($hotFile ?? 'null') . "</code></p>";

        if ($hotFile && file_exists($hotFile)) {
            echo "<p class='fail'>✗ Hot file exists! This forces dev mode.</p>";
            unlink($hotFile);
            echo "<p class='pass'>✓ Deleted hot file</p>";
        }

        // Now test the output
        $tags = $vite->__invoke(['resources/js/app.jsx']);

        if (strpos($tags, '5173') !== false || strpos($tags, '[::1]') !== false || strpos($tags, 'localhost') !== false) {
            echo "<p class='fail'>✗ Vite is STILL using dev server!</p>";
            echo "<p>Output: <pre>" . htmlspecialchars($tags) . "</pre></p>";
            echo "<div class='error-box'>";
            echo "<h3>Root Cause Found</h3>";
            echo "<p>The Vite class is detecting dev mode. This is likely because:</p>";
            echo "<ol>";
            echo "<li>The <code>laravel-vite-plugin</code> is configured with <code>refresh: true</code></li>";
            echo "<li>Or the manifest is not being found at the expected path</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<p class='pass'>✓ Vite is using production assets!</p>";
            echo "<p>Output: <pre>" . htmlspecialchars($tags) . "</pre></p>";
        }
    } catch (Exception $e) {
        echo "<p class='fail'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<div class='success-box' style='margin-top:30px;'>";
    echo "<h3>✅ Fix Applied</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Clear browser cache:</strong> <code>Ctrl+Shift+R</code></li>";
    echo "<li><strong>Visit homepage:</strong> <a href='/'>https://maids.ng/</a></li>";
    echo "</ol>";
    echo "</div>";
    ?>
</body>

</html>