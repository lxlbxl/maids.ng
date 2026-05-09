<?php

namespace App\Http\Controllers\Api;

use App\Events\SalaryPaymentProcessed;
use App\Http\Requests\Api\Salary\ProcessSalaryRequest;
use App\Models\SalarySchedule;
use App\Services\SalaryManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SalaryController extends ApiController
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

        return $this->paginated($schedules, 'Salary schedules retrieved successfully');
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
            return $this->forbidden();
        }

        if ($user->role === 'maid' && $schedule->assignment->maid_id !== $user->id) {
            return $this->forbidden();
        }

        return $this->success($schedule, 'Salary schedule retrieved successfully');
    }

    /**
     * Process salary payment (employer only).
     */
    public function pay(ProcessSalaryRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        if ($user->role !== 'employer') {
            return $this->forbidden('Only employers can process salary payments.');
        }

        $schedule = SalarySchedule::with('assignment')->findOrFail($id);

        if ($schedule->assignment->employer_id !== $user->id) {
            return $this->forbidden();
        }

        if ($schedule->payment_status === 'paid') {
            return $this->error('Salary has already been paid for this period.', Response::HTTP_UNPROCESSABLE_ENTITY, null, 'ALREADY_PAID');
        }

        $amount = $validated['amount'] ?? $schedule->monthly_salary;
        $description = 'Salary payment for period ending ' . ($schedule->current_period_end?->format('Y-m-d') ?? now()->format('Y-m-d'));
        if (!empty($validated['notes'])) {
            $description .= ' - ' . $validated['notes'];
        }

        $result = $this->salaryService->processSalaryPayment(
            $schedule->assignment_id,
            $amount,
            $description
        );

        if ($result) {
            // Fire event
            SalaryPaymentProcessed::dispatch($schedule, $result);

            return $this->success($result, 'Salary payment processed successfully.');
        }

        return $this->error('Failed to process salary payment.', Response::HTTP_BAD_REQUEST, null, 'PAYMENT_FAILED');
    }

    /**
     * Get upcoming salary payments (for employers).
     */
    public function upcoming(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return $this->forbidden('Only employers can view upcoming payments.');
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

        return $this->success($upcoming, 'Upcoming salary payments retrieved successfully');
    }

    /**
     * Get salary history (for maids).
     */
    public function history(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'maid') {
            return $this->forbidden('Only maids can view salary history.');
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

        return $this->success([
            'history' => $history->items(),
            'total_earned' => $totalEarned,
            'total_earned_formatted' => '₦' . number_format($totalEarned, 2),
            'pagination' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ], 'Salary history retrieved successfully');
    }

    /**
     * Get overdue salaries (admin only).
     */
    public function overdue(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->forbidden('Unauthorized. Admin access required.');
        }

        $overdue = $this->salaryService->getOverdueSalaries();

        return $this->success($overdue, 'Overdue salaries retrieved successfully');
    }

    /**
     * Get salary statistics (admin only).
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->forbidden('Unauthorized. Admin access required.');
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

        return $this->success($stats, 'Salary statistics retrieved successfully');
    }
}
