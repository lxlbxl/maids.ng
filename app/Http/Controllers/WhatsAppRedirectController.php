<?php

namespace App\Http\Controllers;

use App\Services\Attribution\AttributionService;
use App\Services\PostHog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Directive 09 (MNG-HERO-01): every WhatsApp CTA site-wide routes through
 * /wa/{source} so attribution travels into the conversation and the funnel is
 * measurable end-to-end.
 *
 * Sources: header | menu_drawer | hero_primary | sticky_bar | footer
 * Intent:  hire (default) | work
 *
 * The client (resources/js/lib/attribution.js) appends first-touch signals as
 * query params (utm_*, gclid, fbclid, ref, lp, ph). We record a wa_clicks row
 * and carry only its short `code` in the prefilled message.
 */
class WhatsAppRedirectController extends Controller
{
    public function __invoke(Request $request, string $source, AttributionService $attribution, PostHog $posthog)
    {
        $source = substr(preg_replace('/[^a-z0-9_\-]/', '', strtolower($source)), 0, 32);
        $intent = $request->query('intent') === 'work' ? 'work' : 'hire';

        $click = null;
        try {
            $click = $attribution->recordClick([
                'source'              => $source,
                'intent'              => $intent,
                'utm_source'          => $request->query('utm_source'),
                'utm_medium'          => $request->query('utm_medium'),
                'utm_campaign'        => $request->query('utm_campaign'),
                'utm_content'         => $request->query('utm_content'),
                'utm_term'            => $request->query('utm_term'),
                'gclid'               => $request->query('gclid'),
                'fbclid'              => $request->query('fbclid'),
                'referrer'            => $request->query('ref') ?: $request->headers->get('referer'),
                'landing_path'        => $request->query('lp'),
                'ip'                  => $request->ip(),
                'user_agent'          => (string) $request->userAgent(),
                'posthog_distinct_id' => $request->query('ph'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('wa redirect: click record failed', ['error' => $e->getMessage()]);
        }

        $tag = $click ? "[src:{$source}/{$click->code}]" : "[src:{$source}]";

        $msg = match ($intent) {
            'work'  => "Hi! I'm looking for work as a helper. {$tag}",
            default => "Hi! I'd like to hire a vetted helper. {$tag}",
        };

        Log::channel('single')->info('whatsapp_cta_tapped', [
            'source'  => $source,
            'intent'  => $intent,
            'channel' => $click?->channel,
            'code'    => $click?->code,
            'referer' => $request->headers->get('referer'),
        ]);

        // Meta CAPI — "Contact": someone tapped a WhatsApp CTA. Intent-to-reach-out,
        // NOT a Lead (no name/purpose yet). Qualified Lead fires when the agent
        // creates a user from the conversation.
        app(\App\Services\MetaCapi::class)->contact(
            'contact_wa_' . substr(md5($request->ip() . $request->userAgent() . now()->format('YmdH')), 0, 16),
            [],
            array_filter([
                'content_name' => 'WhatsApp CTA',
                'source'       => $source,
                'intent'       => $intent,
                'channel'      => $click?->channel,
                'campaign'     => $click?->utm_campaign,
            ]),
            $request,
            'chat',
        );

        // PostHog server-side mirror (client also fires whatsapp_cta_tapped).
        if ($ph = $request->query('ph')) {
            $posthog->capture($ph, 'wa_cta_tapped_server', array_filter([
                'source'   => $source,
                'intent'   => $intent,
                'channel'  => $click?->channel,
                'campaign' => $click?->utm_campaign,
                'wa_click_code' => $click?->code,
            ]));
        }

        $number = config('services.whatsapp.number');

        if (! $number) {
            return redirect($intent === 'work' ? '/register/maid' : '/onboarding');
        }

        return redirect()->away('https://wa.me/' . $number . '?text=' . urlencode($msg));
    }
}
