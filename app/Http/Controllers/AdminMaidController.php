<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminMaidController extends Controller
{
    public function index(Request $request) 
    { 
        $maids = \App\Models\User::role('maid')
            ->with('maidProfile')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->verified, function($q, $v) {
                if ($v === 'yes') $q->whereHas('maidProfile', fn($q2) => $q2->where('nin_verified', true));
                if ($v === 'no') $q->whereHas('maidProfile', fn($q2) => $q2->where('nin_verified', false));
            })
            ->latest()
            ->paginate(20);

        $stats = [
            'total' => \App\Models\User::role('maid')->count(),
            'active' => \App\Models\User::role('maid')->where('status', 'active')->count(),
            'verified' => \App\Models\MaidProfile::where('nin_verified', true)->count(),
        ];

        return Inertia::render('Admin/Maids', [
            'maids' => $maids,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'verified']),
        ]); 
    }

    public function show($id) 
    { 
        $user = \App\Models\User::with(['maidProfile', 'roles', 'reviewsReceived.employer', 'bookingsAsMaid.employer'])->findOrFail($id);
        return Inertia::render('Admin/MaidDetail', [
            'id' => $id,
            'user' => $user,
            'profile' => $user->maidProfile,
            'reviews' => $user->reviewsReceived,
            'bookings' => $user->bookingsAsMaid,
        ]); 
    }

    public function updateStatus($id, Request $request) 
    { 
        $user = \App\Models\User::findOrFail($id);
        $user->update(['status' => $request->status]);
        return back()->with('success', "Status for {$user->name} updated to {$request->status}."); 
    }
}
