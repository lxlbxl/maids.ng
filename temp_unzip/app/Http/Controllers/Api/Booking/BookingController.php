<?php

namespace App\Http\Controllers\Api\Booking;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\EmployerPreference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * API Booking Controller
 * 
 * Handles booking lifecycle operations for API consumers.
 * Designed for Agentic AI integration with clear response formats.
 * 
 * @package App\Http\Controllers\Api\Booking
 * @version 1.0.0
 */
class BookingController extends ApiController
{
    /**
     * List Bookings
     * 
     * Retrieve bookings with filtering options.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam status string optional Filter by status. Example: "active"
     * @queryParam payment_status string optional Filter by payment status. Example: "paid"
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Booking::query()
            ->with(['employer', 'maid.maidProfile', 'preference', 'review', 'disputes']);

        // Filter by user role
        if ($user->hasRole('employer')) {
            $query->where('employer_id', $user->id);
        } elseif ($user->hasRole('maid')) {
            $query->where('maid_id', $user->id);
        }
        // Admins see all bookings

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
     * Get Booking
     * 
     * Retrieve a specific booking by ID.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $booking = Booking::with(['employer', 'maid.maidProfile', 'preference', 'review', 'disputes'])
            ->find($id);

        if (!$booking) {
            return $this->notFound('Booking not found');
        }

        // Check authorization
        if (
            !$user->hasRole('admin') &&
            $booking->employer_id !== $user->id &&
            $booking->maid_id !== $user->id
        ) {
            return $this->forbidden('You do not have permission to view this booking');
        }

        return $this->success(
            new BookingResource($booking),
            'Booking retrieved successfully'
        );
    }

    /**
     * Create Booking
     * 
     * Create a new booking (employer only).
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @bodyParam preference_id integer required Preference ID. Example: 1
     * @bodyParam maid_id integer required Maid ID. Example: 5
     * @bodyParam start_date date required Start date. Example: "2024-02-01"
     * @bodyParam end_date date optional End date. Example: "2024-12-31"
     * @bodyParam schedule_type string required Schedule type. Example: "full-time"
     * @bodyParam agreed_salary integer required Agreed salary. Example: 50000
     * @bodyParam notes string optional Additional notes. Example: "Start at 8 AM daily"
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('employer')) {
            return $this->forbidden('Only employers can create bookings');
        }

        $validator = Validator::make($request->all(), [
            'preference_id' => 'required|integer|exists:employer_preferences,id',
            'maid_id' => 'required|integer|exists:users,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'schedule_type' => 'required|string|in:full-time,part-time,weekends,flexible',
            'agreed_salary' => 'required|integer|min:10000|max:500000',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        // Verify preference belongs to employer
        $preference = EmployerPreference::where('id', $request->preference_id)
            ->where('employer_id', $user->id)
            ->first();

        if (!$preference) {
            return $this->forbidden('Preference not found or does not belong to you');
        }

        // Verify maid exists and is available
        $maid = User::where('id', $request->maid_id)
            ->whereHas('maidProfile', function ($q) {
                $q->where('availability_status', 'available');
            })
            ->first();

        if (!$maid) {
            return $this->error(
                'Maid not found or not available',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['maid_id' => ['The selected maid is not available.']],
                'MAID_UNAVAILABLE'
            );
        }

        // Check if maid already has conflicting bookings
        $conflictingBooking = Booking::where('maid_id', $request->maid_id)
            ->whereIn('status', ['pending', 'confirmed', 'active'])
            ->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date ?? $request->start_date])
                    ->orWhereBetween('end_date', [$request->start_date, $request->end_date ?? $request->start_date]);
            })
            ->first();

        if ($conflictingBooking) {
            return $this->error(
                'Maid has conflicting bookings',
                Response::HTTP_CONFLICT,
                ['maid_id' => ['The selected maid has conflicting bookings for the requested dates.']],
                'BOOKING_CONFLICT'
            );
        }

        $booking = Booking::create([
            'employer_id' => $user->id,
            'maid_id' => $request->maid_id,
            'preference_id' => $request->preference_id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'schedule_type' => $request->schedule_type,
            'agreed_salary' => $request->agreed_salary,
            'notes' => $request->notes,
        ]);

        // Update maid availability
        $maid->maidProfile->update(['availability_status' => 'busy']);

        return $this->success(
            new BookingResource($booking->load(['employer', 'maid.maidProfile', 'preference'])),
            'Booking created successfully',
            [],
            Response::HTTP_CREATED
        );
    }

    /**
     * Confirm Booking
     * 
     * Confirm a pending booking (maid only).
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('maid')) {
            return $this->forbidden('Only maids can confirm bookings');
        }

        $booking = Booking::find($id);

        if (!$booking) {
            return $this->notFound('Booking not found');
        }

        if ($booking->maid_id !== $user->id) {
            return $this->forbidden('You can only confirm your own bookings');
        }

        if ($booking->status !== 'pending') {
            return $this->error(
                'Booking cannot be confirmed',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['status' => ['Only pending bookings can be confirmed.']],
                'INVALID_STATUS'
            );
        }

        $booking->update(['status' => 'confirmed']);

        return $this->success(
            new BookingResource($booking->fresh()->load(['employer', 'maid.maidProfile', 'preference'])),
            'Booking confirmed successfully'
        );
    }

    /**
     * Start Booking
     * 
     * Mark a booking as active (maid or employer).
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $booking = Booking::find($id);

        if (!$booking) {
            return $this->notFound('Booking not found');
        }

        // Check authorization
        if (
            !$user->hasRole('admin') &&
            $booking->employer_id !== $user->id &&
            $booking->maid_id !== $user->id
        ) {
            return $this->forbidden('You do not have permission to start this booking');
        }

        if ($booking->status !== 'confirmed') {
            return $this->error(
                'Booking cannot be started',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['status' => ['Only confirmed bookings can be started.']],
                'INVALID_STATUS'
            );
        }

        $booking->update(['status' => 'active']);

        return $this->success(
            new BookingResource($booking->fresh()->load(['employer', 'maid.maidProfile', 'preference'])),
            'Booking started successfully'
        );
    }

    /**
     * Complete Booking
     * 
     * Mark a booking as completed (maid or employer).
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $booking = Booking::find($id);

        if (!$booking) {
            return $this->notFound('Booking not found');
        }

        // Check authorization
        if (
            !$user->hasRole('admin') &&
            $booking->employer_id !== $user->id &&
            $booking->maid_id !== $user->id
        ) {
            return $this->forbidden('You do not have permission to complete this booking');
        }

        if ($booking->status !== 'active') {
            return $this->error(
                'Booking cannot be completed',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['status' => ['Only active bookings can be completed.']],
                'INVALID_STATUS'
            );
        }

        $booking->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Update maid availability back to available
        $maid = User::find($booking->maid_id);
        if ($maid && $maid->maidProfile) {
            $maid->maidProfile->update(['availability_status' => 'available']);
        }

        return $this->success(
            new BookingResource($booking->fresh()->load(['employer', 'maid.maidProfile', 'preference'])),
            'Booking completed successfully'
        );
    }

    /**
     * Cancel Booking
     * 
     * Cancel a booking (maid or employer).
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * 
     * @bodyParam reason string optional Cancellation reason. Example: "Change of plans"
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $booking = Booking::find($id);

        if (!$booking) {
            return $this->notFound('Booking not found');
        }

        // Check authorization
        if (
            !$user->hasRole('admin') &&
            $booking->employer_id !== $user->id &&
            $booking->maid_id !== $user->id
        ) {
            return $this->forbidden('You do not have permission to cancel this booking');
        }

        if (!in_array($booking->status, ['pending', 'confirmed', 'active'])) {
            return $this->error(
                'Booking cannot be cancelled',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['status' => ['Only pending, confirmed, or active bookings can be cancelled.']],
                'INVALID_STATUS'
            );
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->reason,
        ]);

        // Update maid availability back to available
        $maid = User::find($booking->maid_id);
        if ($maid && $maid->maidProfile) {
            $maid->maidProfile->update(['availability_status' => 'available']);
        }

        return $this->success(
            new BookingResource($booking->fresh()->load(['employer', 'maid.maidProfile', 'preference'])),
            'Booking cancelled successfully'
        );
    }

    /**
     * Get My Bookings (Maid)
     * 
     * Retrieve all bookings for the authenticated maid.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam status string optional Filter by status. Example: "active"
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function getMaidBookings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('maid')) {
            return $this->forbidden('Only maids can access this endpoint');
        }

        $query = $user->bookingsAsMaid()
            ->with(['employer', 'preference', 'review', 'disputes']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
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
     * Get Booking Statistics
     * 
     * Retrieve booking statistics.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Booking::query();

        // Filter by user role
        if ($user->hasRole('employer')) {
            $query->where('employer_id', $user->id);
        } elseif ($user->hasRole('maid')) {
            $query->where('maid_id', $user->id);
        }

        $stats = [
            'total' => $query->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'confirmed' => (clone $query)->where('status', 'confirmed')->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'payment_pending' => (clone $query)->where('payment_status', 'pending')->count(),
            'payment_paid' => (clone $query)->where('payment_status', 'paid')->count(),
            'payment_failed' => (clone $query)->where('payment_status', 'failed')->count(),
        ];

        return $this->success($stats, 'Booking statistics retrieved successfully');
    }
}
