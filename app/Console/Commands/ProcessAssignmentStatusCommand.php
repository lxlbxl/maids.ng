<?php

namespace App\Console\Commands;

use App\Models\MaidAssignment;
use App\Services\AssignmentService;
use App\Services\SmartNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAssignmentStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:process-assignment-status
                            {--action=timeouts : Action to perform (timeouts, reminders, auto-complete, all)}
                            {--dry-run : Show what would be processed without actually processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process assignment status updates, timeouts, and reminders';

    protected AssignmentService $assignmentService;
    protected SmartNotificationService $notificationService;

    public function __construct(
        AssignmentService $assignmentService,
        SmartNotificationService $notificationService
    ) {
        parent::__construct();
        $this->assignmentService = $assignmentService;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->option('action');
        $dryRun = $this->option('dry-run');

        $this->info('🤖 AI Agent: Processing Assignment Status');
        $this->info('==========================================');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $totalProcessed = 0;

        // Process pending acceptance timeouts
        if (in_array($action, ['timeouts', 'all'])) {
            $this->info('⏰ Processing pending acceptance timeouts...');
            $count = $this->processPendingAcceptanceTimeouts($dryRun);
            $this->info("   Processed: {$count} timeouts");
            $totalProcessed += $count;
        }

        // Process acceptance reminders
        if (in_array($action, ['reminders', 'all'])) {
            $this->newLine();
            $this->info('📨 Processing acceptance reminders...');
            $count = $this->processAcceptanceReminders($dryRun);
            $this->info("   Sent: {$count} reminders");
            $totalProcessed += $count;
        }

        // Process auto-complete for finished assignments
        if (in_array($action, ['auto-complete', 'all'])) {
            $this->newLine();
            $this->info('✅ Processing auto-complete assignments...');
            $count = $this->processAutoComplete($dryRun);
            $this->info("   Completed: {$count} assignments");
            $totalProcessed += $count;
        }

        $this->newLine();
        $this->info('==========================================');

        if ($dryRun) {
            $this->info('✅ Dry run completed successfully');
        } else {
            $this->info("✅ Total processed: {$totalProcessed}");
        }

        Log::info('AI Agent: Assignment status processed', [
            'action' => $action,
            'dry_run' => $dryRun,
            'total_processed' => $totalProcessed,
        ]);

        return self::SUCCESS;
    }

    /**
     * Process pending acceptance timeouts (48 hours).
     */
    protected function processPendingAcceptanceTimeouts(bool $dryRun): int
    {
        $timeoutThreshold = now()->subHours(48);

        $assignments = MaidAssignment::where('status', 'pending_acceptance')
            ->where('created_at', '<', $timeoutThreshold)
            ->whereNull('employer_responded_at')
            ->get();

        $processed = 0;

        foreach ($assignments as $assignment) {
            if ($dryRun) {
                $processed++;
                continue;
            }

            try {
                // Auto-reject the assignment
                $this->assignmentService->rejectAssignment(
                    $assignment->id,
                    'Employer did not respond within 48 hours',
                    null,
                    true // Auto-rejection
                );

                $processed++;

                Log::info('Assignment auto-rejected due to timeout', [
                    'assignment_id' => $assignment->id,
                    'employer_id' => $assignment->employer_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to auto-reject assignment', [
                    'assignment_id' => $assignment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Process acceptance reminders (24 hours before timeout).
     */
    protected function processAcceptanceReminders(bool $dryRun): int
    {
        $reminderThreshold = now()->subHours(24);
        $timeoutThreshold = now()->subHours(48);

        $assignments = MaidAssignment::where('status', 'pending_acceptance')
            ->where('created_at', '<', $reminderThreshold)
            ->where('created_at', '>', $timeoutThreshold)
            ->whereNull('employer_responded_at')
            ->where('reminder_sent', false)
            ->get();

        $sent = 0;

        foreach ($assignments as $assignment) {
            if ($dryRun) {
                $sent++;
                continue;
            }

            try {
                // Send reminder notification
                $this->notificationService->sendFollowUpNotification(
                    $assignment->id,
                    "Reminder: You have 24 hours left to accept or reject the maid assignment. Please respond to avoid automatic rejection."
                );

                // Mark reminder as sent
                $assignment->update(['reminder_sent' => true]);

                $sent++;

                Log::info('Acceptance reminder sent', [
                    'assignment_id' => $assignment->id,
                    'employer_id' => $assignment->employer_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send acceptance reminder', [
                    'assignment_id' => $assignment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Process auto-complete for assignments that have reached their end date.
     */
    protected function processAutoComplete(bool $dryRun): int
    {
        $assignments = MaidAssignment::where('status', 'accepted')
            ->whereNotNull('ended_at')
            ->where('ended_at', '<=', now())
            ->get();

        $completed = 0;

        foreach ($assignments as $assignment) {
            if ($dryRun) {
                $completed++;
                continue;
            }

            try {
                $this->assignmentService->completeAssignment($assignment->id);
                $completed++;

                Log::info('Assignment auto-completed', [
                    'assignment_id' => $assignment->id,
                    'employer_id' => $assignment->employer_id,
                    'maid_id' => $assignment->maid_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to auto-complete assignment', [
                    'assignment_id' => $assignment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $completed;
    }
}
