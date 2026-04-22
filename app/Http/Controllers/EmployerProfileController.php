<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployerProfileController extends Controller
{
    public function show() { return Inertia::render('Employer/Profile', ['user' => Auth::user()]); }
    public function update(Request $request) { Auth::user()->update($request->only('name', 'phone', 'location')); return back()->with('success', 'Profile updated.'); }
    public function updatePhoto(Request $request) { return back()->with('success', 'Photo updated.'); }
}
