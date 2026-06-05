<?php

namespace App\Console\Commands;

use App\Models\AiMatchingQueue;
use App\Services\AssignmentService;
use App\Services\MatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAiMatchingQueueCommand extends Command
{
    protected $signature = 'ai:process-matching-queue {--limit=10} {--priority=all} {--type=all} {--dry-run}';
    protected $description = 'Process the AI matching queue jobs';

    protected AssignmentService $assignmentService;
    protected MatchingService $matchingService;

    public function __construct(
        AssignmentService $assignmentService,
        MatchingService $matchingService
    ) {
        parent::__construct();
        $this->assignmentService = $assignmentService;
        $this->matchingService = $matchingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $priority = $this->option('priority');
        $type = $this->option('type');
        $dryRun = $this->option('dry-run');

        $this->info('🤖 AI Agent: Processing Matching Queue');
        $this->info('=======================================');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No jobs will be processed');
            $this->newLine();
        }

        // Get pending jobs
        $jobs = $this->getPendingJobs($limit, $priority, $type);

        if ($jobs->isEmpty()) {
            $this->info('No pending jobs found in the queue.');
            return self::SUCCESS;
        }

        $this->info("Found {$jobs->count()} pending job(s) to process");
        $this->newLine();

        $processed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($jobs as $job) {
            $this->info("Processing job #{$job->id} [{$job->job_type}]...");

            if ($dryRun) {
                $this->info("   Would process: {$job->job_type} for employer #{$job->employer_id}");
                $processed++;
                continue;
            }

            try {
                $result = $this->processJob($job);

                if ($result) {
                    $processed++;
                    $this->info("   ✅ Successfully processed");
                } else {
                    $failed++;
                    $this->error("   ❌ Failed to process");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("   ❌ Error: {$e->getMessage()}");

                // Mark job as failed
                $job->markAsFailed($e->getMessage());

                Log::error('AI matching queue job failed', [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->newLine();
        }

        $this->info('=======================================');
        $this->info("✅ Processed: {$processed}");
        $this->info("❌ Failed: {$failed}");
        $this->info("⏭️  Skipped: {$skipped}");

        Log::info('AI Agent: Matching queue processed', [
            'processed' => $processed,
            'failed' => $failed,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    /**
     * Get pending jobs from the queue.
     */
    protected function getPendingJobs(int $limit, string $priority, string $type)
    {
        $query = AiMatchingQueue::pending()
            ->where('scheduled_at', '<=', now())
            ->where('retry_count', '<', 3)
            ->orderBy('priority', 'desc')
            ->orderBy('scheduled_at', 'asc');

        if ($priority !== 'all') {
            $query->where('priority', $priority);
        }

        if ($type !== 'all') {
            $query->where('job_type', $type);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Process a single job.
     */
    protected function processJob(AiMatchingQueue $job): bool
    {
        // Mark as processing
        $job->markAsProcessing();

        $context = $job->context_json ?? [];

        switch ($job->job_type) {
            case 'guarantee_match':
                return $this->processGuaranteeMatch($job, $context);

            case 'replacement':
                return $this->processReplacement($job, $context);

            case 'direct_selection':
                return $this->processDirectSelection($job, $context);

            case 'auto_match':
                return $this->processAutoMatch($job, $context);

            default:
                throw new \Exception("Unknown job type: {$job->job_type}");
        }
    }

    /**
     * Process guarantee match job.
     */
    protected function processGuaranteeMatch(AiMatchingQueue $job, array $context): bool
    {
        $employerId = $job->employer_id;
        $preferencesId = $context['preferences_id'] ?? null;

        if (!$preferencesId) {
            throw new \Exception('Preferences ID not found in context');
        }

        $preference = \App\Models\EmployerPreference::find($preferencesId);
        if (!$preference) {
            throw new \Exception('Preferences not found');
        }

        $bestMatch = $this->matchingService->findBestMatch($preference);

        if (!$bestMatch) {
            if ($job->retry_count < 2) {
                $job->scheduleRetry(30);
            } else {
                $job->markAsNeedsReview('No suitable match found after multiple attempts');
            }
            return false;
        }

        $assignment = $this->assignmentService->createGuaranteeMatchAssignment(
            $employerId,
            $bestMatch['maid_id'],
            $job->id,
            $preferencesId,
            array_merge($context, [
                'ai_match_score' => $bestMatch['score'] ?? null,
                'ai_match_reasoning' => $bestMatch['reasoning'] ?? null,
            ])
        );

        if ($assignment) {
            $job->markAsCompleted(['assignment_id' => $assignment->id]);
            return true;
        }

        if ($job->retry_count < 2) {
            $job->scheduleRetry(30);
        } else {
            $job->markAsNeedsReview('Failed to create assignment');
        }

        return false;
    }

    /**
     * Process replacement job.
     */
    protected function processReplacement(AiMatchingQueue $job, array $context): bool
    {
        $originalAssignmentId = $context['original_assignment_id'] ?? null;

        if (!$originalAssignmentId) {
            throw new \Exception('Original assignment ID not found in context');
        }

        $assignment = $this->assignmentService->findReplacementMaid($originalAssignmentId);

        if ($assignment) {
            $job->markAsCompleted([
                'assignment_id' => $assignment->id,
                'original_assignment_id' => $originalAssignmentId,
            ]);
            return true;
        }

        if ($job->retry_count < 2) {
            $job->scheduleRetry(60);
        } else {
            $job->markAsNeedsReview('No suitable replacement found after multiple attempts');
        }

        return false;
    }

    /**
     * Process direct selection job.
     */
    protected function processDirectSelection(AiMatchingQueue $job, array $context): bool
    {
        $employerId = $job->employer_id;
        $maidId = $context['maid_id'] ?? null;

        if (!$maidId) {
            throw new \Exception('Maid ID not found in context');
        }

        $assignment = $this->matchingService->processDirectSelection(
            $employerId,
            $maidId,
            $context
        );

        if ($assignment) {
            $job->markAsCompleted(['assignment_id' => $assignment->id]);
            return true;
        }

        return false;
    }

    /**
     * Process auto match job.
     */
    protected function processAutoMatch(AiMatchingQueue $job, array $context): bool
    {
        $employerId = $job->employer_id;
        $preferencesId = $context['preferences_id'] ?? null;

        // This would integrate with ScoutAgent for AI-powered matching
        // For now, we'll mark it as needing review
        $job->markAsNeedsReview('Auto-match requires ScoutAgent integration');

        return false;
    }
}
