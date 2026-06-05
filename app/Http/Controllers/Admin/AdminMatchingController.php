<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminMatchingController extends Controller
{
    public function index()
    {
        $jobs = [];
        $stats = [
            'total_jobs' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'requires_review' => 0,
        ];

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ai_matching_queue')) {
                $query = DB::table('ai_matching_queue')
                    ->orderByDesc('created_at')
                    ->limit(50);

                $rawJobs = $query->get();

                $jobs = $rawJobs->map(function ($job) {
                    $employer = null;
                    if ($job->employer_id) {
                        $employer = DB::table('users')
                            ->where('id', $job->employer_id)
                            ->select('id', 'name', 'email')
                            ->first();
                    }
                    return [
                        'job_id' => $job->job_id,
                        'employer' => $employer ? ['name' => $employer->name] : ['name' => 'Unknown'],
                        'status' => $job->status ?? 'pending',
                        'priority' => $job->priority ?? 5,
                        'created_at' => $job->created_at,
                        'completed_at' => $job->completed_at ?? null,
                        'ai_confidence_score' => $job->ai_confidence_score ? (float) $job->ai_confidence_score / 100 : null,
                        'requires_review' => (bool) ($job->requires_review ?? false),
                    ];
                })->all();

                $stats = [
                    'total_jobs' => DB::table('ai_matching_queue')->count(),
                    'pending' => DB::table('ai_matching_queue')->where('status', 'pending')->count(),
                    'processing' => DB::table('ai_matching_queue')->where('status', 'processing')->count(),
                    'completed' => DB::table('ai_matching_queue')->where('status', 'completed')->count(),
                    'failed' => DB::table('ai_matching_queue')->where('status', 'failed')->count(),
                    'requires_review' => DB::table('ai_matching_queue')->where('requires_review', true)->count(),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Matching queue data fetch failed: ' . $e->getMessage());
        }

        return Inertia::render('Admin/MatchingQueue', [
            'jobs' => $jobs,
            'stats' => $stats,
        ]);
    }

    public function forceProcess()
    {
        try {
            $updated = DB::table('ai_matching_queue')
                ->where('status', 'pending')
                ->update(['status' => 'processing', 'updated_at' => now()]);

            try {
                DB::table('agent_activity_logs')->insert([
                    'agent_type' => 'admin_manual',
                    'action' => 'force_process_queue',
                    'description' => "Admin force-processed {$updated} pending matching jobs",
                    'metadata' => json_encode(['admin_id' => auth()->id(), 'jobs_affected' => $updated]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
            }

            return back()->with('success', "Force-processing initiated for {$updated} pending jobs.");
        } catch (\Throwable $e) {
            return back()->withErrors(['message' => 'Failed: ' . $e->getMessage()]);
        }
    }

    public function approve($jobId)
    {
        try {
            DB::table('ai_matching_queue')
                ->where('job_id', $jobId)
                ->update(['requires_review' => false, 'status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
            return back()->with('success', "Match {$jobId} approved.");
        } catch (\Throwable $e) {
            return back()->withErrors(['message' => 'Failed: ' . $e->getMessage()]);
        }
    }

    public function reject($jobId)
    {
        try {
            DB::table('ai_matching_queue')
                ->where('job_id', $jobId)
                ->update(['requires_review' => false, 'status' => 'failed', 'updated_at' => now()]);
            return back()->with('success', "Match {$jobId} rejected.");
        } catch (\Throwable $e) {
            return back()->withErrors(['message' => 'Failed: ' . $e->getMessage()]);
        }
    }

    public function retry($jobId)
    {
        try {
            DB::table('ai_matching_queue')
                ->where('job_id', $jobId)
                ->update(['status' => 'pending', 'requires_review' => false, 'updated_at' => now()]);
            return back()->with('success', "Match {$jobId} re-queued.");
        } catch (\Throwable $e) {
            return back()->withErrors(['message' => 'Failed: ' . $e->getMessage()]);
        }
    }
}
