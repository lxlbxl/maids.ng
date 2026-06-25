<?php

namespace App\Http\Controllers;

use App\Models\MaidProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaidSearchController extends Controller
{
    public function index(Request $request)
    {
        $query = User::role('maid')
            ->where('status', 'active')
            ->with('maidProfile')
            ->whereHas('maidProfile', fn($q) => $q->where('nin_verified', true));

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhereHas('maidProfile', fn($mp) => $mp->where('location', 'ilike', "%{$search}%"));
            });
        }

        if ($request->filled('location')) {
            $location = $request->input('location');
            $query->whereHas('maidProfile', function ($mp) use ($location) {
                $mp->where(function ($q) use ($location) {
                    $q->where('location', 'ilike', "%{$location}%")
                      ->orWhere('state', 'ilike', "%{$location}%")
                      ->orWhereRaw("EXISTS (SELECT 1 FROM json_array_elements_text(willing_states) WHERE LOWER(value) = ?)", [$locLower])
                      ->orWhereJsonContains('willing_states', 'anywhere');
                });
            });
        }

        $maids = $query->paginate(12)->withQueryString();

        $maidData = $maids->through(function ($maid) {
            $p = $maid->maidProfile;
            return [
                'id' => $maid->id,
                'name' => $maid->name,
                'role' => $p->getMaidRole(),
                'location' => $p->location ?? $maid->location,
                'rating' => round($p->rating, 1),
                'rate' => $p->expected_salary,
                'skills' => $p->skills ?? [],
                'verified' => $p->nin_verified && $p->background_verified,
                'avatar' => $maid->avatar,
                'bio' => $p->bio,
                'gender' => $p->gender,
                'experience_years' => $p->experience_years,
                'availability_status' => $p->availability_status,
                'schedule_preference' => $p->schedule_preference,
                'willing_states' => $p->willing_states ?? [],
                'languages' => $p->languages ?? [],
            ];
        });

        return Inertia::render('Maids/Search', [
            'maids' => $maidData,
            'filters' => $request->only(['search', 'location', 'type', 'schedule']),
        ]);
    }

    public function search(Request $request)
    {
        $query = User::role('maid')->where('status', 'active')->with('maidProfile')->whereHas('maidProfile', fn($q) => $q->where('nin_verified', true));

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhereHas('maidProfile', fn($mp) => $mp->where('location', 'ilike', "%{$search}%"));
            });
        }

        if ($request->filled('location')) {
            $location = $request->input('location');
            $query->whereHas('maidProfile', function ($mp) use ($location) {
                $mp->where(function ($q) use ($location) {
                    $q->where('location', 'ilike', "%{$location}%")
                      ->orWhere('state', 'ilike', "%{$location}%")
                      ->orWhereRaw("EXISTS (SELECT 1 FROM json_array_elements_text(willing_states) WHERE LOWER(value) = ?)", [$locLower])
                      ->orWhereJsonContains('willing_states', 'anywhere');
                });
            });
        }

        return response()->json($query->paginate(12));
    }

    public function show($id)
    {
        $maid = User::role('maid')->whereHas('maidProfile', fn($q) => $q->where('nin_verified', true))->with('maidProfile', 'reviewsReceived.employer')->findOrFail($id);
        $p = $maid->maidProfile;

        return Inertia::render('Maids/Show', [
            'maid' => [
                'id' => $maid->id,
                'name' => $maid->name,
                'role' => $p?->getMaidRole() ?? 'Helper',
                'location' => $p?->location ?? $maid->location,
                'rating' => round($p?->rating ?? 0, 1),
                'total_reviews' => $p?->total_reviews ?? 0,
                'rate' => $p?->expected_salary ?? 0,
                'expected_salary' => $p?->expected_salary ?? 0,
                'skills' => $p?->skills ?? [],
                'bio' => $p?->bio,
                'experience_years' => $p?->experience_years ?? 0,
                'verified' => ($p?->nin_verified ?? false) && ($p?->background_verified ?? false),
                'nin_verified' => $p?->nin_verified ?? false,
                'background_verified' => $p?->background_verified ?? false,
                'avatar' => $maid->avatar,
                'gender' => $p?->gender,
                'willing_states' => $p?->willing_states ?? [],
                'languages' => $p?->languages ?? [],
                'schedule_preference' => $p?->schedule_preference,
                'availability_status' => $p?->availability_status ?? 'available',
                'help_types' => $p?->help_types ?? [],
                'reviews' => $maid->reviewsReceived->map(fn($r) => [
                    'rating' => $r->rating,
                    'comment' => $r->comment,
                    'employer' => $r->employer?->name,
                    'date' => $r->created_at->format('M Y'),
                ]),
            ],
        ]);
    }

    public function featured()
    {
        $maids = User::role('maid')
            ->where('status', 'active')
            ->with('maidProfile')
            ->whereHas('maidProfile', fn($q) => $q->where('rating', '>=', 4.5)->where('nin_verified', true))
            ->take(6)
            ->get();

        return response()->json($maids->map(fn($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'role' => $m->maidProfile->getMaidRole(),
            'rating' => round($m->maidProfile->rating, 1),
            'location' => $m->maidProfile->location,
        ]));
    }

    public function locations()
    {
        $locations = MaidProfile::whereNotNull('location')
            ->where('nin_verified', true)
            ->distinct()
            ->pluck('location');

        return response()->json($locations);
    }

    public function showJson($id)
    {
        $maid = User::role('maid')->whereHas('maidProfile', fn($q) => $q->where('nin_verified', true))->with('maidProfile')->findOrFail($id);
        $p = $maid->maidProfile;
        return response()->json([
            'id' => $maid->id,
            'name' => $maid->name,
            'role' => $p?->getMaidRole() ?? 'Helper',
            'location' => $p?->location ?? $maid->location,
            'rate' => $p?->expected_salary ?? 0,
            'avatar' => $maid->avatar,
            'availability_status' => $p?->availability_status ?? 'available',
            'gender' => $p?->gender,
            'experience_years' => $p?->experience_years ?? 0,
            'skills' => $p?->skills ?? [],
        ]);
    }
}
