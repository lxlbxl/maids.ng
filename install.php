/modelHello
<label for=""></label>
<?php
/**
 * Maids.ng Installation Wizard v2.0
 * 
 * This script automates the installation process on shared hosting.
 * Upload this file to your web root and access it via browser.
 * 
 * Requirements:
 * - PHP 8.2+
 * - MySQL 5.7+ or MariaDB 10.3+
 * - mod_rewrite enabled
 * - PDO PHP Extension
 * - OpenSSL PHP Extension
 * - Mbstring PHP Extension
 * - Tokenizer PHP Extension
 * - XML PHP Extension
 * - Ctype PHP Extension
 * - JSON PHP Extension
 * - BCMath PHP Extension
 */

session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Installation steps
$steps = [
    'welcome' => 'Welcome',
    'requirements' => 'System Requirements',
    'database' => 'Database Configuration',
    'app_config' => 'Application Configuration',
    'services' => 'Services Configuration',
    'install' => 'Installation',
    'complete' => 'Complete'
];

$currentStep = $_GET['step'] ?? 'welcome';
$errors = [];
$success = [];

// Handle session messages
if (isset($_SESSION['success'])) {
    $success[] = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errors[] = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Helper functions
function checkPHPVersion(): bool
{
    return version_compare(PHP_VERSION, '8.2.0', '>=');
}

function checkExtension(string $extension): bool
{
    return extension_loaded($extension);
}

function checkWritable(string $path): bool
{
    return is_writable($path);
}

function generateRandomKey(int $length = 32): string
{
    return base64_encode(random_bytes($length));
}

function generateDeploySecret(): string
{
    return bin2hex(random_bytes(32));
}

function testDatabaseConnection(array $config): bool
{
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function createDatabaseTables(array $config): bool
{
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Check if settings table already has data (indicates previous installation)
        $tableExists = $pdo->query("SHOW TABLES LIKE 'settings'")->fetch();
        $hasData = false;
        if ($tableExists) {
            $count = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
            $hasData = $count > 0;
        }

        // Only import database.sql if settings table is empty or doesn't exist
        if (!$hasData) {
            $sqlFile = __DIR__ . '/database/database.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
            }
        }

        // Create additional tables that might not be in database.sql
        createAdditionalTables($pdo);

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        return true;
    } catch (PDOException $e) {
        global $errors;
        $errors[] = "Database error: " . $e->getMessage();
        return false;
    }
}

