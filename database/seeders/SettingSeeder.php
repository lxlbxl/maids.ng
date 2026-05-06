<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Seed all system settings with sensible defaults.
     *
     * This is the single source of truth for every admin-configurable
     * variable. Safe to re-run — uses updateOrCreate internally.
     */
    public function run(): void
    {
        $s = fn($key, $val, $group, $encrypt = false) =>
            \App\Models\Setting::set($key, $val, $group, $encrypt);

        // ═══════════════════════════════════════════
        // General / Platform
        // ═══════════════════════════════════════════
        $s('platform_name', 'Maids.ng', 'general');
        $s('app_url', env('APP_URL', 'https://maids.ng'), 'general');
        $s('app_timezone', 'Africa/Lagos', 'general');
        $s('app_debug', 'false', 'general');
        $s('support_email', 'support@maids.ng', 'general');
        $s('support_phone', '+234 801 234 5678', 'general');
        $s('maintenance_mode', 'false', 'general');

        // ═══════════════════════════════════════════
        // Financial / Fees
        // ═══════════════════════════════════════════
        $s('service_fee_percentage', '10', 'finance');
        $s('matching_fee_amount', '5000', 'finance');
        $s('guarantee_match_fee', '10000', 'finance');
        $s('nin_verification_fee', '5000', 'finance');
        $s('standalone_verification_fee', '2000', 'finance');
        $s('commission_type', 'percentage', 'finance');  // percentage | fixed
        $s('commission_percent', '10', 'finance');
        $s('commission_fixed_amount', '5000', 'finance');
        $s('min_salary', '15000', 'finance');
        $s('max_salary', '200000', 'finance');
        $s('min_withdrawal', '5000', 'finance');
        $s('max_withdrawal', '500000', 'finance');
        $s('withdrawal_processing_days', '3', 'finance');

        // ── Agent Knowledge Base Pricing Settings ──
        // These are the single source of truth for ALL pricing quoted by ALL agents.
        // Edit these values via /admin/settings — never hardcode in any agent.
        $s('matching_fee', '5000', 'finance');
        $s('premium_matching_fee', '15000', 'finance');
        $s('commission_rate', '15', 'finance');
        $s('guarantee_period_days', '10', 'finance');
        $s('maid_monthly_rate_min', '30000', 'finance');
        $s('maid_monthly_rate_max', '80000', 'finance');
        $s('withdrawal_minimum', '5000', 'finance');
        $s('escrow_release_days', '3', 'finance');

        // ═══════════════════════════════════════════
        // Payment Gateways
        // ═══════════════════════════════════════════
        $s('default_payment_gateway', 'paystack', 'payment');
        $s('paystack_public_key', '', 'payment', true);
        $s('paystack_secret_key', '', 'payment', true);
        $s('paystack_base_url', 'https://api.paystack.co', 'payment');
        $s('flutterwave_public_key', '', 'payment', true);
        $s('flutterwave_secret_key', '', 'payment', true);
        $s('flutterwave_encryption_key', '', 'payment', true);
        $s('flutterwave_base_url', 'https://api.flutterwave.com/v3', 'payment');

        // ═══════════════════════════════════════════
        // SMS
        // ═══════════════════════════════════════════
        $s('sms_active_provider', 'log', 'sms');  // termii | twilio | africastalking | log
        $s('termii_api_key', '', 'sms', true);
        $s('termii_sender_id', 'MaidsNG', 'sms');
        $s('termii_url', 'https://api.ng.termii.com/api', 'sms');
        $s('twilio_sid', '', 'sms', true);
        $s('twilio_token', '', 'sms', true);
        $s('twilio_from', '', 'sms');
        $s('africastalking_username', '', 'sms');
        $s('africastalking_api_key', '', 'sms', true);
        $s('africastalking_from', 'MaidsNG', 'sms');

        // ═══════════════════════════════════════════
        // Email / SMTP
        // ═══════════════════════════════════════════
        $s('mail_mailer', 'smtp', 'email');
        $s('mail_host', '', 'email');
        $s('mail_port', '587', 'email');
        $s('mail_username', '', 'email', true);
        $s('mail_password', '', 'email', true);
        $s('mail_encryption', 'tls', 'email');
        $s('mail_from_address', 'noreply@maids.ng', 'email');
        $s('mail_from_name', 'Maids.ng', 'email');

        // ═══════════════════════════════════════════
        // Identity Verification (QoreID)
        // ═══════════════════════════════════════════
        $s('qoreid_token', '', 'verification', true);
        $s('qoreid_base_url', 'https://api.qoreid.com/v1', 'verification');
        $s('verification_auto_approve', 'false', 'verification');

        // ═══════════════════════════════════════════
        // AI Configuration
        // ═══════════════════════════════════════════
        $s('ai_active_provider', 'openai', 'ai');
        $s('openai_model', 'gpt-4o-mini', 'ai');
        $s('openai_key', '', 'ai', true);
        $s('openrouter_model', 'google/gemini-flash-1.5', 'ai');
        $s('openrouter_key', '', 'ai', true);
        $s('ai_temperature', '0.7', 'ai');
        $s('ai_max_tokens', '2048', 'ai');
        $s('ai_system_prompt', 'You are a helpful assistant for the Maids.ng domestic help matching platform in Nigeria.', 'ai');
        $s('ai_matching_enabled', 'true', 'ai');
        $s('ai_matching_max_results', '5', 'ai');
        $s('ai_matching_min_score', '0.6', 'ai');

        // ═══════════════════════════════════════════
        // Notification Settings
        // ═══════════════════════════════════════════
        $s('notification_work_hours_start', '8', 'notifications');
        $s('notification_work_hours_end', '20', 'notifications');
        $s('notification_max_retries', '3', 'notifications');
        $s('notification_retry_delay_minutes', '5', 'notifications');
        $s('notification_batch_size', '100', 'notifications');

        // ═══════════════════════════════════════════
        // Salary Automation
        // ═══════════════════════════════════════════
        $s('salary_default_day', '28', 'salary');
        $s('salary_reminder_days_before', '3', 'salary');
        $s('salary_auto_debit_enabled', 'true', 'salary');
        $s('salary_escalation_after_days', '3', 'salary');
        $s('salary_max_escalation_level', '3', 'salary');

        // ═══════════════════════════════════════════
        // Security / Deployment
        // ═══════════════════════════════════════════
        $s('deploy_secret', bin2hex(random_bytes(16)), 'security', true);
        $s('api_rate_limit', '60', 'security');
        $s('session_lifetime', '120', 'security');
    }
}
