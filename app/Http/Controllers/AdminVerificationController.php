<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminVerificationController extends Controller
{
    public function index(Request $request) 
    { 
        $sort = $request->sort ?? 'newest';
        $sortDir = $sort === 'oldest' ? 'asc' : 'desc';

        $pending = \App\Models\User::role('maid')
            ->with(['maidProfile', 'ninVerification'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))
            ->when($request->status, function($q, $s) {
                match ($s) {
                    'verified' => $q->whereHas('maidProfile', fn($q2) => $q2->where('nin_verified', true)),
                    'pending' => $q->whereHas('maidProfile', fn($q2) => $q2->where('nin_verified', false))->whereHas('ninVerification', fn($q2) => $q2->where('status', 'pending')),
                    'review_required' => $q->whereHas('ninVerification', fn($q2) => $q2->where('status', 'review_required')),
                    'failed' => $q->whereHas('ninVerification', fn($q2) => $q2->where('status', 'failed')),
                    'unverified' => $q->whereHas('maidProfile', fn($q2) => $q2->where('nin_verified', false)),
                    default => null,
                };
            })
            ->orderBy('created_at', $sortDir)
            ->paginate(10)->withQueryString();

        return Inertia::render('Admin/Verifications', [
            'pendingVerifications' => $pending,
            'filters' => $request->only(['search', 'status', 'sort'])
        ]); 
    }

    public function approve($id) 
    { 
        $user = \App\Models\User::with('ninVerification')->findOrFail($id);
        $profile = $user->maidProfile;

        $profile->update(['nin_verified' => true]);

        if ($user->ninVerification) {
            $user->ninVerification->update(['status' => 'approved', 'reviewed_at' => now()]);
        }

        $agent = new \App\Services\Agents\GatekeeperAgent();
        $agent->recordManualApproval($profile, auth()->user());

        return back()->with('success', "Verification for {$user->name} approved."); 
    }

    public function reject(Request $request, $id) 
    { 
        $user = \App\Models\User::with('ninVerification')->findOrFail($id);

        if ($user->ninVerification) {
            $user->ninVerification->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'review_notes' => $request->notes ?? null,
            ]);
        }

        return back()->with('warning', "Verification for {$user->name} rejected."); 
    }

    public function payload($id)
    {
        $verification = \App\Models\NinVerification::where('user_id', $id)->latest()->firstOrFail();
        return response()->json([
            'user_id' => $verification->user_id,
            'status' => $verification->status,
            'confidence_score' => $verification->confidence_score,
            'qoreid_payload' => $verification->qoreid_payload,
        ]);
    }
}
