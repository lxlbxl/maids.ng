<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use PostHog\PostHog as PostHogClient;

/**
 * Fire-and-forget server-side PostHog. Mirrors MetaCapi: failures are logged,
 * never thrown — analytics must not break a signup or a payment.
 *
 * Client-side pageviews/autocapture come from the JS snippet (admin Settings).
 * This class is only for events the browser can't see: lead_created (agent
 * creates the user from a WhatsApp conversation) and matching_fee_paid.
 */
class PostHog
{
    private bool $ready = false;

    public function __construct()
    {
        $key = config('services.posthog.key');
        if (! $key) {
            return;
        }
        try {
            PostHogClient::init($key, ['host' => config('services.posthog.host', 'https://us.posthog.com')]);
            $this->ready = true;
        } catch (\Throwable $e) {
            Log::warning('PostHog init failed', ['error' => $e->getMessage()]);
        }
    }

    public function enabled(): bool
    {
        return $this->ready;
    }

    /**
     * @param  string  $distinctId  the browser's posthog id when we have it, else a stable hash
     */
    public function capture(string $distinctId, string $event, array $properties = [], array $personSet = []): void
    {
        if (! $this->ready || $distinctId === '') {
            return;
        }
        try {
            $payload = [
                'distinctId' => $distinctId,
                'event'      => $event,
                'properties' => array_filter($properties, fn ($v) => $v !== null && $v !== ''),
            ];
            if ($personSet) {
                $payload['properties']['$set'] = $personSet;
            }
            PostHogClient::capture($payload);
        } catch (\Throwable $e) {
            Log::warning('PostHog capture failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    /** Stable distinct id for a lead we only know by phone. */
    public static function distinctIdForPhone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        return $d === '' ? '' : 'phone_' . substr(hash('sha256', $d . config('app.key')), 0, 24);
    }

    public static function distinctIdForUser(User $user, ?string $fromClick = null): string
    {
        return $fromClick
            ?: ($user->phone ? self::distinctIdForPhone($user->phone) : 'user_' . $user->id);
    }
}
