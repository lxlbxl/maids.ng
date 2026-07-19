import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import HeroMedia from './HeroMedia';
import AudienceToggle, { readAudienceCookie } from './AudienceToggle';
import ProofEyebrow from './ProofEyebrow';
import HeroHeadline from './HeroHeadline';
import WhatsAppCTA from './WhatsAppCTA';
import TrustPills from './TrustPills';

/**
 * Directive 02 + 06 (MNG-HERO-01) — first-viewport orchestrator.
 * Order: toggle → proof eyebrow → headline → subhead w/ guarantee →
 * WhatsApp CTA + reply badge → ghost secondary. All above the fold.
 *
 * Layer stack: 0 photo · 1 video (HeroMedia) · 2 scrim · 3 text.
 * Text lives in the bottom-60% safe zone; media subject occupies the top.
 */

const COPY = {
    hire: {
        subhead: "NIN-verified housekeepers, nannies and cooks — matched to your exact needs on WhatsApp. Not a great fit in 10 days? We replace her. Free.",
        ctaLabel: 'Chat on WhatsApp',
        secondary: { label: 'See Available Helpers →', href: '/maids' },
    },
    work: {
        subhead: "Verified families, agreed salaries, and a team that makes sure you're paid on time — every month. Registration is free, and we never take a cut.",
        ctaLabel: 'Apply on WhatsApp',
        secondary: { label: 'How It Works for Helpers →', href: '#for-workers' },
    },
};

export default function HeroSection({ proof = {}, variant = 'A', intent, onIntentChange }) {
    const copy = COPY[intent] ?? COPY.hire;

    return (
        <section id="hero" className="relative overflow-hidden" style={{ minHeight: 'calc(100svh - 4rem)' }}>
            {/* z-0 / z-1 — media layers */}
            <HeroMedia intent={intent} />

            {/* z-2 — scrim: applied once to the container, identical over photo
                and video, tunable in CSS without a re-export (Directive 06) */}
            <div
                className="absolute inset-0 z-[2] pointer-events-none"
                style={{
                    background: 'linear-gradient(180deg, rgba(6,31,29,0.00) 20%, rgba(6,31,29,0.55) 55%, rgba(6,31,29,0.88) 100%)',
                }}
            />

            {/* z-3 — text layer. Geometry computed from the container, never
                from media dimensions: cross-fade cannot cause reflow. */}
            <div className="relative z-[3] flex flex-col min-h-[calc(100svh-4rem)] max-w-7xl mx-auto px-5 pt-6 pb-10">
                {/* Toggle sits at the top of the content stack */}
                <div className="flex justify-center">
                    <AudienceToggle value={intent} onChange={onIntentChange} />
                </div>

                {/* Safe zone: bottom 60% — text block center on mobile,
                    left-aligned 6-of-12 columns on desktop */}
                <div className="flex-1 flex flex-col justify-end">
                    <div className="w-full text-center lg:text-left lg:max-w-[50%] flex flex-col items-center lg:items-start gap-5">
                        <ProofEyebrow proof={proof} />

                        <HeroHeadline intent={intent} variant={variant} />

                        <p
                            className="text-base leading-relaxed text-[#F5F2EC]/95 max-w-[34ch] sm:max-w-md"
                            style={{ textShadow: '0 1px 12px rgba(6,31,29,0.35)' }}
                        >
                            {copy.subhead}
                        </p>

                        <WhatsAppCTA
                            source="hero_primary"
                            intent={intent}
                            size="lg"
                            label={copy.ctaLabel}
                            badge="We reply in minutes, 7am–10pm"
                            className="min-w-[280px]"
                            glyphClass="w-6 h-6"
                        />

                        {copy.secondary.href.startsWith('#') ? (
                            <a
                                href={copy.secondary.href}
                                className="inline-flex items-center px-6 py-2.5 rounded-full border border-white/35 text-white/90 text-sm font-medium hover:bg-white/10 transition-all"
                            >
                                {copy.secondary.label}
                            </a>
                        ) : (
                            <Link
                                href={copy.secondary.href}
                                className="inline-flex items-center px-6 py-2.5 rounded-full border border-white/35 text-white/90 text-sm font-medium hover:bg-white/10 transition-all"
                            >
                                {copy.secondary.label}
                            </Link>
                        )}

                        {/* Trust pills — hidden on short viewports so the CTA
                            never drops below the fold (iPhone SE rule) */}
                        <TrustPills className="hidden sm:flex mt-1" />
                    </div>
                </div>
            </div>
        </section>
    );
}

/**
 * Shared intent hook — cookie-persisted, deep-linkable via ?intent=work
 * (the "For Workers" nav link uses it). Lives here so Welcome, SiteHeader
 * and StickyCtaBar all read one source of truth.
 */
export function useAudienceIntent() {
    const [intent, setIntent] = useState('hire');

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const fromUrl = params.get('intent');
        if (fromUrl === 'work' || fromUrl === 'hire') {
            setIntent(fromUrl);
            return;
        }
        const fromCookie = readAudienceCookie();
        if (fromCookie) setIntent(fromCookie);
    }, []);

    return [intent, setIntent];
}
