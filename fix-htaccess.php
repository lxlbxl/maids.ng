<?php
/**
 * Quick Fix for Homepage 500 Error
 * This script fixes common .htaccess issues after installation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Maids.ng - Fix Homepage</title>
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
    <h1>🔧 Maids.ng Homepage Fix</h1>

    <?php
    $rootHtaccess = __DIR__ . '/.htaccess';
    $publicHtaccess = __DIR__ . '/public/.htaccess';
    $publicIndex = __DIR__ . '/public/index.php';
    $envFile = __DIR__ . '/.env';

    echo "<h2>Current File Status</h2>";
    echo "<table>";
    echo "<tr><th>File</th><th>Status</th></tr>";

    $files = [
        'Root .htaccess' => $rootHtaccess,
        'Public .htaccess' => $publicHtaccess,
        'Public index.php' => $publicIndex,
        '.env file' => $envFile,
    ];

    foreach ($files as $name => $path) {
        $exists = file_exists($path);
        echo "<tr><td>{$name}</td><td class='" . ($exists ? 'pass' : 'fail') . "'>" . ($exists ? '✓ Exists' : '✗ Missing') . "</td></tr>";
    }
    echo "</table>";

    // Check if .env has APP_KEY
    $envOk = false;
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        $envOk = strpos($envContent, 'APP_KEY=base64:') !== false;
        echo "<h2>.env Configuration</h2>";
        echo "<p>APP_KEY set: " . ($envOk ? '<span class="pass">✓ Yes</span>' : '<span class="fail">✗ No</span>') . "</p>";
    }

    // Fix 1: Check if document root is public/ or root
    echo "<h2>Diagnosis</h2>";
    echo "<div class='info'>";
    echo "<h3>Understanding Your Setup</h3>";
    echo "<p><strong>If your document root is set to <code>public/</code></strong> (recommended):</p>";
    echo "<ul><li>The root <code>.htaccess</code> should NOT redirect to <code>public/</code></li><li>The <code>public/.htaccess</code> handles URL rewriting</li></ul>";
    echo "<p><strong>If your document root is the project root:</strong></p>";
    echo "<ul><li>The root <code>.htaccess</code> redirects all requests to <code>public/</code></li></ul>";
    echo "</div>";

    // Fix 2: Create correct .htaccess files
    echo "<h2>Apply Fix</h2>";
    echo "<div class='info'>";
    echo "<p>Click the button below to create the correct <code>.htaccess</code> files:</p>";

    if (isset($_GET['fix'])) {
        // Create root .htaccess - redirect to public/
        $rootContent = '<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>';
        file_put_contents($rootHtaccess, $rootContent);
        echo "<p class='pass'>✓ Root .htaccess created (redirects to public/)</p>";

        // Create public/.htaccess - Laravel default
        $publicContent = '<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Index
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>';
        file_put_contents($publicHtaccess, $publicContent);
        echo "<p class='pass'>✓ Public .htaccess created (Laravel URL rewriting)</p>";

        // Check APP_KEY
        if (!$envOk && file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            if (strpos($envContent, 'APP_KEY=') !== false && strpos($envContent, 'APP_KEY=base64:') === false) {
                // Generate a proper key
                $key = 'base64:' . base64_encode(random_bytes(32));
                $envContent = preg_replace('/APP_KEY=.*/', 'APP_KEY=' . $key, $envContent);
                file_put_contents($envFile, $envContent);
                echo "<p class='pass'>✓ APP_KEY generated in .env</p>";
            }
        }

        echo "<p><strong>Fix applied! Try visiting your homepage now.</strong></p>";
        echo "<p><a href='/' style='color:blue;'>→ Visit Homepage</a></p>";
    } else {
        echo "<form method='get'><input type='hidden' name='fix' value='1'><button type='submit' style='padding:10px 20px;background:#4CAF50;color:white;border:none;cursor:pointer;border-radius:5px;'>Apply Fix</button></form>";
    }
    echo "</div>";

    // Show error log
    echo "<h2>Error Log</h2>";
    echo "<div class='info'>";
    $laravelLog = __DIR__ . '/storage/logs/laravel.log';
    if (file_exists($laravelLog)) {
        $lines = file($laravelLog);
        $lastLines = array_slice($lines, -30);
        echo "<pre>" . htmlspecialchars(implode('', $lastLines)) . "</pre>";
    } else {
        echo "<p>No Laravel log file found. Check cPanel → Metrics → Errors for server errors.</p>";
    }
    echo "</div>";

    // Manual fix instructions
    echo "<h2>Manual Fix (if above doesn't work)</h2>";
    echo "<div class='info'>";
    echo "<h3>Option 1: Document root is public/</h3>";
    echo "<p>If your hosting has document root set to <code>public/</code>, delete the root <code>.htaccess</code> file.</p>";
    echo "<h3>Option 2: Document root is project root</h3>";
    echo "<p>If your hosting has document root at the project root, the root <code>.htaccess</code> should contain:</p>";
    echo "<pre><IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule></pre>";
    echo "</div>";

    echo "<p style='margin-top:30px;padding-top:20px;border-top:1px solid #ddd;'>";
    echo "<small>Fix script generated: " . date('Y-m-d H:i:s') . "</small>";
    echo "</p>";
    ?>
</body>

</html>