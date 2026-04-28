<?php

namespace App\Http\Controllers\Api;

use App\Events\SalaryPaymentProcessed;
use App\Http\Controllers\Controller;
use App\Models\SalarySchedule;
use App\Services\SalaryManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SalaryController extends Controller
{
    protected SalaryManagementService $salaryService;

    public function __construct(SalaryManagementService $salaryService)
    {
        $this->salaryService = $salaryService;
    }

    /**
     * Get salary schedules for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = SalarySchedule::query();

        if ($user->role === 'employer') {
            $query->whereHas('assignment', function ($q) use ($user) {
                $q->where('employer_id', $user->id);
            });
        } elseif ($user->role === 'maid') {
            $query->whereHas('assignment', function ($q) use ($user) {
                $q->where('maid_id', $user->id);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('payment_status', $request->status);
        }

        // Filter by month/year
        if ($request->has('month')) {
            $query->whereMonth('next_salary_due_date', $request->month);
        }
        if ($request->has('year')) {
            $query->whereYear('next_salary_due_date', $request->year);
        }

        $schedules = $query->with(['assignment.employer', 'assignment.maid'])
            ->orderBy('next_salary_due_date', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    /**
     * Get a specific salary schedule.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $schedule = SalarySchedule::with(['assignment.employer', 'assignment.maid', 'payments'])
            ->findOrFail($id);

        // Check authorization
        if ($user->role === 'employer' && $schedule->assignment->employer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        if ($user->role === 'maid' && $schedule->assignment->maid_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    /**
     * Process salary payment (employer only).
     */
    public function pay(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return response()->json([
                'success' => false,
                'message' => 'Only employers can process salary payments.',
            ], 403);
        }

        $schedule = SalarySchedule::with('assignment')->findOrFail($id);

        if ($schedule->assignment->employer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        if ($schedule->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Salary has already been paid for this period.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $amount = $request->amount ?? $schedule->monthly_salary;
        $description = 'Salary payment for period ending ' . ($schedule->current_period_end?->format('Y-m-d') ?? now()->format('Y-m-d'));
        if ($request->notes) {
            $description .= ' - ' . $request->notes;
        }

        $result = $this->salaryService->processSalaryPayment(
            $schedule->assignment_id,
            $amount,
            $description
        );

        if ($result) {
            // Fire event
            SalaryPaymentProcessed::dispatch($schedule, $result);

            return response()->json([
                'success' => true,
                'message' => 'Salary payment processed successfully.',
                'data' => $result,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to process salary payment.',
            'error' => 'Insufficient balance or processing error',
        ], 400);
    }

    /**
     * Get upcoming salary payments (for employers).
     */
    public function upcoming(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return response()->json([
                'success' => false,
                'message' => 'Only employers can view upcoming payments.',
            ], 403);
        }

        $upcoming = SalarySchedule::whereHas('assignment', function ($q) use ($user) {
            $q->where('employer_id', $user->id)
                ->where('status', 'active');
        })
            ->where('payment_status', 'pending')
            ->where('next_salary_due_date', '<=', now()->addDays(7))
            ->with('assignment.maid')
            ->orderBy('next_salary_due_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $upcoming,
        ]);
    }

    /**
     * Get salary history (for maids).
     */
    public function history(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'maid') {
            return response()->json([
                'success' => false,
                'message' => 'Only maids can view salary history.',
            ], 403);
        }

        $history = SalarySchedule::whereHas('assignment', function ($q) use ($user) {
            $q->where('maid_id', $user->id);
        })
            ->where('payment_status', 'paid')
            ->with('assignment.employer')
            ->orderBy('next_salary_due_date', 'desc')
            ->paginate(20);

        $totalEarned = SalarySchedule::whereHas('assignment', function ($q) use ($user) {
            $q->where('maid_id', $user->id);
        })
            ->where('payment_status', 'paid')
            ->sum('monthly_salary');

        return response()->json([
            'success' => true,
            'data' => [
                'history' => $history,
                'total_earned' => $totalEarned,
                'total_earned_formatted' => '₦' . number_format($totalEarned, 2),
            ],
        ]);
    }

    /**
     * Get overdue salaries (admin only).
     */
    public function overdue(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $overdue = $this->salaryService->getOverdueSalaries();

        return response()->json([
            'success' => true,
            'data' => $overdue,
        ]);
    }

    /**
     * Get salary statistics (admin only).
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $stats = [
            'total_schedules' => SalarySchedule::count(),
            'total_paid' => SalarySchedule::where('payment_status', 'paid')->count(),
            'total_pending' => SalarySchedule::where('payment_status', 'pending')->count(),
            'total_overdue' => SalarySchedule::where('payment_status', 'overdue')->count(),
            'total_amount_scheduled' => SalarySchedule::sum('monthly_salary'),
            'total_amount_paid' => SalarySchedule::where('payment_status', 'paid')->sum('monthly_salary'),
            'this_month_paid' => SalarySchedule::where('payment_status', 'paid')
                ->whereMonth('next_salary_due_date', now()->month)
                ->sum('monthly_salary'),
            'overdue_amount' => SalarySchedule::where('payment_status', 'overdue')->sum('monthly_salary'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
