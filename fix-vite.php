<?php
/**
 * Fix Vite Production Assets - Complete Solution
 * Upload this to your hosting root and visit in browser
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Maids.ng - Vite Assets Fix</title>
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

        .warn {
            color: orange;
        }

        .info {
            background: #f0f7ff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .error-box {
            background: #fee2e2;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ef4444;
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

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
    </style>
</head>

<body>
    <h1>🔧 Maids.ng Vite Assets Fix</h1>

    <?php
    $buildDir = __DIR__ . '/public/build';
    $manifestFile = $buildDir . '/manifest.json';
    $assetsDir = $buildDir . '/assets';

    echo "<h2>Current Status</h2>";
    echo "<table>";
    echo "<tr><th>Check</th><th>Status</th><th>Details</th></tr>";

    // Check build directory
    $buildExists = is_dir($buildDir);
    echo "<tr><td><code>public/build/</code> directory</td><td class='" . ($buildExists ? 'pass' : 'fail') . "'>" . ($buildExists ? '✓ Exists' : '✗ Missing') . "</td><td>" . ($buildExists ? 'Directory found' : 'Need to upload build folder') . "</td></tr>";

    // Check manifest
    $manifestOk = false;
    $manifestDetails = '';
    if (file_exists($manifestFile)) {
        $manifest = json_decode(file_get_contents($manifestFile), true);
        if ($manifest && isset($manifest['resources/js/app.jsx'])) {
            $manifestOk = true;
            $manifestDetails = 'Valid - app.jsx entry found';
        } else {
            $manifestDetails = 'Invalid JSON or missing app.jsx entry';
        }
    } else {
        $manifestDetails = 'manifest.json not found';
    }
    echo "<tr><td><code>public/build/manifest.json</code></td><td class='" . ($manifestOk ? 'pass' : 'fail') . "'>" . ($manifestOk ? '✓ Valid' : '✗ Invalid/Missing') . "</td><td>{$manifestDetails}</td></tr>";

    // Check assets
    $assetCount = 0;
    if ($buildExists && is_dir($assetsDir)) {
        $assetFiles = glob($assetsDir . '/*.{js,css}', GLOB_BRACE);
        $assetCount = count($assetFiles);
    }
    echo "<tr><td>Asset files (.js, .css)</td><td class='" . ($assetCount > 0 ? 'pass' : 'fail') . "'>" . $assetCount . " files</td><td>" . ($assetCount > 0 ? 'Assets found' : 'No asset files') . "</td></tr>";

    // Check .env
    $envFile = __DIR__ . '/.env';
    $appEnv = 'not set';
    $appDebug = 'not set';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        if (preg_match('/APP_ENV=(.+)/', $envContent, $m))
            $appEnv = trim($m[1]);
        if (preg_match('/APP_DEBUG=(.+)/', $envContent, $m))
            $appDebug = trim($m[1]);
    }
    echo "<tr><td>APP_ENV</td><td>" . ($appEnv === 'production' ? 'pass' : 'warn') . "</td><td>{$appEnv}</td></tr>";
    echo "<tr><td>APP_DEBUG</td><td>" . ($appDebug === 'false' ? 'pass' : 'warn') . "</td><td>{$appDebug}</td></tr>";

    echo "</table>";

    // Main diagnosis
    echo "<h2>Diagnosis & Fix</h2>";

    if (!$buildExists || !$manifestOk || $assetCount === 0) {
        echo "<div class='error-box'>";
        echo "<h3>❌ Build assets are missing or invalid!</h3>";
        echo "<p>The <code>@vite()</code> directive falls back to the dev server (localhost:5173) when it can't find <code>public/build/manifest.json</code>.</p>";
        echo "</div>";

        echo "<div class='info'>";
        echo "<h3>📋 How to Fix (Step by Step)</h3>";
        echo "<ol>";
        echo "<li><strong>On your LOCAL machine</strong> (Windows), open terminal and run:</li>";
        echo "<pre>cd c:\\Users\\Alex\\TraeCoder\\Maids.ng\nnpm install\nnpm run build</pre>";
        echo "<li>This creates the <code>public/build/</code> folder with optimized assets</li>";
        echo "<li><strong>Upload to your hosting</strong> via FTP/cPanel:</li>";
        echo "<ul><li>Upload the entire <code>public/build/</code> folder</li><li>It should contain <code>manifest.json</code> and an <code>assets/</code> folder with .js and .css files</li></ul>";
        echo "<li><strong>After uploading</strong>, refresh this page to verify</li>";
        echo "</ol>";
        echo "</div>";

        // Show what the build folder should contain
        echo "<div class='info'>";
        echo "<h3>📁 What public/build/ should look like:</h3>";
        echo "<pre>public/build/
├── manifest.json
└── assets/
    ├── app-xxxxx.js
    ├── app-xxxxx.css
    └── ... (other hashed files)</pre>";
        echo "</div>";

    } else {
        echo "<div class='success-box'>";
        echo "<h3>✅ Build assets look good!</h3>";
        echo "<p>Your <code>public/build/manifest.json</code> exists and is valid.</p>";
        echo "</div>";

        echo "<div class='info'>";
        echo "<h3>If you still see localhost:5173 errors:</h3>";
        echo "<ol>";
        echo "<li><strong>Clear Laravel view cache:</strong></li>";
        echo "<pre>php artisan view:clear</pre>";
        echo "<li><strong>Clear browser cache:</strong> Press <code>Ctrl+Shift+R</code> (or <code>Cmd+Shift+R</code> on Mac)</li>";
        echo "<li><strong>Check APP_ENV is production:</strong> Your .env shows <code>APP_ENV={$appEnv}</code></li>";
        echo "</ol>";
        echo "</div>";
    }

    // Show manifest content for debugging
    if (file_exists($manifestFile)) {
        echo "<h2>manifest.json Content</h2>";
        echo "<div class='info'>";
        echo "<pre>" . htmlspecialchars(json_encode(json_decode(file_get_contents($manifestFile), true), JSON_PRETTY_PRINT)) . "</pre>";
        echo "</div>";
    }

    // List files in build directory
    if ($buildExists) {
        echo "<h2>Files in public/build/</h2>";
        echo "<div class='info'>";
        echo "<pre>";
        listDir($buildDir, '');
        echo "</pre>";
        echo "</div>";
    }

    echo "<p style='margin-top:30px;padding-top:20px;border-top:1px solid #ddd;'>";
    echo "<small>Fix script ran: " . date('Y-m-d H:i:s') . "</small>";
    echo "</p>";

    function listDir($dir, $prefix)
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..')
                continue;
            $path = $dir . '/' . $item;
            echo $prefix . $item . "\n";
            if (is_dir($path)) {
                listDir($path, $prefix . '  ');
            }
        }
    }
    ?>
</body>

</html>