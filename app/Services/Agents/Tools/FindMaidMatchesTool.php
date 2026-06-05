<?php

namespace App\Services\Agents\Tools;

use App\Models\AgentChannelIdentity;
use App\Models\MaidProfile;
use App\Models\User;
use App\Services\Agents\DTOs\InboundMessage;
use Illuminate\Support\Facades\DB;

/**
 * Tool: find_maid_matches
 * Searches for available maids based on employer preferences.
 * Returns top matches with scores and profile summaries.
 */
class FindMaidMatchesTool
{
    public function __invoke(array $args, AgentChannelIdentity $identity, InboundMessage $message): string
    {
        $helpType = $args['help_type'] ?? null;
        $location = $args['location'] ?? null;
        $budgetMin = $args['budget_min'] ?? 30000;
        $budgetMax = $args['budget_max'] ?? 80000;
        $schedule = $args['schedule'] ?? null;
        $limit = min($args['limit'] ?? 5, 10);

        $query = MaidProfile::whereHas('user', function ($q) {
            $q->where('role', 'maid')
                ->where('status', 'active');
        })
            ->where('is_verified', true)
            ->where('is_available', true);

        // Filter by help type (skills overlap)
        if ($helpType) {
            $query->where(function ($q) use ($helpType) {
                $q->whereJsonContains('skills', $helpType)
                    ->orWhere('help_type', 'like', "%{$helpType}%");
            });
        }

        // Filter by location
        if ($location) {
            $query->where('location', 'like', "%{$location}%");
        }

        // Filter by budget range
        $query->whereBetween('expected_monthly_rate', [$budgetMin, $budgetMax]);

        $matches = $query->with(['user', 'ninVerification'])
            ->orderByDesc('rating')
            ->limit($limit)
            ->get();

        if ($matches->isEmpty()) {
            return json_encode([
                'matches' => [],
                'message' => 'No available maids match these criteria right now. I can notify you when someone becomes available.',
                'suggestion' => 'Would you like to broaden your search or set up a notification?',
            ]);
        }

        $results = $matches->map(function ($profile) {
            return [
                'id' => $profile->id,
                'name' => $profile->user?->name,
                'location' => $profile->location,
                'skills' => $profile->skills ?? [],
                'experience_years' => $profile->experience_years,
                'monthly_rate' => $profile->expected_monthly_rate,
                'rating' => $profile->rating,
                'review_count' => $profile->review_count ?? 0,
                'verified' => $profile->ninVerification?->status === 'approved',
                'bio' => $profile->bio,
                'availability' => $profile->availability,
            ];
        })->toArray();

        return json_encode([
            'matches' => $results,
            'count' => count($results),
            'message' => "I found {$results['count']} maids that match your criteria.",
        ]);
    }

    public function description(): string
    {
        return 'Find available maids matching employer preferences. Returns top matches with profiles, ratings, and rates.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'help_type' => [
                    'type' => 'string',
                    'description' => 'Type of help needed: housekeeper, cook, nanny, elderly_care, live_in, driver',
                ],
                'location' => [
                    'type' => 'string',
                    'description' => 'Location or area (e.g., Lekki, Ikeja, Victoria Island)',
                ],
                'budget_min' => [
                    'type' => 'integer',
                    'description' => 'Minimum monthly budget in Naira',
                ],
                'budget_max' => [
                    'type' => 'integer',
                    'description' => 'Maximum monthly budget in Naira',
                ],
                'schedule' => [
                    'type' => 'string',
                    'description' => 'Schedule type: full_time, part_time, live_in, temporary',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Number of matches to return (max 10)',
                ],
            ],
            'required' => [],
        ];
    }
}