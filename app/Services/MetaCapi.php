<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Conversions API (server-side) sender for the Maids.ng pixel.
 *
 * One pixel: config('services.meta.pixel_id') — "Maids.ng Pixel" 1533038361829535,
 * owned by the Maids.ng Business Manager (1257695082798036).
 *
 * Every server event carries an event_id so Meta can dedupe it against the
 * matching browser (fbq) event. Send the SAME event_id from both sides.
 *
 * Fire-and-forget: failures are logged, never thrown — tracking must not break
 * a payment or a signup.
 */
class MetaCapi
{
    private ?string $pixelId;
    private ?string $token;
    private ?string $testCode;
    private string $api;

    public function __construct()
    {
        $this->pixelId  = config('services.meta.pixel_id');
        $this->token    = config('services.meta.capi_token');
        $this->testCode = config('services.meta.capi_test_code') ?: null;
        $this->api      = 'https://graph.facebook.com/' . config('services.meta.graph_version', 'v21.0');
    }

    public function enabled(): bool
    {
        return ! empty($this->pixelId) && ! empty($this->token);
    }

    // ── convenience wrappers ────────────────────────────────────────────────

    public function purchase(?User $user, string $eventId, int|float $value, string $currency = 'NGN', array $custom = [], ?Request $request = null): void
    {
        $this->send('Purchase', $eventId, $this->userData($user, $request), array_merge([
            'value'    => round((float) $value, 2),
            'currency' => $currency,
        ], $custom), 'website', $request);
    }

    public function lead(?User $user, string $eventId, array $custom = [], ?Request $request = null, string $actionSource = 'website'): void
    {
        $this->send('Lead', $eventId, $this->userData($user, $request), $custom, $actionSource, $request);
    }

    public function completeRegistration(?User $user, string $eventId, array $custom = [], ?Request $request = null): void
    {
        $this->send('CompleteRegistration', $eventId, $this->userData($user, $request), $custom, 'website', $request);
    }

    public function contact(string $eventId, array $userData = [], array $custom = [], ?Request $request = null, string $actionSource = 'website'): void
    {
        $this->send('Contact', $eventId, $this->mergeRequestSignals($userData, $request), $custom, $actionSource, $request);
    }

