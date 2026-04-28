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
        
        // Fetch gatekeeper logs for this maid
        $gatekeeperLogs = \App\Models\AgentActivityLog::where('agent', 'Gatekeeper')
            ->where('subject_type', \App\Models\MaidProfile::class)
            ->where('subject_id', $profile->id)
            ->latest()
            ->get();

        return Inertia::render('Maid/Verification', [
            'profile' => $profile,
            'agentLogs' => $gatekeeperLogs
        ]); 
    }

    public function submitNin(Request $request) 
    { 
        $request->validate(['nin' => 'required|string|size:11']);
        auth()->user()->maidProfile->update(['nin' => $request->nin]);
        return back()->with('success', 'NIN submitted. Click "Verify Now" to trigger the Gatekeeper Agent.'); 
    }

    public function verifyNin(Request $request, \App\Services\Agents\GatekeeperAgent $gatekeeper) 
    { 
        $profile = auth()->user()->maidProfile;
        if (!$profile->nin) {
            return back()->with('error', 'Please submit your NIN first.');
        }

        $result = $gatekeeper->verifyIdentity($profile, $profile->nin);
        
        if ($result['success']) {
            return back()->with('success', 'identity verified successfully by our Gatekeeper Agent!');
        } else {
            return back()->with('warning', 'Gatekeeper: ' . $result['reason']);
        }
    }

    public function submitDocument(Request $request) { return back()->with('success', 'Document submitted.'); }
    public function status() { return response()->json(['status' => 'pending']); }
}
