<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployerPreference;
use App\Models\EmployerWallet;
use App\Models\MaidAssignment;
use App\Models\Review;
use App\Models\SalaryPayment;
use App\Models\SalarySchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmployerController extends Controller
{
    /**
     * Get authenticated employer's profile.
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'status' => $user->status,
                'location' => $user->location,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Update employer profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',
            'avatar' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'location' => $user->location,
            ],
        ]);
    }

    /**
     * Get employer's preferences.
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:active,fulfilled,cancelled,all',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = EmployerPreference::where('employer_id', $user->id)
            ->with(['assignment', 'assignment.maid:id,name,avatar']);

        $status = $request->input('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $preferences = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $preferences,
        ]);
    }

    /**
     * Create a new preference.
     */
    public function createPreference(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'help_types' => 'required|array',
            'help_types.*' => 'string|in:live-in,nanny,cooking,elderly-care,driver,cleaning,laundry,childcare',
            'schedule_type' => 'required|string|in:live-in,part-time,full-time,weekends-only,flexible',
            'location' => 'required|string|max:255',
            'state' => 'required|string|max:100',
            'lga' => 'nullable|string|max:100',
            'min_experience' => 'nullable|integer|min:0',
            'max_salary' => 'nullable|integer|min:0',
            'special_requirements' => 'nullable|array',
            'special_requirements.*' => 'string',
            'preferred_gender' => 'nullable|string|in:male,female,no_preference',
            'preferred_age_range' => 'nullable|string|in:18-25,26-35,36-45,46-55,56+,no_preference',
            'start_date' => 'nullable|date|after_or_equal:today',
            'urgency' => 'nullable|string|in:low,medium,high,urgent',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $preference = EmployerPreference::create([
            'employer_id' => $user->id,
            'help_types' => $request->help_types,
            'schedule_type' => $request->schedule_type,
            'location' => $request->location,
            'state' => $request->state,
            'lga' => $request->lga,
            'min_experience' => $request->min_experience ?? 0,
            'max_salary' => $request->max_salary,
            'special_requirements' => $request->special_requirements ?? [],
            'preferred_gender' => $request->preferred_gender ?? 'no_preference',
            'preferred_age_range' => $request->preferred_age_range ?? 'no_preference',
            'start_date' => $request->start_date,
            'urgency' => $request->urgency ?? 'medium',
            'notes' => $request->notes,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preference created successfully.',
            'data' => $preference,
        ], 201);
    }

    /**
     * Get specific preference details.
     */
    public function preferenceDetail(int $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $preference = EmployerPreference::where('employer_id', $user->id)
            ->with(['assignment', 'assignment.maid:id,name,avatar,phone', 'assignment.salarySchedule'])
            ->find($id);

        if (!$preference) {
            return response()->json([
                'success' => false,
                'message' => 'Preference not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $preference,
        ]);
    }

    /**
     * Update a preference.
     */
    public function updatePreference(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $preference = EmployerPreference::where('employer_id', $user->id)->find($id);

        if (!$preference) {
            return response()->json([
                'success' => false,
                'message' => 'Preference not found.',
            ], 404);
        }

        // Only allow updates if preference is still active
        if ($preference->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a preference that is not active.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'help_types' => 'nullable|array',
            'help_types.*' => 'string|in:live-in,nanny,cooking,elderly-care,driver,cleaning,laundry,childcare',
            'schedule_type' => 'nullable|string|in:live-in,part-time,full-time,weekends-only,flexible',
            'location' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'lga' => 'nullable|string|max:100',
            'min_experience' => 'nullable|integer|min:0',
            'max_salary' => 'nullable|integer|min:0',
            'special_requirements' => 'nullable|array',
            'special_requirements.*' => 'string',
            'preferred_gender' => 'nullable|string|in:male,female,no_preference',
            'preferred_age_range' => 'nullable|string|in:18-25,26-35,36-45,46-55,56+,no_preference',
            'start_date' => 'nullable|date|after_or_equal:today',
            'urgency' => 'nullable|string|in:low,medium,high,urgent',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $preference->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Preference updated successfully.',
            'data' => $preference->fresh(),
        ]);
    }

    /**
     * Cancel a preference.
     */
    public function cancelPreference(int $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $preference = EmployerPreference::where('employer_id', $user->id)->find($id);

        if (!$preference) {
            return response()->json([
                'success' => false,
                'message' => 'Preference not found.',
            ], 404);
        }

        if ($preference->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Preference is already cancelled.',
            ], 422);
        }

        $preference->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Preference cancelled successfully.',
        ]);
    }

    /**
     * Get employer's assignments.
     */
    public function assignments(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending_acceptance,accepted,rejected,completed,cancelled,all',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = MaidAssignment::forEmployer($user->id)
            ->with(['maid:id,name,email,phone,avatar', 'preference', 'salarySchedule']);

        $status = $request->input('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $assignments = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    /**
     * Get specific assignment details.
     */
    public function assignmentDetail(int $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $assignment = MaidAssignment::forEmployer($user->id)
            ->with([
                'maid:id,name,email,phone,avatar',
                'maid.maidProfile:bio,skills,experience_years,rating,total_reviews',
                'preference',
                'salarySchedule',
                'salaryPayments',
            ])
            ->find($id);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    /**
     * Get employer's spending summary.
     */
    public function spending(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $wallet = EmployerWallet::firstOrCreate(
            ['employer_id' => $user->id],
            ['balance' => 0, 'currency' => 'NGN']
        );

        // Calculate spending statistics
        $totalSpent = SalaryPayment::where('employer_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount');

        $totalMatchingFees = MaidAssignment::forEmployer($user->id)
            ->where('matching_fee_paid', true)
            ->sum('matching_fee_amount');

        $totalAssignments = MaidAssignment::forEmployer($user->id)
            ->where('status', 'completed')
            ->count();

        $activeAssignments = MaidAssignment::forEmployer($user->id)
            ->active()
            ->count();

        $currentMonthSpending = SalaryPayment::where('employer_id', $user->id)
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        // Get monthly spending for the last 6 months
        $monthlySpending = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $spending = SalaryPayment::where('employer_id', $user->id)
                ->where('status', 'paid')
                ->whereMonth('paid_at', $date->month)
                ->whereYear('paid_at', $date->year)
                ->sum('amount');

            $monthlySpending[] = [
                'month' => $date->format('M Y'),
                'spending' => (float) $spending,
            ];
        }

        // Upcoming payments
        $upcomingPayments = SalarySchedule::where('employer_id', $user->id)
            ->where('payment_status', 'pending')
            ->where('next_salary_due_date', '>=', now())
            ->sum('monthly_salary');

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => [
                    'balance' => $wallet->balance,
                    'currency' => $wallet->currency,
                ],
                'statistics' => [
                    'total_spent' => (float) $totalSpent,
                    'total_matching_fees' => (float) $totalMatchingFees,
                    'current_month_spending' => (float) $currentMonthSpending,
                    'total_completed_assignments' => $totalAssignments,
                    'active_assignments' => $activeAssignments,
                    'upcoming_payments' => (float) $upcomingPayments,
                ],
                'monthly_trend' => $monthlySpending,
            ],
        ]);
    }

    /**
     * Get payment history.
     */
    public function paymentHistory(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payments = SalaryPayment::where('employer_id', $user->id)
            ->with(['assignment:id,maid_id,job_location', 'assignment.maid:id,name'])
            ->orderBy('paid_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Get upcoming salary schedules.
     */
    public function upcomingPayments(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $schedules = SalarySchedule::where('employer_id', $user->id)
            ->with(['assignment:id,maid_id,job_location', 'assignment.maid:id,name'])
            ->where('payment_status', 'pending')
            ->where('next_salary_due_date', '>=', now())
            ->orderBy('next_salary_due_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    /**
     * Submit a review for a maid.
     */
    public function submitReview(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'assignment_id' => 'required|integer|exists:maid_assignments,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
            'categories' => 'nullable|array',
            'categories.*' => 'string|in:punctuality,professionalism,skill,communication,trustworthiness',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $assignment = MaidAssignment::forEmployer($user->id)
            ->find($request->assignment_id);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found.',
            ], 404);
        }

        // Only allow reviews for completed assignments
        if (!$assignment->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Can only review completed assignments.',
            ], 422);
        }

        // Check if already reviewed
        $existingReview = Review::where('assignment_id', $assignment->id)
            ->where('employer_id', $user->id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this assignment.',
            ], 422);
        }

        $review = Review::create([
            'assignment_id' => $assignment->id,
            'employer_id' => $user->id,
            'maid_id' => $assignment->maid_id,
            'rating' => $request->rating,
            'review' => $request->review,
            'categories' => $request->categories ?? [],
        ]);

        // Update maid's average rating
        $maidProfile = $assignment->maid->maidProfile;
        if ($maidProfile) {
            $averageRating = Review::where('maid_id', $assignment->maid_id)->avg('rating');
            $totalReviews = Review::where('maid_id', $assignment->maid_id)->count();
            $maidProfile->update([
                'rating' => $averageRating,
                'total_reviews' => $totalReviews,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data' => $review,
        ], 201);
    }

    /**
     * Get reviews given by employer.
     */
    public function myReviews(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $reviews = Review::where('employer_id', $user->id)
            ->with(['maid:id,name,avatar', 'assignment:id,job_location'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }

    /**
     * Get employer dashboard statistics.
     */
    public function dashboard(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isEmployer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only employers can access this endpoint.',
            ], 403);
        }

        // Assignment statistics
        $pendingAcceptance = MaidAssignment::forEmployer($user->id)
            ->where('status', 'pending_acceptance')
            ->count();

        $activeAssignments = MaidAssignment::forEmployer($user->id)
            ->active()
            ->count();

        $completedAssignments = MaidAssignment::forEmployer($user->id)
            ->where('status', 'completed')
            ->count();

        $activePreferences = EmployerPreference::where('employer_id', $user->id)
            ->where('status', 'active')
            ->count();

        // Wallet info
        $wallet = EmployerWallet::firstOrCreate(
            ['employer_id' => $user->id],
            ['balance' => 0, 'currency' => 'NGN']
        );

        // Recent assignments
        $recentAssignments = MaidAssignment::forEmployer($user->id)
            ->with(['maid:id,name,avatar'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Upcoming payments
        $upcomingPayments = SalarySchedule::where('employer_id', $user->id)
            ->where('payment_status', 'pending')
            ->where('next_salary_due_date', '>=', now())
            ->where('next_salary_due_date', '<=', now()->addDays(7))
            ->sum('monthly_salary');

        // Total spent this month
        $currentMonthSpending = SalaryPayment::where('employer_id', $user->id)
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => [
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                ],
                'statistics' => [
                    'pending_acceptance' => $pendingAcceptance,
                    'active_assignments' => $activeAssignments,
                    'completed_assignments' => $completedAssignments,
                    'active_preferences' => $activePreferences,
                    'wallet_balance' => $wallet->balance,
                    'upcoming_payments' => (float) $upcomingPayments,
                    'current_month_spending' => (float) $currentMonthSpending,
                ],
                'recent_assignments' => $recentAssignments,
            ],
        ]);
    }
}
