<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Maid\{UpdateAvailabilityRequest, UpdateProfileRequest, ListAssignmentsRequest, SearchMaidsRequest};
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

class MaidController extends ApiController
{
    /**
     * Get authenticated maid's profile.
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isMaid()) {
            return $this->forbidden('Unauthorized. Only maids can access this endpoint.');
        }

        $profile = $user->maidProfile()->with('user')->first();

        if (!$profile) {
            return $this->notFound('Maid profile');
        }

        return $this->success([
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
        ], 'Profile retrieved successfully');
    }

    /**
     * Update maid profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $profile = $user->maidProfile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'availability_status' => 'available',
                'rating' => 0,
                'total_reviews' => 0,
            ]
        );

        $profile->update($validated);

        return $this->success($profile->fresh(), 'Profile updated successfully');
    }

    /**
     * Update availability status.
     */
    public function updateAvailability(UpdateAvailabilityRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $profile = $user->maidProfile()->first();

        if (!$profile) {
            return $this->notFound('Maid profile');
        }

        // Check if maid has active assignments before making available
        if ($validated['availability_status'] === 'available') {
            $activeAssignments = MaidAssignment::forMaid($user->id)
                ->active()
                ->count();

            if ($activeAssignments > 0) {
                return $this->error('Cannot set status to available while having active assignments.', 422);
            }
        }

        $profile->update(['availability_status' => $validated['availability_status']]);

        return $this->success([
            'availability_status' => $profile->availability_status,
        ], 'Availability status updated successfully');
    }

    /**
     * Get maid's assignments.
     */
    public function assignments(ListAssignmentsRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();
 
        $query = MaidAssignment::forMaid($user->id)
            ->with(['employer:id,name,email,phone', 'preference']);
 
        $status = $validated['status'] ?? 'all';
        if ($status !== 'all') {
            $query->where('status', $status);
        }
 
        $assignments = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 15);
 
        return $this->paginated($assignments, 'Assignments retrieved successfully');
    }

    /**
     * Get specific assignment details.
     */
    public function assignmentDetail(int $id): JsonResponse
    {
        $user = Auth::user();
 
        $assignment = MaidAssignment::forMaid($user->id)
            ->with([
                'employer:id,name,email,phone,avatar',
                'preference',
                'salarySchedule',
                'salaryPayments',
            ])
            ->find($id);
 
        if (!$assignment) {
            return $this->notFound('Assignment');
        }
 
        return $this->success($assignment, 'Assignment details retrieved successfully');
    }

    /**
     * Get maid's earnings summary.
     */
    public function earnings(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isMaid()) {
            return $this->forbidden('Unauthorized. Only maids can access this endpoint.');
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

        return $this->success([
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
        ], 'Earnings summary retrieved successfully');
    }

    /**
     * Get payment history.
     */
    public function paymentHistory(Request $request): JsonResponse
    {
        $user = Auth::user();
 
        $payments = SalaryPayment::where('maid_id', $user->id)
            ->with(['assignment:id,employer_id,job_location', 'assignment.employer:id,name'])
            ->orderBy('paid_at', 'desc')
            ->paginate($request->input('per_page', 15));
 
        return $this->paginated($payments, 'Payment history retrieved successfully');
    }

    /**
     * Get upcoming salary schedules.
     */
    public function upcomingPayments(Request $request): JsonResponse
    {
        $user = Auth::user();
 
        $schedules = SalarySchedule::where('maid_id', $user->id)
            ->with(['assignment:id,employer_id,job_location', 'assignment.employer:id,name'])
            ->where('payment_status', 'pending')
            ->where('next_salary_due_date', '>=', now())
            ->orderBy('next_salary_due_date', 'asc')
            ->get();
 
        return $this->success($schedules, 'Upcoming payments retrieved successfully');
    }

    /**
     * Get dashboard statistics.
     */
    public function dashboard(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isMaid()) {
            return $this->forbidden('Unauthorized. Only maids can access this endpoint.');
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

        return $this->success([
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
        ], 'Dashboard data retrieved successfully');
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
            return $this->notFound('Maid');
        }
 
        $profile = $maid->maidProfile;
 
        if (!$profile) {
            return $this->notFound('Maid profile');
        }

        // Only show available maids
        if ($profile->availability_status !== 'available') {
            return $this->error('This maid is currently not available.', 404, null, 'MAID_UNAVAILABLE');
        }
 
        return $this->success([
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
        ], 'Public profile retrieved successfully');
    }

    /**
     * List available maids (for employers).
     */
    public function listAvailable(SearchMaidsRequest $request): JsonResponse
    {
        $validated = $request->validated();
 
        $query = MaidProfile::where('availability_status', 'available')
            ->with(['user:id,name,avatar,location']);
 
        // Apply filters
        if (!empty($validated['location'])) {
            $query->whereHas('user', function ($q) use ($validated) {
                $q->where('location', 'like', '%' . $validated['location'] . '%');
            });
        }
 
        if (!empty($validated['state'])) {
            $query->where('state', $validated['state']);
        }
 
        if (!empty($validated['lga'])) {
            $query->where('lga', $validated['lga']);
        }
 
        if (!empty($validated['help_types'])) {
            foreach ($validated['help_types'] as $type) {
                $query->whereJsonContains('help_types', $type);
            }
        }
 
        if (!empty($validated['min_experience'])) {
            $query->where('experience_years', '>=', $validated['min_experience']);
        }
 
        if (!empty($validated['max_salary'])) {
            $query->where('expected_salary', '<=', $validated['max_salary']);
        }
 
        if (!empty($validated['schedule_preference'])) {
            $query->where('schedule_preference', $validated['schedule_preference']);
        }
 
        if ($request->boolean('verified_only')) {
            $query->where('nin_verified', true)
                ->where('background_verified', true);
        }
 
        $maids = $query->orderBy('rating', 'desc')
            ->orderBy('total_reviews', 'desc')
            ->paginate($validated['per_page'] ?? 15);
 
        return $this->paginated($maids, 'Available maids retrieved successfully');
    }
}
