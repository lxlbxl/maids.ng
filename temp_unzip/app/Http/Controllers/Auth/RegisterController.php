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

    public function showMaidRegistrationForm()
    {
        return Inertia::render('Auth/MaidRegister');
    }

    public function registerMaid(Request $request)
    {
        \Log::info('Maid registration attempt', ['data' => $request->except('password', 'password_confirmation', 'avatar')]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'skills' => 'nullable|array',
            'languages' => 'nullable|array',
            'experience_years' => 'nullable|integer',
            'expected_salary' => 'nullable|numeric',
            'location' => 'required|string',
            'nin' => 'nullable|string|size:11',
            'is_foreigner' => 'nullable|boolean',
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        \Log::info('Maid registration validated', ['validated' => array_keys($validated)]);

        // Process Avatar with Compression
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->processAndSaveAvatar($request->file('avatar'));
        }

        // If no email, create a dummy one or use phone
        $email = $validated['email'] ?? $validated['phone'] . '@maids.ng';

        $user = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'location' => $validated['location'],
            'avatar' => $avatarPath,
        ]);

        \Log::info('Maid user created', ['user_id' => $user->id, 'avatar' => $avatarPath]);

        $user->assignRole('maid');

        \Log::info('Maid role assigned', ['user_id' => $user->id]);

        // Create profile
        $user->maidProfile()->create([
            'nin' => $validated['nin'] ?? null,
            'is_foreigner' => $validated['is_foreigner'] ?? false,
            'skills' => $validated['skills'] ?? [],
            'languages' => $validated['languages'] ?? [],
            'experience_years' => (int) ($validated['experience_years'] ?? 0),
            'expected_salary' => (int) ($validated['expected_salary'] ?? 0),
            'location' => $validated['location'],
            'help_types' => $validated['skills'] ?? [],
            'nin_verified' => false,
        ]);

        \Log::info('Maid profile created', ['user_id' => $user->id]);

        // Also create a record in nin_verifications table for tracking (if NIN provided)
        if (!empty($validated['nin'])) {
            try {
                \App\Models\NinVerification::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'submitted_at' => now(),
                ]);
                \Log::info('NinVerification created', ['user_id' => $user->id]);
            } catch (\Exception $e) {
                \Log::warning('NinVerification creation failed during maid registration: ' . $e->getMessage());
            }
        }

        Auth::login($user);

        \Log::info('Maid registration complete, redirecting to dashboard', ['user_id' => $user->id]);

        return redirect()->route('maid.dashboard')->with('success', 'Registration complete! Welcome to Maids.ng.');
    }

    private function processAndSaveAvatar($file)
    {
        try {
            $extension = 'jpg'; // We'll save everything as JPG for consistency and compression
            $filename = 'avatar_' . time() . '_' . uniqid() . '.' . $extension;
            $directory = public_path('storage/avatars');
            
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $fullPath = $directory . '/' . $filename;
            $tempPath = $file->getRealPath();
            
            // Get image info
            $imageInfo = getimagesize($tempPath);
            if (!$imageInfo) return null;
            
            $mime = $imageInfo['mime'];
            
            // Load image based on mime type
            switch ($mime) {
                case 'image/jpeg': $img = imagecreatefromjpeg($tempPath); break;
                case 'image/png': $img = imagecreatefrompng($tempPath); break;
                case 'image/webp': $img = imagecreatefromwebp($tempPath); break;
                default: return null;
            }

            if (!$img) return null;

            // Resize if too large (max 800px width or height)
            $width = imagesx($img);
            $height = imagesy($img);
            $maxSize = 800;
            
            if ($width > $maxSize || $height > $maxSize) {
                if ($width > $height) {
                    $newWidth = $maxSize;
                    $newHeight = floor($height * ($maxSize / $width));
                } else {
                    $newHeight = $maxSize;
                    $newWidth = floor($width * ($maxSize / $height));
                }
                $tmp = imagecreatetruecolor($newWidth, $newHeight);
                
                // Preserve transparency for original images if needed, but since we save as JPG, we'll fill with white
                $white = imagecolorallocate($tmp, 255, 255, 255);
                imagefill($tmp, 0, 0, $white);
                
                imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($img);
                $img = $tmp;
            }

            // Save with 80% quality as JPEG
            imagejpeg($img, $fullPath, 80);
            imagedestroy($img);
            
            return '/storage/avatars/' . $filename;
        } catch (\Exception $e) {
            \Log::error('Image processing failed: ' . $e->getMessage());
            return null;
        }
    }
}

