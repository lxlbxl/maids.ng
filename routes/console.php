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

// Daily cleanup of old logs (keep last 30 days)
Schedule::command('log:clear-old')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Refresh SEO content monthly for pages older than 90 days
Schedule::job(new \App\Jobs\RefreshSeoContent)
    ->monthly()
    ->name('refresh-seo-content')
    ->withoutOverlapping();
