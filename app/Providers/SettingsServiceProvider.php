<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services — inject all DB-managed settings into Laravel config.
     */
    public function boot(): void
    {
        try {
            if ($this->app->has('db') && Schema::hasTable('settings')) {
                $settings = Setting::getAllCached();

                // ── Payment Gateways ──
                $this->setIfExists($settings, 'paystack_public_key', 'services.paystack.public_key');
                $this->setIfExists($settings, 'paystack_public_key', 'paystack.publicKey');
                $this->setIfExists($settings, 'paystack_secret_key', 'services.paystack.secret_key');
                $this->setIfExists($settings, 'paystack_secret_key', 'paystack.secretKey');
                $this->setIfExists($settings, 'paystack_base_url', 'services.paystack.base_url');
                $this->setIfExists($settings, 'paystack_base_url', 'paystack.paymentUrl');

                $this->setIfExists($settings, 'flutterwave_public_key', 'services.flutterwave.public_key');
                $this->setIfExists($settings, 'flutterwave_secret_key', 'services.flutterwave.secret_key');
                $this->setIfExists($settings, 'flutterwave_encryption_key', 'services.flutterwave.encryption_key');
                $this->setIfExists($settings, 'flutterwave_base_url', 'services.flutterwave.base_url');

                // ── Email / SMTP ──
                $this->setIfExists($settings, 'mail_mailer', 'mail.default');
                $this->setIfExists($settings, 'mail_host', 'mail.mailers.smtp.host');
                $this->setIfExists($settings, 'mail_port', 'mail.mailers.smtp.port');
                $this->setIfExists($settings, 'mail_username', 'mail.mailers.smtp.username');
                $this->setIfExists($settings, 'mail_password', 'mail.mailers.smtp.password');
                $this->setIfExists($settings, 'mail_encryption', 'mail.mailers.smtp.encryption');
                $this->setIfExists($settings, 'mail_from_address', 'mail.from.address');
                $this->setIfExists($settings, 'mail_from_name', 'mail.from.name');

                // ── SMS — Termii ──
                $this->setIfExists($settings, 'termii_api_key', 'services.termii.api_key');
                $this->setIfExists($settings, 'termii_sender_id', 'services.termii.sender_id');
                $this->setIfExists($settings, 'termii_url', 'services.termii.url');

                // ── SMS — Twilio ──
                $this->setIfExists($settings, 'twilio_sid', 'services.twilio.sid');
                $this->setIfExists($settings, 'twilio_token', 'services.twilio.token');
                $this->setIfExists($settings, 'twilio_from', 'services.twilio.from');

                // ── SMS — Africa's Talking ──
                $this->setIfExists($settings, 'africastalking_username', 'services.africastalking.username');
                $this->setIfExists($settings, 'africastalking_api_key', 'services.africastalking.api_key');
                $this->setIfExists($settings, 'africastalking_from', 'services.africastalking.from');

                // ── Verification (QoreID) ──
                $this->setIfExists($settings, 'qoreid_token', 'services.qoreid.token');
                $this->setIfExists($settings, 'qoreid_base_url', 'services.qoreid.base_url');

                // ── Financial Settings ──
                $this->setIfExists($settings, 'service_fee_percentage', 'services.fees.service_fee_percentage');
                $this->setIfExists($settings, 'matching_fee_amount', 'services.fees.matching');
                $this->setIfExists($settings, 'guarantee_match_fee', 'services.fees.guarantee_match');
                $this->setIfExists($settings, 'nin_verification_fee', 'services.fees.nin_verification');
                $this->setIfExists($settings, 'standalone_verification_fee', 'services.fees.standalone_verification');
                $this->setIfExists($settings, 'commission_type', 'services.commission.type');
                $this->setIfExists($settings, 'commission_percent', 'services.commission.percent');
                $this->setIfExists($settings, 'commission_fixed_amount', 'services.commission.fixed_amount');
                $this->setIfExists($settings, 'min_salary', 'services.defaults.min_salary');
                $this->setIfExists($settings, 'max_salary', 'services.defaults.max_salary');
                $this->setIfExists($settings, 'min_withdrawal', 'services.defaults.min_withdrawal');
                $this->setIfExists($settings, 'max_withdrawal', 'services.defaults.max_withdrawal');
                $this->setIfExists($settings, 'withdrawal_processing_days', 'services.defaults.withdrawal_processing_days');
                $this->setIfExists($settings, 'default_payment_gateway', 'services.defaults.payment_gateway');

                // ── Platform / App ──
                $this->setIfExists($settings, 'platform_name', 'app.name');
                $this->setIfExists($settings, 'app_url', 'app.url');
                $this->setIfExists($settings, 'app_timezone', 'app.timezone');
                if (isset($settings['app_debug'])) {
                    config(['app.debug' => filter_var($settings['app_debug'], FILTER_VALIDATE_BOOLEAN)]);
                }

                // ── AI ──
                $this->setIfExists($settings, 'openai_key', 'services.openai.key');
                $this->setIfExists($settings, 'openai_model', 'services.openai.model');
                $this->setIfExists($settings, 'openrouter_key', 'services.openrouter.key');
                $this->setIfExists($settings, 'openrouter_model', 'services.openrouter.model');
                $this->setIfExists($settings, 'ai_temperature', 'services.ai.temperature');
                $this->setIfExists($settings, 'ai_max_tokens', 'services.ai.max_tokens');

                // ── Salary Automation ──
                $this->setIfExists($settings, 'salary_default_day', 'services.salary.default_day');
                $this->setIfExists($settings, 'salary_reminder_days_before', 'services.salary.reminder_days_before');
                $this->setIfExists($settings, 'salary_auto_debit_enabled', 'services.salary.auto_debit_enabled');
                $this->setIfExists($settings, 'salary_escalation_after_days', 'services.salary.escalation_after_days');
                $this->setIfExists($settings, 'salary_max_escalation_level', 'services.salary.max_escalation_level');

                // ── Notifications ──
                $this->setIfExists($settings, 'notification_work_hours_start', 'services.notifications.work_hours_start');
                $this->setIfExists($settings, 'notification_work_hours_end', 'services.notifications.work_hours_end');
                $this->setIfExists($settings, 'notification_max_retries', 'services.notifications.max_retries');
                $this->setIfExists($settings, 'notification_batch_size', 'services.notifications.batch_size');

                // ── Security ──
                $this->setIfExists($settings, 'api_rate_limit', 'services.security.api_rate_limit');
                $this->setIfExists($settings, 'session_lifetime', 'session.lifetime');
            }
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::error('SettingsServiceProvider boot failure: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ]);
            }
        }
    }

    /**
     * Helper: set a config value if the setting exists and is non-empty.
     */
    private function setIfExists(array $settings, string $settingKey, string $configKey): void
    {
        if (isset($settings[$settingKey]) && $settings[$settingKey] !== '') {
            config([$configKey => $settings[$settingKey]]);
        }
    }
}
