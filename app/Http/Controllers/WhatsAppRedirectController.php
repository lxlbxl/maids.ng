<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Directive 09 (MNG-HERO-01): every WhatsApp CTA site-wide routes through
 * /wa/{source} so attribution ([src:] tag) travels into the conversation
 * and the funnel is measurable end-to-end.
 *
 * Sources: header | menu_drawer | hero_primary | sticky_bar | footer
 * Intent:  hire (default) | work
 */
class WhatsAppRedirectController extends Controller
{
    public function __invoke(Request $request, string $source)
    {
        $source = substr(preg_replace('/[^a-z0-9_\-]/', '', strtolower($source)), 0, 32);
        $intent = $request->query('intent') === 'work' ? 'work' : 'hire';

        $msg = match ($intent) {
            'work'  => "Hi! I'm looking for work as a helper. [src:{$source}]",
            default => "Hi! I'd like to hire a vetted helper. [src:{$source}]",
        };

        Log::channel('single')->info('whatsapp_cta_tapped', [
            'source' => $source,
            'intent' => $intent,
            'referer' => $request->headers->get('referer'),
        ]);

        $number = config('services.whatsapp.number');

        if (! $number) {
            // No WhatsApp number configured — fall back to the native funnel.
            return redirect($intent === 'work' ? '/register/maid' : '/onboarding');
        }

        return redirect()->away('https://wa.me/' . $number . '?text=' . urlencode($msg));
    }
}
