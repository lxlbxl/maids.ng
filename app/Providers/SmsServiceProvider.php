<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\TermiiProvider;
use App\Services\Sms\TwilioProvider;
use App\Services\Sms\AfricasTalkingProvider;
use App\Services\Sms\LogProvider;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class SmsServiceProvider extends ServiceProvider
{
    /**
     * Register the SMS provider binding.
     */
    public function register(): void
    {
        $this->app->singleton(SmsProviderInterface::class, function ($app) {
            $provider = $this->resolveProvider();

            return match ($provider) {
                'termii'         => new TermiiProvider(),
                'twilio'         => new TwilioProvider(),
                'africastalking' => new AfricasTalkingProvider(),
                default          => new LogProvider(),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine the active SMS provider from settings or env.
     */
    protected function resolveProvider(): string
    {
        // Try env first, then database settings
        $provider = env('SMS_PROVIDER');

        if (! $provider) {
            try {
                if ($this->app->has('db') && Schema::hasTable('settings')) {
                    $provider = Setting::get('sms_active_provider', 'log');
                }
            } catch (\Throwable $e) {
                // Fallback silently
            }
        }

        return $provider ?: 'log';
    }
}
