<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\EmployerPreference;
use App\Models\MatchingFeePayment;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $preferences = EmployerPreference::where('employer_id', $user->id)
            ->with('selectedMaid.maidProfile')
            ->latest()
            ->get();

        $bookings = Booking::where('employer_id', $user->id)
            ->with('maid.maidProfile')
            ->latest()
            ->take(5)
            ->get();

        $payments = MatchingFeePayment::where('employer_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Employer/Dashboard', [
            'preferences' => $preferences->map(fn($p) => [
                'id' => $p->id,
                'help_types' => $p->help_types,
                'location' => $p->location,
                'matching_status' => $p->matching_status,
                'created_at' => $p->created_at->format('M d, Y'),
                'maid' => $p->selectedMaid ? [
                    'id' => $p->selectedMaid->id,
                    'name' => $p->selectedMaid->name,
                    'phone' => $p->matching_status === 'paid' ? $p->selectedMaid->phone : null,
                    'email' => $p->matching_status === 'paid' ? $p->selectedMaid->email : null,
                    'location' => $p->selectedMaid->maidProfile?->location,
                    'rating' => round($p->selectedMaid->maidProfile?->rating ?? 0, 1),
                    'role' => $p->selectedMaid->maidProfile?->getMaidRole(),
                ] : null,
            ]),
            'bookings' => $bookings->map(fn($b) => [
                'id' => $b->id,
                'status' => $b->status,
                'start_date' => $b->start_date?->format('M d, Y'),
                'agreed_salary' => $b->agreed_salary,
                'maid_name' => $b->maid?->name,
            ]),
            'payments' => $payments,
            'stats' => [
                'total_bookings' => Booking::where('employer_id', $user->id)->count(),
                'active_bookings' => Booking::where('employer_id', $user->id)->where('status', 'active')->count(),
            ],
        ]);
    }
}
