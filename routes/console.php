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

// Backstop reconciliation for Flutterwave PWBT payments — the FLW account is
// shared across Digital20 brands and its single webhook may not reach us, so we
// poll pending transfers straight from the FLW API. (In-chat payments are also
// pulled live by the CS agent via /payments/verify-pwbt.)
Schedule::command('payments:reconcile-pwbt --hours=48')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/pwbt-reconcile.log'));

// Move every live match forward one step: close the group claim window and build
// the queue, chase a helper who has gone quiet, and promote the backup when the
// silence has run long enough. Without this a request stalls on whoever forgot
// to chase it — which is how one household waited on a helper who never showed.
// Every 30 minutes; quiet-hours are enforced by the senders it hands work to.
Schedule::command('requests:advance-matching')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/matching-cadence.log'));

// Placement integrity backstop. The write-path guards stop these being created
// through the agent API, but not a direct DB edit or an older code path. The
// Fatima case (an employer recorded as a placed maid) sat wrong for a day
// because nothing was looking. Silent when clean.
Schedule::command('placements:audit --quiet-ok')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/placements-audit.log'));

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

// ============================================
// AUDIT LOG RETENTION PURGE
// ============================================

// Purge API audit logs older than the admin-configured retention period (daily at 03:00)
Schedule::call(function () {
    \App\Http\Controllers\AdminAuditLogController::purgeOldLogs();
})->dailyAt('03:00')->name('purge-audit-logs');

// Prune stale attribution rows daily
Schedule::command('attribution:prune')->dailyAt('04:10')->withoutOverlapping();

