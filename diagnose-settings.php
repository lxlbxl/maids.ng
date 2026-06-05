<?php
/**
 * Maids.ng Settings Diagnostic
 * Upload to web root, access via browser, then DELETE immediately after use.
 * URL: https://maids.ng/diagnose-settings.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='font-family:monospace; background:#111; color:#0f0; padding:20px; max-width:900px; margin:40px auto;'>";
echo "=== Maids.ng Settings Diagnostic ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Check .env exists
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "❌ .env file NOT FOUND at: {$envFile}\n";
    echo "   This is critical. The application cannot connect to the database.\n";
    die("</pre>");
}
echo "✅ .env file found\n";

// 2. Parse .env
$envContent = file_get_contents($envFile);
$env = [];
foreach (explode("\n", $envContent) as $line) {
    $line = trim($line);
    if (empty($line) || str_starts_with($line, '#')) continue;
    if (str_contains($line, '=')) {
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, '"\'');
    }
}

// 3. Check DB config
$dbHost = $env['DB_HOST'] ?? 'NOT SET';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? 'NOT SET';
$dbUser = $env['DB_USERNAME'] ?? 'NOT SET';
$dbPass = $env['DB_PASSWORD'] ?? 'NOT SET';
$dbConn = $env['DB_CONNECTION'] ?? 'NOT SET';
$appKey = $env['APP_KEY'] ?? 'NOT SET';

echo "\n--- Database Configuration ---\n";
echo "DB_CONNECTION: {$dbConn}\n";
echo "DB_HOST:       {$dbHost}\n";
echo "DB_PORT:       {$dbPort}\n";
echo "DB_DATABASE:   {$dbName}\n";
echo "DB_USERNAME:   {$dbUser}\n";
echo "DB_PASSWORD:   " . (strlen($dbPass) > 0 ? str_repeat('*', min(strlen($dbPass), 8)) . " (" . strlen($dbPass) . " chars)" : "EMPTY!") . "\n";
echo "APP_KEY:       " . (strlen($appKey) > 10 ? substr($appKey, 0, 15) . '...' : $appKey) . "\n";

// 4. Check config cache
$configCache = __DIR__ . '/bootstrap/cache/config.php';
if (file_exists($configCache)) {
    echo "\n⚠️  CONFIG CACHE EXISTS at bootstrap/cache/config.php\n";
    echo "   Size: " . filesize($configCache) . " bytes\n";
    echo "   Modified: " . date('Y-m-d H:i:s', filemtime($configCache)) . "\n";
    
    // Check what DB config the cache has
    $cachedConfig = include $configCache;
    if (isset($cachedConfig['database']['connections']['mysql'])) {
        $cachedDb = $cachedConfig['database']['connections']['mysql'];
        echo "   Cached DB_HOST:     " . ($cachedDb['host'] ?? 'N/A') . "\n";
        echo "   Cached DB_DATABASE: " . ($cachedDb['database'] ?? 'N/A') . "\n";
        echo "   Cached DB_USERNAME: " . ($cachedDb['username'] ?? 'N/A') . "\n";
        
        if (($cachedDb['host'] ?? '') !== $dbHost || ($cachedDb['database'] ?? '') !== $dbName) {
            echo "\n   🔴 MISMATCH! Cached config differs from .env!\n";
            echo "   This is likely causing your settings save to fail.\n";
            echo "   FIX: Delete bootstrap/cache/config.php or visit /admin/clear-cache\n";
        }
    }
} else {
    echo "\n✅ No config cache (good — using .env directly)\n";
}

// 5. Test DB connection
echo "\n--- Database Connection Test ---\n";
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName}";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful!\n";
    
    // 6. Check settings table
    echo "\n--- Settings Table Check ---\n";
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'settings'")->fetch();
    if (!$tableCheck) {
        echo "❌ Settings table does NOT exist!\n";
        echo "   FIX: Run the installer or create the table manually.\n";
        die("</pre>");
    }
    echo "✅ Settings table exists\n";
    
    // 7. Check columns
    $columns = $pdo->query("DESCRIBE settings")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    echo "   Columns: " . implode(', ', $columnNames) . "\n";
    
    $required = ['id', 'key', 'value', 'is_encrypted', 'group', 'created_at', 'updated_at'];
    $missing = array_diff($required, $columnNames);
    if (!empty($missing)) {
        echo "   ❌ MISSING columns: " . implode(', ', $missing) . "\n";
        echo "   This is causing the settings save to fail!\n";
    } else {
        echo "   ✅ All required columns present\n";
    }
    
    // 8. Count records
    $count = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    echo "   Records: {$count}\n";
    
    // 9. Test INSERT/UPDATE
    echo "\n--- Write Test ---\n";
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`, `is_encrypted`, `group`, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()");
        $stmt->execute(['_diagnostic_test', 'ok', 0, 'general']);
        echo "✅ Write test PASSED\n";
        
        // Cleanup
        $pdo->exec("DELETE FROM settings WHERE `key` = '_diagnostic_test'");
        echo "✅ Cleanup done\n";
    } catch (PDOException $e) {
        echo "❌ Write test FAILED: " . $e->getMessage() . "\n";
        echo "   This is the exact error causing your settings save to fail.\n";
    }
    
    // 10. Test Eloquent-style updateOrCreate query
    echo "\n--- Eloquent-style Query Test ---\n";
    try {
        // This mimics what Laravel's updateOrCreate does
        $stmt = $pdo->prepare("SELECT * FROM `settings` WHERE (`key` = ?) LIMIT 1");
        $stmt->execute(['platform_name']);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            echo "   Found existing 'platform_name' record (id={$existing['id']})\n";
            $update = $pdo->prepare("UPDATE `settings` SET `value` = ?, `is_encrypted` = ?, `group` = ?, `updated_at` = NOW() WHERE `id` = ?");
            $update->execute([$existing['value'], $existing['is_encrypted'], $existing['group'], $existing['id']]);
            echo "   ✅ Update query works\n";
        } else {
            echo "   No 'platform_name' record found (settings may not be seeded)\n";
            echo "   Trying insert...\n";
            $insert = $pdo->prepare("INSERT INTO `settings` (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $insert->execute(['_test_insert', 'test', 0, 'general']);
            $pdo->exec("DELETE FROM `settings` WHERE `key` = '_test_insert'");
            echo "   ✅ Insert query works\n";
        }
    } catch (PDOException $e) {
        echo "   ❌ Eloquent-style query FAILED: " . $e->getMessage() . "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection FAILED!\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   Code:  " . $e->getCode() . "\n";
    echo "\n   Common fixes:\n";
    echo "   - Check DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env\n";
    echo "   - Delete bootstrap/cache/config.php\n";
}

echo "\n=== Diagnostic Complete ===\n";
echo "\n⚠️  DELETE THIS FILE after use! (diagnose-settings.php)\n";
echo "</pre>";
