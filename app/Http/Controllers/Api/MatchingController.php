<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Models\EmployerPreference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Agents\ScoutAgent;

class MatchingController extends ApiController
{
    /**
     * Find matching maids based on employer preferences.
     * Implements intelligent scoring with location, help type, schedule, and budget alignment.
     * 
     * @param Request $request
     * @param ScoutAgent $scoutAgent
     * @return JsonResponse
     */
    public function findMatches(Request $request, ScoutAgent $scoutAgent): JsonResponse
    {
        $validated = $request->validate([
            'help_types' => 'required|array',
            'schedule' => 'required|string',
            'urgency' => 'required|string',
            'location' => 'required|string',
            'budget_min' => 'nullable|integer',
            'budget_max' => 'nullable|integer',
            'contact_name' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'contact_email' => 'nullable|string|email',
        ]);

        // Save preferences
        $preference = EmployerPreference::create([
            'employer_id' => Auth::guard('sanctum')->id() ?? 0,
            'help_types' => $validated['help_types'],
            'schedule' => $validated['schedule'],
            'urgency' => $validated['urgency'],
            'location' => $validated['location'],
            'budget_min' => $validated['budget_min'] ?? null,
            'budget_max' => $validated['budget_max'] ?? null,
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'matching_status' => 'pending',
            'state' => explode(',', $validated['location'])[1] ?? '',
            'city' => explode(',', $validated['location'])[0] ?? $validated['location'],
        ]);

        // Get all available maids with profiles
        $maids = User::role('maid')
            ->where('status', 'active')
            ->with('maidProfile')
            ->whereHas('maidProfile', fn($q) => $q->where('availability_status', 'available'))
            ->get();

        // Score each maid
        $scored = $maids->map(function ($maid) use ($preference, $scoutAgent) {
            $profile = $maid->maidProfile;
            if (!$profile) return null;

            $scoring = $scoutAgent->scoreMatch($profile, $preference);

            return [
                'id' => $maid->id,
                'name' => $maid->name,
                'role' => $profile->getMaidRole(),
                'location' => $profile->location ?? $maid->location,
                'rating' => round($profile->rating, 1),
                'total_reviews' => $profile->total_reviews,
                'rate' => $profile->expected_salary,
                'skills' => $profile->skills ?? [],
                'experience_years' => $profile->experience_years,
                'verified' => $profile->nin_verified && $profile->background_verified,
                'match' => $scoring['score'],
                'confidence' => $scoring['confidence'],
                'bio' => $profile->bio,
                'avatar' => $maid->avatar,
            ];
        })
        ->filter(fn($m) => $m !== null && $m['match'] >= 40)
        ->sortByDesc('match')
        ->take(10)
        ->values();

        return $this->success([
            'matches' => $scored,
            'preference_id' => $preference->id,
        ], 'Matches found successfully');
    }
}
