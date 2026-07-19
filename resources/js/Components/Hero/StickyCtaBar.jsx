import { useEffect, useState } from 'react';
import WhatsAppCTA from './WhatsAppCTA';

/**
 * Directive 10 (MNG-HERO-01) — mobile sticky CTA bar.
 * Slides in when the hero primary CTA scrolls out of view
 * (IntersectionObserver on #hero); thumb-zone conversion insurance.
 * Fires source=sticky_bar. Respects prefers-reduced-motion.
 */
export default function StickyCtaBar({ intent = 'hire' }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const hero = document.getElementById('hero');
        // Direct check — setVisible with an unchanged value is a no-op render,
        // so an unthrottled passive listener stays cheap. Pages without a hero
        // show the bar after a modest scroll instead.
        const onScroll = () => setVisible(
            hero ? hero.getBoundingClientRect().bottom <= 0 : window.scrollY > 320
        );
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <div
            className={`fixed bottom-0 inset-x-0 z-40 lg:hidden print:hidden transition-transform duration-300 ease-brand motion-reduce:transition-none ${
                visible ? 'translate-y-0' : 'translate-y-full'
            }`}
            aria-hidden={!visible}
        >
            <div className="bg-ivory/95 dark:bg-[#121214]/95 backdrop-blur-md border-t border-[#E5E0D8] dark:border-white/10 px-4 py-3 flex items-center justify-between gap-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                <p className="text-xs text-gray-600 dark:text-gray-300 leading-tight">
                    <span className="font-semibold text-espresso dark:text-white">Replies in minutes</span>
                    <br />7am–10pm, every day
                </p>
                <WhatsAppCTA
                    source="sticky_bar"
                    intent={intent}
                    size="md"
                    label={intent === 'work' ? 'Apply on WhatsApp' : 'Chat on WhatsApp'}
                    className="flex-shrink-0"
                />
            </div>
        </div>
    );
}
