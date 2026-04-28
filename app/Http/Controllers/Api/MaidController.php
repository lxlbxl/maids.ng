<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaidAssignment;
use App\Models\MaidProfile;
use App\Models\MaidWallet;
use App\Models\SalaryPayment;
use App\Models\SalarySchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MaidController extends Controller
{
    /**
     * Get authenticated maid's profile.
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isMaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only maids can access this endpoint.',
            ], 403);
        }

        $profile = $user->maidProfile()->with('user')->first();

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Maid profile not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'status' => $user->status,
                    'location' => $user->location,
                ],
                'profile' => $profile,
                'average_rating' => $user->getAverageRating(),
            ],
        ]);
    }

    /**
     * Update maid profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isMaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only maids can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'bio' => 'nullable|string|max:1000',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'experience_years' => 'nullable|integer|min:0|max:50',
            'help_types' => 'nullable|array',
            'help_types.*' => 'string|in:live-in,nanny,cooking,elderly-care,driver,cleaning,laundry,childcare',
            'schedule_preference' => 'nullable|string|in:live-in,part-time,full-time,weekends-only,flexible',
            'expected_salary' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'lga' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:20',
            'account_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $profile = $user->maidProfile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'availability_status' => 'available',
                'rating' => 0,
                'total_reviews' => 0,
            ]
        );

        $profile->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $profile->fresh(),
        ]);
    }

    /**
     * Update availability status.
     */
    public function updateAvailability(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isMaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only maids can access this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'availability_status' => 'required|string|in:available,matched,busy,unavailable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $profile = $user->maidProfile()->first();

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Maid profile not found.',
            ], 404);
        }

        // Check if maid has active assignments before making available
        if ($request->availability_status === 'available') {
            $activeAssignments = MaidAssignment::forMaid($user->id)
                ->active()
                ->count();

            if ($activeAssignments > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot set status to available while having active assignments.',
                ], 422);
            }
        }

        $profile->update(['availability_status' => $request->availability_status]);

        return response()->json([
            'success' => true,
            'message' => 'Availability status updated successfully.',
            'data' => [
                'availability_status' => $profile->availability_status,
            ],
        ]);
    }

    /**
     * Get maid's assignments.
     */
    public function assignments(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isMaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only maids can access this endpoint.',
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

        $query = MaidAssignment::forMaid($user->id)
            ->with(['employer:id,name,email,phone', 'preference']);

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

        if (!$user->isMaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only maids can access this endpoint.',
            ], 403);
        }

        $assignment = MaidAssignment::forMaid($user->id)
            ->with([
                'employer:id,name,email,phone,avatar',
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
     * Get maid's earnings summary.
     */
    public function earnings(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isMaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only maids can access this endpoint.',
            ], 403);
        }

        $wallet = MaidWallet::firstOrCreate(
            ['maid_id' => $user->id],
            ['balance' => 0, 'currency' => 'NGN']
        );

        // Calculate earnings statistics
        $totalEarned = SalaryPayment::where('maid_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount');

        $totalAssignments = MaidAssignment::forMaid($user->id)
            ->where('status', 'completed')
            ->count();

        $activeAssignments = MaidAssignment::forMaid($user->id)
            ->active()
            ->count();

        $currentMonthEarnings = SalaryPayment::where('maid_id', $user->id)
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $lastMonthEarnings = SalaryPayment::where('maid_id', $user->id)
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->whereYear('paid_at', now()->subMonth()->year)
            ->sum('amount');

        // Get monthly earnings for the last 6 months
        $monthlyEarnings = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $earnings = SalaryPayment::where('maid_id', $user->id)
                ->where('status', 'paid')
                ->whereMonth('paid_at', $date->month)
                ->whereYear('paid_at', $date->year)
                ->sum('amount');

            $monthlyEarnings[] = [
                'month' => $date->format('M Y'),
                'earnings' => (float) $earnings,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => [
                    'balance' => $wallet->balance,
                    'currency' => $wallet->currency,
                ],
                'statistics' => [
                    'total_earned' => (float) $totalEarned,
                    'current_month_earnings' => (float) $currentMonthEarnings,
                    'last_month_earnings' => (float) $lastMonthEarnings,
                    'total_completed_assignments' => $totalAssignments,
                    'active_assignments' => $activeAssignments,
                ],
                'monthly_trend' => $monthlyEarnings,
            ],
        ]);
    }

    /**
     * Get payment history.
     */
    public function paymentHistory(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isMaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only maids can access this endpoint.',
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

        $payments = SalaryPayment::where('maid_id', $user->id)
            ->with(['assignment:id,employer_id,job_location', 'assignment.employer:id,name'])
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

        if (!$user->isMaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only maids can access this endpoint.',
            ], 403);
        }

        $schedules = SalarySchedule::where('maid_id', $user->id)
            ->with(['assignment:id,employer_id,job_location', 'assignment.employer:id,name'])
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
     * Get dashboard statistics.
     */
    public function dashboard(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isMaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only maids can access this endpoint.',
            ], 403);
        }

        $profile = $user->maidProfile()->first();

        // Assignment statistics
        $pendingAcceptance = MaidAssignment::forMaid($user->id)
            ->where('status', 'pending_acceptance')
            ->count();

        $activeAssignments = MaidAssignment::forMaid($user->id)
            ->active()
            ->count();

        $completedAssignments = MaidAssignment::forMaid($user->id)
            ->where('status', 'completed')
            ->count();

        // Wallet info
        $wallet = MaidWallet::firstOrCreate(
            ['maid_id' => $user->id],
            ['balance' => 0, 'currency' => 'NGN']
        );

        // Recent assignments
        $recentAssignments = MaidAssignment::forMaid($user->id)
            ->with(['employer:id,name,avatar'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Upcoming payments
        $upcomingPayments = SalarySchedule::where('maid_id', $user->id)
            ->where('payment_status', 'pending')
            ->where('next_salary_due_date', '>=', now())
            ->where('next_salary_due_date', '<=', now()->addDays(7))
            ->sum('monthly_salary');

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => [
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'availability_status' => $profile?->availability_status ?? 'available',
                    'rating' => $profile?->rating ?? 0,
                    'total_reviews' => $profile?->total_reviews ?? 0,
                    'is_verified' => $profile?->isVerified() ?? false,
                ],
                'statistics' => [
                    'pending_acceptance' => $pendingAcceptance,
                    'active_assignments' => $activeAssignments,
                    'completed_assignments' => $completedAssignments,
                    'wallet_balance' => $wallet->balance,
                    'upcoming_payments' => (float) $upcomingPayments,
                ],
                'recent_assignments' => $recentAssignments,
            ],
        ]);
    }

    /**
     * Get public maid profile (for employers to view).
     */
    public function publicProfile(int $id): JsonResponse
    {
        $maid = User::whereHas('roles', function ($query) {
            $query->where('name', 'maid');
        })->find($id);

        if (!$maid) {
            return response()->json([
                'success' => false,
                'message' => 'Maid not found.',
            ], 404);
        }

        $profile = $maid->maidProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Maid profile not found.',
            ], 404);
        }

        // Only show available maids
        if ($profile->availability_status !== 'available') {
            return response()->json([
                'success' => false,
                'message' => 'This maid is currently not available.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $maid->id,
                'name' => $maid->name,
                'avatar' => $maid->avatar,
                'location' => $maid->location,
                'bio' => $profile->bio,
                'skills' => $profile->skills,
                'experience_years' => $profile->experience_years,
                'help_types' => $profile->help_types,
                'schedule_preference' => $profile->schedule_preference,
                'expected_salary' => $profile->expected_salary,
                'state' => $profile->state,
                'lga' => $profile->lga,
                'rating' => $profile->rating,
                'total_reviews' => $profile->total_reviews,
                'maid_role' => $profile->getMaidRole(),
                'is_verified' => $profile->isVerified(),
            ],
        ]);
    }

    /**
     * List available maids (for employers).
     */
    public function listAvailable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'lga' => 'nullable|string|max:100',
            'help_types' => 'nullable|array',
            'help_types.*' => 'string|in:live-in,nanny,cooking,elderly-care,driver,cleaning,laundry,childcare',
            'min_experience' => 'nullable|integer|min:0',
            'max_salary' => 'nullable|integer|min:0',
            'schedule_preference' => 'nullable|string|in:live-in,part-time,full-time,weekends-only,flexible',
            'verified_only' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = MaidProfile::where('availability_status', 'available')
            ->with(['user:id,name,avatar,location']);

        // Apply filters
        if ($request->filled('location')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('location', 'like', '%' . $request->location . '%');
            });
        }

        if ($request->filled('state')) {
            $query->where('state', $request->state);
        }

        if ($request->filled('lga')) {
            $query->where('lga', $request->lga);
        }

        if ($request->filled('help_types')) {
            foreach ($request->help_types as $type) {
                $query->whereJsonContains('help_types', $type);
            }
        }

        if ($request->filled('min_experience')) {
            $query->where('experience_years', '>=', $request->min_experience);
        }

        if ($request->filled('max_salary')) {
            $query->where('expected_salary', '<=', $request->max_salary);
        }

        if ($request->filled('schedule_preference')) {
            $query->where('schedule_preference', $request->schedule_preference);
        }

        if ($request->boolean('verified_only')) {
            $query->where('nin_verified', true)
                ->where('background_verified', true);
        }

        $maids = $query->orderBy('rating', 'desc')
            ->orderBy('total_reviews', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $maids,
        ]);
    }
}
