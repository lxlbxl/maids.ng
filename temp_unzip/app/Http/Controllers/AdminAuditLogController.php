<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request) 
    { 
        $logs = \App\Models\AgentActivityLog::when($request->agent, fn($q, $a) => $q->where('agent_name', $a))
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/AuditLog', [
            'logs' => $logs,
            'filters' => $request->only(['agent']),
        ]); 
    }

    public function destroyAll() 
    { 
        \App\Models\AgentActivityLog::truncate();
        return back()->with('success', 'All audit logs purged.'); 
    }
}
