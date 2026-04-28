<?php

namespace App\Http\Controllers\Api;

use App\Events\WithdrawalApproved;
use App\Http\Controllers\Controller;
use App\Models\AiMatchingQueue;
use App\Models\EmployerWallet;
use App\Models\MaidAssignment;
use App\Models\MaidWallet;
use App\Models\SalaryPayment;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SalaryManagementService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    protected WalletService $walletService;
    protected SalaryManagementService $salaryService;

    public function __construct(
        WalletService $walletService,
        SalaryManagementService $salaryService
    ) {
        $this->walletService = $walletService;
        $this->salaryService = $salaryService;
    }

    /**
     * Get dashboard statistics.
     */
    public function dashboard(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $stats = [
            'users' => [
                'total' => User::count(),
                'employers' => User::where('role', 'employer')->count(),
                'maids' => User::where('role', 'maid')->count(),
                'new_this_month' => User::whereMonth('created_at', now()->month)->count(),
            ],
            'assignments' => [
                'total' => MaidAssignment::count(),
                'pending_acceptance' => MaidAssignment::where('status', 'pending_acceptance')->count(),
                'active' => MaidAssignment::where('status', 'accepted')->count(),
                'completed' => MaidAssignment::where('status', 'completed')->count(),
            ],
            'matching' => [
                'total_jobs' => AiMatchingQueue::count(),
                'pending' => AiMatchingQueue::where('status', 'pending')->count(),
                'processing' => AiMatchingQueue::where('status', 'processing')->count(),
                'failed' => AiMatchingQueue::where('status', 'failed')->count(),
                'completed_today' => AiMatchingQueue::where('status', 'completed')
                    ->whereDate('completed_at', today())->count(),
                'requires_review' => AiMatchingQueue::where('requires_review', true)->whereNull('reviewed_at')->count(),
            ],
            'financial' => [
                'total_employer_balance' => EmployerWallet::sum('balance'),
                'total_maid_balance' => MaidWallet::sum('balance'),
                'total_escrow' => EmployerWallet::sum('escrow_balance'),
                'pending_withdrawals' => WalletTransaction::where('transaction_type', 'withdrawal_request')
                    ->where('status', 'pending')->count(),
                'pending_withdrawals_amount' => WalletTransaction::where('transaction_type', 'withdrawal_request')
                    ->where('status', 'pending')->sum('amount'),
            ],
            'salary' => [
                'active_schedules' => SalarySchedule::where('is_active', true)->count(),
                'overdue_schedules' => SalarySchedule::where('payment_status', 'overdue')
                    ->orWhere(function ($q) {
                        $q->where('payment_status', 'pending')
                            ->whereNotNull('next_salary_due_date')
                            ->where('next_salary_due_date', '<', now());
                    })->count(),
                'due_this_week' => SalarySchedule::where('payment_status', 'pending')
                    ->whereNotNull('next_salary_due_date')
                    ->whereBetween('next_salary_due_date', [now(), now()->addDays(7)])->count(),
                'total_monthly_obligation' => SalarySchedule::where('is_active', true)->sum('monthly_salary'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get pending withdrawal requests.
     */
    public function pendingWithdrawals(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $withdrawals = WalletTransaction::where('type', 'withdrawal')
            ->where('status', 'pending')
            ->with(['wallet.user'])
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $withdrawals,
        ]);
    }

    /**
     * Approve a withdrawal request.
     */
    public function approveWithdrawal(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $withdrawal = WalletTransaction::where('type', 'withdrawal')
            ->where('status', 'pending')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update withdrawal status
        $withdrawal->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
            'metadata' => array_merge($withdrawal->metadata ?? [], [
                'admin_notes' => $request->notes,
            ]),
        ]);

        // Fire event for bank transfer processing
        WithdrawalApproved::dispatch($withdrawal);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal approved successfully. Bank transfer will be processed shortly.',
            'data' => $withdrawal->fresh(),
        ]);
    }

    /**
     * Reject a withdrawal request.
     */
    public function rejectWithdrawal(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $withdrawal = WalletTransaction::where('type', 'withdrawal')
            ->where('status', 'pending')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Refund the amount to wallet
        $this->walletService->credit(
            $withdrawal->wallet->user_id,
            $withdrawal->amount,
            'refund',
            null,
            [
                'withdrawal_id' => $withdrawal->id,
                'rejection_reason' => $request->reason,
            ]
        );

        // Update withdrawal status
        $withdrawal->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $user->id,
            'metadata' => array_merge($withdrawal->metadata ?? [], [
                'rejection_reason' => $request->reason,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal rejected and amount refunded to user wallet.',
            'data' => $withdrawal->fresh(),
        ]);
    }

    /**
     * Get all users.
     */
    public function users(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get user details.
     */
    public function userDetails(int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $userDetails = User::with([
            'wallet',
            'wallet.transactions' => function ($q) {
                $q->latest()->limit(10);
            },
            'assignmentsAsEmployer',
            'assignmentsAsMaid',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $userDetails,
        ]);
    }

    /**
     * Update user status.
     */
    public function updateUserStatus(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $targetUser = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:active,suspended,banned',
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $targetUser->update([
            'status' => $request->status,
            'status_reason' => $request->reason,
            'status_updated_at' => now(),
            'status_updated_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully.',
            'data' => $targetUser->fresh(),
        ]);
    }

    /**
     * Get system settings.
     */
    public function settings(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $settings = [
            'matching_fee' => config('services.matching_fee', 5000),
            'platform_fee_percentage' => config('services.platform_fee_percentage', 5),
            'min_withdrawal_amount' => config('services.min_withdrawal_amount', 1000),
            'max_withdrawal_amount' => config('services.max_withdrawal_amount', 500000),
            'work_hours_start' => config('services.work_hours_start', 8),
            'work_hours_end' => config('services.work_hours_end', 20),
            'sms_provider' => config('services.sms.provider', 'termii'),
            'ai_matching_enabled' => config('services.ai_matching.enabled', true),
        ];

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update system settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'matching_fee' => 'nullable|numeric|min:0',
            'platform_fee_percentage' => 'nullable|numeric|min:0|max:100',
            'min_withdrawal_amount' => 'nullable|numeric|min:0',
            'max_withdrawal_amount' => 'nullable|numeric|min:0',
            'work_hours_start' => 'nullable|integer|min:0|max:23',
            'work_hours_end' => 'nullable|integer|min:0|max:23',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update settings in database or cache
        foreach ($request->only([
            'matching_fee',
            'platform_fee_percentage',
            'min_withdrawal_amount',
            'max_withdrawal_amount',
            'work_hours_start',
            'work_hours_end',
        ]) as $key => $value) {
            if ($value !== null) {
                \App\Models\Setting::set($key, $value);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
        ]);
    }

    /**
     * Salary Schedules Dashboard - Admin oversight of all schedules.
     */
    public function salarySchedules(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,reminder_sent,payment_initiated,paid,overdue,disputed',
            'employer_id' => 'nullable|integer',
            'maid_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $query = SalarySchedule::with(['employer:id,name,email,phone', 'maid:id,name,email,phone', 'assignment:id,status']);

        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }
        if ($request->filled('employer_id')) {
            $query->where('employer_id', $request->employer_id);
        }
        if ($request->filled('maid_id')) {
            $query->where('maid_id', $request->maid_id);
        }

        $schedules = $query->orderBy('next_salary_due_date')->paginate($request->input('per_page', 20));

        $summary = [
            'total' => SalarySchedule::count(),
            'active' => SalarySchedule::where('is_active', true)->count(),
            'pending' => SalarySchedule::where('payment_status', 'pending')->count(),
            'paid' => SalarySchedule::where('payment_status', 'paid')->count(),
            'overdue' => SalarySchedule::where('payment_status', 'overdue')->count(),
            'total_monthly_obligation' => SalarySchedule::where('is_active', true)->sum('monthly_salary'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'schedules' => $schedules,
            ],
        ]);
    }

    /**
     * Get overdue salary schedules with escalation info.
     */
    public function overdueSalaries(Request $request): JsonResponse
    {
        $query = SalarySchedule::where(function ($q) {
                $q->where('payment_status', 'overdue')
                    ->orWhere(function ($q2) {
                        $q2->where('payment_status', 'pending')
                            ->whereNotNull('next_salary_due_date')
                            ->where('next_salary_due_date', '<', now());
                    });
            })
            ->with(['employer:id,name,email,phone', 'maid:id,name,email,phone', 'assignment:id,status'])
            ->orderBy('next_salary_due_date')
            ->paginate($request->input('per_page', 20));

        $summary = [
            'total_overdue' => $query->total(),
            'total_overdue_amount' => $query->getCollection()->sum('monthly_salary'),
            'escalation_levels' => [
                'level_0' => $query->getCollection()->where('escalation_level', 0)->count(),
                'level_1' => $query->getCollection()->where('escalation_level', 1)->count(),
                'level_2' => $query->getCollection()->where('escalation_level', 2)->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'overdue' => $query,
            ],
        ]);
    }

    /**
     * Escalate an overdue salary schedule.
     */
    public function escalateSalary(Request $request, int $id): JsonResponse
    {
        $schedule = SalarySchedule::findOrFail($id);

        if ($schedule->payment_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Schedule is already paid.'], 422);
        }

        $schedule->escalate();

        $level = $schedule->escalation_level;
        if ($level >= 2) {
            $schedule->payment_status = 'overdue';
            $schedule->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Escalated to level {$level}.",
            'data' => $schedule->fresh(),
        ]);
    }

    /**
     * Send manual reminder for a salary schedule.
     */
    public function sendSalaryReminder(int $id): JsonResponse
    {
        $schedule = SalarySchedule::with('employer')->findOrFail($id);

        if (!$schedule->employer) {
            return response()->json(['success' => false, 'message' => 'Employer not found.'], 404);
        }

        $amountFormatted = number_format($schedule->monthly_salary, 2);
        $dueDate = $schedule->next_salary_due_date?->format('Y-m-d') ?? 'N/A';
        $message = "Salary reminder: Payment of N{$amountFormatted} for your maid is due on {$dueDate}. Please ensure your wallet is funded. - Maids.ng";

        $notificationService = app(\App\Services\NotificationService::class);
        $result = $notificationService->sendSms(
            $schedule->employer,
            $message,
            ['type' => 'salary_reminder', 'schedule_id' => $schedule->id, 'amount' => $schedule->monthly_salary],
            'salary_reminder'
        );

        if ($result['success'] || $result['scheduled']) {
            $schedule->markReminderSent();
        }

        return response()->json([
            'success' => true,
            'message' => $result['success'] ? 'Reminder sent successfully.' : 'Reminder scheduled for next work hours.',
            'data' => $result,
        ]);
    }

    /**
     * Process batch salary payments for multiple schedules.
     */
    public function processBatchPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schedule_ids' => 'required|array',
            'schedule_ids.*' => 'integer|exists:salary_schedules,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $results = ['processed' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];

        DB::beginTransaction();
        try {
            foreach ($request->schedule_ids as $scheduleId) {
                $schedule = SalarySchedule::with('assignment')->find($scheduleId);

                if (!$schedule) {
                    $results['skipped']++;
                    continue;
                }

                if ($schedule->payment_status === 'paid') {
                    $results['skipped']++;
                    continue;
                }

                if (!$schedule->assignment || $schedule->assignment->status !== 'active') {
                    $results['skipped']++;
                    continue;
                }

                $result = $this->salaryService->processSalaryPayment(
                    $schedule->assignment_id,
                    $schedule->monthly_salary,
                    'Admin batch payment: ' . ($request->notes ?? '')
                );

                if ($result) {
                    $schedule->advancePeriod();
                    $results['processed']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Schedule #{$scheduleId}: Insufficient balance or processing error";
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Batch payment failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Batch payment complete. {$results['processed']} processed, {$results['failed']} failed, {$results['skipped']} skipped.",
            'data' => $results,
        ]);
    }

    /**
     * Manually mark a schedule as paid (admin override).
     */
    public function markSchedulePaid(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $schedule = SalarySchedule::findOrFail($id);

        if ($schedule->payment_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Already paid.'], 422);
        }

        $user = Auth::user();

        $schedule->update([
            'payment_status' => 'paid',
            'special_notes' => ($schedule->special_notes ?? '') . "\n[Admin override by {$user->id}: {$request->notes}]",
        ]);

        $schedule->advancePeriod();

        return response()->json([
            'success' => true,
            'message' => 'Schedule marked as paid.',
            'data' => $schedule->fresh(),
        ]);
    }

    /**
     * AI Matching Engine Monitor - Queue status and job management.
     */
    public function aiMatchingMonitor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,scheduled,processing,completed,failed,cancelled,paused',
            'job_type' => 'nullable|string',
            'employer_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $query = AiMatchingQueue::with(['employer:id,name,email', 'maid:id,name', 'preference:id', 'assignment:id', 'reviewer:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('job_type')) {
            $query->where('job_type', $request->job_type);
        }
        if ($request->filled('employer_id')) {
            $query->where('employer_id', $request->employer_id);
        }

        $jobs = $query->orderBy('priority')->orderBy('created_at')->paginate($request->input('per_page', 20));

        $summary = [
            'total' => AiMatchingQueue::count(),
            'pending' => AiMatchingQueue::pending()->count(),
            'processing' => AiMatchingQueue::processing()->count(),
            'completed' => AiMatchingQueue::completed()->count(),
            'failed' => AiMatchingQueue::failed()->count(),
            'requires_review' => AiMatchingQueue::requiresReview()->count(),
            'avg_processing_time_ms' => AiMatchingQueue::whereNotNull('processing_duration_ms')->avg('processing_duration_ms'),
            'success_rate' => AiMatchingQueue::count() > 0
                ? round((AiMatchingQueue::completed()->count() / AiMatchingQueue::count()) * 100, 2)
                : 0,
            'by_type' => AiMatchingQueue::selectRaw('job_type, COUNT(*) as count')
                ->groupBy('job_type')
                ->pluck('count', 'job_type'),
            'today' => [
                'created' => AiMatchingQueue::whereDate('created_at', today())->count(),
                'completed' => AiMatchingQueue::completed()->whereDate('completed_at', today())->count(),
                'failed' => AiMatchingQueue::failed()->whereDate('updated_at', today())->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'jobs' => $jobs,
            ],
        ]);
    }

    /**
     * Get detailed info for a specific AI matching job.
     */
    public function aiMatchingJobDetail(string $jobId): JsonResponse
    {
        $job = AiMatchingQueue::where('job_id', $jobId)
            ->with(['employer', 'maid', 'preference', 'assignment', 'reviewer', 'parentJob', 'childJobs'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $job,
        ]);
    }

    /**
     * Retry a failed AI matching job.
     */
    public function retryMatchingJob(string $jobId): JsonResponse
    {
        $job = AiMatchingQueue::where('job_id', $jobId)->firstOrFail();

        if (!$job->canRetry()) {
            return response()->json([
                'success' => false,
                'message' => 'Job cannot be retried (max attempts reached or not in retryable state).',
            ], 422);
        }

        $job->update([
            'status' => 'pending',
            'next_attempt_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Job queued for retry.',
            'data' => ['job_id' => $job->job_id, 'status' => 'pending'],
        ]);
    }

    /**
     * Cancel a pending/scheduled AI matching job.
     */
    public function cancelMatchingJob(string $jobId): JsonResponse
    {
        $job = AiMatchingQueue::where('job_id', $jobId)->firstOrFail();

        if (!in_array($job->status, ['pending', 'scheduled'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot cancel job with status: {$job->status}",
            ], 422);
        }

        $job->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Job cancelled.',
        ]);
    }

    /**
     * Wallet Overview - Employer and Maid balances.
     */
    public function walletOverview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'wallet_type' => 'nullable|string|in:employer,maid',
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $type = $request->input('wallet_type', 'employer');

        if ($type === 'employer') {
            $query = EmployerWallet::with(['employer:id,name,email,phone']);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('employer', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $wallets = $query->orderBy('balance', 'desc')->paginate($request->input('per_page', 20));
        } else {
            $query = MaidWallet::with(['maid:id,name,email,phone']);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('maid', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $wallets = $query->orderBy('balance', 'desc')->paginate($request->input('per_page', 20));
        }

        $summary = [
            'employer' => [
                'total_wallets' => EmployerWallet::count(),
                'total_balance' => EmployerWallet::sum('balance'),
                'total_escrow' => EmployerWallet::sum('escrow_balance'),
                'total_deposited' => EmployerWallet::sum('total_deposited'),
                'total_spent' => EmployerWallet::sum('total_spent'),
            ],
            'maid' => [
                'total_wallets' => MaidWallet::count(),
                'total_balance' => MaidWallet::sum('balance'),
                'total_earned' => MaidWallet::sum('total_earned'),
                'total_withdrawn' => MaidWallet::sum('total_withdrawn'),
                'pending_withdrawal' => MaidWallet::sum('pending_withdrawal'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'wallets' => $wallets,
            ],
        ]);
    }

    /**
     * Adjust a wallet balance (admin override).
     */
    public function adjustWalletBalance(Request $request, int $walletId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'wallet_type' => 'required|string|in:employer,maid',
            'amount' => 'required|numeric',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $amount = (float) $request->amount;

        if ($request->wallet_type === 'employer') {
            $wallet = EmployerWallet::findOrFail($walletId);

            if ($wallet->employer_id === null) {
                return response()->json(['success' => false, 'message' => 'Invalid wallet.'], 422);
            }

            DB::beginTransaction();
            try {
                $balanceBefore = $wallet->balance;
                $wallet->balance += $amount;
                if ($amount > 0) {
                    $wallet->total_deposited += $amount;
                }
                $wallet->last_activity_at = now();
                $wallet->save();

                WalletTransaction::create([
                    'wallet_type' => 'employer',
                    'employer_id' => $wallet->employer_id,
                    'transaction_type' => $amount > 0 ? 'credit' : 'debit',
                    'amount' => abs($amount),
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->balance,
                    'description' => "Admin adjustment: {$request->reason}",
                    'status' => 'completed',
                    'processed_at' => now(),
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        } else {
            $wallet = MaidWallet::findOrFail($walletId);

            if ($wallet->maid_id === null) {
                return response()->json(['success' => false, 'message' => 'Invalid wallet.'], 422);
            }

            DB::beginTransaction();
            try {
                $balanceBefore = $wallet->balance;
                $wallet->balance += $amount;
                if ($amount > 0) {
                    $wallet->total_earned += $amount;
                }
                $wallet->last_activity_at = now();
                $wallet->save();

                WalletTransaction::create([
                    'wallet_type' => 'maid',
                    'maid_id' => $wallet->maid_id,
                    'transaction_type' => $amount > 0 ? 'credit' : 'debit',
                    'amount' => abs($amount),
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->balance,
                    'description' => "Admin adjustment: {$request->reason}",
                    'status' => 'completed',
                    'processed_at' => now(),
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Wallet balance adjusted by " . ($amount > 0 ? '+' : '') . "N" . number_format($amount, 2),
            'data' => $wallet->fresh(),
        ]);
    }

    /**
     * Salary payment history (admin view).
     */
    public function salaryPaymentHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,employer_paid,processing,paid_to_maid,failed,disputed,refunded',
            'employer_id' => 'nullable|integer',
            'maid_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $query = SalaryPayment::with(['employer:id,name', 'maid:id,name', 'assignment:id', 'processor:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('employer_id')) {
            $query->where('employer_id', $request->employer_id);
        }
        if ($request->filled('maid_id')) {
            $query->where('maid_id', $request->maid_id);
        }

        $payments = $query->orderBy('due_date', 'desc')->paginate($request->input('per_page', 20));

        $summary = [
            'total' => SalaryPayment::count(),
            'total_amount' => SalaryPayment::sum('net_amount'),
            'paid' => SalaryPayment::where('status', 'paid_to_maid')->count(),
            'pending' => SalaryPayment::where('status', 'pending')->count(),
            'overdue' => SalaryPayment::overdue()->count(),
            'disputed' => SalaryPayment::where('status', 'disputed')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'payments' => $payments,
            ],
        ]);
    }
}
