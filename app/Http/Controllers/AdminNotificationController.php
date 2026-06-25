<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request) 
    { 
        $sort = $request->sort ?? 'newest';
        $sortDir = $sort === 'oldest' ? 'asc' : 'desc';

        $notifications = \App\Models\Notification::with('user')
            ->when($request->search, fn($q, $s) => $q->where('title', 'ilike', "%{$s}%")->orWhere('message', 'ilike', "%{$s}%"))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->orderBy('created_at', $sortDir)
            ->paginate(20)->withQueryString();

        return Inertia::render('Admin/Notifications', [
            'notifications' => $notifications,
            'filters' => $request->only(['search', 'type', 'sort']),
        ]); 
    }

    public function store(Request $request) 
    { 
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'target' => 'required|in:all,maids,employers',
        ]);

        $query = \App\Models\User::query();
        if ($request->target === 'maids') {
            $query->role('maid');
        } elseif ($request->target === 'employers') {
            $query->role('employer');
        }

        $users = $query->get();
        foreach ($users as $user) {
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'type' => 'broadcast',
                'title' => $request->title,
                'message' => $request->message,
            ]);
        }

        return back()->with('success', "Notification sent to {$users->count()} users."); 
    }
}
