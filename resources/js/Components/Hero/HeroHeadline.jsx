/**
 * Directive 03 (MNG-HERO-01) — copy system, exact text.
 * Variant A (speed-led) is the default; Variant B is the A/B challenger.
 * Headline names the noun, the state and the clock.
 */
export default function HeroHeadline({ intent = 'hire', variant = 'A' }) {
    if (intent === 'work') {
        return (
            <h1 className="font-display font-bold text-white leading-[1.05] text-[40px] sm:text-5xl lg:text-6xl" style={{ textShadow: '0 1px 12px rgba(6,31,29,0.35)' }}>
                Steady Work. Fair Pay.<br />
                <em className="italic">Real Homes.</em>
            </h1>
        );
    }

    if (variant === 'B') {
        return (
            <h1 className="font-display font-bold text-white leading-[1.05] text-[40px] sm:text-5xl lg:text-6xl" style={{ textShadow: '0 1px 12px rgba(6,31,29,0.35)' }}>
                Your Home, Sorted.<br />
                <em className="italic">Vetted Help in Days.</em>
            </h1>
        );
    }

    return (
        <h1 className="font-display font-bold text-white leading-[1.05] text-[40px] sm:text-5xl lg:text-6xl" style={{ textShadow: '0 1px 12px rgba(6,31,29,0.35)' }}>
            A Vetted Helper in<br />
            Your Home <em className="italic">This Week.</em>
        </h1>
    );
}
