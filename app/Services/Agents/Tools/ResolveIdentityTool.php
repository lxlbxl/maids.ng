<?php

namespace App\Services\Agents\Tools;

use App\Models\AgentChannelIdentity;
use App\Models\User;
use App\Services\Agents\DTOs\InboundMessage;

/**
 * Tool: resolve_identity
 * Looks up a user by phone or email across all channels.
 * Returns identity status and whether OTP verification is needed.
 */
class ResolveIdentityTool
{
    public function __invoke(array $args, AgentChannelIdentity $identity, InboundMessage $message): string
    {
        $phone = $args['phone'] ?? $message->phone;
        $email = $args['email'] ?? $message->email;

        if (!$phone && !$email) {
            return json_encode([
                'status' => 'unknown',
                'message' => 'No phone or email provided to resolve identity.',
            ]);
        }

        // Try to find by phone
        if ($phone) {
            $found = AgentChannelIdentity::where('phone', $phone)
                ->where('is_verified', true)
                ->with('user')
                ->first();

            if ($found) {
                return json_encode([
                    'status' => 'verified',
                    'user_id' => $found->user_id,
                    'name' => $found->user?->name,
                    'email' => $found->user?->email,
                    'tier' => 'authenticated',
                    'channels' => AgentChannelIdentity::where('user_id', $found->user_id)
                        ->pluck('channel')
                        ->toArray(),
                ]);
            }
        }

        // Try to find by email
        if ($email) {
            $found = AgentChannelIdentity::where('email', $email)
                ->where('is_verified', true)
                ->with('user')
                ->first();

            if ($found) {
                return json_encode([
                    'status' => 'verified',
                    'user_id' => $found->user_id,
                    'name' => $found->user?->name,
                    'phone' => $found->user?->phone,
                    'tier' => 'authenticated',
                ]);
            }
        }

        // Check if there's an unverified identity
        $unverified = AgentChannelIdentity::where(function ($q) use ($phone, $email) {
            if ($phone)
                $q->where('phone', $phone);
            if ($email)
                $q->orWhere('email', $email);
        })->first();

        if ($unverified) {
            return json_encode([
                'status' => 'unverified',
                'message' => 'Identity found but not verified. OTP required.',
                'channel' => $unverified->channel,
            ]);
        }

        return json_encode([
            'status' => 'new',
            'message' => 'No existing identity found. This appears to be a new contact.',
        ]);
    }

    public function description(): string
    {
        return 'Resolve a user identity by phone number or email. Returns verification status and user details if found.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'phone' => [
                    'type' => 'string',
                    'description' => 'Phone number in international format (e.g., +234...)',
                ],
                'email' => [
                    'type' => 'string',
                    'description' => 'Email address',
                ],
            ],
            'required' => [],
        ];
    }
}