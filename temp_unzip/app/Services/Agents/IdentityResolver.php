<?php

namespace App\Services\Agents;

use App\Models\AgentChannelIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * IdentityResolver — Resolves user identity across channels.
 *
 * Maps external channel identifiers (phone, email, social IDs) to internal
 * user accounts and channel-specific identity records.
 */
class IdentityResolver
{
    /**
     * Find or create a channel identity from contact info.
     *
     * @param string $channel Channel name (web, email, whatsapp, etc.)
     * @param string $externalId External channel message/user ID
     * @param array{ phone?: string, email?: string, name?: string } $contact
     * @return AgentChannelIdentity
     */
    public function resolve(string $channel, string $externalId, array $contact = []): AgentChannelIdentity
    {
        // Try to find existing identity for this channel+external_id
        $identity = AgentChannelIdentity::where('channel', $channel)
            ->where('external_id', $externalId)
            ->first();

        if ($identity) {
            // Update any new contact info
            $updates = [];
            if (!empty($contact['phone']) && $identity->phone !== $contact['phone']) {
                $updates['phone'] = $contact['phone'];
            }
            if (!empty($contact['email']) && $identity->email !== $contact['email']) {
                $updates['email'] = $contact['email'];
            }
            if (!empty($contact['name']) && $identity->display_name !== $contact['name']) {
                $updates['display_name'] = $contact['name'];
            }
            if (!empty($updates)) {
                $identity->update($updates);
            }

            $identity->update(['last_seen_at' => now()]);

            return $identity;
        }

        // Try to resolve by phone or email across channels
        $resolved = $this->findExistingIdentity($contact);

        if ($resolved) {
            // Link this channel to the existing identity
            $identity = AgentChannelIdentity::create([
                'channel' => $channel,
                'external_id' => $externalId,
                'user_id' => $resolved->user_id,
                'phone' => $contact['phone'] ?? $resolved->phone,
                'email' => $contact['email'] ?? $resolved->email,
                'display_name' => $contact['name'] ?? $resolved->display_name,
                'is_verified' => $resolved->is_verified,
            ]);

            Log::info('Channel identity linked to existing user', [
                'new_identity_id' => $identity->id,
                'existing_identity_id' => $resolved->id,
                'channel' => $channel,
            ]);

            return $identity;
        }

        // Create new guest/lead identity
        $identity = AgentChannelIdentity::create([
            'channel' => $channel,
            'external_id' => $externalId,
            'phone' => $contact['phone'] ?? null,
            'email' => $contact['email'] ?? null,
            'display_name' => $contact['name'] ?? null,
            'is_verified' => false,
        ]);

        Log::info('New guest identity created', [
            'identity_id' => $identity->id,
            'channel' => $channel,
        ]);

        return $identity;
    }

    /**
     * Find an existing verified identity by phone or email.
     *
     * @param array{ phone?: string, email?: string } $contact
     * @return AgentChannelIdentity|null
     */
    private function findExistingIdentity(array $contact): ?AgentChannelIdentity
    {
        $query = AgentChannelIdentity::where('is_verified', true);

        if (!empty($contact['phone'])) {
            $query->where(function ($q) use ($contact) {
                $q->where('phone', $contact['phone'])
                    ->orWhere('phone', $this->normalizePhone($contact['phone']));
            });
        }

        if (!empty($contact['email'])) {
            $query->orWhere('email', $contact['email']);
        }

        return $query->first();
    }

    /**
     * Normalize a phone number for consistent matching.
     *
     * @param string $phone
     * @return string
     */
    private function normalizePhone(string $phone): string
    {
        // Remove spaces, dashes, parentheses
        $normalized = preg_replace('/[\s\-\(\)]/', '', $phone);

        // Convert leading 0 to +234 for Nigerian numbers
        if (str_starts_with($normalized, '0') && strlen($normalized) === 11) {
            $normalized = '+234' . substr($normalized, 1);
        }

        // Add + if missing
        if (!str_starts_with($normalized, '+') && str_starts_with($normalized, '234')) {
            $normalized = '+' . $normalized;
        }

        return $normalized;
    }

    /**
     * Get the user tier for an identity.
     *
     * @param AgentChannelIdentity $identity
     * @return string 'guest', 'lead', or 'authenticated'
     */
    public function getTier(AgentChannelIdentity $identity): string
    {
        if ($identity->user_id) {
            return 'authenticated';
        }

        if ($identity->phone || $identity->email) {
            return 'lead';
        }

        return 'guest';
    }

    /**
     * Link an identity to a user account (after registration or login).
     *
     * @param AgentChannelIdentity $identity
     * @param int $userId
     * @return void
     */
    public function linkToUser(AgentChannelIdentity $identity, int $userId): void
    {
        $identity->update([
            'user_id' => $userId,
            'is_verified' => true,
        ]);

        Log::info('Identity linked to user account', [
            'identity_id' => $identity->id,
            'user_id' => $userId,
        ]);
    }
}