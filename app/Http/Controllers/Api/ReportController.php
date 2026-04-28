<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentActivityLog;
use App\Models\AiMatchingQueue;
use App\Models\EmployerWallet;
use App\Models\MaidAssignment;
use App\Models\MaidProfile;
use App\Models\MaidWallet;
use App\Models\NotificationLog;
use App\Models\SalaryPayment;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * Get platform overview statistics (Admin only).
     */
    public function platformOverview(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can access this endpoint.',
            ], 403);
        }

        // User statistics
        $totalUsers = User::count();
        $totalMaids = User::role('maid')->count();
        $totalEmployers = User::role('employer')->count();
        $verifiedMaids = MaidProfile::where('nin_verified', true)
            ->where('background_verified', true)
            ->count();
        $availableMaids = MaidProfile::where('availability_status', 'available')->count();

        // Assignment statistics
        $totalAssignments = MaidAssignment::count();
        $activeAssignments = MaidAssignment::active()->count();
        $completedAssignments = MaidAssignment::where('status', 'completed')->count();
        $pendingAcceptance = MaidAssignment::where('status', 'pending_acceptance')->count();

        // Financial statistics
        $totalRevenue = MaidAssignment::where('matching_fee_paid', true)
            ->sum('matching_fee_amount');
        $totalSalaryPaid = SalaryPayment::where('status', 'paid')->sum('amount');
        $totalMaidEarnings = MaidWallet::sum('balance');
        $totalEmployerBalances = EmployerWallet::sum('balance');

        // Recent activity
        $recentAssignments = MaidAssignment::with(['employer:id,name', 'maid:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                    'maids' => $totalMaids,
                    'employers' => $totalEmployers,
                    'verified_maids' => $verifiedMaids,
                    'available_maids' => $availableMaids,
                ],
                'assignments' => [
                    'total' => $totalAssignments,
                    'active' => $activeAssignments,
                    'completed' => $completedAssignments,
                    'pending_acceptance' => $pendingAcceptance,
                ],
                'financial' => [
                    'total_revenue' => (float) $totalRevenue,
                    'total_salary_paid' => (float) $totalSalaryPaid,
                    'total_maid_earnings_balance' => (float) $totalMaidEarnings,
                    'total_employer_balances' => (float) $totalEmployerBalances,
                ],
                'recent_assignments' => $recentAssignments,
            ],
        ]);
    }

    /**
     * Get financial reports (Admin only).
     */
    public function financialReport(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'group_by' => 'nullable|string|in:day,week,month,year',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $startDate = $request->input('start_date', now()->subMonths(6)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $groupBy = $request->input('group_by', 'month');

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Revenue from matching fees
        $revenueQuery = MaidAssignment::where('matching_fee_paid', true)
            ->whereBetween('employer_accepted_at', [$start, $end]);

        // Salary payments
        $salaryQuery = SalaryPayment::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end]);

        // Group by time period
        $revenueData = $this->groupByTimePeriod($revenueQuery, 'employer_accepted_at', $groupBy, 'matching_fee_amount');
        $salaryData = $this->groupByTimePeriod($salaryQuery, 'paid_at', $groupBy, 'amount');

        // Summary statistics
        $totalRevenue = $revenueQuery->sum('matching_fee_amount');
        $totalSalaries = $salaryQuery->sum('amount');
        $totalTransactions = WalletTransaction::whereBetween('created_at', [$start, $end])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                    'group_by' => $groupBy,
                ],
                'summary' => [
                    'total_revenue' => (float) $totalRevenue,
                    'total_salaries_paid' => (float) $totalSalaries,
                    'total_transactions' => $totalTransactions,
                    'net_platform_revenue' => (float) ($totalRevenue - $totalSalaries),
                ],
                'revenue_trend' => $revenueData,
                'salary_trend' => $salaryData,
            ],
        ]);
    }

    /**
     * Get user activity reports (Admin only).
     */
    public function userActivityReport(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_type' => 'nullable|string|in:maid,employer,all',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $startDate = $request->input('start_date', now()->subMonths(1)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $userType = $request->input('user_type', 'all');

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // New registrations
        $userQuery = User::whereBetween('created_at', [$start, $end]);
        if ($userType !== 'all') {
            $userQuery->role($userType);
        }
        $newRegistrations = $userQuery->count();

        // Active users (users with assignments in period)
        $activeEmployers = MaidAssignment::whereBetween('created_at', [$start, $end])
            ->distinct('employer_id')
            ->count('employer_id');

        $activeMaids = MaidAssignment::whereBetween('created_at', [$start, $end])
            ->distinct('maid_id')
            ->count('maid_id');

        // Top employers by assignments
        $topEmployers = MaidAssignment::select('employer_id', DB::raw('count(*) as assignment_count'))
            ->whereBetween('created_at', [$start, $end])
            ->with('employer:id,name,email')
            ->groupBy('employer_id')
            ->orderBy('assignment_count', 'desc')
            ->limit(10)
            ->get();

        // Top maids by assignments
        $topMaids = MaidAssignment::select('maid_id', DB::raw('count(*) as assignment_count'))
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->with('maid:id,name,email')
            ->groupBy('maid_id')
            ->orderBy('assignment_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'new_registrations' => $newRegistrations,
                'active_employers' => $activeEmployers,
                'active_maids' => $activeMaids,
                'top_employers' => $topEmployers,
                'top_maids' => $topMaids,
            ],
        ]);
    }

    /**
     * Get assignment analytics (Admin only).
     */
    public function assignmentAnalytics(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can access this endpoint.',
            ], 403);
        }

        // Status distribution
        $statusDistribution = MaidAssignment::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Assignment type distribution
        $typeDistribution = MaidAssignment::select('assignment_type', DB::raw('count(*) as count'))
            ->groupBy('assignment_type')
            ->get()
            ->pluck('count', 'assignment_type');

        // Average time to acceptance
        $avgTimeToAccept = MaidAssignment::whereNotNull('employer_accepted_at')
            ->whereNotNull('created_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, employer_accepted_at)) as avg_hours'))
            ->first();

        // Completion rate
        $totalCompleted = MaidAssignment::where('status', 'completed')->count();
        $totalStarted = MaidAssignment::whereIn('status', ['completed', 'cancelled'])->count();
        $completionRate = $totalStarted > 0 ? ($totalCompleted / $totalStarted) * 100 : 0;

        // Acceptance rate
        $totalResponded = MaidAssignment::whereIn('status', ['accepted', 'rejected'])->count();
        $totalAccepted = MaidAssignment::where('status', 'accepted')->count();
        $acceptanceRate = $totalResponded > 0 ? ($totalAccepted / $totalResponded) * 100 : 0;

        // Monthly trend
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $created = MaidAssignment::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $completed = MaidAssignment::where('status', 'completed')
                ->whereYear('completed_at', $date->year)
                ->whereMonth('completed_at', $date->month)
                ->count();

            $monthlyTrend[] = [
                'month' => $date->format('M Y'),
                'created' => $created,
                'completed' => $completed,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status_distribution' => $statusDistribution,
                'type_distribution' => $typeDistribution,
                'average_time_to_accept_hours' => $avgTimeToAccept?->avg_hours ?? 0,
                'completion_rate' => round($completionRate, 2),
                'acceptance_rate' => round($acceptanceRate, 2),
                'monthly_trend' => $monthlyTrend,
            ],
        ]);
    }

    /**
     * Get AI matching reports (Admin only).
     */
    public function aiMatchingReport(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $startDate = $request->input('start_date', now()->subMonths(1)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Queue statistics
        $totalJobs = AiMatchingQueue::whereBetween('created_at', [$start, $end])->count();
        $completedJobs = AiMatchingQueue::where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->count();
        $failedJobs = AiMatchingQueue::where('status', 'failed')
            ->whereBetween('updated_at', [$start, $end])
            ->count();
        $pendingJobs = AiMatchingQueue::where('status', 'pending')->count();
        $processingJobs = AiMatchingQueue::where('status', 'processing')->count();

        // Average processing time
        $avgProcessingTime = AiMatchingQueue::where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) as avg_minutes'))
            ->first();

        // Success rate
        $successRate = $totalJobs > 0 ? ($completedJobs / $totalJobs) * 100 : 0;

        // Recent jobs
        $recentJobs = AiMatchingQueue::with(['preference:id', 'assignment:id'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'queue_statistics' => [
                    'total_jobs' => $totalJobs,
                    'completed' => $completedJobs,
                    'failed' => $failedJobs,
                    'pending' => $pendingJobs,
                    'processing' => $processingJobs,
                    'success_rate' => round($successRate, 2),
                    'average_processing_minutes' => $avgProcessingTime?->avg_minutes ?? 0,
                ],
                'recent_jobs' => $recentJobs,
            ],
        ]);
    }

    /**
     * Get notification delivery reports (Admin only).
     */
    public function notificationReport(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $startDate = $request->input('start_date', now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Channel distribution
        $channelDistribution = NotificationLog::whereBetween('created_at', [$start, $end])
            ->select('channel', DB::raw('count(*) as count'))
            ->groupBy('channel')
            ->get()
            ->pluck('count', 'channel');

        // Status distribution
        $statusDistribution = NotificationLog::whereBetween('created_at', [$start, $end])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Delivery rate
        $totalNotifications = NotificationLog::whereBetween('created_at', [$start, $end])->count();
        $deliveredNotifications = NotificationLog::where('status', 'delivered')
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $deliveryRate = $totalNotifications > 0 ? ($deliveredNotifications / $totalNotifications) * 100 : 0;

        // Recent notifications
        $recentNotifications = NotificationLog::with(['user:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'channel_distribution' => $channelDistribution,
                'status_distribution' => $statusDistribution,
                'delivery_rate' => round($deliveryRate, 2),
                'total_notifications' => $totalNotifications,
                'recent_notifications' => $recentNotifications,
            ],
        ]);
    }

    /**
     * Get agent activity logs (Admin only).
     */
    public function agentActivityLogs(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'agent_type' => 'nullable|string|in:ai_matching,assignment_manager,salary_manager,notification_manager,all',
            'action' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = AgentActivityLog::query();

        if ($request->filled('agent_type') && $request->agent_type !== 'all') {
            $query->where('agent_type', $request->agent_type);
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 25));

        // Summary statistics
        $summary = [
            'total_logs' => AgentActivityLog::count(),
            'ai_matching_logs' => AgentActivityLog::where('agent_type', 'ai_matching')->count(),
            'assignment_logs' => AgentActivityLog::where('agent_type', 'assignment_manager')->count(),
            'salary_logs' => AgentActivityLog::where('agent_type', 'salary_manager')->count(),
            'notification_logs' => AgentActivityLog::where('agent_type', 'notification_manager')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'logs' => $logs,
            ],
        ]);
    }

    /**
     * Export report data (Admin only).
     */
    public function export(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'report_type' => 'required|string|in:assignments,financial,users,notifications,ai_matching',
            'format' => 'required|string|in:csv,json',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $startDate = $request->input('start_date', now()->subMonths(1)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $data = [];

        switch ($request->report_type) {
            case 'assignments':
                $data = MaidAssignment::with(['employer:id,name,email', 'maid:id,name,email'])
                    ->whereBetween('created_at', [$start, $end])
                    ->get();
                break;

            case 'financial':
                $data = WalletTransaction::with(['user:id,name,email'])
                    ->whereBetween('created_at', [$start, $end])
                    ->get();
                break;

            case 'users':
                $data = User::with('roles')
                    ->whereBetween('created_at', [$start, $end])
                    ->get();
                break;

            case 'notifications':
                $data = NotificationLog::with(['user:id,name,email'])
                    ->whereBetween('created_at', [$start, $end])
                    ->get();
                break;

            case 'ai_matching':
                $data = AiMatchingQueue::with(['preference:id', 'assignment:id'])
                    ->whereBetween('created_at', [$start, $end])
                    ->get();
                break;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'report_type' => $request->report_type,
                'format' => $request->format,
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'record_count' => $data->count(),
                'records' => $data,
            ],
        ]);
    }

    /**
     * Helper method to group query results by time period.
     */
    private function groupByTimePeriod($query, string $dateColumn, string $groupBy, string $sumColumn): array
    {
        $format = match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m',
        };

        $results = $query->select(
            DB::raw("DATE_FORMAT({$dateColumn}, '{$format}') as period"),
            DB::raw("SUM({$sumColumn}) as total")
        )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $results->map(function ($item) {
            return [
                'period' => $item->period,
                'total' => (float) $item->total,
            ];
        })->toArray();
    }
}
