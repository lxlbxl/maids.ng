<?php
/**
 * Final Vite Production Fix
 * Directly tests and fixes Vite asset loading
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Maids.ng - Final Vite Fix</title>
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
    <h1>🔧 Final Vite Production Fix</h1>

    <?php
    $basePath = __DIR__;

    // 1. Direct manifest test
    echo "<h2>1. Direct Manifest Test</h2>";
    $manifestFile = $basePath . '/public/build/manifest.json';
    if (file_exists($manifestFile)) {
        $manifest = json_decode(file_get_contents($manifestFile), true);
        echo "<p class='pass'>✓ manifest.json found at: {$manifestFile}</p>";
        echo "<p>Full path: <code>" . realpath($manifestFile) . "</code></p>";

        if (isset($manifest['resources/js/app.jsx'])) {
            $app = $manifest['resources/js/app.jsx'];
            echo "<p>App JS: <code>{$app['file']}</code></p>";
            if (isset($app['css'])) {
                foreach ($app['css'] as $css) {
                    echo "<p>App CSS: <code>{$css}</code></p>";
                }
            }
        }
    } else {
        echo "<p class='fail'>✗ manifest.json NOT found</p>";
    }

    // 2. Check public_path() resolution
    echo "<h2>2. Laravel Path Resolution</h2>";
    try {
        require $basePath . '/vendor/autoload.php';
        $app = require $basePath . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

        $publicPath = $app->publicPath();
        echo "<p>public_path(): <code>{$publicPath}</code></p>";

        $expectedManifest = $publicPath . '/build/manifest.json';
        echo "<p>Expected manifest path: <code>{$expectedManifest}</code></p>";
        echo "<p>File exists: " . (file_exists($expectedManifest) ? '<span class="pass">✓ Yes</span>' : '<span class="fail">✗ No</span>') . "</p>";

        // Check if paths match
        $realManifest = realpath($manifestFile);
        $realExpected = realpath($expectedManifest);
        echo "<p>Real manifest path: <code>{$realManifest}</code></p>";
        echo "<p>Real expected path: <code>{$realExpected}</code></p>";
        echo "<p>Paths match: " . ($realManifest === $realExpected ? '<span class="pass">✓ Yes</span>' : '<span class="fail">✗ No</span>') . "</p>";

    } catch (Exception $e) {
        echo "<p class='fail'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // 3. Check for any .vite or hot files anywhere
    echo "<h2>3. Scanning for Hot Files</h2>";
    $scanDirs = [
        $basePath,
        $basePath . '/public',
        $basePath . '/public/build',
        $basePath . '/storage',
    ];

    $foundHot = false;
    foreach ($scanDirs as $dir) {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->getFilename() === 'hot') {
                    echo "<p class='fail'>✗ Found hot file: " . $file->getPathname() . "</p>";
                    echo "<p>Content: <code>" . htmlspecialchars(file_get_contents($file->getPathname())) . "</code></p>";
                    unlink($file->getPathname());
                    echo "<p class='pass'>✓ Deleted</p>";
                    $foundHot = true;
                }
            }
        }
    }
    if (!$foundHot) {
        echo "<p class='pass'>✓ No hot files found anywhere</p>";
    }

    // 4. Check .env for any dev settings
    echo "<h2>4. .env Analysis</h2>";
    $envFile = $basePath . '/.env';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        $lines = explode("\n", $envContent);
        echo "<table style='width:100%;border-collapse:collapse;'>";
        echo "<tr><th style='padding:5px;border-bottom:1px solid #ddd;'>Key</th><th style='padding:5px;border-bottom:1px solid #ddd;'>Value</th></tr>";
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0)
                continue;
            if (preg_match('/^([A-Z_]+)=(.*)$/', $line, $m)) {
                $key = $m[1];
                $val = $m[2];
                $highlight = '';
                if (in_array(strtolower($key), ['app_env', 'app_debug', 'app_url'])) {
                    $highlight = 'style="background:#fff3cd;"';
                }
                echo "<tr {$highlight}><td style='padding:5px;border-bottom:1px solid #ddd;'><code>{$key}</code></td><td style='padding:5px;border-bottom:1px solid #ddd;'><code>" . htmlspecialchars($val) . "</code></td></tr>";
            }
        }
        echo "</table>";
    }

    // 5. The actual fix - check Laravel Vite source
    echo "<h2>5. Laravel Vite Class Analysis</h2>";
    $viteClassFile = $basePath . '/vendor/laravel/framework/src/Illuminate/Foundation/Vite.php';
    if (file_exists($viteClassFile)) {
        $viteSource = file_get_contents($viteClassFile);

        // Check for hotFile property
        if (preg_match('/\$hotFile\s*=\s*[\'"]([^\'"]*)[\'"]/', $viteSource, $m)) {
            echo "<p>Default hotFile: <code>{$m[1]}</code></p>";
        }

        // Check for buildDirectory property
        if (preg_match('/\$buildDirectory\s*=\s*[\'"]([^\'"]*)[\'"]/', $viteSource, $m)) {
            echo "<p>Default buildDirectory: <code>{$m[1]}</code></p>";
        }

        // Check for manifestPath property
        if (preg_match('/\$manifestPath\s*=\s*[\'"]([^\'"]*)[\'"]/', $viteSource, $m)) {
            echo "<p>Default manifestPath: <code>{$m[1]}</code></p>";
        }

        // Check the __invoke method for dev server detection
        if (strpos($viteSource, 'localhost') !== false || strpos($viteSource, '5173') !== false) {
            echo "<p>The Vite class contains localhost/5173 references (dev server fallback)</p>";
        }
    }

    // 6. Create a test to see what @vite() actually outputs
    echo "<h2>6. Testing @vite() Output</h2>";
    try {
        // Create a minimal request context
        $request = Illuminate\Http\Request::create('/', 'GET');
        $app->instance('request', $request);

        $vite = $app->make(\Illuminate\Foundation\Vite::class);

        // Call __invoke directly
        $output = $vite('resources/js/app.jsx');
        echo "<p>Vite output:</p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";

        if (strpos($output, '5173') !== false || strpos($output, '[::1]') !== false || strpos($output, 'localhost') !== false) {
            echo "<div class='error-box'>";
            echo "<h3>❌ Vite is using DEV SERVER!</h3>";
            echo "<p>This means the manifest is NOT being used. The @vite() directive is falling back to the dev server.</p>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<h3>🔧 Possible Solutions:</h3>";
            echo "<ol>";
            echo "<li><strong>Check Laravel version:</strong> Run <code>php artisan --version</code> on the server</li>";
            echo "<li><strong>Clear ALL caches:</strong> Run <code>php artisan optimize:clear</code> on the server</li>";
            echo "<li><strong>Rebuild assets:</strong> Delete <code>public/build/</code> and run <code>npm run build</code> again locally, then re-upload</li>";
            echo "<li><strong>Check vite.config.js:</strong> The <code>buildDirectory</code> might be configured differently</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<div class='success-box'>";
            echo "<h3>✅ Vite is using PRODUCTION assets!</h3>";
            echo "<p>The output shows correct production asset paths.</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<p class='fail'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<p style='margin-top:30px;padding-top:20px;border-top:1px solid #ddd;'>";
    echo "<small>Script ran: " . date('Y-m-d H:i:s') . "</small>";
    echo "</p>";
    ?>
</body>

</html>