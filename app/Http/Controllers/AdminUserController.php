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

    public function index() 
    { 
        $users = \App\Models\User::with('roles')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'roles' => \Spatie\Permission\Models\Role::all()
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

    public function destroy($id) 
    { 
        \App\Models\User::findOrFail($id)->delete();
        return back()->with('success', 'User deleted from system.'); 
    }
}
