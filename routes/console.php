<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================
// AI AGENT CRON SCHEDULES
// ============================================

// Process notifications every minute (high frequency for time-sensitive messages)
Schedule::command('ai:process-notifications')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-notifications.log'));

// Process matching queue every 5 minutes (matching, replacements, assignments)
Schedule::command('ai:process-matching-queue')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-matching.log'));

// Process assignment status every 15 minutes (timeouts, reminders, completions)
Schedule::command('ai:process-assignment-status')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-assignments.log'));

// Process salary reminders daily at 9 AM (3-day, 1-day, due date reminders)
Schedule::command('ai:process-salary-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-salary-reminders.log'));

// Background sweep for pending NIN verifications every 30 minutes
Schedule::command('ai:verify-pending-nins')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-nin-verifications.log'));

// Daily cleanup of old logs (keep last 30 days)
Schedule::command('log:clear-old')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Refresh SEO content monthly for pages older than 90 days
Schedule::job(new \App\Jobs\RefreshSeoContent)
    ->monthly()
    ->name('refresh-seo-content')
    ->withoutOverlapping();

// ============================================
// CONTROL ROOM SCHEDULES
// ============================================

// Reset agent daily spend counter at midnight
Schedule::call(function () {
    \App\Models\AgentOverride::query()->update([
        'current_daily_spend_usd' => 0,
        'spend_reset_at' => now(),
    ]);
    \Illuminate\Support\Facades\Cache::flush();
})->dailyAt('00:00')->name('reset-agent-daily-spend');

// Check AI provider health every 5 minutes
Schedule::job(new \App\Jobs\CheckAiProviderHealth)
    ->everyFiveMinutes()
    ->name('check-ai-health');

// Check QoreID NIN Premium availability every 30 minutes
Schedule::command('qoreid:health')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->name('check-qoreid-health');

// ============================================
// WEBHOOK SCHEDULES
// ============================================

// Process pending webhook deliveries every minute
Schedule::command('webhooks:process')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/webhooks.log'));
