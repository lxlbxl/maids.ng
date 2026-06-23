<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function show($id) 
    { 
        return Inertia::render('Admin/UserDetail', [
            'userId' => $id,
            'user' => \App\Models\User::with(['roles', 'maidProfile', 'employerPreferences'])->findOrFail($id)
        ]); 
    }

    public function index(Request $request) 
    { 
        $sort = $request->sort ?? 'newest';
        $sortField = match ($sort) {
            'name_asc', 'name_desc', 'name' => 'name',
            default => 'created_at',
        };
        $sortDir = in_array($sort, ['name_asc', 'oldest']) ? 'asc' : 'desc';

        $users = \App\Models\User::role('employer')
            ->with(['roles', 'employerPreferences'])
            ->when($request->search, fn($q, $s) => $q->where(fn($q2) => $q2->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', $sortDir)
            ->paginate(20)->withQueryString();

        // Fallback for stats if tables/roles are missing
        $stats = [
            'total' => 0,
            'active_bookings' => 0,
            'total_spent' => 0,
            'new_signups' => 0,
        ];

        try {
            $stats['total'] = \App\Models\User::role('employer')->count();
            $stats['active_bookings'] = \App\Models\Booking::whereIn('status', ['pending', 'confirmed'])->count();
            $stats['total_spent'] = \App\Models\WalletTransaction::where('transaction_type', 'debit')->where('status', 'completed')->sum('amount');
            $stats['new_signups'] = \App\Models\User::role('employer')->where('created_at', '>=', now()->subDays(7))->count();
        } catch (\Exception $e) {
            // Log error or ignore to keep dashboard running
            \Illuminate\Support\Facades\Log::error("Dashboard stats error: " . $e->getMessage());
        }

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'sort']),
            'roles' => \Spatie\Permission\Models\Role::all()
        ]); 
    }

    public function staff(Request $request)
    {
        $staff = \App\Models\User::role('admin')
            ->with('roles')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20);

        $stats = [
            'total_staff' => \App\Models\User::role('admin')->count(),
            'active_now' => 1, // Placeholder for real-time tracking
            'audit_actions' => \Illuminate\Support\Facades\DB::table('agent_activity_logs')->count(),
        ];

        return Inertia::render('Admin/Staff', [
            'staff' => $staff,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'sort']),
            'roles' => \Spatie\Permission\Models\Role::all(),
        ]);
    }

    public function updateStatus($id, Request $request) 
    { 
        $user = \App\Models\User::findOrFail($id);
        $user->update(['status' => $request->status]);

        return back()->with('success', "User [{$user->name}] is now {$request->status}."); 
    }

    public function assignRole($id, Request $request) 
    { 
        $user = \App\Models\User::findOrFail($id);
        $user->syncRoles([$request->role]);

        return back()->with('success', "Role for [{$user->name}] updated to {$request->role}."); 
    }

    public function update(Request $request, $id)
    {
        $user = \App\Models\User::findOrFail($id);
        $user->update($request->only(['name', 'email', 'phone']));
        return back()->with('success', "User [{$user->name}] updated.");
    }

    public function destroy($id) 
    { 
        \App\Models\User::findOrFail($id)->delete();
        return back()->with('success', 'User deleted from system.'); 
    }
}
