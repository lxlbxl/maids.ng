<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function indexEmployer() { 
        $user = Auth::user();
        
        $matchingFees = \App\Models\MatchingFeePayment::where('employer_id', $user->id)
            ->latest()
            ->paginate(10, ['*'], 'matching_page');

        // Note: Salary payments would typically be in a separate table like 'SalaryPayment' or similar
        // For now, let's assume we have a table or relationship. If not, we'll return an empty collection
        // for demonstration of the Treasurer agent's oversight.
        
        return Inertia::render('Employer/Payments', [
            'matchingFees' => $matchingFees,
            'payouts' => [] // Placeholder for employee payouts triggered by Treasurer
        ]); 
    }
    public function indexMaid() { 
        $user = Auth::user();
        
        // Fetch payouts processed by our Treasurer Agent
        // For now using AgentActivityLog to show the history of processing
        $payoutLogs = \App\Models\AgentActivityLog::where('agent_name', 'Treasurer')
            ->where('reasoning', 'like', "%Maid ID: {$user->id}%")
            ->latest()
            ->paginate(10);

        return Inertia::render('Maid/Earnings', [
            'payoutLogs' => $payoutLogs,
            'stats' => [
                'total_earned' => 0, // In real app, sum from payout table
                'pending_payout' => 0,
            ]
        ]); 
    }
    public function initialize(Request $request) { return response()->json(['status' => 'initialized']); }
    public function verify(Request $request) { 
        return redirect()->route('employer.dashboard')
            ->with('warning', 'General payment verification is currently handled via specialized controllers.'); 
    }
    public function webhook(Request $request) { 
        Log::info('General payment webhook received', ['payload' => $request->all()]);
        return response()->json(['status' => 'received']); 
    }
    public function stats() { return response()->json([]); }
    public function requestPayout(Request $request) { return back()->with('success', 'Payout request submitted.'); }
}
