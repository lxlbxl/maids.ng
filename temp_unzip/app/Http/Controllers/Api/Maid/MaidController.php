<?php

namespace App\Http\Controllers\Api\Maid;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\MaidProfileResource;
use App\Models\MaidProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * API Maid Controller
 * 
 * Handles maid profile operations for API consumers.
 * Designed for Agentic AI integration with clear response formats.
 * 
 * @package App\Http\Controllers\Api\Maid
 * @version 1.0.0
 */
class MaidController extends ApiController
{
    /**
     * List Maids
     * 
     * Retrieve a paginated list of available maids with filtering options.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam location string optional Filter by location. Example: Lagos
     * @queryParam state string optional Filter by state. Example: Lagos State
     * @queryParam skills array optional Filter by skills. Example: ["cooking", "cleaning"]
     * @queryParam help_types array optional Filter by help types. Example: ["live-in", "nanny"]
     * @queryParam availability_status string optional Filter by availability. Example: available
     * @queryParam min_rating float optional Minimum rating. Example: 4.0
     * @queryParam verified_only boolean optional Only verified maids. Example: true
     * @queryParam per_page integer optional Items per page (default: 15). Example: 20
     * @queryParam page integer optional Page number. Example: 1
     */
    public function index(Request $request): JsonResponse
    {
        $query = MaidProfile::query()
            ->with(['user'])
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            });

        // Location filters
        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        if ($request->has('state')) {
            $query->where('state', $request->state);
        }

        if ($request->has('lga')) {
            $query->where('lga', $request->lga);
        }

        // Skills filter (JSON contains)
        if ($request->has('skills')) {
            $skills = is_array($request->skills) ? $request->skills : [$request->skills];
            foreach ($skills as $skill) {
                $query->whereJsonContains('skills', $skill);
            }
        }

        // Help types filter (JSON contains)
        if ($request->has('help_types')) {
            $helpTypes = is_array($request->help_types) ? $request->help_types : [$request->help_types];
            foreach ($helpTypes as $type) {
                $query->whereJsonContains('help_types', $type);
            }
        }

        // Availability status
        if ($request->has('availability_status')) {
            $query->where('availability_status', $request->availability_status);
        } else {
            // Default to available maids only
            $query->where('availability_status', 'available');
        }

        // Minimum rating
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        // Verified only
        if ($request->boolean('verified_only')) {
            $query->where('nin_verified', true)
                ->where('background_verified', true);
        }

        // Salary range
        if ($request->has('min_salary')) {
            $query->where('expected_salary', '>=', $request->min_salary);
        }

        if ($request->has('max_salary')) {
            $query->where('expected_salary', '<=', $request->max_salary);
        }

        // Experience years
        if ($request->has('min_experience')) {
            $query->where('experience_years', '>=', $request->min_experience);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSortFields = ['rating', 'experience_years', 'expected_salary', 'created_at', 'total_reviews'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100); // Max 100 per page

        $maids = $query->paginate($perPage);

        return $this->paginated(
            MaidProfileResource::collection($maids),
            $maids,
            'Maids retrieved successfully',
            [
                'filters_applied' => $request->only([
                    'location',
                    'state',
                    'lga',
                    'skills',
                    'help_types',
                    'availability_status',
                    'min_rating',
                    'verified_only',
                    'min_salary',
                    'max_salary',
                    'min_experience',
                    'sort_by'
                ])
            ]
        );
    }

    /**
     * Get Maid Profile
     * 
     * Retrieve detailed information about a specific maid.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $maid = MaidProfile::with(['user', 'reviews'])
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
            ->find($id);

        if (!$maid) {
            return $this->notFound('Maid profile not found');
        }

        return $this->success(
            new MaidProfileResource($maid),
            'Maid profile retrieved successfully'
        );
    }

    /**
     * Search Maids
     * 
     * Search maids by name, skills, or location.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam q string required Search query. Example: "cooking Lagos"
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $searchTerm = $request->q;

        $query = MaidProfile::query()
            ->with(['user'])
            ->whereHas('user', function ($q) use ($searchTerm) {
                $q->where('status', 'active')
                    ->where(function ($subQ) use ($searchTerm) {
                        $subQ->where('name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('location', 'like', '%' . $searchTerm . '%');
                    });
            })
            ->orWhere(function ($q) use ($searchTerm) {
                $q->where('bio', 'like', '%' . $searchTerm . '%')
                    ->orWhere('location', 'like', '%' . $searchTerm . '%')
                    ->orWhere('state', 'like', '%' . $searchTerm . '%')
                    ->orWhere('skills', 'like', '%' . $searchTerm . '%')
                    ->orWhere('help_types', 'like', '%' . $searchTerm . '%');
            });

        $perPage = min($request->get('per_page', 15), 100);
        $maids = $query->paginate($perPage);

        return $this->paginated(
            MaidProfileResource::collection($maids),
            $maids,
            'Search results retrieved successfully',
            ['search_query' => $searchTerm]
        );
    }

    /**
     * Get My Profile (Maid)
     * 
     * Retrieve the authenticated maid's own profile.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function myProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('maid')) {
            return $this->forbidden('Only maids can access this endpoint');
        }

        $profile = $user->maidProfile()->with(['user', 'reviews'])->first();

        if (!$profile) {
            return $this->notFound('Maid profile not found');
        }

        return $this->success(
            new MaidProfileResource($profile),
            'Profile retrieved successfully'
        );
    }

    /**
     * Update My Profile (Maid)
     * 
     * Update the authenticated maid's profile.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @bodyParam bio string optional Bio/Description. Example: "Experienced housekeeper..."
     * @bodyParam skills array optional List of skills. Example: ["cooking", "cleaning", "laundry"]
     * @bodyParam experience_years integer optional Years of experience. Example: 5
     * @bodyParam help_types array optional Types of help offered. Example: ["live-in", "cooking"]
     * @bodyParam schedule_preference string optional Schedule preference. Example: "full-time"
     * @bodyParam expected_salary integer optional Expected salary. Example: 50000
     * @bodyParam location string optional Location. Example: "Lekki, Lagos"
     * @bodyParam state string optional State. Example: "Lagos State"
     * @bodyParam lga string optional LGA. Example: "Eti-Osa"
     * @bodyParam availability_status string optional Availability status. Example: "available"
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('maid')) {
            return $this->forbidden('Only maids can access this endpoint');
        }

        $profile = $user->maidProfile;

        if (!$profile) {
            return $this->notFound('Maid profile not found');
        }

        $validator = Validator::make($request->all(), [
            'bio' => 'nullable|string|max:1000',
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:50',
            'experience_years' => 'nullable|integer|min:0|max:50',
            'help_types' => 'nullable|array',
            'help_types.*' => 'string|in:live-in,nanny,cooking,elderly-care,driver,cleaning,laundry',
            'schedule_preference' => 'nullable|string|in:full-time,part-time,weekends,flexible',
            'expected_salary' => 'nullable|integer|min:10000|max:500000',
            'location' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'lga' => 'nullable|string|max:100',
            'availability_status' => 'nullable|string|in:available,busy,unavailable',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $profile->update($request->only([
            'bio',
            'skills',
            'experience_years',
            'help_types',
            'schedule_preference',
            'expected_salary',
            'location',
            'state',
            'lga',
            'availability_status'
        ]));

        return $this->success(
            new MaidProfileResource($profile->fresh()->load(['user', 'reviews'])),
            'Profile updated successfully'
        );
    }

    /**
     * Update Bank Details (Maid)
     * 
     * Update the authenticated maid's bank account details.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @bodyParam bank_name string required Bank name. Example: "First Bank of Nigeria"
     * @bodyParam account_number string required Account number (10 digits). Example: "1234567890"
     * @bodyParam account_name string required Account holder name. Example: "Jane Doe"
     */
    public function updateBankDetails(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('maid')) {
            return $this->forbidden('Only maids can access this endpoint');
        }

        $profile = $user->maidProfile;

        if (!$profile) {
            return $this->notFound('Maid profile not found');
        }

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|size:10|regex:/^[0-9]+$/',
            'account_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $profile->update($request->only(['bank_name', 'account_number', 'account_name']));

        return $this->success(
            [
                'bank_name' => $profile->bank_name,
                'account_number' => '****' . substr($profile->account_number, -4),
                'account_name' => $profile->account_name,
            ],
            'Bank details updated successfully'
        );
    }

    /**
     * Get Available Skills List
     * 
     * Retrieve the list of available skills for maids.
     * 
     * @return JsonResponse
     */
    public function getSkills(): JsonResponse
    {
        $skills = [
            'cooking' => 'Cooking & Meal Preparation',
            'cleaning' => 'House Cleaning',
            'laundry' => 'Laundry & Ironing',
            'childcare' => 'Childcare & Babysitting',
            'elderly-care' => 'Elderly Care',
            'pet-care' => 'Pet Care',
            'gardening' => 'Gardening',
            'driving' => 'Driving',
            'grocery-shopping' => 'Grocery Shopping',
            'meal-planning' => 'Meal Planning',
            'organizing' => 'Home Organization',
            'deep-cleaning' => 'Deep Cleaning',
        ];

        return $this->success(
            ['skills' => $skills],
            'Available skills retrieved successfully'
        );
    }

    /**
     * Get Help Types List
     * 
     * Retrieve the list of available help types.
     * 
     * @return JsonResponse
     */
    public function getHelpTypes(): JsonResponse
    {
        $helpTypes = [
            'live-in' => [
                'label' => 'Live-in Helper',
                'description' => 'Full-time helper living with the family',
            ],
            'nanny' => [
                'label' => 'Nanny',
                'description' => 'Childcare specialist',
            ],
            'cooking' => [
                'label' => 'Cook',
                'description' => 'Meal preparation specialist',
            ],
            'elderly-care' => [
                'label' => 'Elderly Caregiver',
                'description' => 'Care for elderly family members',
            ],
            'driver' => [
                'label' => 'Driver',
                'description' => 'Personal or family driver',
            ],
            'cleaning' => [
                'label' => 'Cleaner',
                'description' => 'House cleaning services',
            ],
            'laundry' => [
                'label' => 'Laundry Service',
                'description' => 'Laundry and ironing services',
            ],
        ];

        return $this->success(
            ['help_types' => $helpTypes],
            'Help types retrieved successfully'
        );
    }

    /**
     * Get Top Rated Maids
     * 
     * Retrieve the top-rated maids.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam limit integer optional Number of results (default: 10, max: 50). Example: 10
     */
    public function getTopRated(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);

        $maids = MaidProfile::with(['user'])
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
            ->where('availability_status', 'available')
            ->where('total_reviews', '>=', 3) // At least 3 reviews
            ->orderBy('rating', 'desc')
            ->orderBy('total_reviews', 'desc')
            ->limit($limit)
            ->get();

        return $this->success(
            MaidProfileResource::collection($maids),
            'Top rated maids retrieved successfully',
            ['count' => $maids->count()]
        );
    }

    /**
     * Get Verified Maids
     * 
     * Retrieve maids who are fully verified (NIN + Background check).
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function getVerified(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 100);

        $maids = MaidProfile::with(['user'])
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
            ->where('nin_verified', true)
            ->where('background_verified', true)
            ->where('availability_status', 'available')
            ->orderBy('rating', 'desc')
            ->paginate($perPage);

        return $this->paginated(
            MaidProfileResource::collection($maids),
            $maids,
            'Verified maids retrieved successfully',
            ['verification_required' => ['nin_verified', 'background_verified']]
        );
    }
}
