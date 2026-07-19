import { usePage } from '@inertiajs/react';
import SiteHeader from '@/Components/Layout/SiteHeader';
import SiteFooter from '@/Components/Layout/SiteFooter';
import StickyCtaBar from '@/Components/Hero/StickyCtaBar';

/**
 * Shared public-page shell: Directive 01 header (sticky, WhatsApp CTA),
 * mobile sticky CTA bar, and the shared footer. Every public page except
 * the quiz funnel wraps its content in this layout so the conversion
 * surface is uniform site-wide.
 *
 * Props:
 *  - footer:    render SiteFooter (default true; pages with bespoke endings can opt out)
 *  - stickyCta: render the mobile bottom bar (default true; auth forms opt out)
 *  - intent:    'hire' | 'work' — forwarded to every WhatsApp CTA
 */
export default function PublicLayout({ children, footer = true, stickyCta = true, intent = 'hire' }) {
    const { auth } = usePage().props;

    return (
        <>
            <SiteHeader auth={auth} intent={intent} />
            {children}
            {footer && <SiteFooter intent={intent} />}
            {stickyCta && <StickyCtaBar intent={intent} />}
        </>
    );
}
