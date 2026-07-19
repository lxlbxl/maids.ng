/**
 * Directive 08 (MNG-HERO-01) — outcome language, zero emoji.
 * Consistent 1.5px-stroke SVGs; copy speaks to the Nigerian fear stack.
 */
const stroke = { fill: 'none', stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round', strokeLinejoin: 'round' };

function ShieldCheck({ className }) {
    return (
        <svg viewBox="0 0 24 24" {...stroke} className={className} aria-hidden="true">
            <path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6l7-3z" />
            <path d="M9 12l2 2 4-4" />
        </svg>
    );
}

function Clock({ className }) {
    return (
        <svg viewBox="0 0 24 24" {...stroke} className={className} aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
        </svg>
    );
}

function Refresh({ className }) {
    return (
        <svg viewBox="0 0 24 24" {...stroke} className={className} aria-hidden="true">
            <path d="M20 11a8 8 0 1 0-2.34 6.34" />
            <path d="M20 5v6h-6" />
        </svg>
    );
}

const PILLS = [
    { Icon: ShieldCheck, text: 'NIN + Guarantor Verified' },
    { Icon: Clock, text: 'Matched to Your Home in Days' },
    { Icon: Refresh, text: 'Free Replacement, 10 Days' },
];

export default function TrustPills({ className = '' }) {
    return (
        <div className={`flex flex-wrap justify-center lg:justify-start gap-2.5 ${className}`}>
            {PILLS.map(({ Icon, text }) => (
                <div
                    key={text}
                    className="flex items-center gap-2 px-3.5 py-1.5 bg-white/10 border border-white/20 rounded-full text-[13px] text-white/90 backdrop-blur-sm"
                >
                    <Icon className="w-4 h-4 text-teal-light" />
                    <span>{text}</span>
                </div>
            ))}
        </div>
    );
}
