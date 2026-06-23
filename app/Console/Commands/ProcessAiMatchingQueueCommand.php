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

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $priority = $this->option('priority');
        $type = $this->option('type');
        $dryRun = $this->option('dry-run');

        $this->info('AI Agent: Processing Matching Queue');
        $this->info('=======================================');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No jobs will be processed');
            $this->newLine();
        }

        $jobs = $this->getPendingJobs($limit, $priority, $type);

        if ($jobs->isEmpty()) {
            $this->info('No pending jobs found in the queue.');
            return self::SUCCESS;
        }

        $this->info("Found {$jobs->count()} pending job(s) to process");
        $this->newLine();

        $processed = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $this->info("Processing job #{$job->id} [{$job->job_type}]...");

            if ($dryRun) {
                $this->info("   Would process: {$job->job_type} for employer #{$job->employer_id}");
                $processed++;
                continue;
            }

            try {
                if ($this->processJob($job)) {
                    $processed++;
                    $this->info("   Successfully processed");
                } else {
                    $failed++;
                    $this->error("   Failed to process");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("   Error: {$e->getMessage()}");
                $job->markFailed($e->getMessage());
                Log::error('AI matching queue job failed', ['job_id' => $job->id, 'error' => $e->getMessage()]);
            }

            $this->newLine();
        }

        $this->info('=======================================');
        $this->info("Processed: {$processed}");
        $this->info("Failed: {$failed}");

        Log::info('AI Agent: Matching queue processed', [
            'processed' => $processed, 'failed' => $failed, 'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    protected function getPendingJobs(int $limit, string $priority, string $type)
    {
        $query = AiMatchingQueue::pending()
            ->where(function ($q) {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            })
            ->where('attempt_count', '<', 3)
            ->orderBy('priority', 'asc')
            ->orderBy('scheduled_at', 'asc');

        if ($priority !== 'all') $query->where('priority', $priority);
        if ($type !== 'all') $query->where('job_type', $type);

        return $query->limit($limit)->get();
    }

    protected function processJob(AiMatchingQueue $job): bool
    {
        $job->markProcessing();
        $context = $job->context_snapshot ?? [];

        return match ($job->job_type) {
            'guarantee_match' => $this->processGuaranteeMatch($job, $context),
            'replacement_search', 'replacement' => $this->processReplacement($job, $context),
            'direct_selection' => $this->processDirectSelection($job, $context),
            'auto_match' => $this->processAutoMatch($job, $context),
            default => throw new \Exception("Unknown job type: {$job->job_type}"),
        };
    }

    protected function processGuaranteeMatch(AiMatchingQueue $job, array $context): bool
    {
        $preference = \App\Models\EmployerPreference::find($job->preference_id);
        if (!$preference) { $job->markFailed('Preferences not found'); return false; }

        $bestMatch = $this->matchingService->findBestMatch($preference);
        if (!$bestMatch) { $job->markFailed('No suitable match found', 'no_match'); return false; }

        $assignment = $this->matchingService->processGuaranteeMatchAssignment(
            $job->employer_id, $bestMatch['maid_id'], $job->id, 'ai',
            ['monthly_salary' => $preference->budget_max, 'start_date' => now()->addDays(7)->format('Y-m-d')],
            $job->preference_id
        );

        if ($assignment) { $job->markCompleted(['assignment_id' => $assignment->id]); return true; }
        $job->markFailed('Failed to create assignment');
        return false;
    }

    protected function processReplacement(AiMatchingQueue $job, array $context): bool
    {
        $preference = \App\Models\EmployerPreference::find($job->preference_id);
        $bestMatch = $preference ? $this->matchingService->findBestMatch($preference) : null;
        if (!$bestMatch) { $job->markFailed('No replacement found', 'no_match'); return false; }

        $assignment = $this->matchingService->processGuaranteeMatchAssignment(
            $job->employer_id, $bestMatch['maid_id'], $job->id, 'ai',
            ['monthly_salary' => $preference?->budget_max, 'start_date' => now()->addDays(7)->format('Y-m-d')]
        );

        if ($assignment) { $job->markCompleted(['assignment_id' => $assignment->id]); return true; }
        $job->markFailed('Failed to create replacement');
        return false;
    }

    protected function processDirectSelection(AiMatchingQueue $job, array $context): bool
    {
        if (!$job->maid_id) { $job->markFailed('Maid ID missing'); return false; }

        $preference = \App\Models\EmployerPreference::find($job->preference_id);
        $assignment = $this->matchingService->processDirectSelection(
            $job->employer_id, $job->maid_id,
            ['monthly_salary' => $preference?->budget_max, 'start_date' => now()->addDays(7)->format('Y-m-d')],
            $job->preference_id
        );

        if ($assignment) { $job->markCompleted(['assignment_id' => $assignment->id]); return true; }
        $job->markFailed('Failed direct selection');
        return false;
    }

    protected function processAutoMatch(AiMatchingQueue $job, array $context): bool
    {
        $preference = \App\Models\EmployerPreference::find($job->preference_id);
        if (!$preference) { $job->markFailed('Preferences not found'); return false; }

        $bestMatch = $this->matchingService->findBestMatch($preference);
        if (!$bestMatch) { $job->markFailed('No matching maids found', 'no_match'); return false; }

        $assignment = $this->matchingService->processGuaranteeMatchAssignment(
            $job->employer_id, $bestMatch['maid_id'], $job->id, 'ai',
            ['monthly_salary' => $preference->budget_max, 'start_date' => now()->addDays(7)->format('Y-m-d')],
            $job->preference_id
        );

        if ($assignment) {
            $job->setAiResults($bestMatch['score'] * 100, $bestMatch['reasoning'], ['match_data' => $bestMatch]);
            $job->markCompleted(['assignment_id' => $assignment->id]);
            return true;
        }
        $job->markFailed('Failed auto match');
        return false;
    }
}
