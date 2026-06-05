<?php

namespace App\Http\Controllers;

use App\Services\MaidProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MaidProfileController extends Controller
{
    public function __construct(private MaidProfileService $maidProfileService)
    {
    }

    public function show()
    {
        return Inertia::render('Maid/Profile', [
            'user' => Auth::user(),
            'profile' => Auth::user()->maidProfile,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $user->update($request->only('name', 'phone', 'location'));

        // Recalculate profile completeness after update
        $this->maidProfileService->recalculate($user);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePhoto(Request $request)
    {
        $user = Auth::user();

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('maid-photos', 'public');
            $user->maidProfile?->update(['photo_path' => $path]);
        }

        // Recalculate profile completeness after photo upload
        $this->maidProfileService->recalculate($user);

        return back()->with('success', 'Photo updated.');
    }

    public function updateAvailability(Request $request)
    {
        $user = Auth::user();
        $user->maidProfile?->update(['availability_status' => $request->input('status')]);

        // Recalculate profile completeness
        $this->maidProfileService->recalculate($user);

        return back()->with('success', 'Availability updated.');
    }

    public function updateSkills(Request $request)
    {
        $user = Auth::user();
        $user->maidProfile?->update(['skills' => $request->input('skills')]);

        // Recalculate profile completeness after skills update
        $this->maidProfileService->recalculate($user);

        return back()->with('success', 'Skills updated.');
    }

    public function updateBankDetails(Request $request)
    {
        $user = Auth::user();
        $user->maidProfile?->update($request->only('bank_name', 'account_number', 'account_name'));

        // Recalculate profile completeness
        $this->maidProfileService->recalculate($user);

        return back()->with('success', 'Bank details updated.');
    }
}
