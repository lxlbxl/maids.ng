<?php

namespace App\Services\Agents;

use App\Models\MaidProfile;
use App\Models\EmployerPreference;
use App\Services\AgentService;

class ScoutAgent extends AgentService
{
    protected string $agentName = 'scout';

    public function getName(): string
    {
        return 'Scout';
    }

    /**
     * Find and score all available maids against an employer's preferences.
     * This is the main entry point — used by both automated jobs and human HITL execution.
     */
    public function findMatches(EmployerPreference $preference, int $limit = 10): array
    {
        if (!$this->canProceed(
            'match.score',
            'match_employer',
            "Find matches for Employer #{$preference->employer_id}",
            ['preference_id' => $preference->id, 'employer_id' => $preference->employer_id],
            ['related_user_id' => $preference->employer_id]
        )) {
            return [];
        }

        $startTime = now();

        $candidates = MaidProfile::where('availability_status', 'available')
            ->whereHas('user', fn($q) => $q->where('status', 'active'))
            ->with('user')
            ->when($preference->state, fn($q) => $q->where('state', $preference->state))
            ->get();

        $results = [];
        foreach ($candidates as $maid) {
            $score = $this->scoreMatch($maid, $preference);
            $results[] = array_merge($score, [
                'maid_id'    => $maid->user_id,
                'name'       => $maid->user->name ?? 'Unknown',
                'location'   => $maid->location,
                'skills'     => $maid->skills ?? [],
                'rating'     => $maid->rating ?? 0,
                'salary'     => $maid->expected_salary ?? 0,
                'verified'   => $maid->nin_verified,
                'profile_id' => $maid->id,
            ]);
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        $results = array_slice($results, 0, $limit);

        $topMessage = !empty($results)
            ? "Matched Employer #{$preference->employer_id} — top: {$results[0]['name']} (" . round($results[0]['score']) . "%)"
            : "No matches found for Employer #{$preference->employer_id}";

        $this->logEvent(
            'match.scored',
            !empty($results) ? 'success' : 'warning',
            $topMessage,
            [
                'employer_id'               => $preference->employer_id,
                'preference_id'             => $preference->id,
                'help_type'                 => $preference->help_types ?? [],
                'location'                  => $preference->location,
                'budget'                    => $preference->budget_max ?? 0,
                'top_matches'               => array_map(fn($r) => [
                    'id' => $r['maid_id'], 'name' => $r['name'], 'score' => round($r['score'])
                ], array_slice($results, 0, 3)),
                'total_candidates_evaluated' => count($candidates),
                'matches_returned'           => count($results),
            ],
            [
                'related_user_id' => $preference->employer_id,
                'related_model'   => 'EmployerPreference',
                'related_id'      => $preference->id,
                'duration_ms'     => now()->diffInMilliseconds($startTime),
            ]
        );

        return $results;
    }

    /**
     * Score a maid against employer preferences.
     * Over time, this algorithm should learn from the Referee Agent's dispute data and Sentinel's ratings.
     */
    public function scoreMatch(MaidProfile $maid, EmployerPreference $preferences): array
    {
        $score = 0;
        $maxScore = 100;
        $breakdown = [];

        // 1. Help Type Match (Max 35 points) - attributes are already arrays due to model casts
        $employerHelpTypes = array_map('strtolower', (array) ($preferences->help_types ?? []));
        $maidHelpTypes = array_map('strtolower', (array) ($maid->help_types ?? []));

        if (empty($maidHelpTypes)) {
            $maidHelpTypes = array_map('strtolower', (array) ($maid->skills ?? []));
        }

        $matchingTypes = array_intersect($employerHelpTypes, $maidHelpTypes);
        $helpScore = count($employerHelpTypes) > 0
            ? (count($matchingTypes) / count($employerHelpTypes)) * 35
            : 35;
        $score += $helpScore;
        $breakdown['help_type'] = $helpScore;

        // 2. Budget Match (Max 25 points)
        $employerBudget = $preferences->budget_max ?? 100000;
        $maidRate = $maid->expected_salary ?? 0;

        if ($maidRate <= $employerBudget) {
            $budgetScore = 25;
        } elseif ($maidRate <= ($employerBudget * 1.2)) {
            $budgetScore = 15;
        } else {
            $budgetScore = 0;
        }
        $score += $budgetScore;
        $breakdown['budget'] = $budgetScore;

        // 3. Location Match / Proximity (Max 25 points)
        $locationScore = 0;
        $maidLoc = strtolower(trim($maid->location ?? ''));
        $prefLoc = strtolower(trim($preferences->location ?? ''));
        $prefCity = strtolower(trim($preferences->city ?? ''));
        $prefState = strtolower(trim($preferences->state ?? ''));

        if (!empty($prefLoc) && !empty($maidLoc) && (str_contains($maidLoc, $prefLoc) || str_contains($prefLoc, $maidLoc))) {
            $locationScore = 25;
        } elseif (!empty($prefCity) && !empty($maidLoc) && str_contains($maidLoc, $prefCity)) {
            $locationScore = 25;
        } elseif (!empty($prefState) && !empty($maidLoc) && str_contains($maidLoc, $prefState)) {
            $locationScore = 20;
        } else {
            // State/City explicit fields check
            $maidState = strtolower(trim($maid->state ?? ''));
            $maidCity = strtolower(trim($maid->city ?? ''));

            if (!empty($maidState) && !empty($prefState) && $maidState === $prefState) {
                $locationScore = 15;
                if (!empty($maidCity) && !empty($prefCity) && $maidCity === $prefCity) {
                    $locationScore = 25;
                }
            }
        }
        $score += $locationScore;
        $breakdown['location'] = $locationScore;

        // 4. Quality & Sentinel adjustment (Max 15 points)
        $ratingScore = (($maid->rating ?? 0) / 5) * 15;
        $score += $ratingScore;
        $breakdown['quality'] = $ratingScore;

        $confidence = ($maid->isVerified() ? 50 : 20) + (count($maidHelpTypes) > 0 ? 50 : 0);

        return [
            'score' => round($score),
            'confidence' => $confidence,
            'breakdown' => $breakdown,
            'agent' => $this->getName(),
        ];
    }

    /**
     * Generate a human-readable explanation for why a maid is a good match.
     * Uses LLM + Knowledge Base context for natural language output.
     */
    public function generateMatchExplanation(MaidProfile $maid, EmployerPreference $preferences, array $scoreBreakdown): string
    {
        $systemPrompt = $this->knowledge->buildContext('scout', 'guest');

        $prompt = "Explain why this maid is a good match for the employer in 2-3 friendly sentences.
            
            Maid: {$maid->user->name}
            Skills: " . implode(', ', $maid->skills ?? []) . "
            Expected Salary: ₦" . number_format($maid->expected_salary ?? 0) . "
            Location: {$maid->location}
            Rating: " . ($maid->rating ?? 'N/A') . "/5
            Verified: " . ($maid->nin_verified ? 'Yes' : 'No') . "
            
            Employer Needs:
            Help Types: " . implode(', ', $preferences->help_types ?? []) . "
            Budget: ₦" . number_format($preferences->budget_max ?? 0) . "
            Location: {$preferences->location}
            
            Match Score: {$scoreBreakdown['score']}/100
            Breakdown: Help Type " . round($scoreBreakdown['breakdown']['help_type'] ?? 0) . "pts, Budget " . round($scoreBreakdown['breakdown']['budget'] ?? 0) . "pts, Location " . round($scoreBreakdown['breakdown']['location'] ?? 0) . "pts, Quality " . round($scoreBreakdown['breakdown']['quality'] ?? 0) . "pts";

        $aiResponse = $this->think($prompt, [
            'system_prompt' => $systemPrompt,
        ]);

        if (isset($aiResponse['error'])) {
            return $this->generateFallbackExplanation($maid, $preferences, $scoreBreakdown);
        }

        return $aiResponse['content'] ?? $this->generateFallbackExplanation($maid, $preferences, $scoreBreakdown);
    }

    /**
     * Fallback explanation when AI is unavailable.
     */
    private function generateFallbackExplanation(MaidProfile $maid, EmployerPreference $preferences, array $scoreBreakdown): string
    {
        $score = $scoreBreakdown['score'] ?? 0;
        $maidName = $maid->user->name;
        $skills = implode(', ', $maid->skills ?? []);
        $rating = $maid->rating ?? 0;

        if ($score >= 80) {
            return "{$maidName} is an excellent match with a {$score}/100 score. She has skills in {$skills} and a {$rating}/5 rating.";
        } elseif ($score >= 60) {
            return "{$maidName} is a good match with a {$score}/100 score. Her skills in {$skills} align well with your needs.";
        }

        return "{$maidName} has a {$score}/100 match score. Review her profile to see if she meets your requirements.";
    }
}
