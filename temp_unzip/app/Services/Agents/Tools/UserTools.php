<?php

namespace App\Services\Agents\Tools;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * UserTools — Account creation, profile lookup, and user management.
 */
class UserTools
{
    /**
     * Create a new Maids.ng account.
     *
     * @param array{ name: string, phone: string, email?: string, role: string, password?: string } $args
     * @param \App\Models\AgentChannelIdentity $identity
     * @param \App\Services\Agents\DTOs\ChannelMessage $message
     * @return array{ success: bool, user_id?: int, message: string, temp_password?: string }
     */
    public function __invoke(array $args, $identity, $message): array
    {
        $validator = Validator::make($args, [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^\+?[0-9]{10,15}$/',
            'email' => 'nullable|email|max:255',
            'role' => 'required|in:employer,maid',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Invalid input: ' . implode(', ', $validator->errors()->all()),
            ];
        }

        // Check if user already exists
        $existing = User::where('phone', $args['phone'])
            ->orWhere('email', $args['email'] ?? '')
            ->first();

        if ($existing) {
            return [
                'success' => false,
                'message' => 'An account with this phone number or email already exists. Would you like to log in instead?',
            ];
        }

        $password = $args['password'] ?? bin2hex(random_bytes(4));

        $user = User::create([
            'name' => $args['name'],
            'email' => $args['email'] ?? null,
            'phone' => $args['phone'],
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        // Assign role
        $role = Role::where('name', $args['role'])->first();
        if ($role) {
            $user->assignRole($role);
        }

        // Link the channel identity to this user
        if ($identity) {
            $identity->update([
                'user_id' => $user->id,
                'is_verified' => true,
            ]);
        }

        Log::info('Account created via Ambassador agent', [
            'user_id' => $user->id,
            'role' => $args['role'],
            'channel' => $message->channel,
        ]);

        return [
            'success' => true,
            'user_id' => $user->id,
            'message' => "Account created successfully! You are registered as a {$args['role']}.",
            'temp_password' => $args['password'] ? null : $password,
        ];
    }

    /**
     * Look up a user by phone or email.
     *
     * @param array{ phone?: string, email?: string } $args
     * @return array{ found: bool, user_id?: int, name?: string, role?: string, tier: string, is_verified: bool }
     */
    public function lookup(array $args): array
    {
        $query = User::query();

        if (!empty($args['phone'])) {
            $query->where('phone', $args['phone']);
        }

        if (!empty($args['email'])) {
            $query->orWhere('email', $args['email']);
        }

        $user = $query->first();

        if (!$user) {
            return [
                'found' => false,
                'tier' => 'guest',
                'is_verified' => false,
            ];
        }

        $role = $user->getRoleNames()->first() ?? 'employer';

        return [
            'found' => true,
            'user_id' => $user->id,
            'name' => $user->name,
            'role' => $role,
            'tier' => 'authenticated',
            'is_verified' => (bool) $user->email_verified_at,
        ];
    }
}