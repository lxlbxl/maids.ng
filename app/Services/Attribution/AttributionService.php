<?php

namespace App\Services\Attribution;

use App\Models\LeadAttribution;
use App\Models\User;
use App\Models\UserAttribution;
use App\Models\WaClick;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The one place attribution is written and read.
 *
 * Flow: recordClick() at /wa/{source}  ->  resolveCode() + stampLead() in the
 * bridge  ->  attachToUser() when the account is created  ->  snapshotForPayment()
 * when the matching fee is paid.
 */
class AttributionService
{
    public function __construct(private readonly AttributionClassifier $classifier)
    {
    }

    /** Digits-only, Nigerian-intl normalised (0803… -> 234803…). */
    public static function normalisePhone(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw);
        if (Str::startsWith($d, '0')) {
            $d = '234' . substr($d, 1);
        } elseif (Str::startsWith($d, '234')) {
            // ok
        } elseif (strlen($d) === 10) {
            $d = '234' . $d;
        }
        return $d;
    }

    /**
     * Record a WhatsApp-CTA tap and return the WaClick (its `code` goes into the
     * prefilled message).
     *
     * @param  array  $ctx  source, intent, utm_source, utm_medium, utm_campaign,
     *                       utm_content, utm_term, gclid, fbclid, referrer,
     *                       landing_path, ip, user_agent, posthog_distinct_id
     */
    public function recordClick(array $ctx): WaClick
    {
        $referrer = $ctx['referrer'] ?? null;
        $refDomain = AttributionClassifier::referringDomain($referrer);

        $verdict = $this->classifier->classify([
            'utm_source'       => $ctx['utm_source'] ?? null,
            'utm_medium'       => $ctx['utm_medium'] ?? null,
            'utm_campaign'     => $ctx['utm_campaign'] ?? null,
            'gclid'            => $ctx['gclid'] ?? null,
            'fbclid'           => $ctx['fbclid'] ?? null,
            'referring_domain' => $refDomain,
        ]);

        return WaClick::create([
            'code'                => $this->uniqueCode(),
            'source'              => $this->clean($ctx['source'] ?? null, 40),
            'intent'              => ($ctx['intent'] ?? 'hire') === 'work' ? 'work' : 'hire',
            'channel'             => $verdict['channel'],
            'utm_source'          => $this->clean($ctx['utm_source'] ?? null, 120),
            'utm_medium'          => $this->clean($ctx['utm_medium'] ?? null, 120),
            'utm_campaign'        => $this->clean($ctx['utm_campaign'] ?? null, 190),
            'utm_content'         => $this->clean($ctx['utm_content'] ?? null, 190),
            'utm_term'            => $this->clean($ctx['utm_term'] ?? null, 190),
            'gclid'               => $this->clean($ctx['gclid'] ?? null, 255),
            'fbclid'              => $this->clean($ctx['fbclid'] ?? null, 255),
            'referrer'            => $referrer ? Str::limit($referrer, 500, '') : null,
            'referring_domain'    => $refDomain,
            'landing_path'        => $this->clean($ctx['landing_path'] ?? null, 255),
            'ip'                  => $this->clean($ctx['ip'] ?? null, 45),
            'user_agent'          => $ctx['user_agent'] ? Str::limit($ctx['user_agent'], 500, '') : null,
            'posthog_distinct_id' => $this->clean($ctx['posthog_distinct_id'] ?? null, 190),
        ]);
    }

    /** Bridge -> resolve a click code to its attribution array. */
    public function resolveCode(string $code): ?array
    {
        $click = WaClick::where('code', $this->clean($code, 16))->first();
        if (! $click) {
            return null;
        }
        return $this->arrayFromClick($click);
    }

    /** Build attribution from a Meta Click-to-WhatsApp `referral` object. */
    public function fromCtwaReferral(array $referral): array
    {
        return [
            'channel'   => 'meta_ctwa',
            'campaign'  => $referral['headline'] ?? $referral['source_id'] ?? null,
            'source'    => 'ctwa',
            'ctwa'      => array_filter([
                'source_type' => $referral['source_type'] ?? null,
                'source_id'   => $referral['source_id'] ?? null,
                'source_url'  => $referral['source_url'] ?? null,
                'headline'    => $referral['headline'] ?? null,
                'ctwa_clid'   => $referral['ctwa_clid'] ?? null,
            ]),
            'first_seen_at' => now()->toIso8601String(),
        ];
    }

    public static function whatsappDirect(): array
    {
        return ['channel' => 'whatsapp_direct', 'campaign' => null, 'source' => null, 'first_seen_at' => now()->toIso8601String()];
    }

    /** Bridge -> stash attribution against a phone until the account is created. */
    public function stampLead(string $phone, array $attribution, ?int $waClickId = null): void
    {
        $phone = self::normalisePhone($phone);
        if ($phone === '') {
            return;
        }
        LeadAttribution::updateOrCreate(
            ['phone' => $phone],
            [
                'attribution' => $attribution,
                'wa_click_id' => $waClickId,
                'expires_at'  => now()->addDays(45),
            ]
        );
    }

    public function attributionForPhone(string $phone): ?array
    {
        $lead = LeadAttribution::where('phone', self::normalisePhone($phone))->first();
        return $lead?->attribution;
    }

    /**
     * Permanent attribution for a freshly-created user. Called from
     * AgentApi\UserController@store and the native RegisterController.
     */
    public function attachToUser(User $user, ?array $attribution, ?array $firstTouchCookie = null): ?UserAttribution
    {
        $attribution ??= $firstTouchCookie ? $this->fromCookie($firstTouchCookie) : null;
        if (! $attribution) {
            return null;
        }

        $waClickId = null;
        if (! empty($attribution['wa_click_id'])) {
            $waClickId = (int) $attribution['wa_click_id'];
        } elseif ($user->phone) {
            $waClickId = LeadAttribution::where('phone', self::normalisePhone($user->phone))->value('wa_click_id');
        }

        $row = UserAttribution::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_touch' => $attribution,
                'channel'     => $attribution['channel'] ?? 'direct',
                'campaign'    => $attribution['campaign'] ?? null,
                'source'      => $attribution['source'] ?? null,
                'wa_click_id' => $waClickId,
            ]
        );

        if ($waClickId) {
            WaClick::where('id', $waClickId)->whereNull('resolved_user_id')
                ->update(['resolved_user_id' => $user->id, 'resolved_at' => now()]);
        }

        return $row;
    }

    /** The attribution snapshot to freeze onto a paid matching_fee_payments row. */
    public function snapshotForPayment(User $user): ?array
    {
        $ua = UserAttribution::where('user_id', $user->id)->first();
        if (! $ua) {
            return null;
        }
        return [
            'channel'  => $ua->channel,
            'campaign' => $ua->campaign,
            'source'   => $ua->source,
            'detail'   => $ua->first_touch,
            'as_of'    => now()->toIso8601String(),
        ];
    }

    /** Normalise a client-supplied first-touch cookie payload into an attribution array. */
    public function fromCookie(array $ft): array
    {
        $refDomain = AttributionClassifier::referringDomain($ft['ref'] ?? $ft['referrer'] ?? null);
        $verdict = $this->classifier->classify([
            'utm_source'       => $ft['utm_source'] ?? null,
            'utm_medium'       => $ft['utm_medium'] ?? null,
            'utm_campaign'     => $ft['utm_campaign'] ?? null,
            'gclid'            => $ft['gclid'] ?? null,
            'fbclid'           => $ft['fbclid'] ?? null,
            'referring_domain' => $refDomain,
        ]);
        return [
            'channel'          => $verdict['channel'],
            'campaign'         => $verdict['campaign'],
            'source'           => $ft['source'] ?? 'site',
            'utm_source'       => $ft['utm_source'] ?? null,
            'utm_medium'       => $ft['utm_medium'] ?? null,
            'utm_campaign'     => $ft['utm_campaign'] ?? null,
            'utm_content'      => $ft['utm_content'] ?? null,
            'utm_term'         => $ft['utm_term'] ?? null,
            'gclid'            => $ft['gclid'] ?? null,
            'fbclid'           => $ft['fbclid'] ?? null,
            'referrer'         => $ft['ref'] ?? $ft['referrer'] ?? null,
            'referring_domain' => $refDomain,
            'landing_path'     => $ft['lp'] ?? $ft['landing_path'] ?? null,
            'first_seen_at'    => $ft['ts'] ?? now()->toIso8601String(),
        ];
    }

    private function arrayFromClick(WaClick $c): array
    {
        return [
            'channel'          => $c->channel,
            'campaign'         => $c->utm_campaign ?: ($c->referring_domain ?: null),
            'source'           => $c->source,
            'intent'           => $c->intent,
            'utm_source'       => $c->utm_source,
            'utm_medium'       => $c->utm_medium,
            'utm_campaign'     => $c->utm_campaign,
            'utm_content'      => $c->utm_content,
            'utm_term'         => $c->utm_term,
            'gclid'            => $c->gclid,
            'fbclid'           => $c->fbclid,
            'referrer'         => $c->referrer,
            'referring_domain' => $c->referring_domain,
            'landing_path'     => $c->landing_path,
            'wa_click_id'      => $c->id,
            'first_seen_at'    => optional($c->created_at)->toIso8601String(),
        ];
    }

    private function uniqueCode(): string
    {
        for ($i = 0; $i < 6; $i++) {
            $code = Str::lower(Str::random(10));
            if (! WaClick::where('code', $code)->exists()) {
                return $code;
            }
        }
        return Str::lower(Str::random(14));
    }

    private function clean(?string $v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim(strip_tags($v));
        return $v === '' ? null : Str::limit($v, $max, '');
    }
}
