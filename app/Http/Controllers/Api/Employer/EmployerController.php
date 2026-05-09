<?php

namespace App\Http\Controllers\Api\Employer;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Employer\{CreatePreferenceRequest, UpdatePreferenceRequest, CreateReviewRequest};
use App\Http\Resources\BookingResource;
use App\Http\Resources\EmployerPreferenceResource;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\EmployerPreference;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API Employer Controller
 * 
 * Handles employer-specific operations for API consumers.
 * Designed for Agentic AI integration with clear response formats.
 * 
 * @package App\Http\Controllers\Api\Employer
 * @version 1.0.0
 */
class EmployerController extends ApiController
{
    /**
     * Get My Preferences
     * 
     * Retrieve the authenticated employer's job preferences.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('employer')) {
            return $this->forbidden('Only employers can access this endpoint');
        }

        $preferences = $user->employerPreferences()->with(['bookings'])->get();

        return $this->success(
            EmployerPreferenceResource::collection($preferences),
            'Preferences retrieved successfully',
            ['count' => $preferences->count()]
        );
    }

    /**
     * Create Preference
     * 
     * Create a new job preference for the authenticated employer.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @bodyParam help_type string required Type of help needed. Example: "live-in"
     * @bodyParam location string required Location. Example: "Lekki, Lagos"
     * @bodyParam state string required State. Example: "Lagos State"
     * @bodyParam lga string optional LGA. Example: "Eti-Osa"
     * @bodyParam schedule_type string required Schedule type. Example: "full-time"
     * @bodyParam salary_budget integer required Salary budget. Example: 50000
     * @bodyParam required_skills array optional Required skills. Example: ["cooking", "cleaning"]
     * @bodyParam num_children integer optional Number of children. Example: 2
     * * @bodyParam children_ages array optional Ages of children. Example: [3, 5]
     * @bodyParam has_elderly boolean optional Has elderly to care for. Example: false
     * @bodyParam elderly_condition string optional Elderly condition details. Example: "mobility issues"
     * @bodyParam special_requirements string optional Special requirements. Example: "Must be able to drive"
     * @bodyParam start_date date optional Preferred start date. Example: "2024-02-01"
     * @bodyParam urgency string optional Urgency level. Example: "immediate"
     */
    public function createPreference(CreatePreferenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $preference = $user->employerPreferences()->create(array_merge($validated, [
            'required_skills' => $validated['required_skills'] ?? [],
            'children_ages' => $validated['children_ages'] ?? [],
            'has_elderly' => $validated['has_elderly'] ?? false,
            'urgency' => $validated['urgency'] ?? 'flexible',
            'status' => 'active',
        ]));

        return $this->success(
            new EmployerPreferenceResource($preference),
            'Preference created successfully',
            [],
            Response::HTTP_CREATED
        );
    }

