<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaidProfileController extends Controller
{
    public function show() { return Inertia::render('Maid/Profile', ['user' => Auth::user(), 'profile' => Auth::user()->maidProfile]); }
    public function update(Request $request) { Auth::user()->update($request->only('name', 'phone', 'location')); return back()->with('success', 'Profile updated.'); }
    public function updatePhoto(Request $request) { return back()->with('success', 'Photo updated.'); }
    public function updateAvailability(Request $request) { Auth::user()->maidProfile?->update(['availability_status' => $request->input('status')]); return back()->with('success', 'Availability updated.'); }
    public function updateSkills(Request $request) { Auth::user()->maidProfile?->update(['skills' => $request->input('skills')]); return back()->with('success', 'Skills updated.'); }
    public function updateBankDetails(Request $request) { Auth::user()->maidProfile?->update($request->only('bank_name', 'account_number', 'account_name')); return back()->with('success', 'Bank details updated.'); }
}
