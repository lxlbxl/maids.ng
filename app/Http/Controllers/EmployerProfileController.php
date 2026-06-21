<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmployerProfileController extends Controller
{
    public function show() { return Inertia::render('Employer/Profile', ['user' => Auth::user()]); }
    public function update(Request $request) { Auth::user()->update($request->only('name', 'phone', 'location')); return back()->with('success', 'Profile updated.'); }
    public function updatePhoto(Request $request) { return back()->with('success', 'Photo updated.'); }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The current password you entered is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password changed successfully.');
    }
}