    /**
     * Update Preference
     * 
     * Update an existing job preference.
     * 
     * @param UpdatePreferenceRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updatePreference(UpdatePreferenceRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
 
        $preference = $user->employerPreferences()->find($id);
 
        if (!$preference) {
            return $this->notFound('Preference not found');
        }
 
        $preference->update($validated);

        return $this->success(
            new EmployerPreferenceResource($preference->fresh()),
            'Preference updated successfully'
        );
    }

    /**
     * Delete Preference
     * 
     * Delete a job preference.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function deletePreference(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('employer')) {
            return $this->forbidden('Only employers can access this endpoint');
        }

        $preference = $user->employerPreferences()->find($id);

        if (!$preference) {
            return $this->notFound('Preference not found');
        }

        // Check if there are active bookings
        $activeBookings = $preference->bookings()->whereIn('status', ['pending', 'confirmed', 'active'])->count();
        if ($activeBookings > 0) {
            return $this->error(
                'Cannot delete preference with active bookings',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['bookings' => ['This preference has active bookings. Close them first.']],
                'ACTIVE_BOOKINGS_EXIST'
            );
        }

        $preference->delete();

        return $this->success(null, 'Preference deleted successfully');
    }

    /**
     * Get My Bookings
     * 
     * Retrieve all bookings for the authenticated employer.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam status string optional Filter by status. Example: "active"
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function getBookings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('employer')) {
            return $this->forbidden('Only employers can access this endpoint');
        }

        $query = $user->bookingsAsEmployer()
            ->with(['maid.maidProfile', 'preference', 'review', 'disputes']);

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
     * Get My Reviews
     * 
     * Retrieve reviews given by the authenticated employer.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function getReviews(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('employer')) {
            return $this->forbidden('Only employers can access this endpoint');
        }

        $perPage = min($request->get('per_page', 15), 100);

        $reviews = $user->reviewsGiven()
            ->with(['maid', 'booking'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->paginated(
            ReviewResource::collection($reviews),
            $reviews,
            'Reviews retrieved successfully'
        );
    }

    /**
     * Create Review
     * 
     * Create a review for a completed booking.
     * 
     * @param CreateReviewRequest $request
     * @return JsonResponse
     * 
     * @bodyParam booking_id integer required Booking ID. Example: 123
     * @bodyParam rating integer required Rating (1-5). Example: 5
     * @bodyParam comment string optional Review comment. Example: "Excellent service!"
     * @bodyParam categories array optional Category ratings. Example: {"punctuality": 5, "cleanliness": 4}
     */
    public function createReview(CreateReviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Verify the booking belongs to this employer and is completed
        $booking = Booking::where('id', $validated['booking_id'])
            ->where('employer_id', $user->id)
            ->where('status', 'completed')
            ->first();

        if (!$booking) {
            return $this->error(
                'Invalid booking or booking not completed',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                null,
                'INVALID_BOOKING'
            );
        }

        // Check if review already exists
        if ($booking->review) {
            return $this->error(
                'Review already exists for this booking',
                Response::HTTP_CONFLICT,
                null,
                'REVIEW_EXISTS'
            );
        }

        $review = Review::create([
            'booking_id' => $booking->id,
            'employer_id' => $user->id,
            'maid_id' => $booking->maid_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'categories' => $validated['categories'] ?? [],
        ]);

        // Update maid's rating
        $this->updateMaidRating($booking->maid_id);

        return $this->success(
            new ReviewResource($review->load(['maid', 'booking'])),
            'Review created successfully',
            [],
            Response::HTTP_CREATED
        );
    }

    /**
     * Get Dashboard Stats
     * 
     * Retrieve dashboard statistics for the employer.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('employer')) {
            return $this->forbidden('Only employers can access this endpoint');
        }

        $stats = [
            'active_bookings' => $user->bookingsAsEmployer()->whereIn('status', ['pending', 'confirmed', 'active'])->count(),
            'completed_bookings' => $user->bookingsAsEmployer()->where('status', 'completed')->count(),
            'cancelled_bookings' => $user->bookingsAsEmployer()->where('status', 'cancelled')->count(),
            'total_reviews_given' => $user->reviewsGiven()->count(),
            'active_preferences' => $user->employerPreferences()->where('status', 'active')->count(),
            'recent_bookings' => BookingResource::collection(
                $user->bookingsAsEmployer()
                    ->with(['maid.maidProfile'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
            ),
        ];

        return $this->success($stats, 'Dashboard stats retrieved successfully');
    }

    /**
     * Update Maid Rating
     * 
     * Helper method to update maid's average rating.
     * 
     * @param int $maidId
     * @return void
     */
    private function updateMaidRating(int $maidId): void
    {
        $maid = User::find($maidId);
        if ($maid && $maid->maidProfile) {
            $avgRating = $maid->reviewsReceived()->avg('rating') ?? 0;
            $totalReviews = $maid->reviewsReceived()->count();

            $maid->maidProfile->update([
                'rating' => round($avgRating, 2),
                'total_reviews' => $totalReviews,
            ]);
        }
    }
}
