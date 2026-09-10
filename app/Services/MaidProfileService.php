<?php

namespace App\Services;

use App\Models\User;

class MaidProfileService
{
    /**
     * Score at or above which a profile is considered complete.
     * A NIN-verified maid with every self-serve field filled reaches 85, so 80
     * is the point at which we stop reporting them as incomplete.
     */
    public const COMPLETION_THRESHOLD = 80;

    /**
     * Recalculate and save the completeness score for a maid.
     * Call this after every profile update, document upload, or NIN submission.
     */
    public function recalculate(User $maid): int
    {
        $profile = $maid->maidProfile;

        if (!$profile) {
            return 0;
        }

        $score = 0;

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

        $completeness = min($score, 100);
        $isComplete = $completeness >= self::COMPLETION_THRESHOLD;

        $updates = [
            'profile_completeness' => $completeness,
            'is_profile_complete'  => $isComplete,
        ];

        // Stamp the completion time the first time the threshold is crossed;
        // keep the original timestamp on later recalcs.
        if ($isComplete && !$profile->profile_completed_at) {
            $updates['profile_completed_at'] = now();
        }

        $profile->update($updates);

        return $score;
    }
}
