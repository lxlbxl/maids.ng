<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * API Authentication Controller
 * 
 * Handles token-based authentication for API consumers.
 * Designed for Agentic AI integration with clear response formats.
 * 
 * @package App\Http\Controllers\Api\Auth
 * @version 1.0.0
 */
class AuthController extends ApiController
{
    /**
     * Login - Obtain API Token
     * 
     * Authenticate user and return access token.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @bodyParam email string required User email address. Example: user@example.com
     * @bodyParam password string required User password. Example: password123
     * @bodyParam device_name string optional Device identifier for token. Example: Mobile App
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error(
                'Invalid credentials',
                Response::HTTP_UNAUTHORIZED,
                ['email' => ['The provided credentials are incorrect.']],
                'INVALID_CREDENTIALS'
            );
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return $this->error(
                'Account is not active',
                Response::HTTP_FORBIDDEN,
                null,
                'ACCOUNT_INACTIVE'
            );
        }

        // Create token
        $deviceName = $request->device_name ?? 'API Client';
        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    /**
     * Register - Create New Account
     * 
     * Register a new user (employer or maid).
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @bodyParam name string required Full name. Example: John Doe
     * @bodyParam email string required Unique email. Example: john@example.com
     * @bodyParam phone string required Phone number. Example: +2348012345678
     * @bodyParam password string required Min 8 characters. Example: securepass123
     * @bodyParam password_confirmation string required Must match password.
     * @bodyParam role string required Either 'employer' or 'maid'. Example: employer
     * @bodyParam location string optional Location. Example: Lagos
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:employer,maid',
            'location' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'location' => $request->location,
            'status' => 'active',
        ]);

        // Assign role
        $user->assignRole($request->role);

        // Create profile based on role
        if ($request->role === 'maid') {
            $user->maidProfile()->create([
                'availability_status' => 'available',
            ]);
        }

        // Create token
        $token = $user->createToken('API Registration')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registration successful', [], Response::HTTP_CREATED);
    }

    /**
     * Logout - Revoke Token
     * 
     * Revoke the current access token.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Logout All - Revoke All Tokens
     * 
     * Revoke all access tokens for the user.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Revoke all tokens
        $request->user()->tokens()->delete();

        return $this->success(null, 'All sessions logged out successfully');
    }

    /**
     * Get Current User
     * 
     * Retrieve authenticated user details.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($request->user()->load(['maidProfile', 'employerPreferences'])),
            'User retrieved successfully'
        );
    }

    /**
     * Refresh Token
     * 
     * Revoke current token and create new one.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke current token
        $user->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('API Token Refreshed')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Token refreshed successfully');
    }

    /**
     * Update Profile
     * 
     * Update authenticated user profile.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
            'location' => 'nullable|string|max:255',
            'avatar' => 'nullable|string|max:255',
        ]);

        $user->update($request->only(['name', 'phone', 'location', 'avatar']));

        return $this->success(
            new UserResource($user->fresh()),
            'Profile updated successfully'
        );
    }

    /**
     * Change Password
     * 
     * Update user password.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error(
                'Current password is incorrect',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['current_password' => ['The current password is incorrect.']],
                'INVALID_CURRENT_PASSWORD'
            );
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return $this->success(null, 'Password changed successfully');
    }
}
