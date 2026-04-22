<?php

namespace App\Services\Agents;

use App\Models\MaidProfile;
use App\Models\User;
use App\Services\AgentService;

class SentinelAgent extends AgentService
{
    public function getName(): string
    {
        return 'Sentinel';
    }

    /**
     * Monitor maid quality and issue warnings or suspensions based on review metrics.
     * Can be called daily via Laravel Schedule or upon new review submission.
     */
    public function assessMaidQuality(MaidProfile $maid): void
    {
        $action = "assess_quality";
        $rating = $maid->rating; // assumes numeric average

        if ($rating === null || $rating == 0) {
            return; // Not enough data
        }

        if ($rating < 2.0 && $maid->bookings_count >= 3) {
            // Very low rating after multiple bookings - SUSPEND
            // In a real app we'd trigger an email/notification
            $maid->user->assignRole('suspended_maid'); // Example
            
            $this->escalate(
                $action,
                "auto_suspended",
                "Maid rating fell below critical threshold of 2.0 ({$rating}) across {$maid->bookings_count} bookings.",
                $maid,
                95
            );
        } elseif ($rating < 3.0) {
            // Warning territory
            $this->logDecision(
                action: $action,
                decision: "issued_warning",
                confidenceScore: 80,
                reasoning: "Maid rating dropping to concerning levels ({$rating}). Notification dispatched to offer training resources.",
                subject: $maid
            );
        } else {
            // Good standing
            $this->logDecision(
                action: $action,
                decision: "cleared",
                confidenceScore: 90,
                reasoning: "Maid maintaining healthy rating ({$rating}).",
                subject: $maid
            );
        }
    }

    /**
     * Compute "Profile Strength" for a maid to encourage them to complete their profiles.
     */
    public function generateProfileTips(MaidProfile $maid): array
    {
        $prompt = "Generate a strategic 'Profile Strength' report for a household helper.
                   Helper Profile JSON: " . json_encode([
                       'name' => $maid->user->name,
                       'bio' => $maid->bio,
                       'skills' => json_decode($maid->skills, true) ?? [],
                       'is_verified' => $maid->nin_verified,
                       'experience' => $maid->experience_years,
                       'current_score' => $maid->rating
                   ]) . "
                   
                   Return JSON: { 'score': 0-100, 'tips': ['tip1', 'tip2', 'tip3'] }
                   Focus on: How to get more high-paying Lekki/Ikoyi jobs.";

        $aiResponse = $this->think($prompt, [
            'system_prompt' => "You are the Sentinel Agent, a career growth coach for household professionals in Nigeria."
        ]);

        if (isset($aiResponse['error'])) {
            return [
                'score' => 50,
                'tips' => ["Sentinel IQ temporarily offline. Complete your NIN verification to stay ahead."]
            ];
        }

        $result = json_decode($aiResponse['content'], true) ?? [
            'score' => 50,
            'tips' => ["Your profile is being reviewed by Sentinel..."]
        ];

        return [
            'score' => $result['score'] ?? 50,
            'tips' => $result['tips'] ?? []
        ];
    }
}
