<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index() 
    { 
        $notifications = \App\Models\Notification::latest()->paginate(20);

        return Inertia::render('Admin/Notifications', [
            'notifications' => $notifications,
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
