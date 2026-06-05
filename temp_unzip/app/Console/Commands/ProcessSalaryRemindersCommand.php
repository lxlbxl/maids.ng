<?php

namespace App\Console\Commands;

use App\Services\SalaryManagementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSalaryRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:process-salary-reminders
                            {--type=all : Type of reminders to process (3_days, 1_day, due, all)}
                            {--dry-run : Show what would be processed without actually sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process automated salary reminders for employers (3 days, 1 day before due, and due date)';

    protected SalaryManagementService $salaryService;

    public function __construct(SalaryManagementService $salaryService)
    {
        parent::__construct();
        $this->salaryService = $salaryService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $dryRun = $this->option('dry-run');

        $this->info('🤖 AI Agent: Processing Salary Reminders');
        $this->info('========================================');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No notifications will be sent');
            $this->newLine();
        }

        $totalProcessed = 0;

        // Process 3-day reminders
        if (in_array($type, ['3_days', 'all'])) {
            $this->info('📅 Processing 3-day reminders...');

            if ($dryRun) {
                $count = $this->getThreeDayReminderCount();
                $this->info("   Would process: {$count} reminders");
            } else {
                $sent = $this->salaryService->processThreeDayReminders();
                $this->info("   Sent: {$sent} reminders");
                $totalProcessed += $sent;
            }
        }

        // Process 1-day reminders
        if (in_array($type, ['1_day', 'all'])) {
            $this->newLine();
            $this->info('📅 Processing 1-day reminders...');

            if ($dryRun) {
                $count = $this->getOneDayReminderCount();
                $this->info("   Would process: {$count} reminders");
            } else {
                $sent = $this->salaryService->processOneDayReminders();
                $this->info("   Sent: {$sent} reminders");
                $totalProcessed += $sent;
            }
        }

        // Process due salaries
        if (in_array($type, ['due', 'all'])) {
            $this->newLine();
            $this->info('💰 Processing due salary payments...');

            if ($dryRun) {
                $count = $this->getDueSalaryCount();
                $this->info("   Would process: {$count} payments");
            } else {
                $processed = $this->salaryService->processDueSalaries();
                $this->info("   Processed: {$processed} payments");
                $totalProcessed += $processed;
            }
        }

        $this->newLine();
        $this->info('========================================');

        if ($dryRun) {
            $this->info('✅ Dry run completed successfully');
        } else {
            $this->info("✅ Total processed: {$totalProcessed}");
        }

        Log::info('AI Agent: Salary reminders processed', [
            'type' => $type,
            'dry_run' => $dryRun,
            'total_processed' => $totalProcessed,
        ]);

        return self::SUCCESS;
    }

    /**
     * Get count of 3-day reminders that would be sent.
     */
    protected function getThreeDayReminderCount(): int
    {
        return \App\Models\SalarySchedule::where('status', 'pending')
            ->where('reminder_3_days_sent', false)
            ->whereDate('due_date', '=', now()->addDays(3)->toDateString())
            ->count();
    }

    /**
     * Get count of 1-day reminders that would be sent.
     */
    protected function getOneDayReminderCount(): int
    {
        return \App\Models\SalarySchedule::where('status', 'pending')
            ->where('reminder_1_day_sent', false)
            ->whereDate('due_date', '=', now()->addDay()->toDateString())
            ->count();
    }

    /**
     * Get count of due salaries that would be processed.
     */
    protected function getDueSalaryCount(): int
    {
        return \App\Models\SalarySchedule::where('status', 'pending')
            ->whereDate('due_date', '<=', now()->toDateString())
            ->where('escrow_funded', false)
            ->count();
    }
}
