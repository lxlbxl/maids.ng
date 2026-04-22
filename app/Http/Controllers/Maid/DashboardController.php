<?php

namespace App\Http\Controllers\Maid;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(\App\Services\Agents\SentinelAgent $sentinel)
    {
        $user = Auth::user();
        $profile = $user->maidProfile;

        $bookings = Booking::where('maid_id', $user->id)
            ->with('employer')
            ->latest()
            ->take(5)
            ->get();

        // Get AI generated profile tips and strength score from Sentinel Agent
        $profileInsights = $profile ? $sentinel->generateProfileTips($profile) : ['score' => 0, 'tips' => []];

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
