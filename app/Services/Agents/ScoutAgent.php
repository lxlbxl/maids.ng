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

        // 1. Help Type Match (Max 35 points) - attributes are already arrays due to model casts
        $employerHelpTypes = array_map('strtolower', (array)($preferences->help_types ?? []));
        $maidHelpTypes = array_map('strtolower', (array)($maid->help_types ?? []));
        
        if (empty($maidHelpTypes)) {
            $maidHelpTypes = array_map('strtolower', (array)($maid->skills ?? []));
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
}
