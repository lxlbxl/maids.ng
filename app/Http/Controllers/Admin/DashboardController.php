<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MatchingFeePayment;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Aggregate Agent Performance
        $agentStats = \App\Models\AgentActivityLog::select('agent_name')
            ->selectRaw('count(*) as decision_count')
            ->selectRaw('avg(confidence_score) as avg_confidence')
            ->groupBy('agent_name')
            ->get();

        // Count escalations across different subjects
        $escalationQueue = \App\Models\AgentActivityLog::where('decision', 'queued_for_review')->count();
        
        // Financial Overview (Treasurer Escrow)
        $escrowTotal = Booking::where('status', 'active')->sum('agreed_salary');

        // Recent Central Intelligence Feed
        $recentActivity = \App\Models\AgentActivityLog::latest()->take(10)->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users' => User::count(),
                'total_maids' => User::role('maid')->count(),
                'total_employers' => User::role('employer')->count(),
                'total_bookings' => Booking::count(),
                'active_bookings' => Booking::where('status', 'active')->count(),
                'total_revenue' => MatchingFeePayment::where('status', 'paid')->sum('amount'),
                'pending_verifications' => User::role('maid')->whereHas('maidProfile', fn($q) => $q->where('nin_verified', false))->count(),
            ],
            'agentHealth' => $agentStats,
            'escalationCount' => $escalationQueue,
            'escrowTotal' => $escrowTotal,
            'recentActivity' => $recentActivity
        ]);
    }
}
