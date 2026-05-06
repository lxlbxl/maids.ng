<?php
/**
 * Maids.ng Server Diagnostic Script
 * Run this file to check if your server meets the requirements
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Maids.ng - Server Check</title>
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
    </style>
</head>

<body>
    <h1>🔍 Maids.ng Server Diagnostic</h1>

    <h2>PHP Information</h2>
    <table>
        <tr>
            <th>Check</th>
            <th>Required</th>
            <th>Your Server</th>
            <th>Status</th>
        </tr>
        <?php
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.2.0', '>=');
        echo "<tr><td>PHP Version</td><td>8.2+</td><td>{$phpVersion}</td><td class='" . ($phpOk ? 'pass' : 'fail') . "'>" . ($phpOk ? '✓ PASS' : '✗ FAIL') . "</td></tr>";

        // Check required extensions
        $extensions = [
            'pdo' => 'PDO',
            'pdo_mysql' => 'PDO MySQL',
            'mbstring' => 'Mbstring',
            'openssl' => 'OpenSSL',
            'json' => 'JSON',
            'curl' => 'cURL',
            'fileinfo' => 'Fileinfo',
            'tokenizer' => 'Tokenizer',
            'xml' => 'XML',
            'bcmath' => 'BCMath',
            'ctype' => 'Ctype',
            'gd' => 'GD (or imagick)',
        ];

        foreach ($extensions as $ext => $name) {
            $loaded = extension_loaded($ext);
            echo "<tr><td>{$name} Extension</td><td>Required</td><td>" . ($loaded ? 'Installed' : 'NOT Installed') . "</td><td class='" . ($loaded ? 'pass' : 'fail') . "'>" . ($loaded ? '✓ PASS' : '✗ FAIL') . "</td></tr>";
        }
        ?>
    </table>

    <h2>Directory Permissions</h2>
    <table>
        <tr>
            <th>Directory</th>
            <th>Status</th>
        </tr>
        <?php
        $dirs = [
            'storage',
            'storage/app',
            'storage/app/public',
            'storage/framework',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
            'public',
        ];

        foreach ($dirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (is_dir($path)) {
                $writable = is_writable($path);
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                echo "<tr><td>{$dir}</td><td class='" . ($writable ? 'pass' : 'fail') . "'>" . ($writable ? '✓ Writable' : '✗ NOT Writable') . " ({$perms})</td></tr>";
            } else {
                echo "<tr><td>{$dir}</td><td class='warn'>⚠ Directory does not exist</td></tr>";
            }
        }
        ?>
    </table>

    <h2>File Checks</h2>
    <table>
        <tr>
            <th>File</th>
            <th>Status</th>
        </tr>
        <?php
        $files = [
            '.env' => 'Environment file',
            '.env.example' => 'Environment example',
            'public/.htaccess' => 'Public .htaccess',
            '.htaccess' => 'Root .htaccess',
            'database/database.sql' => 'Database schema',
            'vendor/autoload.php' => 'Composer autoload',
            'composer.json' => 'Composer config',
        ];

        foreach ($files as $file => $desc) {
            $path = __DIR__ . '/' . $file;
            $exists = file_exists($path);
            echo "<tr><td>{$desc} ({$file})</td><td class='" . ($exists ? 'pass' : 'fail') . "'>" . ($exists ? '✓ Exists' : '✗ MISSING') . "</td></tr>";
        }
        ?>
    </table>

    <h2>Database Connection Test</h2>
    <div class="info">
        <p>Enter your database credentials to test the connection:</p>
        <form method="POST">
            <table>
                <tr>
                    <td>Host:</td>
                    <td><input type="text" name="db_host" value="localhost" style="width:200px;padding:5px;"></td>
                </tr>
                <tr>
                    <td>Port:</td>
                    <td><input type="text" name="db_port" value="3306" style="width:200px;padding:5px;"></td>
                </tr>
                <tr>
                    <td>Database:</td>
                    <td><input type="text" name="db_name" placeholder="your_database_name"
                            style="width:200px;padding:5px;"></td>
                </tr>
                <tr>
                    <td>Username:</td>
                    <td><input type="text" name="db_user" placeholder="your_username" style="width:200px;padding:5px;">
                    </td>
                </tr>
                <tr>
                    <td>Password:</td>
                    <td><input type="password" name="db_pass" placeholder="your_password"
                            style="width:200px;padding:5px;"></td>
                </tr>
                <tr>
                    <td colspan="2"><button type="submit" name="test_db"
                            style="padding:10px 20px;background:#4CAF50;color:white;border:none;cursor:pointer;">Test
                            Connection</button></td>
                </tr>
            </table>
        </form>
    </div>

    <?php
    if (isset($_POST['test_db'])) {
        $host = $_POST['db_host'] ?? 'localhost';
        $port = $_POST['db_port'] ?? '3306';
        $dbname = $_POST['db_name'] ?? '';
        $user = $_POST['db_user'] ?? '';
        $pass = $_POST['db_pass'] ?? '';

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname}";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "<p class='pass'>✓ Database connection successful!</p>";

            // Check if tables exist
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (count($tables) > 0) {
                echo "<p>Found " . count($tables) . " tables in database.</p>";
            } else {
                echo "<p class='warn'>⚠ Database is empty. You need to run install.php or import database.sql</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='fail'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    ?>

    <h2>PHP Error Log (Last 20 lines)</h2>
    <div class="info">
        <pre><?php
        $logFile = ini_get('error_log');
        if ($logFile && file_exists($logFile)) {
            $lines = file($logFile);
            $lastLines = array_slice($lines, -20);
            echo htmlspecialchars(implode('', $lastLines));
        } else {
            echo "Error log file not found or not configured.\n";
            echo "Check your cPanel → Errors section for details.\n";
            echo "Common error log locations:\n";
            echo "  - /home/username/logs/error_log\n";
            echo "  - /home/username/public_html/storage/logs/laravel.log\n";
            echo "  - Check cPanel → Metrics → Errors\n";
        }
        ?></pre>
    </div>

    <h2>Next Steps</h2>
    <div class="info">
        <h3>If install.php shows 500 error, try these fixes:</h3>
        <ol>
            <li><strong>Check PHP Version:</strong> In cPanel, go to <strong>Select PHP Version</strong> and ensure PHP
                8.2 or higher is selected.</li>
            <li><strong>Enable Extensions:</strong> In <strong>Select PHP Version → Options</strong>, make sure all
                required extensions are checked.</li>
            <li><strong>Fix Permissions:</strong> Set these folders to 755:
                <pre>chmod -R 755 storage/
chmod -R 755 bootstrap/cache/</pre>
            </li>
            <li><strong>Check .htaccess:</strong> If you have a root .htaccess, it might be causing issues. Try renaming
                it temporarily.</li>
            <li><strong>View Error Details:</strong> In cPanel, go to <strong>Metrics → Errors</strong> to see the
                actual error message.</li>
        </ol>
    </div>

    <p style="margin-top:30px;padding-top:20px;border-top:1px solid #ddd;">
        <small>Diagnostic script generated:
            <?php echo date('Y-m-d H:i:s'); ?>
        </small>
    </p>
</body>

</html>