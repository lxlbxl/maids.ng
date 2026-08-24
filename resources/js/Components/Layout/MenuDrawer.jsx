import { useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import WhatsAppCTA from '@/Components/Hero/WhatsAppCTA';

/**
 * Directive 01 (MNG-HERO-01) — mobile menu drawer.
 * Items ordered by intent, not sitemap logic. WhatsApp first, Log In last,
 * trust strip at the bottom. Slides from right, 85% width, 40% ink scrim.
 * Closes on scrim tap, Esc, and route change.
 */
export default function MenuDrawer({ open, onClose, auth, intent = 'hire', dashboardHref }) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        // Close on route change
        const off = router.on('navigate', onClose);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            off();
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    return (
        <div className={`fixed inset-0 z-[60] lg:hidden ${open ? '' : 'pointer-events-none'}`} aria-hidden={!open}>
            {/* Scrim — 40% ink */}
            <div
                onClick={onClose}
                className={`absolute inset-0 transition-opacity duration-300 motion-reduce:transition-none ${open ? 'opacity-100' : 'opacity-0'}`}
                style={{ backgroundColor: 'rgba(6,31,29,0.4)' }}
            />

            {/* Panel — 85% width, slides from right */}
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Menu"
                className={`absolute top-0 right-0 h-full w-[85%] max-w-sm bg-ivory dark:bg-[#18181a] shadow-brand-3 flex flex-col transition-transform duration-300 ease-brand motion-reduce:transition-none ${
                    open ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                <div className="flex items-center justify-between px-5 h-16 border-b border-[#E5E0D8] dark:border-white/10">
                    <img src="/maids-logo.png" alt="Maids.ng" className="h-7 dark:brightness-0 dark:invert" />
                    <button
                        type="button"
                        aria-label="Close menu"
                        onClick={onClose}
                        className="w-11 h-11 flex items-center justify-center rounded-brand-md text-espresso dark:text-white hover:bg-black/5 dark:hover:bg-white/10"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" className="w-6 h-6" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>

                <nav className="flex-1 overflow-y-auto px-5 py-6 flex flex-col gap-1">
                    {/* 1 — WhatsApp, full width, top of drawer */}
                    <WhatsAppCTA source="menu_drawer" intent={intent} size="md" label="WhatsApp Us" className="w-full mb-4" />

                    <Link href="/maids" className="py-3 text-espresso dark:text-white font-medium hover:text-teal transition-colors">Find Help</Link>
                    <a href="/#how" onClick={onClose} className="py-3 text-espresso dark:text-white font-medium hover:text-teal transition-colors">How It Works</a>
                    <Link href="/verify-service" className="py-3 text-espresso dark:text-white font-medium hover:text-teal transition-colors">Verify a Helper</Link>

                    <div className="border-t border-[#E5E0D8] dark:border-white/10 my-2" />
                    <a href="/?intent=work#hero" onClick={onClose} className="py-3 text-espresso dark:text-white font-medium hover:text-copper transition-colors">For Workers</a>

                    {dashboardHref ? (
                        <Link href={dashboardHref} className="py-3 text-teal font-medium">My Dashboard</Link>
                    ) : (
                        <Link href="/login" className="py-3 text-gray-500 dark:text-gray-400 font-medium hover:text-teal transition-colors">Log In</Link>
                    )}
                </nav>

                {/* Trust strip — strongest true metric (Directive 07) */}
                <div className="px-5 py-4 border-t border-[#E5E0D8] dark:border-white/10 text-sm text-gray-600 dark:text-gray-400">
                    <span className="inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 24 24" fill="currentColor" className="w-3.5 h-3.5 text-copper" aria-hidden="true">
                            <path d="M12 2l2.9 6.26 6.85.72-5.12 4.61 1.43 6.73L12 16.9l-6.06 3.42 1.43-6.73L2.25 8.98l6.85-.72L12 2z" />
                        </svg>
                        2,000+ families helped across Nigeria
                    </span>
                </div>
            </div>
        </div>
    );
}
