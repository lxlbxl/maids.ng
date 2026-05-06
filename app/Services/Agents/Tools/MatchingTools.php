<?php

namespace App\Services\Agents\Tools;

use App\Models\EmployerPreference;
use App\Models\MaidProfile;
use App\Services\MatchingService;
use Illuminate\Support\Facades\Log;

/**
 * MatchingTools — Maid search, matching, and assignment status.
 */
class MatchingTools
{
    public function __construct(
        private readonly MatchingService $matchingService,
    ) {
    }

    /**
     * Find matching maids for an employer.
     *
     * @param array{ help_type?: string, schedule?: string, location?: string, budget_min?: int, budget_max?: int, urgency?: string } $args
     * @param \App\Models\AgentChannelIdentity $identity
     * @param \App\Services\Agents\DTOs\ChannelMessage $message
     * @return array{ success: bool, matches?: array, message: string, preference_id?: int }
     */
    public function __invoke(array $args, $identity, $message): array
    {
        // If user is authenticated, try to use their existing preferences
        $preference = null;
        if ($identity->user_id) {
            $preference = EmployerPreference::where('employer_id', $identity->user_id)
                ->latest()
                ->first();
        }

        // Build or update preferences from args
        if ($args) {
            if ($preference) {
                $preference->update([
                    'help_type' => $args['help_type'] ?? $preference->help_type,
                    'schedule_type' => $args['schedule'] ?? $preference->schedule_type,
                    'location' => $args['location'] ?? $preference->location,
                    'budget_min' => $args['budget_min'] ?? $preference->budget_min,
                    'budget_max' => $args['budget_max'] ?? $preference->budget_max,
                    'urgency' => $args['urgency'] ?? $preference->urgency,
                ]);
            } else {
                $preference = EmployerPreference::create([
                    'employer_id' => $identity->user_id,
                    'help_type' => $args['help_type'] ?? 'general',
                    'schedule_type' => $args['schedule'] ?? 'full_time',
                    'location' => $args['location'] ?? '',
                    'budget_min' => $args['budget_min'] ?? 30000,
                    'budget_max' => $args['budget_max'] ?? 80000,
                    'urgency' => $args['urgency'] ?? 'flexible',
                    'matching_status' => 'pending',
                ]);
            }
        }

        if (!$preference) {
            return [
                'success' => false,
                'message' => 'I need more information to find matches. Could you tell me what type of help you need and your location?',
            ];
        }

        // Run matching
        try {
            $matches = $this->matchingService->findMatches($preference, 10);

            $formatted = collect($matches)->map(function ($match) {
                return [
                    'maid_id' => $match['maid']->id,
                    'name' => $match['maid']->user->name,
                    'help_type' => $match['maid']->help_type,
                    'location' => $match['maid']->location,
                    'monthly_rate' => $match['maid']->monthly_rate,
                    'experience_years' => $match['maid']->experience_years,
                    'rating' => $match['maid']->average_rating,
                    'match_score' => $match['score'],
                    'is_verified' => (bool) $match['maid']->is_verified,
                ];
            })->take(5)->all();

            return [
                'success' => true,
                'matches' => $formatted,
                'message' => "I found " . count($formatted) . " maids that match your requirements.",
                'preference_id' => $preference->id,
            ];
        } catch (\Throwable $e) {
            Log::error('MatchingTools error: ' . $e->getMessage(), [
                'preference_id' => $preference->id,
            ]);

            return [
                'success' => false,
                'message' => 'I encountered an error while searching. Let me connect you with our team.',
            ];
        }
    }

    /**
     * Check assignment status for an employer.
     *
     * @param array{ employer_id: int } $args
     * @return array{ found: bool, status?: string, stage?: string, maid_name?: string, message: string }
     */
    public function checkStatus(array $args): array
    {
        $preference = EmployerPreference::where('employer_id', $args['employer_id'])
            ->latest()
            ->first();

        if (!$preference) {
            return [
                'found' => false,
                'message' => 'No matching request found. Would you like to start the matching process?',
            ];
        }

        $status = $preference->matching_status ?? 'pending';
        $stage = match ($status) {
            'pending' => 'Waiting for preferences',
            'matching' => 'Finding matches',
            'matches_ready' => 'Matches available',
            'maid_selected' => 'Maid selected',
            'payment_pending' => 'Payment pending',
            'active' => 'Active assignment',
            'completed' => 'Assignment completed',
            'cancelled' => 'Matching cancelled',
            default => 'In progress',
        };

        $maidName = null;
        if ($preference->maid_id) {
            $maid = MaidProfile::with('user')->find($preference->maid_id);
            $maidName = $maid?->user?->name;
        }

        return [
            'found' => true,
            'status' => $status,
            'stage' => $stage,
            'maid_name' => $maidName,
            'message' => "Your matching status is: {$stage}",
        ];
    }
}