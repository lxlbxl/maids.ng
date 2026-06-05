<?php
/**
 * Maids.ng — Web-Based Migration Runner
 *
 * Runs all pending Laravel migrations via the web browser.
 * Safe for shared hosting where CLI is not available.
 *
 * SECURITY: Set MIGRATE_TOKEN in .env or delete this file after use.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

// Simple token-based protection — set MIGRATE_TOKEN in .env for extra security
$expectedToken = env('MIGRATE_TOKEN', '');
$providedToken = $_GET['token'] ?? $_POST['token'] ?? '';
$showForm = empty($expectedToken) && empty($providedToken);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Maids.ng — Migration Runner</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 900px; margin: 0 auto; padding: 40px 20px; background: #f1f5f9; color: #334155; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { margin-bottom: 20px; color: #1e293b; }
        h2 { margin: 20px 0 10px; font-size: 18px; }
        .step { margin: 12px 0; padding: 12px 16px; border-left: 4px solid #3b82f6; background: #eff6ff; border-radius: 0 6px 6px 0; }
        .success { color: #059669; font-weight: bold; }
        .error { color: #dc2626; }
        .warning { color: #d97706; }
        .skipped { color: #94a3b8; }
        pre { background: #1e293b; color: #f8fafc; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 13px; margin-top: 10px; white-space: pre-wrap; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 14px; }
        th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-ran { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        .btn { display: inline-block; padding: 10px 24px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn:hover { background: #2563eb; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        .alert { padding: 12px 16px; border-radius: 6px; margin: 15px 0; }
        .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    </style>
</head>
<body>
    <div class="card">

        <?php if ($showForm): ?>
            <h1>🔧 Maids.ng Migration Runner</h1>
            <div class="alert alert-warning">
                <strong>⚠️ Authentication Required</strong><br>
                Set <code>MIGRATE_TOKEN=your_secret_here</code> in your <code>.env</code> file, then pass it as <code>?token=your_secret_here</code> to access this page.
            </div>
            <form method="GET">
                <div class="form-group">
                    <label for="token">Token</label>
                    <input type="text" id="token" name="token" placeholder="Enter migration token" required>
                </div>
                <button type="submit" class="btn">Run Migrations</button>
            </form>

        <?php elseif (!empty($expectedToken) && $providedToken !== $expectedToken): ?>
            <h1>🔒 Access Denied</h1>
            <div class="alert alert-warning">
                Invalid token. Set <code>MIGRATE_TOKEN</code> in your <code>.env</code> file to match the token you provide.
            </div>

        <?php else: ?>
            <h1>🔧 Maids.ng — Migration Runner</h1>

            <?php if (isset($_GET['ensure_tables'])): ?>
                <?php
                // ── MANUAL TABLE ENSURANCE MODE ──
                // Use direct SQL to create any missing tables that migrations may have missed
                $results = [];

                $tablesToEnsure = [
                    'nin_verifications' => "CREATE TABLE IF NOT EXISTS `nin_verifications` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `user_id` bigint(20) unsigned NOT NULL,
                        `nin_hash` varchar(64) DEFAULT NULL,
                        `status` enum('pending','approved','rejected','manual_review') NOT NULL DEFAULT 'pending',
                        `confidence_score` tinyint(3) unsigned DEFAULT NULL,
                        `external_reference` varchar(255) DEFAULT NULL,
                        `review_notes` text DEFAULT NULL,
                        `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `reviewed_at` timestamp NULL DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `nin_verifications_user_id_unique` (`user_id`),
                        KEY `nin_verifications_status_submitted_at_index` (`status`,`submitted_at`),
                        CONSTRAINT `nin_verifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'employer_wallets' => "CREATE TABLE IF NOT EXISTS `employer_wallets` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `employer_id` bigint(20) unsigned NOT NULL,
                        `balance` decimal(15,2) NOT NULL DEFAULT '0.00',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `employer_wallets_employer_id_unique` (`employer_id`),
                        CONSTRAINT `employer_wallets_employer_id_foreign` FOREIGN KEY (`employer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'maid_wallets' => "CREATE TABLE IF NOT EXISTS `maid_wallets` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `maid_id` bigint(20) unsigned NOT NULL,
                        `balance` decimal(15,2) NOT NULL DEFAULT '0.00',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `maid_wallets_maid_id_unique` (`maid_id`),
                        CONSTRAINT `maid_wallets_maid_id_foreign` FOREIGN KEY (`maid_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'wallet_transactions' => "CREATE TABLE IF NOT EXISTS `wallet_transactions` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `user_id` bigint(20) unsigned NOT NULL,
                        `type` varchar(255) NOT NULL,
                        `amount` decimal(15,2) NOT NULL,
                        `status` varchar(255) NOT NULL DEFAULT 'pending',
                        `reference` varchar(255) DEFAULT NULL,
                        `description` text DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `wallet_transactions_user_id_foreign` (`user_id`),
                        CONSTRAINT `wallet_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'maid_assignments' => "CREATE TABLE IF NOT EXISTS `maid_assignments` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `employer_id` bigint(20) unsigned NOT NULL,
                        `maid_id` bigint(20) unsigned NOT NULL,
                        `preference_id` bigint(20) unsigned DEFAULT NULL,
                        `status` varchar(255) NOT NULL DEFAULT 'pending',
                        `notes` text DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `maid_assignments_employer_id_foreign` (`employer_id`),
                        KEY `maid_assignments_maid_id_foreign` (`maid_id`),
                        CONSTRAINT `maid_assignments_employer_id_foreign` FOREIGN KEY (`employer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `maid_assignments_maid_id_foreign` FOREIGN KEY (`maid_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'salary_schedules' => "CREATE TABLE IF NOT EXISTS `salary_schedules` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `assignment_id` bigint(20) unsigned NOT NULL,
                        `employer_id` bigint(20) unsigned NOT NULL,
                        `maid_id` bigint(20) unsigned NOT NULL,
                        `monthly_salary` decimal(15,2) NOT NULL,
                        `payment_status` varchar(255) NOT NULL DEFAULT 'pending',
                        `next_salary_due_date` date DEFAULT NULL,
                        `first_salary_date` date DEFAULT NULL,
                        `reminder_count` int(11) NOT NULL DEFAULT '0',
                        `last_reminder_sent_at` timestamp NULL DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `salary_schedules_assignment_id_foreign` (`assignment_id`),
                        CONSTRAINT `salary_schedules_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `maid_assignments` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'salary_payments' => "CREATE TABLE IF NOT EXISTS `salary_payments` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `assignment_id` bigint(20) unsigned NOT NULL,
                        `employer_id` bigint(20) unsigned NOT NULL,
                        `maid_id` bigint(20) unsigned NOT NULL,
                        `amount` decimal(15,2) NOT NULL,
                        `description` text DEFAULT NULL,
                        `paid_at` timestamp NULL DEFAULT NULL,
                        `status` varchar(255) NOT NULL DEFAULT 'pending',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `salary_payments_assignment_id_foreign` (`assignment_id`),
                        CONSTRAINT `salary_payments_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `maid_assignments` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'salary_reminders' => "CREATE TABLE IF NOT EXISTS `salary_reminders` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `schedule_id` bigint(20) unsigned NOT NULL,
                        `employer_id` bigint(20) unsigned NOT NULL,
                        `sent_at` timestamp NULL DEFAULT NULL,
                        `status` varchar(255) NOT NULL DEFAULT 'pending',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `salary_reminders_schedule_id_foreign` (`schedule_id`),
                        CONSTRAINT `salary_reminders_schedule_id_foreign` FOREIGN KEY (`schedule_id`) REFERENCES `salary_schedules` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'ai_matching_queue' => "CREATE TABLE IF NOT EXISTS `ai_matching_queue` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `employer_id` bigint(20) unsigned NOT NULL,
                        `preference_id` bigint(20) unsigned NOT NULL,
                        `status` varchar(255) NOT NULL DEFAULT 'pending',
                        `priority` int(11) NOT NULL DEFAULT '5',
                        `ai_confidence_score` int(11) DEFAULT NULL,
                        `requires_review` tinyint(1) NOT NULL DEFAULT '0',
                        `completed_at` timestamp NULL DEFAULT NULL,
                        `error_message` text DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'notifications_log' => "CREATE TABLE IF NOT EXISTS `notifications_log` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `user_id` bigint(20) unsigned NOT NULL,
                        `notification_id` bigint(20) unsigned DEFAULT NULL,
                        `channel` varchar(255) NOT NULL,
                        `status` varchar(255) NOT NULL DEFAULT 'pending',
                        `sent_at` timestamp NULL DEFAULT NULL,
                        `error_message` text DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `notifications_log_user_id_foreign` (`user_id`),
                        CONSTRAINT `notifications_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'standalone_verifications' => "CREATE TABLE IF NOT EXISTS `standalone_verifications` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `requester_id` bigint(20) unsigned NOT NULL,
                        `maid_nin` varchar(255) NOT NULL,
                        `maid_first_name` varchar(255) NOT NULL,
                        `maid_last_name` varchar(255) NOT NULL,
                        `amount` decimal(15,2) NOT NULL,
                        `payment_reference` varchar(255) NOT NULL,
                        `payment_status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
                        `gateway` varchar(255) NOT NULL DEFAULT 'paystack',
                        `verification_status` enum('pending','success','failed','review') NOT NULL DEFAULT 'pending',
                        `verification_data` json DEFAULT NULL,
                        `report_path` varchar(255) DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `standalone_verifications_payment_reference_unique` (`payment_reference`),
                        CONSTRAINT `standalone_verifications_requester_id_foreign` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'agent_prompt_templates' => "CREATE TABLE IF NOT EXISTS `agent_prompt_templates` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `agent_name` varchar(255) NOT NULL,
                        `template_name` varchar(255) NOT NULL,
                        `content` text NOT NULL,
                        `version` int(11) NOT NULL DEFAULT '1',
                        `is_active` tinyint(1) NOT NULL DEFAULT '1',
                        `created_by` bigint(20) unsigned DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'agent_knowledge_base' => "CREATE TABLE IF NOT EXISTS `agent_knowledge_base` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `title` varchar(255) NOT NULL,
                        `content` text NOT NULL,
                        `category` varchar(255) DEFAULT NULL,
                        `tags` json DEFAULT NULL,
                        `is_active` tinyint(1) NOT NULL DEFAULT '1',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'agent_channel_identities' => "CREATE TABLE IF NOT EXISTS `agent_channel_identities` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `channel` varchar(255) NOT NULL,
                        `external_id` varchar(255) DEFAULT NULL,
                        `display_name` varchar(255) DEFAULT NULL,
                        `phone` varchar(255) DEFAULT NULL,
                        `email` varchar(255) DEFAULT NULL,
                        `metadata` json DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'agent_conversations' => "CREATE TABLE IF NOT EXISTS `agent_conversations` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `channel_identity_id` bigint(20) unsigned NOT NULL,
                        `status` varchar(255) NOT NULL DEFAULT 'open',
                        `last_message_at` timestamp NULL DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        CONSTRAINT `agent_conversations_channel_identity_id_foreign` FOREIGN KEY (`channel_identity_id`) REFERENCES `agent_channel_identities` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'agent_messages' => "CREATE TABLE IF NOT EXISTS `agent_messages` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `conversation_id` bigint(20) unsigned NOT NULL,
                        `direction` varchar(255) NOT NULL,
                        `content` text NOT NULL,
                        `metadata` json DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        CONSTRAINT `agent_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `agent_conversations` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'agent_leads' => "CREATE TABLE IF NOT EXISTS `agent_leads` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `channel_identity_id` bigint(20) unsigned NOT NULL,
                        `conversation_id` bigint(20) unsigned DEFAULT NULL,
                        `status` varchar(255) NOT NULL DEFAULT 'new',
                        `phone` varchar(255) DEFAULT NULL,
                        `email` varchar(255) DEFAULT NULL,
                        `metadata` json DEFAULT NULL,
                        `converted_at` timestamp NULL DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        CONSTRAINT `agent_leads_channel_identity_id_foreign` FOREIGN KEY (`channel_identity_id`) REFERENCES `agent_channel_identities` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'user_events' => "CREATE TABLE IF NOT EXISTS `user_events` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `user_id` bigint(20) unsigned NOT NULL,
                        `event_type` varchar(255) NOT NULL,
                        `event_data` json DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `user_events_user_id_foreign` (`user_id`),
                        CONSTRAINT `user_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                ];

                foreach ($tablesToEnsure as $tableName => $sql) {
                    echo '<div class="step">';
                    if (!Schema::hasTable($tableName)) {
                        try {
                            DB::statement($sql);
                            echo "<strong>{$tableName}</strong>: <span class=\"success\">✅ Created</span>";
                        } catch (\Throwable $e) {
                            echo "<strong>{$tableName}</strong>: <span class=\"error\">❌ Failed — " . htmlspecialchars($e->getMessage()) . "</span>";
                        }
                    } else {
                        echo "<strong>{$tableName}</strong>: <span class=\"skipped\">⏭ Already exists</span>";
                    }
                    echo '</div>';
                }

                // Check for missing columns on existing tables
                echo '<h2>📋 Checking for missing columns...</h2>';

                $columnChecks = [
                    ['table' => 'users', 'column' => 'role', 'sql' => "ALTER TABLE users ADD COLUMN role VARCHAR(255) DEFAULT NULL AFTER status"],
                    ['table' => 'users', 'column' => 'last_login_at', 'sql' => "ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL AFTER status"],
                    ['table' => 'employer_preferences', 'column' => 'quiz_status', 'sql' => "ALTER TABLE employer_preferences ADD COLUMN quiz_status VARCHAR(255) DEFAULT NULL AFTER matching_status"],
                    ['table' => 'employer_preferences', 'column' => 'city', 'sql' => "ALTER TABLE employer_preferences ADD COLUMN city VARCHAR(255) DEFAULT NULL AFTER location"],
                    ['table' => 'maid_profiles', 'column' => 'nin', 'sql' => "ALTER TABLE maid_profiles ADD COLUMN nin VARCHAR(255) DEFAULT NULL AFTER user_id"],
                    ['table' => 'maid_profiles', 'column' => 'profile_completeness', 'sql' => "ALTER TABLE maid_profiles ADD COLUMN profile_completeness INT DEFAULT 0 AFTER account_name"],
                    ['table' => 'bookings', 'column' => 'payment_status', 'sql' => "ALTER TABLE bookings ADD COLUMN payment_status VARCHAR(255) NOT NULL DEFAULT 'pending' AFTER status"],
                    ['table' => 'matching_fee_payments', 'column' => 'payment_type', 'sql' => "ALTER TABLE matching_fee_payments ADD COLUMN payment_type VARCHAR(255) NOT NULL DEFAULT 'matching_fee' AFTER status"],
                    ['table' => 'salary_schedules', 'column' => 'payment_status', 'sql' => "ALTER TABLE salary_schedules ADD COLUMN payment_status VARCHAR(255) NOT NULL DEFAULT 'pending' AFTER monthly_salary"],
                    ['table' => 'salary_schedules', 'column' => 'next_salary_due_date', 'sql' => "ALTER TABLE salary_schedules ADD COLUMN next_salary_due_date DATE DEFAULT NULL AFTER payment_status"],
                    ['table' => 'salary_schedules', 'column' => 'first_salary_date', 'sql' => "ALTER TABLE salary_schedules ADD COLUMN first_salary_date DATE DEFAULT NULL AFTER next_salary_due_date"],
                    ['table' => 'salary_schedules', 'column' => 'reminder_count', 'sql' => "ALTER TABLE salary_schedules ADD COLUMN reminder_count INT NOT NULL DEFAULT 0 AFTER first_salary_date"],
                    ['table' => 'salary_schedules', 'column' => 'last_reminder_sent_at', 'sql' => "ALTER TABLE salary_schedules ADD COLUMN last_reminder_sent_at TIMESTAMP NULL DEFAULT NULL AFTER reminder_count"],
                    ['table' => 'maid_assignments', 'column' => 'status', 'sql' => "ALTER TABLE maid_assignments ADD COLUMN status VARCHAR(255) NOT NULL DEFAULT 'pending' AFTER preference_id"],
                    ['table' => 'maid_assignments', 'column' => 'notes', 'sql' => "ALTER TABLE maid_assignments ADD COLUMN notes TEXT DEFAULT NULL AFTER status"],
                    ['table' => 'notifications_log', 'column' => 'channel', 'sql' => "ALTER TABLE notifications_log ADD COLUMN channel VARCHAR(255) NOT NULL AFTER notification_id"],
                ];

                foreach ($columnChecks as $check) {
                    echo '<div class="step">';
                    if (Schema::hasTable($check['table'])) {
                        if (!Schema::hasColumn($check['table'], $check['column'])) {
                            try {
                                DB::statement($check['sql']);
                                echo "<strong>{$check['table']}.{$check['column']}</strong>: <span class=\"success\">✅ Column added</span>";
                            } catch (\Throwable $e) {
                                echo "<strong>{$check['table']}.{$check['column']}</strong>: <span class=\"warning\">⚠️ " . htmlspecialchars($e->getMessage()) . "</span>";
                            }
                        } else {
                            echo "<strong>{$check['table']}.{$check['column']}</strong>: <span class=\"skipped\">⏭ Already exists</span>";
                        }
                    } else {
                        echo "<strong>{$check['table']}.{$check['column']}</strong>: <span class=\"skipped\">⏭ Table does not exist (will be created above)</span>";
                    }
                    echo '</div>';
                }
                ?>

                <div class="alert alert-success">
                    <h3 class="success">🎉 Database tables ensured!</h3>
                    <p>All required tables and columns have been checked and created where missing.</p>
                </div>

            <?php elseif (isset($_GET['run_migrations'])): ?>
                <?php
                // ── STANDARD MIGRATION MODE ──
                // Run Laravel's built-in migration system
                echo '<h2>📦 Running Laravel Migrations...</h2>';

                ob_implicit_flush(true);
                ob_end_flush();

                echo '<div class="step">';
                echo "Starting migrations...<br><br>";

                try {
                    Artisan::call('migrate', ['--force' => true]);
                    $output = Artisan::output();
                    echo '<pre>' . htmlspecialchars($output) . '</pre>';
                    echo '<span class="success">✅ Migrations completed.</span>';
                } catch (\Throwable $e) {
                    echo '<span class="error">❌ Migration failed: ' . htmlspecialchars($e->getMessage()) . '</span>';
                    echo '<p class="warning">If migrations fail due to table/column conflicts, try the <a href="?ensure_tables&token=' . htmlspecialchars($providedToken) . '"><strong>Ensure Tables</strong></a> mode instead.</p>';
                }

                echo '</div>';

                // Clear caches
                echo '<div class="step">';
                echo "Clearing caches...<br>";
                try {
                    Artisan::call('route:clear');
                    Artisan::call('config:clear');
                    Artisan::call('cache:clear');
                    Artisan::call('view:clear');
                    echo '<span class="success">✅ All caches cleared.</span>';
                } catch (\Throwable $e) {
                    echo '<span class="warning">⚠️ Cache clear had issues (non-critical): ' . htmlspecialchars($e->getMessage()) . '</span>';
                }
                echo '</div>';
                ?>

            <?php else: ?>
                <h2>📊 Current Database Status</h2>

                <?php
                // Show migration status
                echo '<div class="step">';
                echo '<h3>Laravel Migrations</h3>';
                echo '<pre>';
                Artisan::call('migrate:status');
                echo htmlspecialchars(Artisan::output());
                echo '</pre>';
                echo '</div>';

                // Show existing tables
                echo '<div class="step">';
                echo '<h3>Existing Tables</h3>';
                $tables = DB::select('SHOW TABLES');
                $dbName = DB::getDatabaseName();
                $tableKey = "Tables_in_{$dbName}";
                echo '<table><tr><th>Table Name</th><th>Status</th></tr>';
                foreach ($tables as $table) {
                    $name = $table->$tableKey;
                    echo "<tr><td>{$name}</td><td><span class=\"badge badge-ran\">Exists</span></td></tr>";
                }
                echo '</table>';
                echo '</div>';
                ?>

                <h2>🚀 Actions</h2>
                <div class="alert alert-info">
                    Choose an action below. If standard migrations fail, use "Ensure Tables" mode which creates tables directly.
                </div>

                <p>
                    <a href="?run_migrations&token=<?php echo htmlspecialchars($providedToken); ?>" class="btn">📦 Run Standard Migrations</a>
                    <a href="?ensure_tables&token=<?php echo htmlspecialchars($providedToken); ?>" class="btn" style="background: #10b981;">🔧 Ensure All Tables Exist</a>
                </p>

            <?php endif; ?>

            <div class="alert alert-warning" style="margin-top: 30px;">
                <strong>⚠️ Security Reminder:</strong> Delete <code>run-migration.php</code> after use, or set a <code>MIGRATE_TOKEN</code> in your <code>.env</code> file to protect this page.
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
