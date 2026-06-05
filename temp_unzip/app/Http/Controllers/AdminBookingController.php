<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function index(Request $request) 
    { 
        $bookings = \App\Models\Booking::with(['employer', 'maid', 'maid.maidProfile'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, function($q, $s) {
                $q->whereHas('employer', fn($q2) => $q2->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('maid', fn($q2) => $q2->where('name', 'like', "%{$s}%"));
            })
            ->latest()
            ->paginate(20);

        $stats = [
            'total' => \App\Models\Booking::count(),
            'active' => \App\Models\Booking::where('status', 'active')->count(),
            'completed' => \App\Models\Booking::where('status', 'completed')->count(),
            'cancelled' => \App\Models\Booking::where('status', 'cancelled')->count(),
        ];

        return Inertia::render('Admin/Bookings', [
            'bookings' => $bookings,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
        ]); 
    }

    public function show($id) 
    { 
        $booking = \App\Models\Booking::with(['employer', 'maid', 'maid.maidProfile', 'review', 'disputes'])->findOrFail($id);
        return Inertia::render('Admin/BookingDetail', [
            'booking' => $booking,
        ]); 
    }

    public function updateStatus($id, Request $request) 
    { 
        $booking = \App\Models\Booking::findOrFail($id);
        $booking->update(['status' => $request->status]);
        return back()->with('success', 'Booking status updated.'); 
    }
}
