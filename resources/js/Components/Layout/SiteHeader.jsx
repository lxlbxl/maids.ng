import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import WhatsAppCTA from '@/Components/Hero/WhatsAppCTA';
import MenuDrawer from './MenuDrawer';

/**
 * Directive 01 (MNG-HERO-01) — the header is a conversion surface.
 * Sticky, compress-on-scroll, always carries a live path to WhatsApp.
 * "Log In" lives in the drawer on mobile; four nav links maximum.
 */
const NAV_LINKS = [
    { label: 'Find Help', href: '/maids', inertia: true },
    { label: 'How It Works', href: '/#how', inertia: false },
    { label: 'Verify a Helper', href: '/verify-service', inertia: true },
    { label: 'For Workers', href: '/?intent=work#hero', inertia: false },
];

export default function SiteHeader({ auth, intent = 'hire' }) {
    const [compressed, setCompressed] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);

    useEffect(() => {
        let ticking = false;
        const onScroll = () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => {
                setCompressed(window.scrollY > 120);
                ticking = false;
            });
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const dashboardHref = auth?.user
        ? auth.user.roles?.includes('admin')
            ? '/admin/dashboard'
            : auth.user.roles?.includes('employer')
                ? '/employer/dashboard'
                : '/maid/dashboard'
        : null;

    return (
        <>
            <header
                className={`sticky top-0 z-50 w-full bg-ivory/95 dark:bg-[#121214]/95 backdrop-blur-md border-b border-[#E5E0D8] dark:border-white/10 transition-all duration-300 ${
                    compressed ? 'h-14' : 'h-16'
                }`}
            >
                <div className="max-w-7xl mx-auto px-4 sm:px-6 h-full flex items-center justify-between gap-3">
                    {/* Left: logo */}
                    <Link href="/" className="flex-shrink-0">
                        <img
                            src="/maids-logo.png"
                            alt="Maids.ng"
                            className={`dark:brightness-0 dark:invert transition-transform duration-300 origin-left ${
                                compressed ? 'h-7 scale-[0.85]' : 'h-8'
                            }`}
                        />
                    </Link>

                    {/* Desktop nav */}
                    <nav className="hidden lg:flex items-center gap-7">
                        {NAV_LINKS.map((l) =>
                            l.inertia ? (
                                <Link key={l.label} href={l.href} className="text-sm text-gray-600 dark:text-gray-300 hover:text-teal transition-colors">
                                    {l.label}
                                </Link>
                            ) : (
                                <a key={l.label} href={l.href} className="text-sm text-gray-600 dark:text-gray-300 hover:text-teal transition-colors">
                                    {l.label}
                                </a>
                            )
                        )}
                    </nav>

                    {/* Right cluster */}
                    <div className="flex items-center gap-3">
                        {/* Desktop: Log In / Dashboard as quiet text link */}
                        {dashboardHref ? (
                            <Link href={dashboardHref} className="hidden lg:block text-sm text-teal font-medium hover:text-teal-dark transition-colors">
                                My Dashboard
                            </Link>
                        ) : (
                            <Link href="/login" className="hidden lg:block text-sm text-gray-600 dark:text-gray-300 hover:text-teal transition-colors">
                                Log In
                            </Link>
                        )}

                        {/* The header CTA — replaces "Get Started" everywhere.
                            Speed promise travels with the button (desktop). */}
                        <div className="flex flex-col items-center">
                            <WhatsAppCTA source="header" intent={intent} size="sm" label="WhatsApp Us" glyphClass="w-4 h-4" />
                            <span className="hidden lg:block text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 leading-none">
                                Replies in minutes
                            </span>
                        </div>

                        {/* Hamburger — 44×44 tap target */}
                        <button
                            type="button"
                            aria-label="Open menu"
                            aria-expanded={drawerOpen}
                            onClick={() => setDrawerOpen(true)}
                            className="lg:hidden w-11 h-11 flex items-center justify-center rounded-brand-md text-espresso dark:text-white hover:bg-black/5 dark:hover:bg-white/10 transition-colors"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" className="w-6 h-6" aria-hidden="true">
                                <path d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </header>

            <MenuDrawer open={drawerOpen} onClose={() => setDrawerOpen(false)} auth={auth} intent={intent} dashboardHref={dashboardHref} />
        </>
    );
}
