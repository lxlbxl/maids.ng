<?php

namespace App\Services\Agents;

use App\Models\MaidProfile;
use App\Models\EmployerPreference;
use App\Services\AgentService;

class ScoutAgent extends AgentService
{
    public function getName(): string
    {
        return 'Scout';
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

        // 1. Help Type Match (Max 35 points)
        $employerHelpTypes = is_array($preferences->help_types) 
            ? $preferences->help_types 
            : json_decode($preferences->help_types, true) ?? [];
            
        $maidSkills = is_array($maid->skills) 
            ? $maid->skills 
            : json_decode($maid->skills, true) ?? [];

        $matchingSkills = array_intersect($employerHelpTypes, $maidSkills);
        $helpScore = count($employerHelpTypes) > 0 
            ? (count($matchingSkills) / count($employerHelpTypes)) * 35 
            : 35;
        $score += $helpScore;
        $breakdown['help_type'] = $helpScore;

        // 2. Budget Match (Max 25 points)
        $employerBudget = $preferences->budget_max ?? 100000;
        $maidRate = $maid->expected_salary ?? 0;
        
        if ($maidRate <= $employerBudget) {
            $budgetScore = 25;
        } elseif ($maidRate <= ($employerBudget * 1.2)) {
            $budgetScore = 15; // 20% over budget is acceptable but penalized
        } else {
            $budgetScore = 0;
        }
        $score += $budgetScore;
        $breakdown['budget'] = $budgetScore;

        // 3. Location Match / Willingness to Travel (Max 25 points)
        $locationScore = 0;
        $maidState = $maid->state ?? '';
        $prefState = $preferences->state ?? '';
        $maidCity = $maid->city ?? '';
        $prefCity = $preferences->city ?? '';

        if (!empty($maidState) && !empty($prefState) && strtolower($maidState) === strtolower($prefState)) {
            $locationScore = 15;
            if (!empty($maidCity) && !empty($prefCity) && strtolower($maidCity) === strtolower($prefCity)) {
                $locationScore = 25;
            }
        }
        $score += $locationScore;
        $breakdown['location'] = $locationScore;

        // 4. Quality & Sentinel adjustment (Max 15 points)
        $ratingScore = ($maid->rating / 5) * 15;
        $score += $ratingScore;
        $breakdown['quality'] = $ratingScore;

        // Confidence calculation based on data completeness
        $confidence = ($maid->isVerified() ? 50 : 20) + (count($maidSkills) > 0 ? 50 : 0);

        // Record the background decision periodically (we wouldn't log every single match in a loop for perf, 
        // but for high-scoring ones we might). Let's just return the score block for the controller to use.
        return [
            'score' => round($score),
            'confidence' => $confidence,
            'breakdown' => $breakdown,
            'agent' => $this->getName(),
            // The controller can trigger logDecision if they proceed with this match
        ];
    }
}
