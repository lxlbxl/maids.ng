<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminVerificationController extends Controller
{
    public function index() 
    { 
        $pending = \App\Models\User::role('maid')
            ->whereHas('maidProfile', function($query) {
                $query->where('nin_verified', false)->orWhere('background_verified', false);
            })
            ->with('maidProfile')
            ->latest()
            ->paginate(10);

        return Inertia::render('Admin/Verifications', [
            'pendingVerifications' => $pending
        ]); 
    }

    public function approve($id) 
    { 
        $user = \App\Models\User::findOrFail($id);
        $profile = $user->maidProfile;

        $profile->update([
            'nin_verified' => true,
            'background_verified' => true // or handle separately if needed
        ]);

        // Log via Gatekeeper Agent for Mission Control transparency
        $agent = new \App\Services\Agents\GatekeeperAgent();
        $agent->recordManualApproval($profile, auth()->user()); // Correctly log the manual approval

        return back()->with('success', "Verification for {$user->name} successful. Helper is now active."); 
    }

    public function reject(Request $request, $id) 
    { 
        $user = \App\Models\User::findOrFail($id);
        
        // We might want to notify them or reset docs
        return back()->with('warning', "Verification for {$user->name} rejected."); 
    }
}
