<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EscalationController extends Controller
{
    /**
     * Display the list of escalated items queued for manual review.
     */
    public function index()
    {
        return Inertia::render('Admin/Escalations', [
            'escalations' => AgentActivityLog::where('decision', 'queued_for_review')
                ->latest()
                ->paginate(15)
        ]);
    }

    /**
     * Resolve an escalation with a human override.
     */
    public function resolve(Request $request, $id)
    {
        $request->validate([
            'resolution' => 'required|in:approve,reject'
        ]);

        $log = AgentActivityLog::findOrFail($id);
        $resolution = $request->resolution;

        // Perform the override action based on the subject type
        $subject = $log->subject;
        
        if (!$subject) {
            return back()->with('error', 'Subject entity not found for this escalation.');
        }

        switch ($log->agent_name) {
            case 'Gatekeeper':
                if ($log->action === 'verify_identity' && $subject instanceof \App\Models\MaidProfile) {
                    $subject->update(['is_verified' => ($resolution === 'approve')]);
                }
                break;

            case 'Referee':
                if ($log->action === 'assess_dispute' && $subject instanceof \App\Models\Booking) {
                    $subject->update(['status' => ($resolution === 'approve' ? 'active' : 'cancelled')]);
                }
                break;

            case 'Sentinel':
                // For sentinel, manual override might mean restoring a suspended profile
                if ($log->action === 'assess_maid_quality' && $subject instanceof \App\Models\MaidProfile) {
                    $subject->update(['availability_status' => ($resolution === 'approve' ? 'available' : 'suspended')]);
                }
                break;
        }

        // Mark the log as resolved by human
        $log->update([
            'decision' => $resolution === 'approve' ? 'approved_by_admin' : 'rejected_by_admin',
            'reasoning' => $log->reasoning . " | HUMAN OVERRIDE: " . auth()->user()->name . " has " . $resolution . "d this item manually."
        ]);

        return back()->with('success', "Escalation #{$id} has been resolved manually by Human Administrator.");
    }
}
