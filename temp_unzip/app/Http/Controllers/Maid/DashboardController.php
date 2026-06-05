<?php

namespace App\Http\Controllers\Maid;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $profile = $user->maidProfile;

        $bookings = Booking::where('maid_id', $user->id)
            ->with('employer')
            ->latest()
            ->take(5)
            ->get();

        // Resolve SentinelAgent from container with graceful degradation
        $profileInsights = ['score' => 0, 'tips' => []];
        if ($profile) {
            try {
                $sentinel = app(\App\Services\Agents\SentinelAgent::class);
                $profileInsights = $sentinel->generateProfileTips($profile);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SentinelAgent profile insights failed, using fallback: ' . $e->getMessage());

                // Fallback: rule-based profile scoring when AI is unavailable
                $score = 0;
                $tips = [];

                if (!empty($profile->bio)) { $score += 15; } else { $tips[] = 'Add a short bio to stand out to employers.'; }
                if (!empty($profile->skills)) { $score += 20; } else { $tips[] = 'Select the services you can offer.'; }
                if (!empty($profile->nin)) { $score += 10; } else { $tips[] = 'Submit your NIN for identity verification.'; }
                if ($profile->nin_verified) { $score += 15; } else { $tips[] = 'Complete NIN verification to get the "Verified" badge.'; }
                if ($profile->background_verified) { $score += 15; }
                if ($profile->expected_salary) { $score += 10; } else { $tips[] = 'Set your expected monthly salary.'; }
                if ($profile->availability_status === 'available') { $score += 15; } else { $tips[] = 'Mark yourself as available to receive job offers.'; }

                if (empty($tips)) {
                    $tips[] = 'Great job! Your profile is complete. Keep your availability updated.';
                }

                $profileInsights = ['score' => min($score, 100), 'tips' => $tips];
            }
        }

        return Inertia::render('Maid/Dashboard', [
            'profile' => $profile ? [
                'bio' => $profile->bio,
                'skills' => $profile->skills,
                'rating' => round($profile->rating ?? 0, 1),
                'total_reviews' => $profile->total_reviews,
                'availability_status' => $profile->availability_status,
                'expected_salary' => $profile->expected_salary,
                'nin_verified' => $profile->nin_verified,
                'background_verified' => $profile->background_verified,
            ] : null,
            'profileInsights' => $profileInsights,
            'bookings' => $bookings->map(fn($b) => [
                'id' => $b->id,
                'status' => $b->status,
                'employer_name' => $b->employer?->name,
                'start_date' => $b->start_date ? $b->start_date->format('M d, Y') : 'TBD',
                'agreed_salary' => $b->agreed_salary,
            ]),
            'stats' => [
                'total_bookings' => Booking::where('maid_id', $user->id)->count(),
                'active_bookings' => Booking::where('maid_id', $user->id)->where('status', 'active')->count(),
                'completed_bookings' => Booking::where('maid_id', $user->id)->where('status', 'completed')->count(),
            ],
        ]);
    }
}
