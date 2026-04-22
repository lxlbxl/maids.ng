<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminDisputeController extends Controller
{
    public function index() 
    { 
        $disputes = \App\Models\Dispute::with(['user', 'booking.employer', 'booking.maid'])
            ->latest()
            ->paginate(10);

        $stats = [
            'total' => \App\Models\Dispute::count(),
            'pending' => \App\Models\Dispute::where('status', 'pending')->count(),
            'resolved' => \App\Models\Dispute::where('status', 'resolved')->count(),
            'escalated' => \App\Models\Dispute::where('status', 'escalated')->count(),
        ];

        return Inertia::render('Admin/Disputes', [
            'disputes' => $disputes,
            'stats' => $stats,
        ]); 
    }

    public function resolve($id, Request $request) 
    { 
        $dispute = \App\Models\Dispute::findOrFail($id);
        $dispute->update([
            'status' => 'resolved',
            'resolution' => $request->notes,
        ]);
        return back()->with('success', 'Dispute marked as resolved.'); 
    }
}