function createAdditionalTables(PDO $pdo): void
{
    // Roles and Permissions (Spatie)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `roles` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `guard_name` varchar(255) NOT NULL DEFAULT 'web',
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `roles_name_guard_name_unique` (`name`, `guard_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `permissions` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `guard_name` varchar(255) NOT NULL DEFAULT 'web',
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `permissions_name_guard_name_unique` (`name`, `guard_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `model_has_permissions` (
        `permission_id` bigint(20) unsigned NOT NULL,
        `model_type` varchar(255) NOT NULL,
        `model_id` bigint(20) unsigned NOT NULL,
        PRIMARY KEY (`permission_id`, `model_id`, `model_type`),
        KEY `model_has_permissions_model_id_model_type_index` (`model_id`, `model_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `model_has_roles` (
        `role_id` bigint(20) unsigned NOT NULL,
        `model_type` varchar(255) NOT NULL,
        `model_id` bigint(20) unsigned NOT NULL,
        PRIMARY KEY (`role_id`, `model_id`, `model_type`),
        KEY `model_has_roles_model_id_model_type_index` (`model_id`, `model_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `role_has_permissions` (
        `permission_id` bigint(20) unsigned NOT NULL,
        `role_id` bigint(20) unsigned NOT NULL,
        PRIMARY KEY (`permission_id`, `role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Employer Wallets
    $pdo->exec("CREATE TABLE IF NOT EXISTS `employer_wallets` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `employer_id` bigint(20) unsigned NOT NULL,
        `balance` decimal(12,2) NOT NULL DEFAULT '0.00',
        `escrow_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
        `total_deposited` decimal(12,2) NOT NULL DEFAULT '0.00',
        `total_spent` decimal(12,2) NOT NULL DEFAULT '0.00',
        `total_refunded` decimal(12,2) NOT NULL DEFAULT '0.00',
        `currency` varchar(3) NOT NULL DEFAULT 'NGN',
        `timezone` varchar(50) DEFAULT 'Africa/Lagos',
        `is_active` tinyint(1) NOT NULL DEFAULT '1',
        `last_activity_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `employer_wallets_employer_id_unique` (`employer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Maid Wallets
    $pdo->exec("CREATE TABLE IF NOT EXISTS `maid_wallets` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `maid_id` bigint(20) unsigned NOT NULL,
        `balance` decimal(12,2) NOT NULL DEFAULT '0.00',
        `total_earned` decimal(12,2) NOT NULL DEFAULT '0.00',
        `total_withdrawn` decimal(12,2) NOT NULL DEFAULT '0.00',
        `pending_withdrawal` decimal(12,2) NOT NULL DEFAULT '0.00',
        `salary_day` int(11) DEFAULT NULL,
        `employment_start_date` date DEFAULT NULL,
        `next_salary_due_date` date DEFAULT NULL,
        `bank_name` varchar(255) DEFAULT NULL,
        `account_number` varchar(255) DEFAULT NULL,
        `account_name` varchar(255) DEFAULT NULL,
        `currency` varchar(3) NOT NULL DEFAULT 'NGN',
        `is_active` tinyint(1) NOT NULL DEFAULT '1',
        `last_activity_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `maid_wallets_maid_id_unique` (`maid_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Wallet Transactions
    $pdo->exec("CREATE TABLE IF NOT EXISTS `wallet_transactions` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `wallet_type` enum('employer','maid') NOT NULL,
        `employer_id` bigint(20) unsigned DEFAULT NULL,
        `maid_id` bigint(20) unsigned DEFAULT NULL,
        `transaction_type` varchar(50) NOT NULL,
        `amount` decimal(12,2) NOT NULL,
        `balance_before` decimal(12,2) NOT NULL,
        `balance_after` decimal(12,2) NOT NULL,
        `description` text,
        `reference_id` bigint(20) unsigned DEFAULT NULL,
        `reference_type` varchar(50) DEFAULT NULL,
        `payment_reference` varchar(255) DEFAULT NULL,
        `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
        `failure_reason` text,
        `processed_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `wallet_transactions_employer_id_index` (`employer_id`),
        KEY `wallet_transactions_maid_id_index` (`maid_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Maid Assignments
    $pdo->exec("CREATE TABLE IF NOT EXISTS `maid_assignments` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `employer_id` bigint(20) unsigned NOT NULL,
        `maid_id` bigint(20) unsigned NOT NULL,
        `preference_id` bigint(20) unsigned DEFAULT NULL,
        `assigned_by` bigint(20) unsigned DEFAULT NULL,
        `assigned_by_type` enum('ai','admin','employer') DEFAULT 'admin',
        `assignment_type` enum('direct_selection','guarantee_match','manual','auto') DEFAULT 'manual',
        `status` enum('pending_acceptance','accepted','rejected','completed','cancelled') DEFAULT 'pending_acceptance',
        `matching_fee_paid` tinyint(1) NOT NULL DEFAULT '0',
        `matching_fee_amount` decimal(10,2) DEFAULT NULL,
        `guarantee_match` tinyint(1) NOT NULL DEFAULT '0',
        `guarantee_period_days` int(11) DEFAULT '90',
        `ai_match_score` decimal(5,2) DEFAULT NULL,
        `ai_match_reasoning` json DEFAULT NULL,
        `employer_accepted_at` timestamp NULL DEFAULT NULL,
        `employer_rejected_at` timestamp NULL DEFAULT NULL,
        `employer_responded_at` timestamp NULL DEFAULT NULL,
        `rejection_reason` text,
        `started_at` timestamp NULL DEFAULT NULL,
        `completed_at` timestamp NULL DEFAULT NULL,
        `cancelled_at` timestamp NULL DEFAULT NULL,
        `cancellation_reason` text,
        `cancelled_by` bigint(20) unsigned DEFAULT NULL,
        `reminder_sent` tinyint(1) NOT NULL DEFAULT '0',
        `ended_at` timestamp NULL DEFAULT NULL,
        `response_deadline` timestamp NULL DEFAULT NULL,
        `context_json` json DEFAULT NULL,
        `matched_until` timestamp NULL DEFAULT NULL,
        `salary_amount` decimal(10,2) DEFAULT NULL,
        `salary_currency` varchar(3) DEFAULT 'NGN',
        `job_location` varchar(255) DEFAULT NULL,
        `job_type` varchar(50) DEFAULT NULL,
        `special_requirements` json DEFAULT NULL,
        `notes` text,
        `refund_amount` decimal(10,2) DEFAULT NULL,
        `refund_transaction_id` bigint(20) unsigned DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `maid_assignments_employer_id_index` (`employer_id`),
        KEY `maid_assignments_maid_id_index` (`maid_id`),
        KEY `maid_assignments_status_index` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Salary Schedules
    $pdo->exec("CREATE TABLE IF NOT EXISTS `salary_schedules` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `assignment_id` bigint(20) unsigned NOT NULL,
        `employer_id` bigint(20) unsigned NOT NULL,
        `maid_id` bigint(20) unsigned NOT NULL,
        `monthly_salary` decimal(10,2) NOT NULL,
        `salary_day` int(11) NOT NULL DEFAULT '28',
        `employment_start_date` date DEFAULT NULL,
        `first_salary_date` date DEFAULT NULL,
        `current_period_start` date DEFAULT NULL,
        `current_period_end` date DEFAULT NULL,
        `next_salary_due_date` date DEFAULT NULL,
        `reminder_days_before` int(11) NOT NULL DEFAULT '3',
        `last_reminder_sent_at` timestamp NULL DEFAULT NULL,
        `next_reminder_scheduled_at` timestamp NULL DEFAULT NULL,
        `reminder_3_days_sent` tinyint(1) NOT NULL DEFAULT '0',
        `reminder_1_day_sent` tinyint(1) NOT NULL DEFAULT '0',
        `reminder_due_sent` tinyint(1) NOT NULL DEFAULT '0',
        `payment_status` enum('pending','reminder_sent','payment_initiated','paid','overdue','disputed') DEFAULT 'pending',
        `escrow_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
        `escrow_funded` tinyint(1) NOT NULL DEFAULT '0',
        `escrow_funded_at` timestamp NULL DEFAULT NULL,
        `reminder_count` int(11) NOT NULL DEFAULT '0',
        `escalation_level` int(11) NOT NULL DEFAULT '0',
        `last_escalation_at` timestamp NULL DEFAULT NULL,
        `salary_breakdown` json DEFAULT NULL,
        `special_notes` text,
        `is_active` tinyint(1) NOT NULL DEFAULT '1',
        `created_by` bigint(20) unsigned DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `salary_schedules_assignment_id_index` (`assignment_id`),
        KEY `salary_schedules_employer_id_index` (`employer_id`),
        KEY `salary_schedules_maid_id_index` (`maid_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Salary Payments
    $pdo->exec("CREATE TABLE IF NOT EXISTS `salary_payments` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `salary_schedule_id` bigint(20) unsigned NOT NULL,
        `assignment_id` bigint(20) unsigned NOT NULL,
        `employer_id` bigint(20) unsigned NOT NULL,
        `maid_id` bigint(20) unsigned NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `payment_method` varchar(50) DEFAULT NULL,
        `payment_reference` varchar(255) DEFAULT NULL,
        `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
        `paid_at` timestamp NULL DEFAULT NULL,
        `period_start` date DEFAULT NULL,
        `period_end` date DEFAULT NULL,
        `notes` text,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Notification Logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notification_logs` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) unsigned DEFAULT NULL,
        `user_type` enum('employer','maid','admin') DEFAULT NULL,
        `notification_type` varchar(100) NOT NULL,
        `channel` enum('sms','email','whatsapp','push','in_app') NOT NULL,
        `subject` varchar(255) DEFAULT NULL,
        `content` text,
        `context_json` json DEFAULT NULL,
        `reference_id` bigint(20) unsigned DEFAULT NULL,
        `reference_type` varchar(50) DEFAULT NULL,
        `scheduled_at` timestamp NULL DEFAULT NULL,
        `sent_at` timestamp NULL DEFAULT NULL,
        `delivered_at` timestamp NULL DEFAULT NULL,
        `read_at` timestamp NULL DEFAULT NULL,
        `status` enum('pending','scheduled','sent','delivered','failed','cancelled') DEFAULT 'pending',
        `delivery_status` enum('pending','sent','delivered','failed','bounced','rejected') DEFAULT 'pending',
        `delivery_response` json DEFAULT NULL,
        `follow_up_sequence` int(11) DEFAULT NULL,
        `parent_notification_id` bigint(20) unsigned DEFAULT NULL,
        `requires_follow_up` tinyint(1) NOT NULL DEFAULT '0',
        `follow_up_scheduled_at` timestamp NULL DEFAULT NULL,
        `ai_generated` tinyint(1) NOT NULL DEFAULT '0',
        `ai_prompt_used` text,
        `local_time_sent` timestamp NULL DEFAULT NULL,
        `timezone` varchar(50) DEFAULT 'Africa/Lagos',
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `notification_logs_user_id_index` (`user_id`),
        KEY `notification_logs_status_index` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // AI Matching Queue
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ai_matching_queue` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `job_id` varchar(255) NOT NULL,
        `job_type` enum('auto_match','replacement_search','guarantee_match','status_check','reminder_send','salary_reminder','follow_up') DEFAULT 'auto_match',
        `employer_id` bigint(20) unsigned DEFAULT NULL,
        `maid_id` bigint(20) unsigned DEFAULT NULL,
        `preference_id` bigint(20) unsigned DEFAULT NULL,
        `assignment_id` bigint(20) unsigned DEFAULT NULL,
        `priority` int(11) NOT NULL DEFAULT '5',
        `scheduled_at` timestamp NULL DEFAULT NULL,
        `started_at` timestamp NULL DEFAULT NULL,
        `completed_at` timestamp NULL DEFAULT NULL,
        `status` enum('pending','scheduled','processing','completed','failed','cancelled','paused') DEFAULT 'pending',
        `attempt_count` int(11) NOT NULL DEFAULT '0',
        `max_attempts` int(11) NOT NULL DEFAULT '3',
        `next_attempt_at` timestamp NULL DEFAULT NULL,
        `retry_delay_minutes` int(11) NOT NULL DEFAULT '30',
        `payload` json DEFAULT NULL,
        `result` json DEFAULT NULL,
        `match_candidates` json DEFAULT NULL,
        `selected_maid_id` bigint(20) unsigned DEFAULT NULL,
        `ai_confidence_score` decimal(5,2) DEFAULT NULL,
        `ai_reasoning` text,
        `ai_analysis_data` json DEFAULT NULL,
        `last_error` text,
        `error_log` json DEFAULT NULL,
        `failure_category` varchar(50) DEFAULT NULL,
        `processed_by_instance` varchar(255) DEFAULT NULL,
        `processing_duration_ms` int(11) DEFAULT NULL,
        `worker_pid` int(11) DEFAULT NULL,
        `context_snapshot` json DEFAULT NULL,
        `parent_job_id` bigint(20) unsigned DEFAULT NULL,
        `job_chain_sequence` int(11) DEFAULT NULL,
        `requires_review` tinyint(1) NOT NULL DEFAULT '0',
        `reviewed_by` bigint(20) unsigned DEFAULT NULL,
        `reviewed_at` timestamp NULL DEFAULT NULL,
        `review_notes` text,
        `review_decision` enum('approved','rejected','needs_revision') DEFAULT NULL,
        `notification_sent` tinyint(1) NOT NULL DEFAULT '0',
        `notification_sent_at` timestamp NULL DEFAULT NULL,
        `notification_channel` varchar(50) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `ai_matching_queue_job_id_unique` (`job_id`),
        KEY `ai_matching_queue_status_index` (`status`),
        KEY `ai_matching_queue_employer_id_index` (`employer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `key` varchar(255) NOT NULL,
        `value` text,
        `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
        `group` varchar(50) NOT NULL DEFAULT 'general',
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `settings_key_unique` (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Agent Activity Logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS `agent_activity_logs` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `agent_name` varchar(100) NOT NULL,
        `activity_type` varchar(100) NOT NULL,
        `status` enum('success','failed','pending') DEFAULT 'pending',
        `input_data` json DEFAULT NULL,
        `output_data` json DEFAULT NULL,
        `error_message` text,
        `processing_time_ms` int(11) DEFAULT NULL,
        `reference_id` bigint(20) unsigned DEFAULT NULL,
        `reference_type` varchar(50) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Support Tickets
    $pdo->exec("CREATE TABLE IF NOT EXISTS `support_tickets` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) unsigned NOT NULL,
        `user_type` enum('employer','maid') NOT NULL,
        `subject` varchar(255) NOT NULL,
        `description` text NOT NULL,
        `category` varchar(100) DEFAULT NULL,
        `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
        `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
        `assigned_to` bigint(20) unsigned DEFAULT NULL,
        `resolved_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Disputes
    $pdo->exec("CREATE TABLE IF NOT EXISTS `disputes` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `booking_id` bigint(20) unsigned DEFAULT NULL,
        `assignment_id` bigint(20) unsigned DEFAULT NULL,
        `raised_by` bigint(20) unsigned NOT NULL,
        `raised_by_type` enum('employer','maid') NOT NULL,
        `against` bigint(20) unsigned NOT NULL,
        `against_type` enum('employer','maid') NOT NULL,
        `subject` varchar(255) NOT NULL,
        `description` text NOT NULL,
        `status` enum('open','under_review','resolved','closed') DEFAULT 'open',
        `resolution` text,
        `resolved_by` bigint(20) unsigned DEFAULT NULL,
        `resolved_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Standalone Verifications
    $pdo->exec("CREATE TABLE IF NOT EXISTS `standalone_verifications` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `requester_id` bigint(20) unsigned NOT NULL,
        `maid_nin` varchar(50) NOT NULL,
        `maid_first_name` varchar(255) NOT NULL,
        `maid_last_name` varchar(255) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `payment_reference` varchar(255) NOT NULL,
        `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
        `gateway` varchar(50) DEFAULT 'paystack',
        `verification_status` enum('pending','success','failed','review') DEFAULT 'pending',
        `verification_data` json DEFAULT NULL,
        `report_path` varchar(255) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `standalone_verifications_payment_reference_unique` (`payment_reference`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Cache, Jobs, Failed Jobs
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cache` (
        `key` varchar(255) NOT NULL,
        `value` mediumtext NOT NULL,
        `expiration` int(11) NOT NULL,
        PRIMARY KEY (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `cache_locks` (
        `key` varchar(255) NOT NULL,
        `owner` varchar(255) NOT NULL,
        `expiration` int(11) NOT NULL,
        PRIMARY KEY (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `jobs` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `queue` varchar(255) NOT NULL,
        `payload` longtext NOT NULL,
        `attempts` tinyint(3) unsigned NOT NULL,
        `reserved_at` int(10) unsigned DEFAULT NULL,
        `available_at` int(10) unsigned NOT NULL,
        `created_at` int(10) unsigned NOT NULL,
        PRIMARY KEY (`id`),
        KEY `jobs_queue_index` (`queue`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `failed_jobs` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `uuid` varchar(255) NOT NULL,
        `connection` text NOT NULL,
        `queue` text NOT NULL,
        `payload` longtext NOT NULL,
        `exception` longtext NOT NULL,
        `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `job_batches` (
        `id` varchar(255) NOT NULL,
        `name` varchar(255) NOT NULL,
        `total_jobs` int(11) NOT NULL,
        `pending_jobs` int(11) NOT NULL,
        `failed_jobs` int(11) NOT NULL,
        `failed_job_ids` json NOT NULL,
        `options` mediumtext,
        `cancelled_at` int(11) DEFAULT NULL,
        `created_at` int(11) NOT NULL,
        `finished_at` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Salary Reminders
    $pdo->exec("CREATE TABLE IF NOT EXISTS `salary_reminders` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `salary_schedule_id` bigint(20) unsigned NOT NULL,
        `employer_id` bigint(20) unsigned NOT NULL,
        `maid_id` bigint(20) unsigned NOT NULL,
        `reminder_type` enum('3_days','1_day','due','overdue') NOT NULL,
        `sent_at` timestamp NULL DEFAULT NULL,
        `status` enum('pending','sent','failed') DEFAULT 'pending',
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function seedSettings(PDO $pdo): void
{
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
        ['deploy_secret', generateDeploySecret(), 'general', 1],

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

        // Agent Channels (Meta, WhatsApp, Email Polling)
        ['meta_page_access_token', '', 'agents', 1],
        ['meta_webhook_verify_token', '', 'agents', 1],
        ['meta_app_secret', '', 'agents', 1],
        ['meta_default_reply', 'Thank you for your message. Our team will respond shortly.', 'agents', 0],
        ['whatsapp_from_number', '', 'agents', 0],
        ['whatsapp_default_reply', 'Thank you for your message. Our team will respond shortly.', 'agents', 0],
        ['email_imap_host', '', 'agents', 0],
        ['email_imap_port', '993', 'agents', 0],
        ['email_imap_username', '', 'agents', 1],
        ['email_imap_password', '', 'agents', 1],
        ['email_imap_folder', 'INBOX', 'agents', 0],
        ['email_poll_interval_seconds', '300', 'agents', 0],
        ['email_default_reply', 'Thank you for your email. Our team will respond shortly.', 'agents', 0],
    ];

    // Use INSERT IGNORE to skip settings that already exist (from database.sql import)
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`, `group`, is_encrypted, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");

    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }

    // Update existing settings with new values if they differ
    $updateStmt = $pdo->prepare("UPDATE settings SET `value` = ?, is_encrypted = ?, updated_at = NOW() WHERE `key` = ? AND (`value` != ? OR is_encrypted != ?)");
    foreach ($settings as $setting) {
        $updateStmt->execute([$setting[1], $setting[3], $setting[0], $setting[1], $setting[3]]);
    }
}

function createAdminUser(array $dbConfig, array $appConfig): bool
{
    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Hash password using Laravel's default (Bcrypt)
        $hashedPassword = password_hash($appConfig['admin_password'], PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        // Check if user exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$appConfig['admin_email']]);
        $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            $userId = $existingUser['id'];
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $now, $userId]);
        } else {
            // Insert Admin User (no 'role' column - roles are in model_has_roles)
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, status, created_at, updated_at) VALUES (?, ?, ?, 'active', ?, ?)");
            $stmt->execute(['Administrator', $appConfig['admin_email'], $hashedPassword, $now, $now]);
            $userId = $pdo->lastInsertId();
        }

        // Create Core Roles
        $roles = ['admin', 'maid', 'employer'];
        foreach ($roles as $roleName) {
            $pdo->prepare("INSERT IGNORE INTO roles (name, guard_name, created_at, updated_at) VALUES (?, 'web', ?, ?)")
                ->execute([$roleName, $now, $now]);
        }

        // Assign Admin Role
        $roleIdStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin' AND guard_name = 'web' LIMIT 1");
        $roleIdStmt->execute();
        $role = $roleIdStmt->fetch(PDO::FETCH_ASSOC);

        if ($role) {
            $pdo->prepare("INSERT IGNORE INTO model_has_roles (role_id, model_type, model_id) VALUES (?, 'App\\\Models\\\User', ?)")
                ->execute([$role['id'], $userId]);
        }

        return true;
    } catch (PDOException $e) {
        global $errors;
        $errors[] = "Admin creation failed: " . $e->getMessage();
        return false;
    }
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($currentStep) {
        case 'database':
            $dbConfig = [
                'host' => $_POST['db_host'] ?? 'localhost',
                'port' => $_POST['db_port'] ?? '3306',
                'database' => $_POST['db_name'] ?? '',
                'username' => $_POST['db_user'] ?? '',
                'password' => $_POST['db_pass'] ?? ''
            ];

            if (testDatabaseConnection($dbConfig)) {
                $_SESSION['db_config'] = $dbConfig;
                $_SESSION['success'] = "Database connection successful!";
                header("Location: ?step=app_config");
                exit;
            } else {
                $errors[] = "Could not connect to database. Please check your credentials.";
            }
            break;

        case 'app_config':
            $_SESSION['app_config'] = [
                'app_name' => $_POST['app_name'] ?? 'Maids.ng',
                'app_url' => $_POST['app_url'] ?? '',
                'app_env' => $_POST['app_env'] ?? 'production',
                'admin_email' => $_POST['admin_email'] ?? '',
                'admin_password' => $_POST['admin_password'] ?? ''
            ];
            header("Location: ?step=services");
            exit;
            break;

        case 'services':
            $_SESSION['services_config'] = [
                // Payment
                'paystack_public_key' => $_POST['paystack_public_key'] ?? '',
                'paystack_secret_key' => $_POST['paystack_secret_key'] ?? '',
                'flutterwave_public_key' => $_POST['flutterwave_public_key'] ?? '',
                'flutterwave_secret_key' => $_POST['flutterwave_secret_key'] ?? '',

                // SMS
                'sms_provider' => $_POST['sms_provider'] ?? 'log',
                'termii_api_key' => $_POST['termii_api_key'] ?? '',
                'termii_sender_id' => $_POST['termii_sender_id'] ?? 'MaidsNG',
                'twilio_sid' => $_POST['twilio_sid'] ?? '',
                'twilio_token' => $_POST['twilio_token'] ?? '',
                'twilio_from' => $_POST['twilio_from'] ?? '',

                // AI
                'ai_provider' => $_POST['ai_provider'] ?? 'openai',
                'openai_key' => $_POST['openai_key'] ?? '',
                'openrouter_key' => $_POST['openrouter_key'] ?? '',

                // Email
                'mail_host' => $_POST['mail_host'] ?? '',
                'mail_port' => $_POST['mail_port'] ?? '587',
                'mail_username' => $_POST['mail_username'] ?? '',
                'mail_password' => $_POST['mail_password'] ?? '',

                // Agent Channels — Meta
                'meta_page_access_token' => $_POST['meta_page_access_token'] ?? '',
                'meta_webhook_verify_token' => $_POST['meta_webhook_verify_token'] ?? '',
                'meta_app_secret' => $_POST['meta_app_secret'] ?? '',

                // Agent Channels — WhatsApp
                'whatsapp_from_number' => $_POST['whatsapp_from_number'] ?? '',

                // Agent Channels — Email Polling (IMAP)
                'email_imap_host' => $_POST['email_imap_host'] ?? '',
                'email_imap_port' => $_POST['email_imap_port'] ?? '993',
                'email_imap_username' => $_POST['email_imap_username'] ?? '',
                'email_imap_password' => $_POST['email_imap_password'] ?? '',
            ];
            header("Location: ?step=install");
            exit;
            break;

        case 'install':
            // Validate session data exists
            if (empty($_SESSION['db_config']) || empty($_SESSION['app_config']) || empty($_SESSION['services_config'])) {
                $errors[] = "Session data missing. Please go back and complete all previous steps (Database, App Config, Services).";
                $errors[] = "If the problem persists, your hosting may not support PHP sessions. Please contact your hosting provider.";
                break;
            }

            // Perform installation
            $installed = true;

            // 1. Create .env file
            $envContent = generateEnvFile($_SESSION['db_config'], $_SESSION['app_config'], $_SESSION['services_config']);
            if (!file_put_contents(__DIR__ . '/.env', $envContent)) {
                $errors[] = "Could not create .env file. Please check file permissions.";
                $installed = false;
            }

            // 2. Create database tables
            if ($installed && !createDatabaseTables($_SESSION['db_config'])) {
                $installed = false;
            }

            // 3. Seed settings
            if ($installed) {
                try {
                    $dsn = "mysql:host={$_SESSION['db_config']['host']};port={$_SESSION['db_config']['port']};dbname={$_SESSION['db_config']['database']}";
                    $pdo = new PDO($dsn, $_SESSION['db_config']['username'], $_SESSION['db_config']['password']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    seedSettings($pdo);
                } catch (PDOException $e) {
                    $errors[] = "Settings seeding failed: " . $e->getMessage();
                }
            }

            // 4. Create admin user
            if ($installed && !createAdminUser($_SESSION['db_config'], $_SESSION['app_config'])) {
                $installed = false;
            }

            // 5. Create storage directories
            if ($installed) {
                $dirs = [
                    'storage/app',
                    'storage/app/public',
                    'storage/framework/cache',
                    'storage/framework/cache/data',
                    'storage/framework/sessions',
                    'storage/framework/views',
                    'storage/logs',
                    'bootstrap/cache'
                ];

                foreach ($dirs as $dir) {
                    $path = __DIR__ . '/' . $dir;
                    if (!is_dir($path)) {
                        mkdir($path, 0755, true);
                    }
                }
            }

            // 6. Set permissions
            if ($installed) {
                chmod(__DIR__ . '/storage', 0755);
                chmod(__DIR__ . '/bootstrap/cache', 0755);

                // Create storage link
                if (!file_exists(__DIR__ . '/public/storage')) {
                    @symlink(__DIR__ . '/storage/app/public', __DIR__ . '/public/storage');
                }
            }

            // 7. Create .htaccess files for shared hosting
            if ($installed) {
                $rootHtaccess = "<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>";
                if (!file_exists(__DIR__ . '/.htaccess')) {
                    file_put_contents(__DIR__ . '/.htaccess', $rootHtaccess);
                }

                $publicHtaccess = "<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Index
    </IfModule>

    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>";
                if (!file_exists(__DIR__ . '/public/.htaccess')) {
                    file_put_contents(__DIR__ . '/public/.htaccess', $publicHtaccess);
                }
            }

            if ($installed) {
                $_SESSION['installed'] = true;
                $_SESSION['success'] = "Installation completed successfully!";
                header("Location: ?step=complete");
                exit;
            }
            break;
    }
}

function generateEnvFile(array $dbConfig, array $appConfig, array $servicesConfig): string
{
    $appKey = 'base64:' . generateRandomKey(32);
    $deploySecret = generateDeploySecret();

    $paystackPublic = $servicesConfig['paystack_public_key'] ?? '';
    $paystackSecret = $servicesConfig['paystack_secret_key'] ?? '';
    $flutterwavePublic = $servicesConfig['flutterwave_public_key'] ?? '';
    $flutterwaveSecret = $servicesConfig['flutterwave_secret_key'] ?? '';
    $termiiKey = $servicesConfig['termii_api_key'] ?? '';
    $twilioSid = $servicesConfig['twilio_sid'] ?? '';
    $twilioToken = $servicesConfig['twilio_token'] ?? '';
    $openaiKey = $servicesConfig['openai_key'] ?? '';
    $openrouterKey = $servicesConfig['openrouter_key'] ?? '';
    $mailHost = $servicesConfig['mail_host'] ?? '';
    $mailPort = $servicesConfig['mail_port'] ?? '587';
    $mailUser = $servicesConfig['mail_username'] ?? '';
    $mailPass = $servicesConfig['mail_password'] ?? '';

    // Agent Channel credentials (stored in .env as fallback)
    $metaPageToken = $servicesConfig['meta_page_access_token'] ?? '';
    $metaWebhookToken = $servicesConfig['meta_webhook_verify_token'] ?? '';
    $metaAppSecret = $servicesConfig['meta_app_secret'] ?? '';
    $whatsappFrom = $servicesConfig['whatsapp_from_number'] ?? '';
    $emailImapHost = $servicesConfig['email_imap_host'] ?? '';
    $emailImapPort = $servicesConfig['email_imap_port'] ?? '993';
    $emailImapUser = $servicesConfig['email_imap_username'] ?? '';
    $emailImapPass = $servicesConfig['email_imap_password'] ?? '';

    return <<<ENV
APP_NAME="{$appConfig['app_name']}"
APP_ENV={$appConfig['app_env']}
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL={$appConfig['app_url']}
APP_TIMEZONE=Africa/Lagos

LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null

DB_CONNECTION=mysql
DB_HOST={$dbConfig['host']}
DB_PORT={$dbConfig['port']}
DB_DATABASE={$dbConfig['database']}
DB_USERNAME={$dbConfig['username']}
DB_PASSWORD={$dbConfig['password']}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST={$mailHost}
MAIL_PORT={$mailPort}
MAIL_USERNAME={$mailUser}
MAIL_PASSWORD={$mailPass}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@maids.ng"
MAIL_FROM_NAME="{$appConfig['app_name']}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="\${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="\${PUSHER_HOST}"
VITE_PUSHER_PORT="\${PUSHER_PORT}"
VITE_PUSHER_SCHEME="\${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="\${PUSHER_APP_CLUSTER}"

# Payment Gateways
PAYSTACK_PUBLIC_KEY={$paystackPublic}
PAYSTACK_SECRET_KEY={$paystackSecret}
PAYSTACK_PAYMENT_URL=https://api.paystack.co
MERCHANT_EMAIL=

FLUTTERWAVE_PUBLIC_KEY={$flutterwavePublic}
FLUTTERWAVE_SECRET_KEY={$flutterwaveSecret}
FLUTTERWAVE_ENCRYPTION_KEY=
FLUTTERWAVE_PAYMENT_URL=https://api.flutterwave.com/v3

# AI Services
OPENROUTER_API_KEY={$openrouterKey}
OPENAI_API_KEY={$openaiKey}
AI_PROVIDER=openai

# SMS Configuration
SMS_PROVIDER={$servicesConfig['sms_provider']}
TERMII_API_KEY={$termiiKey}
TERMII_SENDER_ID={$servicesConfig['termii_sender_id']}
TERMII_URL=https://api.ng.termii.com/api

TWILIO_SID={$twilioSid}
TWILIO_AUTH_TOKEN={$twilioToken}
TWILIO_FROM={$servicesConfig['twilio_from']}

AFRICASTALKING_USERNAME=
AFRICASTALKING_API_KEY=
AFRICASTALKING_FROM=MaidsNG

# Verification Services
NIN_VERIFICATION_ENABLED=false
NIN_API_KEY=
NIN_API_URL=
BACKGROUND_CHECK_ENABLED=false
BACKGROUND_CHECK_API_KEY=

# Agent Channel Configuration (Meta, WhatsApp, Email Polling)
# These are fallbacks — primary config is in the database settings table (admin-configurable)
META_PAGE_ACCESS_TOKEN={$metaPageToken}
META_WEBHOOK_VERIFY_TOKEN={$metaWebhookToken}
META_APP_SECRET={$metaAppSecret}
WHATSAPP_FROM_NUMBER={$whatsappFrom}
EMAIL_IMAP_HOST={$emailImapHost}
EMAIL_IMAP_PORT={$emailImapPort}
EMAIL_IMAP_USERNAME={$emailImapUser}
EMAIL_IMAP_PASSWORD={$emailImapPass}

# Deployment
DEPLOY_SECRET={$deploySecret}
ENV;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maids.ng Installation Wizard v2.0</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 900px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
        }

        .progress-bar {
            display: flex;
            background: #f3f4f6;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .progress-bar li {
            flex: 1;
            text-align: center;
            padding: 15px 10px;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            position: relative;
        }

        .progress-bar li.active {
            background: #667eea;
            color: white;
        }

        .progress-bar li.completed {
            background: #10b981;
            color: white;
        }

        .content {
            padding: 40px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6b7280;
            margin-left: 10px;
        }

        .requirement-list {
            list-style: none;
        }

        .requirement-list li {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .requirement-list li:last-child {
            border-bottom: none;
        }

        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pass {
            background: #d1fae5;
            color: #065f46;
        }

        .status-fail {
            background: #fee2e2;
            color: #991b1b;
        }

        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }

        .info-box h3 {
            color: #1e40af;
            margin-bottom: 10px;
        }

        .info-box p {
            color: #1e3a8a;
            line-height: 1.6;
        }

        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #374151;
            margin: 30px 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .help-text {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }

        @media (max-width: 600px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🏠 Maids.ng Installation v2.0</h1>
            <p>Complete setup wizard for shared hosting deployment</p>
        </div>

        <ul class="progress-bar">
            <?php
            $stepReached = false;
            foreach ($steps as $key => $label):
                $class = '';
                if ($key === $currentStep) {
                    $class = 'active';
                    $stepReached = true;
                } elseif (!$stepReached) {
                    $class = 'completed';
                }
                ?>
                <li class="<?php echo $class; ?>">
                    <?php echo $label; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="content">
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error">❌
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <?php foreach ($success as $msg): ?>
                    <div class="alert alert-success">✅
                        <?php echo htmlspecialchars($msg); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php switch ($currentStep):
                case 'welcome': ?>
                    <div class="info-box">
                        <h3>Welcome to Maids.ng v2.0!</h3>
                        <p>This wizard will guide you through the complete installation process on your shared hosting. The
                            platform now includes:</p>
                        <ul style="margin-top: 10px; margin-left: 20px; line-height: 1.8;">
                            <li>AI-Powered Matching Engine</li>
                            <li>Dual Wallet System (Employer & Maid)</li>
                            <li>Automated Salary Management</li>
                            <li>Multi-Provider SMS (Termii, Twilio, Africa's Talking)</li>
                            <li>Payment Integration (Paystack, Flutterwave)</li>
                            <li>Comprehensive Notification System</li>
                            <li>Ambassador AI Agent (Web Chat, Meta DM, Email)</li>
                            <li>Agent Channel Configuration (Admin UI)</li>
                        </ul>
                    </div>

                    <p style="margin-bottom: 20px;"><strong>Requirements:</strong></p>
                    <ol style="margin-left: 20px; line-height: 2;">
                        <li>MySQL database credentials</li>
                        <li>FTP/cPanel access to upload files</li>
                        <li>PHP 8.2 or higher</li>
                        <li>Payment gateway API keys (optional)</li>
                        <li>SMS provider API keys (optional)</li>
                    </ol>

                    <form method="get" style="margin-top: 30px;">
                        <input type="hidden" name="step" value="requirements">
                        <button type="submit" class="btn">Start Installation →</button>
                    </form>
                    <?php break; ?>

                <?php case 'requirements':
                    $requirements = [
                        'PHP 8.2+' => checkPHPVersion(),
                        'PDO Extension' => checkExtension('pdo'),
                        'PDO MySQL' => checkExtension('pdo_mysql'),
                        'OpenSSL' => checkExtension('openssl'),
                        'Mbstring' => checkExtension('mbstring'),
                        'Tokenizer' => checkExtension('tokenizer'),
                        'XML' => checkExtension('xml'),
                        'Ctype' => checkExtension('ctype'),
                        'JSON' => checkExtension('json'),
                        'BCMath' => checkExtension('bcmath'),
                        'Fileinfo' => checkExtension('fileinfo'),
                    ];

                    $writable = [
                        'storage/' => checkWritable(__DIR__ . '/storage'),
                        'bootstrap/cache/' => checkWritable(__DIR__ . '/bootstrap/cache'),
                        'public/' => checkWritable(__DIR__ . '/public'),
                    ];

                    $allPassed = !in_array(false, $requirements, true) && !in_array(false, $writable, true);
                    ?>
                    <h2 style="margin-bottom: 20px;">System Requirements</h2>

                    <h3 style="margin: 20px 0 10px;">PHP Extensions</h3>
                    <ul class="requirement-list">
                        <?php foreach ($requirements as $name => $passed): ?>
                            <li>
                                <span>
                                    <?php echo $name; ?>
                                </span>
                                <span class="status <?php echo $passed ? 'status-pass' : 'status-fail'; ?>">
                                    <?php echo $passed ? '✓ Pass' : '✗ Fail'; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h3 style="margin: 20px 0 10px;">Directory Permissions</h3>
                    <ul class="requirement-list">
                        <?php foreach ($writable as $name => $passed): ?>
                            <li>
                                <span>
                                    <?php echo $name; ?>
                                </span>
                                <span class="status <?php echo $passed ? 'status-pass' : 'status-fail'; ?>">
                                    <?php echo $passed ? '✓ Writable' : '✗ Not Writable'; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($allPassed): ?>
                        <div class="alert alert-success" style="margin-top: 20px;">
                            ✅ All requirements met! You can proceed with the installation.
                        </div>
                        <form method="get" style="margin-top: 20px;">
                            <input type="hidden" name="step" value="database">
                            <button type="submit" class="btn">Continue →</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-error" style="margin-top: 20px;">
                            ❌ Some requirements are not met. Please fix the issues above and refresh this page.
                        </div>
                    <?php endif; ?>
                    <?php break; ?>

                <?php case 'database': ?>
                    <h2 style="margin-bottom: 20px;">Database Configuration</h2>

                    <div class="info-box">
                        <h3>Database Setup</h3>
                        <p>Please create a MySQL database and user in your hosting control panel (cPanel/DirectAdmin) before
                            proceeding. Enter the credentials below.</p>
                    </div>

                    <form method="post">
                        <div class="two-column">
                            <div class="form-group">
                                <label for="db_host">Database Host</label>
                                <input type="text" id="db_host" name="db_host" value="localhost" required>
                            </div>

                            <div class="form-group">
                                <label for="db_port">Port</label>
                                <input type="number" id="db_port" name="db_port" value="3306" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="db_name">Database Name</label>
                            <input type="text" id="db_name" name="db_name" required>
                        </div>

                        <div class="form-group">
                            <label for="db_user">Database Username</label>
                            <input type="text" id="db_user" name="db_user" required>
                        </div>

                        <div class="form-group">
                            <label for="db_pass">Database Password</label>
                            <input type="password" id="db_pass" name="db_pass" required>
                        </div>

                        <button type="submit" class="btn">Test & Continue →</button>
                        <a href="?step=requirements" class="btn btn-secondary">← Back</a>
                    </form>
                    <?php break; ?>

                <?php case 'app_config': ?>
                    <h2 style="margin-bottom: 20px;">Application Configuration</h2>

                    <form method="post">
                        <div class="form-group">
                            <label for="app_name">Application Name</label>
                            <input type="text" id="app_name" name="app_name" value="Maids.ng" required>
                        </div>

                        <div class="form-group">
                            <label for="app_url">Application URL</label>
                            <input type="text" id="app_url" name="app_url" placeholder="https://yourdomain.com" required>
                            <div class="help-text">Include https:// and no trailing slash</div>
                        </div>

                        <div class="form-group">
                            <label for="app_env">Environment</label>
                            <select id="app_env" name="app_env">
                                <option value="production">Production</option>
                                <option value="local">Development</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="admin_email">Admin Email</label>
                            <input type="email" id="admin_email" name="admin_email" required>
                        </div>

                        <div class="form-group">
                            <label for="admin_password">Admin Password</label>
                            <input type="password" id="admin_password" name="admin_password" minlength="8" required>
                            <div class="help-text">Minimum 8 characters</div>
                        </div>

                        <button type="submit" class="btn">Continue →</button>
                        <a href="?step=database" class="btn btn-secondary">← Back</a>
                    </form>
                    <?php break; ?>

                <?php case 'services': ?>
                    <h2 style="margin-bottom: 20px;">Services Configuration</h2>

                    <div class="info-box">
                        <h3>Optional Configuration</h3>
                        <p>These settings are optional and can be configured later from the admin panel. You can skip this step
                            and configure them later.</p>
                    </div>

                    <form method="post">
                        <h3 class="section-title">💳 Payment Gateways</h3>
                        <div class="two-column">
                            <div class="form-group">
                                <label for="paystack_public_key">Paystack Public Key</label>
                                <input type="text" id="paystack_public_key" name="paystack_public_key"
                                    placeholder="pk_test_...">
                            </div>
                            <div class="form-group">
                                <label for="paystack_secret_key">Paystack Secret Key</label>
                                <input type="password" id="paystack_secret_key" name="paystack_secret_key"
                                    placeholder="sk_test_...">
                            </div>
                        </div>

                        <div class="two-column">
                            <div class="form-group">
                                <label for="flutterwave_public_key">Flutterwave Public Key</label>
                                <input type="text" id="flutterwave_public_key" name="flutterwave_public_key"
                                    placeholder="FLWPUBK_TEST-...">
                            </div>
                            <div class="form-group">
                                <label for="flutterwave_secret_key">Flutterwave Secret Key</label>
                                <input type="password" id="flutterwave_secret_key" name="flutterwave_secret_key"
                                    placeholder="FLWSECK_TEST-...">
                            </div>
                        </div>

                        <h3 class="section-title">📱 SMS Provider</h3>
                        <div class="form-group">
                            <label for="sms_provider">SMS Provider</label>
                            <select id="sms_provider" name="sms_provider">
                                <option value="log">Log Only (Development)</option>
                                <option value="termii">Termii</option>
                                <option value="twilio">Twilio</option>
                                <option value="africastalking">Africa's Talking</option>
                            </select>
                        </div>

                        <div class="two-column">
                            <div class="form-group">
                                <label for="termii_api_key">Termii API Key</label>
                                <input type="password" id="termii_api_key" name="termii_api_key">
                            </div>
                            <div class="form-group">
                                <label for="termii_sender_id">Termii Sender ID</label>
                                <input type="text" id="termii_sender_id" name="termii_sender_id" value="MaidsNG">
                            </div>
                        </div>

                        <div class="two-column">
                            <div class="form-group">
                                <label for="twilio_sid">Twilio SID</label>
                                <input type="text" id="twilio_sid" name="twilio_sid">
                            </div>
                            <div class="form-group">
                                <label for="twilio_token">Twilio Auth Token</label>
                                <input type="password" id="twilio_token" name="twilio_token">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="twilio_from">Twilio From Number</label>
                            <input type="text" id="twilio_from" name="twilio_from" placeholder="+1234567890">
                        </div>

                        <h3 class="section-title">🤖 AI Configuration</h3>
                        <div class="form-group">
                            <label for="ai_provider">AI Provider</label>
                            <select id="ai_provider" name="ai_provider">
                                <option value="openai">OpenAI</option>
                                <option value="openrouter">OpenRouter</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="openai_key">OpenAI API Key</label>
                            <input type="password" id="openai_key" name="openai_key" placeholder="sk-...">
                        </div>

                        <div class="form-group">
                            <label for="openrouter_key">OpenRouter API Key</label>
                            <input type="password" id="openrouter_key" name="openrouter_key">
                        </div>

                        <h3 class="section-title">📧 Email Configuration (SMTP)</h3>
                        <div class="two-column">
                            <div class="form-group">
                                <label for="mail_host">SMTP Host</label>
                                <input type="text" id="mail_host" name="mail_host" placeholder="smtp.gmail.com">
                            </div>
                            <div class="form-group">
                                <label for="mail_port">SMTP Port</label>
                                <input type="number" id="mail_port" name="mail_port" value="587">
                            </div>
                        </div>

                        <div class="two-column">
                            <div class="form-group">
                                <label for="mail_username">SMTP Username</label>
                                <input type="text" id="mail_username" name="mail_username">
                            </div>
                            <div class="form-group">
                                <label for="mail_password">SMTP Password</label>
                                <input type="password" id="mail_password" name="mail_password">
                            </div>
                        </div>

                        <h3 class="section-title">🤖 Agent Channel Configuration</h3>
                        <div class="info-box" style="margin-bottom: 15px;">
                            <p style="margin:0; font-size:13px;">These credentials can also be configured later via
                                <strong>Admin → Settings → Agents tab</strong>. All agent channels are optional — skip if you're
                                not ready.
                            </p>
                        </div>

                        <h4 style="margin: 15px 0 10px; color: #1e40af;">Meta (Facebook/Instagram) Messenger</h4>
                        <div class="form-group">
                            <label for="meta_page_access_token">Meta Page Access Token</label>
                            <input type="password" id="meta_page_access_token" name="meta_page_access_token"
                                placeholder="EAA...">
                            <div class="help-text">From Facebook Developer Console → Messenger → Settings</div>
                        </div>
                        <div class="form-group">
                            <label for="meta_webhook_verify_token">Meta Webhook Verify Token</label>
                            <input type="text" id="meta_webhook_verify_token" name="meta_webhook_verify_token"
                                placeholder="maids-ng-webhook-2024">
                            <div class="help-text">Custom token for webhook verification (create your own)</div>
                        </div>
                        <div class="form-group">
                            <label for="meta_app_secret">Meta App Secret</label>
                            <input type="password" id="meta_app_secret" name="meta_app_secret"
                                placeholder="Your Meta app secret">
                        </div>

                        <h4 style="margin: 15px 0 10px; color: #1e40af;">WhatsApp (Future)</h4>
                        <div class="form-group">
                            <label for="whatsapp_from_number">WhatsApp From Number</label>
                            <input type="text" id="whatsapp_from_number" name="whatsapp_from_number" placeholder="+14155238886">
                            <div class="help-text">Meta WhatsApp Business API number (sandbox or production)</div>
                        </div>

                        <h4 style="margin: 15px 0 10px; color: #1e40af;">Email Polling (IMAP)</h4>
                        <div class="two-column">
                            <div class="form-group">
                                <label for="email_imap_host">IMAP Host</label>
                                <input type="text" id="email_imap_host" name="email_imap_host" placeholder="imap.gmail.com">
                            </div>
                            <div class="form-group">
                                <label for="email_imap_port">IMAP Port</label>
                                <input type="number" id="email_imap_port" name="email_imap_port" value="993">
                            </div>
                        </div>
                        <div class="two-column">
                            <div class="form-group">
                                <label for="email_imap_username">IMAP Username</label>
                                <input type="text" id="email_imap_username" name="email_imap_username"
                                    placeholder="agent@maids.ng">
                            </div>
                            <div class="form-group">
                                <label for="email_imap_password">IMAP Password</label>
                                <input type="password" id="email_imap_password" name="email_imap_password">
                            </div>
                        </div>

                        <button type="submit" class="btn">Continue →</button>
                        <a href="?step=app_config" class="btn btn-secondary">← Back</a>
                    </form>
                    <?php break; ?>

                <?php case 'install': ?>
                    <h2 style="margin-bottom: 20px;">Ready to Install</h2>

                    <div class="info-box">
                        <h3>Installation Summary</h3>
                        <p>Click the button below to start the installation. This will:</p>
                        <ul style="margin-top: 10px; margin-left: 20px; line-height: 1.8;">
                            <li>Create the <code>.env</code> configuration file</li>
                            <li>Set up all database tables (30+ tables)</li>
                            <li>Seed default settings</li>
                            <li>Create admin user with role</li>
                            <li>Create required directories</li>
                            <li>Set proper file permissions</li>
                            <li>Configure .htaccess for shared hosting</li>
                        </ul>
                    </div>

                    <form method="post">
                        <button type="submit" class="btn" style="font-size: 18px; padding: 15px 40px;">
                            🚀 Start Installation
                        </button>
                        <a href="?step=services" class="btn btn-secondary">← Back</a>
                    </form>
                    <?php break; ?>

                <?php case 'complete': ?>
                    <div style="text-align: center; padding: 40px 0;">
                        <div style="font-size: 80px; margin-bottom: 20px;">🎉</div>
                        <h2 style="margin-bottom: 20px;">Installation Complete!</h2>
                        <p style="font-size: 18px; color: #6b7280; margin-bottom: 30px;">
                            Maids.ng v2.0 has been successfully installed on your server.
                        </p>
                    </div>

                    <div class="info-box">
                        <h3>Next Steps</h3>
                        <ol style="margin-top: 10px; margin-left: 20px; line-height: 2;">
                            <li><strong>Delete install.php</strong> for security: <code>rm install.php</code></li>
                            <li><strong>Access your site:</strong> <a href="/" target="_blank">Visit Homepage</a></li>
                            <li><strong>Admin login:</strong> Use the email and password you configured</li>
                            <li><strong>Configure payments:</strong> Add your Paystack/Flutterwave keys in admin settings</li>
                            <li><strong>Set up email:</strong> Configure SMTP in admin settings for notifications</li>
                            <li><strong>Configure SMS:</strong> Add your Termii/Twilio credentials for SMS notifications</li>
                            <li><strong>Agent Channels:</strong> Configure Meta, WhatsApp, and Email Polling credentials at
                                <strong>Admin → Settings → Agents tab</strong>
                            </li>
                            <li><strong>Deploy Secret:</strong> Save your deploy secret for future updates</li>
                        </ol>
                    </div>
                    <div class="alert alert-success" style="margin-top: 20px;">
                        <strong>Important:</strong> For security reasons, please delete the install.php file immediately!
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <a href="/" class="btn">Go to Homepage →</a>
                        <a href="/admin" class="btn btn-secondary">Go to Admin →</a>
                    </div>
                    <?php break; ?>
            <?php endswitch; ?>
        </div>
    </div>
</body>

</html>