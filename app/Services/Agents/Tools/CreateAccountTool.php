<?php

namespace App\Services\Agents\Tools;

use App\Models\AgentChannelIdentity;
use App\Models\User;
use App\Services\Agents\DTOs\InboundMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Tool: create_account
 * Creates a new Maids.ng user account from the conversation context.
 * Used when a guest/lead decides to register.
 */
class CreateAccountTool
{
    public function __invoke(array $args, AgentChannelIdentity $identity, InboundMessage $message): string
    {
        $name = $args['name'] ?? $identity->display_name;
        $phone = $args['phone'] ?? $identity->phone ?? $message->phone;
        $email = $args['email'] ?? $identity->email ?? $message->email;
        $role = $args['role'] ?? 'employer'; // employer or maid

        if (!$name || !$phone) {
            return json_encode([
                'success' => false,
                'message' => 'Name and phone number are required to create an account.',
                'missing' => array_filter([
                    'name' => !$name ? 'name' : null,
                    'phone' => !$phone ? 'phone' : null,
                ]),
            ]);
        }

        try {
            DB::beginTransaction();

            // Check if account already exists
            $existing = User::where('phone', $phone)->first();
            if ($existing) {
                // Link this identity to the existing user
                $identity->update([
                    'user_id' => $existing->id,
                    'is_verified' => true,
                ]);

                DB::commit();

                return json_encode([
                    'success' => true,
                    'message' => 'Account already exists and has been linked.',
                    'user_id' => $existing->id,
                    'role' => $existing->role,
                ]);
            }

            // Generate a random password (user can reset later)
            $password = Str::random(12);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'role' => $role,
                'status' => 'active',
            ]);

            // Assign role via Spatie
            $user->assignRole($role);

            // Link identity to user
            $identity->update([
                'user_id' => $user->id,
                'is_verified' => true,
            ]);

            DB::commit();

            return json_encode([
                'success' => true,
                'message' => "Account created successfully! Welcome to Maids.ng, {$name}.",
                'user_id' => $user->id,
                'role' => $role,
                'next_step' => $role === 'employer'
                    ? 'You can now start the matching quiz to find your ideal maid.'
                    : 'You can now complete your profile to start receiving job matches.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return json_encode([
                'success' => false,
                'message' => 'Failed to create account. Please try again or contact support.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ]);
        }
    }

    public function description(): string
    {
        return 'Create a new Maids.ng user account. Requires name and phone number. Email and role (employer/maid) are optional.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Full name of the user',
                ],
                'phone' => [
                    'type' => 'string',
                    'description' => 'Phone number in international format',
                ],
                'email' => [
                    'type' => 'string',
                    'description' => 'Email address (optional)',
                ],
                'role' => [
                    'type' => 'string',
                    'enum' => ['employer', 'maid'],
                    'description' => 'User role — employer (looking for help) or maid (offering services)',
                ],
            ],
            'required' => ['name', 'phone'],
        ];
    }
}