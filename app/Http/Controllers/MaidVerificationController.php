<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class MaidVerificationController extends Controller
{
    public function show() 
    { 
        $user = auth()->user();
        $profile = $user->maidProfile;
        
        // Fetch gatekeeper logs for this maid (graceful if no profile yet)
        $gatekeeperLogs = $profile ? \App\Models\AgentActivityLog::where('agent_name', 'Gatekeeper')
            ->where('subject_type', \App\Models\MaidProfile::class)
            ->where('subject_id', $profile->id)
            ->latest()
            ->get() : [];

        return Inertia::render('Maid/Verification', [
            'profile' => $profile,
            'agentLogs' => $gatekeeperLogs
        ]); 
    }

    public function submitNin(Request $request) 
    { 
        $user = auth()->user();
        $profile = $user->maidProfile;
        
        if (!$profile) {
            $profile = $user->maidProfile()->create([
                'location' => $user->location ?? '',
                'skills' => [],
                'help_types' => [],
            ]);
        }

        // Prevent NIN change if already verified
        if ($profile->nin_verified) {
            return back()->with('error', 'Your identity has already been verified. NIN cannot be changed.');
        }
        
        $request->validate(['nin' => 'required|string|size:11|unique:maid_profiles,nin,' . $profile->id]);
        $profile->update(['nin' => $request->nin]);

        // Reset verification tracking so sweep retries with new NIN
        \App\Models\NinVerification::where('user_id', $user->id)
            ->where('status', 'failed')
            ->update(['status' => 'pending', 'reviewed_at' => null]);

        return back()->with('success', 'NIN submitted. Click "Verify Now" to trigger the Gatekeeper Agent.'); 
    }

    public function verifyNin(Request $request, \App\Services\Agents\GatekeeperAgent $gatekeeper) 
    { 
        $user = auth()->user();
        $profile = $user->maidProfile;
        
        if (!$profile || !$profile->nin) {
            return back()->with('error', 'Please submit your NIN first.');
        }

        try {
            $result = $gatekeeper->verifyIdentity($profile, $profile->nin);
            
            if ($result['success']) {
                return back()->with('success', 'Identity verified successfully by our Gatekeeper Agent!');
            } else {
                return back()->with('warning', 'Gatekeeper: ' . $result['reason']);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('GatekeeperAgent verification failed: ' . $e->getMessage());
            return back()->with('error', 'Verification service temporarily unavailable. Please try again later.');
        }
    }

    public function submitDocument(Request $request) { return back()->with('success', 'Document submitted.'); }
    public function status() 
    { 
        $user = auth()->user();
        $verification = \App\Models\NinVerification::where('user_id', $user->id)
            ->latest()
            ->first();

        return response()->json([
            'status' => $verification->status ?? 'none',
            'nin_verified' => $user->maidProfile->nin_verified ?? false,
            'confidence_score' => $verification->confidence_score ?? 0,
            'reviewed_at' => $verification->reviewed_at ?? null,
        ]); 
    }
}