    /**
     * Click-to-WhatsApp Purchase — attributes a payment back to the ad the
     * customer tapped before messaging Peace on WhatsApp.
     *
     * Meta only accepts `Purchase` for action_source "business_messaging", and
     * the event must carry the real `ctwa_clid` from the ad click plus the
     * WhatsApp page id. We send this IN ADDITION to the normal website Purchase
     * (different event_id suffix so it is not deduped away).
     */
    public function purchaseCtwa(string $eventId, string $ctwaClid, string $phone, int|float $value, string $currency = 'NGN', array $custom = []): void
    {
        if (! $this->enabled() || $ctwaClid === '') {
            return;
        }

        $userData = ['ctwa_clid' => $ctwaClid];
        foreach ($this->phoneVariants($phone) as $variant) {
            $userData['ph'][] = $this->hash($variant);
        }
        if ($pageId = config('services.meta.page_id')) {
            $userData['page_id'] = (string) $pageId;
        }

        $event = array_filter([
            'event_name'        => 'Purchase',
            'event_time'        => time(),
            'event_id'          => $eventId,
            'action_source'     => 'business_messaging',
            'messaging_channel' => 'whatsapp',
            'user_data'         => $userData,
            'custom_data'       => array_merge([
                'value'    => round((float) $value, 2),
                'currency' => $currency,
            ], $custom) ?: null,
        ], fn ($v) => $v !== null);

        try {
            $resp = Http::asForm()->timeout(6)->post("{$this->api}/{$this->pixelId}/events", [
                'access_token' => $this->token,
                'data'         => json_encode([$event]),
            ] + ($this->testCode ? ['test_event_code' => $this->testCode] : []));

            if ($resp->failed()) {
                Log::warning('MetaCapi: CTWA purchase failed', [
                    'event_id' => $eventId, 'status' => $resp->status(),
                    'body' => $resp->json() ?? $resp->body(),
                ]);
            } else {
                Log::info('MetaCapi: CTWA purchase sent', [
                    'event_id' => $eventId, 'received' => $resp->json('events_received'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('MetaCapi: CTWA purchase exception', ['event_id' => $eventId, 'error' => $e->getMessage()]);
        }
    }

    /** Normalised phone-keyed cache lookups for a stored ctwa_clid (7-day window). */
    public function ctwaClidForPhone(string $phone): ?string
    {
        foreach ($this->phoneVariants($phone) as $variant) {
            $clid = \Illuminate\Support\Facades\Cache::get('ctwa_clid:' . $variant);
            if ($clid) {
                return $clid;
            }
        }
        return null;
    }

    /** Store a ctwa_clid against every normalised variant of a phone number. */
    public function storeCtwaClid(string $phone, string $clid, int $days = 7): void
    {
        foreach ($this->phoneVariants($phone) as $variant) {
            \Illuminate\Support\Facades\Cache::put('ctwa_clid:' . $variant, $clid, now()->addDays($days));
        }
    }

    // ── core ───────────────────────────────────────────────────────────────

    public function send(string $eventName, string $eventId, array $userData, array $customData, string $actionSource, ?Request $request = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $userData = array_filter($userData, fn ($v) => $v !== null && $v !== '' && $v !== []);
        if (empty($userData)) {
            Log::warning('MetaCapi: skipping event with no user_data', ['event' => $eventName, 'event_id' => $eventId]);
            return;
        }

        // action_source "chat" (Peace's WhatsApp conversations) and other
        // non-web sources must not carry event_source_url.
        $webLike = in_array($actionSource, ['website', 'email', 'app'], true);

        $event = array_filter([
            'event_name'       => $eventName,
            'event_time'       => time(),
            'event_id'         => $eventId,
            'action_source'    => $actionSource,
            'event_source_url' => $webLike ? ($request?->fullUrl() ?? ($customData['event_source_url'] ?? config('app.url'))) : null,
            'user_data'        => $userData,
            'custom_data'      => array_diff_key($customData, ['event_source_url' => null, 'messaging_channel' => null]) ?: null,
        ], fn ($v) => $v !== null);

        $payload = ['data' => [$event]];
        if ($this->testCode) {
            $payload['test_event_code'] = $this->testCode;
        }

        try {
            $resp = Http::asForm()->timeout(6)->post("{$this->api}/{$this->pixelId}/events", [
                'access_token' => $this->token,
                'data'         => json_encode([$event]),
            ] + ($this->testCode ? ['test_event_code' => $this->testCode] : []));

            if ($resp->failed()) {
                Log::warning('MetaCapi: send failed', [
                    'event' => $eventName, 'event_id' => $eventId,
                    'status' => $resp->status(), 'body' => $resp->json() ?? $resp->body(),
                ]);
            } else {
                Log::info('MetaCapi: sent', [
                    'event' => $eventName, 'event_id' => $eventId,
                    'received' => $resp->json('events_received'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('MetaCapi: exception', ['event' => $eventName, 'error' => $e->getMessage()]);
        }
    }

    // ── user data / hashing ────────────────────────────────────────────────

    private function userData(?User $user, ?Request $request): array
    {
        $data = [];

        if ($user) {
            if ($user->email && ! str_ends_with($user->email, '@maids.ng')) {
                $data['em'] = [$this->hash($user->email)];
            }
            foreach ($this->phoneVariants((string) $user->phone) as $variant) {
                $data['ph'][] = $this->hash($variant);
            }
            if ($user->name) {
                $parts = preg_split('/\s+/', trim($user->name));
                $data['fn'] = [$this->hash($parts[0] ?? '')];
                if (count($parts) > 1) {
                    $data['ln'] = [$this->hash(end($parts))];
                }
            }
            $data['external_id'] = [$this->hash((string) $user->id)];
        }

        return $this->mergeRequestSignals($data, $request);
    }

    /** Attach _fbp / _fbc / IP / UA from the current request — big match-quality boost. */
    private function mergeRequestSignals(array $data, ?Request $request): array
    {
        $request ??= request();
        if (! $request) {
            return $data;
        }

        $fbp = $request->cookie('_fbp');
        $fbc = $request->cookie('_fbc');
        if (! $fbc && $request->query('fbclid')) {
            $fbc = 'fb.1.' . (int) (microtime(true) * 1000) . '.' . $request->query('fbclid');
        }
        if ($fbp) $data['fbp'] = $fbp;
        if ($fbc) $data['fbc'] = $fbc;
        if ($ip = $request->ip()) $data['client_ip_address'] = $ip;
        if ($ua = $request->userAgent()) $data['client_user_agent'] = $ua;

        return $data;
    }

    private function hash(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }

    /** Same phone-variant logic the agent-api UserController uses. */
    private function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return [];
        }
        $variants = [$digits];
        if (str_starts_with($digits, '0')) {
            $variants[] = '234' . substr($digits, 1);
        } elseif (str_starts_with($digits, '234')) {
            $variants[] = '0' . substr($digits, 3);
        }
        return array_values(array_unique(array_filter($variants)));
    }
}
