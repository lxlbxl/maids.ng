<?php

namespace App\Services\Attribution;

/**
 * Turns raw first-touch signals (utm params, gclid/fbclid, referrer) into a
 * single `channel` bucket plus a best-effort `campaign` label. Pure — no I/O.
 *
 * Channels: google_ads | organic_search | meta_paid | meta_organic | paid_other
 *         | email | referral | other | direct | meta_ctwa | whatsapp_direct
 */
class AttributionClassifier
{
    private const SEARCH_DOMAINS = ['google.', 'bing.', 'duckduckgo.', 'yahoo.', 'ecosia.', 'yandex.', 'baidu.', 'ask.com'];
    private const META_DOMAINS   = ['facebook.', 'fb.com', 'instagram.', 'l.facebook.', 'lm.facebook.', 'm.facebook.'];
    private const MAIL_DOMAINS   = ['mail.google.', 'outlook.', 'mail.yahoo.', 'mail.'];
    private const OWN_DOMAINS    = ['maids.ng'];
    private const PAID_MEDIUMS   = ['cpc', 'ppc', 'paid', 'paidsearch', 'paid-search', 'display', 'cpm', 'social-paid', 'paid-social', 'paidsocial'];

    /**
     * @param  array  $ctx  keys: utm_source, utm_medium, utm_campaign, gclid, fbclid, referring_domain
     * @return array{channel:string, campaign:?string}
     */
    public function classify(array $ctx): array
    {
        $utmSource   = strtolower(trim((string) ($ctx['utm_source'] ?? '')));
        $utmMedium   = strtolower(trim((string) ($ctx['utm_medium'] ?? '')));
        $utmCampaign = trim((string) ($ctx['utm_campaign'] ?? '')) ?: null;
        $gclid       = trim((string) ($ctx['gclid'] ?? ''));
        $fbclid      = trim((string) ($ctx['fbclid'] ?? ''));
        $refDomain   = strtolower(trim((string) ($ctx['referring_domain'] ?? '')));

        $isRef = fn (array $needles) => $refDomain !== '' && array_filter($needles, fn ($n) => str_contains($refDomain, $n));

        // 1. Google Ads — an explicit click id is the strongest signal.
        if ($gclid !== '') {
            return ['channel' => 'google_ads', 'campaign' => $utmCampaign];
        }

        // 2. Meta — fbclid or a facebook/instagram referrer.
        if ($fbclid !== '') {
            return ['channel' => 'meta_paid', 'campaign' => $utmCampaign];
        }
        if ($isRef(self::META_DOMAINS)) {
            $paid = in_array($utmMedium, self::PAID_MEDIUMS, true);
            return ['channel' => $paid ? 'meta_paid' : 'meta_organic', 'campaign' => $utmCampaign];
        }

        // 3. Any other explicitly-paid medium.
        if (in_array($utmMedium, self::PAID_MEDIUMS, true)) {
            return ['channel' => 'paid_other', 'campaign' => $utmCampaign ?: ($utmSource ?: null)];
        }

        // 4. Organic search.
        if ($isRef(self::SEARCH_DOMAINS) || in_array($utmMedium, ['organic', 'seo'], true)) {
            return ['channel' => 'organic_search', 'campaign' => $utmCampaign];
        }

        // 5. Email.
        if ($utmMedium === 'email' || $isRef(self::MAIL_DOMAINS)) {
            return ['channel' => 'email', 'campaign' => $utmCampaign ?: ($utmSource ?: null)];
        }

        // 6. Some other external referrer.
        if ($refDomain !== '' && ! $isRef(self::OWN_DOMAINS)) {
            return ['channel' => 'referral', 'campaign' => $refDomain];
        }

        // 7. UTM-tagged but nothing above matched.
        if ($utmSource !== '' || $utmMedium !== '') {
            return ['channel' => 'other', 'campaign' => $utmCampaign ?: trim("$utmSource / $utmMedium", ' /')];
        }

        // 8. Nothing at all.
        return ['channel' => 'direct', 'campaign' => null];
    }

    public static function referringDomain(?string $referrer): ?string
    {
        if (! $referrer) {
            return null;
        }
        $host = parse_url($referrer, PHP_URL_HOST);
        return $host ? strtolower(preg_replace('/^www\./', '', $host)) : null;
    }

    /** Human label for the Paperclip issue comment. */
    public static function label(string $channel): string
    {
        return [
            'google_ads'      => 'Google Ads',
            'organic_search'  => 'Organic search',
            'meta_paid'       => 'Meta ads',
            'meta_organic'    => 'Facebook / Instagram (organic)',
            'meta_ctwa'       => 'Meta ad (Click-to-WhatsApp)',
            'paid_other'      => 'Paid (other)',
            'email'           => 'Email',
            'referral'        => 'Referral',
            'whatsapp_direct' => 'WhatsApp direct',
            'other'           => 'Other',
            'direct'          => 'Direct',
        ][$channel] ?? ucfirst(str_replace('_', ' ', $channel));
    }
}
