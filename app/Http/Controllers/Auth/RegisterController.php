<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:employer,maid',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'location' => $request->input('location'),
        ]);

        $user->assignRole($validated['role']);

        Auth::login($user);

        // If registering as employer with preference data, redirect to matching payment
        if ($validated['role'] === 'employer' && $request->has('preference_id')) {
            return redirect()->route('employer.matching.payment', $request->input('preference_id'));
        }

        if ($validated['role'] === 'employer') {
            return redirect()->route('employer.dashboard');
        }

        return redirect()->route('maid.dashboard');
    }
}
