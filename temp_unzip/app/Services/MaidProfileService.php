<?php

namespace App\Services;

use App\Models\User;

class MaidProfileService
{
    /**
     * Recalculate and save the completeness score for a maid.
     * Call this after every profile update, document upload, or NIN submission.
     */
    public function recalculate(User $maid): int
    {
        $score = 0;
        $profile = $maid->maidProfile;

        if (!$profile) {
            return 0;
        }

        // Each criterion worth points that sum to 100
        if ($maid->name) {
            $score += 10;
        }
        if ($maid->phone) {
            $score += 10;
        }
        if ($maid->email) {
            $score += 5;
        }
        if (!empty($profile->location)) {
            $score += 10;
        }
        if (!empty($profile->skills)) {
            $score += 15;
        }
        if (!empty($profile->bio)) {
            $score += 5;
        }
        if ($profile->experience_years !== null && $profile->experience_years > 0) {
            $score += 5;
        }
        if ($profile->expected_salary) {
            $score += 5;
        }
        if ($profile->nin_verified) {
            $score += 20;
        }
        if ($profile->background_verified) {
            $score += 15;
        }

        $profile->update([
            'profile_completeness' => min($score, 100),
        ]);

        return $score;
    }
}