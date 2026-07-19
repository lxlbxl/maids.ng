import { useEffect, useRef, useState } from 'react';

/**
 * Directive 05 + 06 (MNG-HERO-01) — photo base layer + conditional video
 * enhancement. The photo is ALWAYS the LCP; no video markup exists in the
 * initial HTML. Video injects only after window load + idle, and only when
 * every gate passes. Cross-fade fires on `canplaythrough`, aborts at 4s.
 *
 * Layer stack (z-index): 0 photo · 1 video · scrim and text live above,
 * owned by HeroSection.
 */

const MEDIA = {
    hire: {
        mobile: {
            avif: '/media/hero/hero-mobile-480.avif 480w, /media/hero/hero-mobile-768.avif 768w',
            webp: '/media/hero/hero-mobile-480.webp 480w, /media/hero/hero-mobile-768.webp 768w',
            fallback: '/media/hero/hero-mobile-768.jpg',
        },
        desktop: {
            avif: '/media/hero/hero-desktop-828.avif 828w, /media/hero/hero-desktop-1200.avif 1200w',
            webp: '/media/hero/hero-desktop-828.webp 828w, /media/hero/hero-desktop-1200.webp 1200w',
            fallback: '/media/hero/hero-desktop-1200.jpg',
        },
        alt: 'A uniformed Maids.ng helper setting the dining table in a bright Nigerian family home',
    },
    // Work-side media: same shoot until a dedicated onboarding photo exists
    // (Directive 09 — work-side visuals are the lowest-priority asset).
    work: {
        mobile: {
            avif: '/media/hero/hero-mobile-480.avif 480w, /media/hero/hero-mobile-768.avif 768w',
            webp: '/media/hero/hero-mobile-480.webp 480w, /media/hero/hero-mobile-768.webp 768w',
            fallback: '/media/hero/hero-mobile-768.jpg',
        },
        desktop: {
            avif: '/media/hero/hero-desktop-828.avif 828w, /media/hero/hero-desktop-1200.avif 1200w',
            webp: '/media/hero/hero-desktop-828.webp 828w, /media/hero/hero-desktop-1200.webp 1200w',
            fallback: '/media/hero/hero-desktop-1200.jpg',
        },
        alt: 'A uniformed Maids.ng helper at work in a bright Nigerian family home',
    },
};

function passesGate(container) {
    if (typeof window === 'undefined') return false;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false;
    if (document.visibilityState !== 'visible') return false;

    // User hasn't scrolled past the hero
    if (container) {
        const rect = container.getBoundingClientRect();
        if (rect.bottom <= 0) return false;
    }

    const conn = navigator.connection;
    if (conn) {
        if (conn.effectiveType !== '4g' || conn.saveData === true) return false;
        return true;
    }
    // navigator.connection unavailable (Safari/iOS): pass ONLY on desktop
    // viewports; mobile Safari gets photo only.
    return window.innerWidth >= 1024;
}

