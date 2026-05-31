<?php
namespace App\Http\Controllers;

use App\Models\ApiAuditLog;
use App\Models\Setting;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAuditLogController extends Controller
{
    /**
     * List API audit log entries with filters.
     */
    public function index(Request $request)
    {
        $query = ApiAuditLog::query();

        if ($request->filled('method')) {
            $query->where('method', strtoupper($request->method));
        }
        if ($request->filled('endpoint')) {
            $query->where('endpoint', 'like', '%' . $request->endpoint . '%');
        }
        if ($request->filled('status')) {
            $query->where('response_status', $request->status);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->latest()->paginate(50)->withQueryString();

        // Retention setting (days)
        $retentionDays = (int) Setting::get('audit_log_retention_days', 90);

        return Inertia::render('Admin/AuditLog', [
            'logs'          => $logs,
            'filters'       => $request->only(['method', 'endpoint', 'status', 'user_id', 'from', 'to']),
            'retentionDays' => $retentionDays,
        ]);
    }

    /**
     * Update audit log retention setting.
     */
    public function updateRetention(Request $request)
    {
        $request->validate(['days' => 'required|integer|min:1|max:3650']);
        Setting::set('audit_log_retention_days', $request->days);
        return back()->with('success', "Retention period set to {$request->days} days.");
    }

    /**
     * Purge all audit log entries.
     */
    public function destroyAll()
    {
        ApiAuditLog::truncate();
        return back()->with('success', 'All API audit logs purged.');
    }

    /**
     * Purge audit logs older than the configured retention period.
     * Called by the scheduler.
     */
    public static function purgeOldLogs(): void
    {
        $days = (int) Setting::get('audit_log_retention_days', 90);
        ApiAuditLog::where('created_at', '<', now()->subDays($days))->delete();
    }
}
