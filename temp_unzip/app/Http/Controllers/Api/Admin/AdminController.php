<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BookingResource;
use App\Http\Resources\MaidProfileResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\UserResource;
use App\Models\Booking;
use App\Models\MaidProfile;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * API Admin Controller
 * 
 * Handles admin operations for API consumers.
 * Designed for Agentic AI integration with clear response formats.
 * 
 * @package App\Http\Controllers\Api\Admin
 * @version 1.0.0
 */
class AdminController extends ApiController
{
    /**
     * Get Dashboard Statistics
     * 
     * Retrieve comprehensive dashboard statistics.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        $this->requireRole('admin');

        $stats = [
            'users' => [
                'total' => User::count(),
                'employers' => User::role('employer')->count(),
                'maids' => User::role('maid')->count(),
                'admins' => User::role('admin')->count(),
                'active' => User::where('status', 'active')->count(),
                'inactive' => User::where('status', '!=', 'active')->count(),
                'new_this_month' => User::whereMonth('created_at', now()->month)->count(),
            ],
            'maids' => [
                'total' => MaidProfile::count(),
                'available' => MaidProfile::where('availability_status', 'available')->count(),
                'busy' => MaidProfile::where('availability_status', 'busy')->count(),
                'verified' => MaidProfile::where('nin_verified', true)->where('background_verified', true)->count(),
                'unverified' => MaidProfile::where('nin_verified', false)->orWhere('background_verified', false)->count(),
                'avg_rating' => round(MaidProfile::avg('rating') ?? 0, 2),
            ],
            'bookings' => [
                'total' => Booking::count(),
                'pending' => Booking::where('status', 'pending')->count(),
                'confirmed' => Booking::where('status', 'confirmed')->count(),
                'active' => Booking::where('status', 'active')->count(),
                'completed' => Booking::where('status', 'completed')->count(),
                'cancelled' => Booking::where('status', 'cancelled')->count(),
                'this_month' => Booking::whereMonth('created_at', now()->month)->count(),
            ],
            'payments' => [
                'total' => Payment::count(),
                'completed' => Payment::where('status', 'completed')->count(),
                'pending' => Payment::where('status', 'pending')->count(),
                'failed' => Payment::where('status', 'failed')->count(),
                'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
                'revenue_this_month' => Payment::where('status', 'completed')
                    ->whereMonth('created_at', now()->month)
                    ->sum('amount'),
            ],
            'reviews' => [
                'total' => Review::count(),
                'avg_rating' => round(Review::avg('rating') ?? 0, 2),
                'this_month' => Review::whereMonth('created_at', now()->month)->count(),
            ],
        ];

        return $this->success($stats, 'Dashboard statistics retrieved successfully');
    }

    /**
     * List Users
     * 
     * Retrieve all users with filtering.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam role string optional Filter by role. Example: "maid"
     * @queryParam status string optional Filter by status. Example: "active"
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function listUsers(Request $request): JsonResponse
    {
        $this->requireRole('admin');

        $query = User::query();

        if ($request->has('role')) {
            $query->role($request->role);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        $perPage = min($request->get('per_page', 15), 100);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginated(
            UserResource::collection($users),
            $users,
            'Users retrieved successfully'
        );
    }

    /**
     * Get User
     * 
     * Retrieve a specific user by ID.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function getUser(Request $request, int $id): JsonResponse
    {
        $this->requireRole('admin');

        $user = User::with(['maidProfile', 'employerPreferences', 'bookingsAsEmployer', 'bookingsAsMaid'])
            ->find($id);

        if (!$user) {
            return $this->notFound('User not found');
        }

        return $this->success(
            new UserResource($user),
            'User retrieved successfully'
        );
    }

    /**
     * Update User Status
     * 
     * Update a user's status.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * 
     * @bodyParam status string required New status. Example: "active"
     */
    public function updateUserStatus(Request $request, int $id): JsonResponse
    {
        $this->requireRole('admin');

        $user = User::find($id);

        if (!$user) {
            return $this->notFound('User not found');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:active,inactive,suspended',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $user->update(['status' => $request->status]);

        return $this->success(
            new UserResource($user->fresh()),
            'User status updated successfully'
        );
    }

    /**
     * List Maids
     * 
     * Retrieve all maids with filtering.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam verification_status string optional Filter by verification. Example: "verified"
     * @queryParam availability_status string optional Filter by availability. Example: "available"
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function listMaids(Request $request): JsonResponse
    {
        $this->requireRole('admin');

        $query = MaidProfile::query()->with(['user']);

        if ($request->has('verification_status')) {
            if ($request->verification_status === 'verified') {
                $query->where('nin_verified', true)->where('background_verified', true);
            } else {
                $query->where(function ($q) {
                    $q->where('nin_verified', false)->orWhere('background_verified', false);
                });
            }
        }

        if ($request->has('availability_status')) {
            $query->where('availability_status', $request->availability_status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $perPage = min($request->get('per_page', 15), 100);
        $maids = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginated(
            MaidProfileResource::collection($maids),
            $maids,
            'Maids retrieved successfully'
        );
    }

    /**
     * Verify Maid
     * 
     * Update maid verification status.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * 
     * @bodyParam nin_verified boolean optional NIN verification status. Example: true
     * @bodyParam background_verified boolean optional Background check status. Example: true
     */
    public function verifyMaid(Request $request, int $id): JsonResponse
    {
        $this->requireRole('admin');

        $maid = MaidProfile::find($id);

        if (!$maid) {
            return $this->notFound('Maid profile not found');
        }

        $validator = Validator::make($request->all(), [
            'nin_verified' => 'sometimes|boolean',
            'background_verified' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $maid->update($request->only(['nin_verified', 'background_verified']));

        return $this->success(
            new MaidProfileResource($maid->fresh()->load(['user'])),
            'Maid verification updated successfully'
        );
    }

    /**
     * List Bookings
     * 
     * Retrieve all bookings with filtering.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam status string optional Filter by status. Example: "active"
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function listBookings(Request $request): JsonResponse
    {
        $this->requireRole('admin');

        $query = Booking::query()->with(['employer', 'maid.maidProfile', 'preference']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $bookings = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginated(
            BookingResource::collection($bookings),
            $bookings,
            'Bookings retrieved successfully'
        );
    }

    /**
     * List Payments
     * 
     * Retrieve all payments with filtering.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam status string optional Filter by status. Example: "completed"
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function listPayments(Request $request): JsonResponse
    {
        $this->requireRole('admin');

        $query = Payment::query()->with(['booking.employer', 'booking.maid']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginated(
            PaymentResource::collection($payments),
            $payments,
            'Payments retrieved successfully'
        );
    }

    /**
     * List Reviews
     * 
     * Retrieve all reviews with filtering.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam min_rating integer optional Minimum rating. Example: 4
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function listReviews(Request $request): JsonResponse
    {
        $this->requireRole('admin');

        $query = Review::query()->with(['employer', 'maid', 'booking']);

        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $reviews = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginated(
            ReviewResource::collection($reviews),
            $reviews,
            'Reviews retrieved successfully'
        );
    }

    /**
     * Get Revenue Report
     * 
     * Retrieve revenue statistics.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam period string optional Period (daily, weekly, monthly). Example: "monthly"
     * @queryParam start_date date optional Start date. Example: "2024-01-01"
     * @queryParam end_date date optional End date. Example: "2024-12-31"
     */
    public function getRevenueReport(Request $request): JsonResponse
    {
        $this->requireRole('admin');

        $period = $request->get('period', 'monthly');
        $startDate = $request->get('start_date', now()->subYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $format = match ($period) {
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m',
        };

        $revenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$format}') as period"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $summary = [
            'total_revenue' => $revenue->sum('total'),
            'total_transactions' => $revenue->sum('count'),
            'average_transaction' => $revenue->avg('total') ?? 0,
            'periods' => $revenue,
        ];

        return $this->success($summary, 'Revenue report retrieved successfully');
    }

    /**
     * Get System Health
     * 
     * Retrieve system health status.
     * 
     * @return JsonResponse
     */
    public function getSystemHealth(): JsonResponse
    {
        $this->requireRole('admin');

        $health = [
            'database' => [
                'status' => 'healthy',
                'connections' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
            ],
            'users' => [
                'total' => User::count(),
                'active_last_24h' => User::where('updated_at', '>=', now()->subDay())->count(),
            ],
            'bookings' => [
                'pending_action' => Booking::where('status', 'pending')->count(),
                'active_now' => Booking::where('status', 'active')->count(),
            ],
            'payments' => [
                'pending' => Payment::where('status', 'pending')->count(),
                'failed_last_24h' => Payment::where('status', 'failed')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        return $this->success($health, 'System health retrieved successfully');
    }

    /**
     * Fetch AI Models from Provider API
     * 
     * Dynamically fetch available models from OpenAI or OpenRouter API.
     * 
     * @param Request $request
     * @param string $provider
     * @return JsonResponse
     */
    public function fetchAiModels(Request $request, string $provider): JsonResponse
    {
        // Properly check role and abort if unauthorized
        $roleCheck = $this->requireRole('admin');
        if ($roleCheck) {
            return $roleCheck;
        }

        $validProviders = ['openai', 'openrouter'];

        if (!in_array($provider, $validProviders)) {
            return $this->validationError(['provider' => 'Invalid provider. Must be openai or openrouter']);
        }

        // Clear cache if refresh is requested
        if ($request->boolean('refresh')) {
            \Illuminate\Support\Facades\Cache::forget("{$provider}_models_list");
        }

        $aiService = new \App\Services\Ai\AiService();
        $result = $aiService->fetchModels($provider);

        if (isset($result['error'])) {
            return $this->error($result['error'], 400);
        }

        $models = $result['models'] ?? [];

        // Get search query if provided
        $search = $request->get('search', '');

        // Filter models if search query provided
        if ($search) {
            $filtered = [];
            foreach ($models as $id => $name) {
                if (stripos($id, $search) !== false || stripos($name, $search) !== false) {
                    $filtered[$id] = $name;
                }
            }
            $models = $filtered;
        }

        $totalAvailable = count($models);

        // Only limit to 10 for initial load (no search). Return all results when searching.
        if (!$search && $totalAvailable > 10) {
            $displayModels = array_slice($models, 0, 10, true);
        } else {
            $displayModels = $models;
        }

        return $this->success([
            'provider' => $provider,
            'models' => $displayModels,
            'total_available' => $totalAvailable,
            'showing' => count($displayModels),
            'has_more' => $totalAvailable > count($displayModels),
        ], 'Models retrieved successfully');
    }
}