export default function HeroMedia({ intent = 'hire' }) {
    const media = MEDIA[intent] ?? MEDIA.hire;
    const containerRef = useRef(null);
    const videoRef = useRef(null);
    const [videoAllowed, setVideoAllowed] = useState(false);
    const [videoVisible, setVideoVisible] = useState(false);

    // Step 1–2: after window load + idle, run the gate. A tab loaded in the
    // background retries once it becomes visible (visibility is a "not yet",
    // the other gates are a "no").
    useEffect(() => {
        let cancelled = false;

        const attempt = () => {
            if (cancelled) return;
            if (document.visibilityState !== 'visible') {
                document.addEventListener('visibilitychange', onVisible);
                return;
            }
            if (passesGate(containerRef.current)) setVideoAllowed(true);
        };
        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                document.removeEventListener('visibilitychange', onVisible);
                attempt();
            }
        };
        const run = () => {
            // timeout guards against rIC being throttled indefinitely on
            // occluded/background tabs
            if (window.requestIdleCallback) window.requestIdleCallback(attempt, { timeout: 2500 });
            else setTimeout(attempt, 2000);
        };

        if (document.readyState === 'complete') run();
        else window.addEventListener('load', run, { once: true });

        return () => {
            cancelled = true;
            window.removeEventListener('load', run);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, []);

    // Steps 3–5: inject, cross-fade on canplaythrough, abort at 4s,
    // pause on hidden / scroll-out.
    useEffect(() => {
        if (!videoAllowed) return;
        const video = videoRef.current;
        if (!video) return;

        let done = false;
        const abortTimer = setTimeout(() => {
            if (done) return;
            done = true;
            // Stalled — remove, remain on photo, no retry.
            setVideoAllowed(false);
            window.posthog?.capture?.('hero_video_aborted', {
                connection: navigator.connection?.effectiveType ?? 'unknown',
                viewport: window.innerWidth,
            });
        }, 4000);

        const onReady = () => {
            if (done) return;
            done = true;
            clearTimeout(abortTimer);
            setVideoVisible(true);
            video.play?.().catch(() => {});
            window.posthog?.capture?.('hero_video_played', {
                connection: navigator.connection?.effectiveType ?? 'unknown',
                viewport: window.innerWidth,
            });
        };
        video.addEventListener('canplaythrough', onReady, { once: true });
        video.load();

        const onVisibility = () => {
            if (document.visibilityState === 'hidden') video.pause();
            else if (done && videoRef.current) video.play?.().catch(() => {});
        };
        document.addEventListener('visibilitychange', onVisibility);

        const io = new IntersectionObserver(([entry]) => {
            if (!entry.isIntersecting) video.pause();
            else if (done) video.play?.().catch(() => {});
        }, { threshold: 0 });
        if (containerRef.current) io.observe(containerRef.current);

        return () => {
            clearTimeout(abortTimer);
            video.removeEventListener('canplaythrough', onReady);
            document.removeEventListener('visibilitychange', onVisibility);
            io.disconnect();
        };
    }, [videoAllowed]);

    return (
        <div ref={containerRef} className="absolute inset-0 overflow-hidden" aria-hidden={videoVisible ? 'true' : undefined}>
            {/* z-0 — photo, always present, always the LCP */}
            <picture>
                {/* Mobile: portrait art direction (subject top, calm bottom 60%) */}
                <source media="(max-width: 767px)" type="image/avif" srcSet={media.mobile.avif} sizes="100vw" />
                <source media="(max-width: 767px)" type="image/webp" srcSet={media.mobile.webp} sizes="100vw" />
                {/* Desktop: landscape (subject right, calm left) */}
                <source type="image/avif" srcSet={media.desktop.avif} sizes="100vw" />
                <source type="image/webp" srcSet={media.desktop.webp} sizes="100vw" />
                <img
                    src={media.desktop.fallback}
                    alt={media.alt}
                    fetchpriority="high"
                    decoding="async"
                    className="absolute inset-0 w-full h-full object-cover object-[70%_20%] md:object-[right_top]"
                />
            </picture>

            {/* z-1 — video, conditional, cross-fades in. Poster = hero photo so
                even the video element's first paint matches the base layer. */}
            {videoAllowed && (
                <video
                    ref={videoRef}
                    muted
                    loop
                    playsInline
                    preload="none"
                    poster={media.desktop.fallback}
                    aria-hidden="true"
                    className="absolute inset-0 w-full h-full object-cover object-[70%_20%] md:object-[right_top] transition-opacity duration-[600ms] ease-linear"
                    style={{ opacity: videoVisible ? 1 : 0 }}
                >
                    <source src="/media/hero/hero-480.webm" type='video/webm; codecs="av01.0.05M.08"' media="(max-width: 1023px)" />
                    <source src="/media/hero/hero-720.webm" type='video/webm; codecs="av01.0.05M.08"' />
                    <source src="/media/hero/hero-480.mp4" type="video/mp4" media="(max-width: 1023px)" />
                    <source src="/media/hero/hero-720.mp4" type="video/mp4" />
                </video>
            )}
        </div>
    );
}
