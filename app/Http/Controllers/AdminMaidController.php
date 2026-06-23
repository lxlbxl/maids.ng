<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminMaidController extends Controller
{
    public function index(Request $request) 
    { 
        $sort = $request->sort ?? 'newest';
        $sortField = match ($sort) {
            'name_asc' => 'name',
            'name_desc' => 'name',
            'rating' => null,
            'oldest' => 'created_at',
            default => 'created_at',
        };
        $sortDir = match ($sort) {
            'name_asc' => 'asc',
            'oldest' => 'asc',
            default => 'desc',
        };

        $maids = \App\Models\User::role('maid')
            ->with(['maidProfile', 'ninVerification'])
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            }))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->verified, fn($q, $v) => $q->whereHas('maidProfile', fn($q2) => $this->applyVerifiedFilter($q2, $v)))
            ->when($request->location, fn($q, $l) => $q->whereHas('maidProfile', fn($q2) => $q2->where('location', 'like', "%{$l}%")));

        if ($sort === 'rating') {
            $maids = $maids->leftJoin('maid_profiles', 'users.id', '=', 'maid_profiles.user_id')
                ->orderBy('maid_profiles.rating', $sortDir)
                ->select('users.*');
        } elseif ($sortField) {
            $maids = $maids->orderBy($sortField, $sortDir);
        }

        $maids = $maids->paginate(20)->withQueryString();

        $stats = [
            'total' => \App\Models\User::role('maid')->count(),
            'active' => \App\Models\User::role('maid')->where('status', 'active')->count(),
            'verified' => \App\Models\MaidProfile::where('nin_verified', true)->count(),
            'pending_verification' => \App\Models\MaidProfile::where('nin_verified', false)->whereNotNull('nin')->count(),
            'new_this_week' => \App\Models\User::role('maid')->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return Inertia::render('Admin/Maids', [
            'maids' => $maids,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'verified', 'location', 'sort']),
        ]); 
    }

    private function applyVerifiedFilter($query, string $value): void
    {
        match ($value) {
            'yes' => $query->where('nin_verified', true),
            'no' => $query->where('nin_verified', false),
            'review_required' => $query->whereHas('user.ninVerification', fn($q) => $q->where('status', 'review_required')),
            'pending' => $query->whereHas('user.ninVerification', fn($q) => $q->where('status', 'pending')),
            'failed' => $query->whereHas('user.ninVerification', fn($q) => $q->where('status', 'failed')),
            default => null,
        };
    }

    public function show($id) 
    { 
        $user = \App\Models\User::with(['maidProfile', 'ninVerification', 'roles', 'reviewsReceived.employer', 'bookingsAsMaid.employer'])->findOrFail($id);
        return Inertia::render('Admin/MaidDetail', [
            'id' => $id,
            'user' => $user,
            'profile' => $user->maidProfile,
            'ninVerification' => $user->ninVerification,
            'reviews' => $user->reviewsReceived,
            'bookings' => $user->bookingsAsMaid,
        ]); 
    }

    public function updateStatus($id, Request $request) 
    { 
        $user = \App\Models\User::findOrFail($id);
        $user->status = $request->status;
        $user->save();
        return back()->with('success', 'Status updated.');
    }
}
