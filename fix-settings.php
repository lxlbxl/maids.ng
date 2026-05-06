<?php
/**
 * Maids.ng — Fix Settings Table
 * Adds the missing `is_encrypted` column and seeds all default settings.
 * Upload to web root, access via browser, then DELETE immediately.
 * URL: https://maids.ng/fix-settings.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='font-family:monospace; background:#111; color:#0f0; padding:20px; max-width:900px; margin:40px auto;'>";
echo "=== Maids.ng Settings Table Fix ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Parse .env
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "❌ .env file not found\n";
    die("</pre>");
}

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

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? '';
$dbUser = $env['DB_USERNAME'] ?? '';
$dbPass = $env['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
    echo "✅ Database connected\n\n";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    die("</pre>");
}

// ─── Step 1: Add missing is_encrypted column ───
echo "--- Step 1: Add missing column ---\n";
$columns = $pdo->query("DESCRIBE settings")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('is_encrypted', $columns)) {
    try {
        $pdo->exec("ALTER TABLE `settings` ADD COLUMN `is_encrypted` tinyint(1) NOT NULL DEFAULT '0' AFTER `value`");
        echo "✅ Added 'is_encrypted' column\n";
    } catch (PDOException $e) {
        echo "❌ Failed to add column: " . $e->getMessage() . "\n";
        die("</pre>");
    }
} else {
    echo "✅ 'is_encrypted' column already exists\n";
}

// Verify
$columns = $pdo->query("DESCRIBE settings")->fetchAll(PDO::FETCH_COLUMN);
echo "   Current columns: " . implode(', ', $columns) . "\n\n";

// ─── Step 2: Seed all default settings ───
echo "--- Step 2: Seed default settings ---\n";

$settings = [
    // General
    ['platform_name', 'Maids.ng', 'general', 0],
    ['app_url', '', 'general', 0],
    ['app_timezone', 'Africa/Lagos', 'general', 0],
    ['app_debug', 'false', 'general', 0],
    ['support_email', 'support@maids.ng', 'general', 0],
    ['support_phone', '+234 801 234 5678', 'general', 0],
    ['contact_phone', '+234 801 234 5678', 'general', 0],
    ['maintenance_mode', 'false', 'general', 0],

    // Financial
    ['service_fee_percentage', '10', 'finance', 0],
    ['matching_fee_amount', '5000', 'finance', 0],
    ['guarantee_match_fee', '10000', 'finance', 0],
    ['nin_verification_fee', '5000', 'finance', 0],
    ['standalone_verification_fee', '2000', 'finance', 0],
    ['commission_type', 'percentage', 'finance', 0],
    ['commission_percent', '10', 'finance', 0],
    ['commission_fixed_amount', '5000', 'finance', 0],
    ['min_salary', '15000', 'finance', 0],
    ['max_salary', '200000', 'finance', 0],
    ['min_withdrawal', '5000', 'finance', 0],
    ['max_withdrawal', '500000', 'finance', 0],
    ['withdrawal_processing_days', '3', 'finance', 0],

    // Payment Gateways
    ['default_payment_gateway', 'paystack', 'payment', 0],
    ['paystack_public_key', '', 'payment', 1],
    ['paystack_secret_key', '', 'payment', 1],
    ['paystack_base_url', 'https://api.paystack.co', 'payment', 0],
    ['flutterwave_public_key', '', 'payment', 1],
    ['flutterwave_secret_key', '', 'payment', 1],
    ['flutterwave_encryption_key', '', 'payment', 1],
    ['flutterwave_base_url', 'https://api.flutterwave.com/v3', 'payment', 0],

    // SMS
    ['sms_active_provider', 'log', 'sms', 0],
    ['termii_api_key', '', 'sms', 1],
    ['termii_sender_id', 'MaidsNG', 'sms', 0],
    ['termii_url', 'https://api.ng.termii.com/api', 'sms', 0],
    ['twilio_sid', '', 'sms', 1],
    ['twilio_token', '', 'sms', 1],
    ['twilio_from', '', 'sms', 0],
    ['africastalking_username', '', 'sms', 0],
    ['africastalking_api_key', '', 'sms', 1],
    ['africastalking_from', 'MaidsNG', 'sms', 0],

    // Email
    ['mail_mailer', 'smtp', 'email', 0],
    ['mail_host', '', 'email', 0],
    ['mail_port', '587', 'email', 0],
    ['mail_username', '', 'email', 1],
    ['mail_password', '', 'email', 1],
    ['mail_encryption', 'tls', 'email', 0],
    ['mail_from_address', 'noreply@maids.ng', 'email', 0],
    ['mail_from_name', 'Maids.ng', 'email', 0],

    // Verification
    ['qoreid_token', '', 'verification', 1],
    ['qoreid_base_url', 'https://api.qoreid.com/v1', 'verification', 0],
    ['verification_auto_approve', 'false', 'verification', 0],

    // AI
    ['ai_active_provider', 'openai', 'ai', 0],
    ['openai_model', 'gpt-4o-mini', 'ai', 0],
    ['openai_key', '', 'ai', 1],
    ['openrouter_model', 'google/gemini-flash-1.5', 'ai', 0],
    ['openrouter_key', '', 'ai', 1],
    ['ai_matching_enabled', 'true', 'ai', 0],
    ['ai_min_confidence_score', '0.7', 'ai', 0],

    // Notifications
    ['notification_enabled', 'true', 'notification', 0],
    ['email_notifications', 'true', 'notification', 0],
    ['sms_notifications', 'true', 'notification', 0],
    ['whatsapp_notifications', 'false', 'notification', 0],
    ['push_notifications', 'false', 'notification', 0],

    // Matching
    ['matching_enabled', 'true', 'matching', 0],
    ['guarantee_match_enabled', 'true', 'matching', 0],
    ['max_matches_per_request', '5', 'matching', 0],
    ['match_response_timeout_hours', '48', 'matching', 0],
];

$inserted = 0;
$skipped = 0;

$stmt = $pdo->prepare("INSERT IGNORE INTO `settings` (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, NOW(), NOW())");

foreach ($settings as $setting) {
    $result = $stmt->execute([$setting[0], $setting[1], $setting[3], $setting[2]]);
    if ($stmt->rowCount() > 0) {
        $inserted++;
        echo "   + {$setting[0]}\n";
    } else {
        $skipped++;
    }
}

echo "\n   Inserted: {$inserted} new settings\n";
echo "   Skipped:  {$skipped} (already existed)\n";

// ─── Step 3: Verify ───
echo "\n--- Step 3: Verification ---\n";
$count = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
echo "   Total settings in DB: {$count}\n";

// Test write
try {
    $pdo->prepare("INSERT INTO `settings` (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()")
        ->execute(['_fix_test', 'ok', 0, 'general']);
    $pdo->exec("DELETE FROM `settings` WHERE `key` = '_fix_test'");
    echo "   ✅ Write test PASSED — Settings save should now work!\n";
} catch (PDOException $e) {
    echo "   ❌ Write test FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== Fix Complete ===\n";
echo "\n✅ Now go to Admin > Settings and try saving again.\n";
echo "⚠️  DELETE THIS FILE after use! (fix-settings.php)\n";
echo "</pre>";
