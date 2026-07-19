/**
 * Directive 09 (MNG-HERO-01) — hire | work fork, cookie-persisted, SVG icons.
 * Two-sided marketplace toggle: a structural advantage — restyled, never removed.
 */
const COOKIE = 'mng_audience';

export function readAudienceCookie() {
    if (typeof document === 'undefined') return null;
    const m = document.cookie.match(new RegExp(`(?:^|; )${COOKIE}=(hire|work)`));
    return m ? m[1] : null;
}

export function writeAudienceCookie(value) {
    if (typeof document === 'undefined') return;
    document.cookie = `${COOKIE}=${value}; path=/; max-age=${60 * 60 * 24 * 180}; SameSite=Lax`;
}

function HomeIcon({ className = 'w-4 h-4' }) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
            <path d="M3 10.5 12 3l9 7.5" />
            <path d="M5 9.5V21h14V9.5" />
            <path d="M9.5 21v-6h5v6" />
        </svg>
    );
}

function BriefcaseIcon({ className = 'w-4 h-4' }) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
            <rect x="3" y="7.5" width="18" height="13" rx="2" />
            <path d="M8.5 7.5V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v1.5" />
            <path d="M3 13h18" />
        </svg>
    );
}

export default function AudienceToggle({ value, onChange }) {
    const setValue = (v) => {
        writeAudienceCookie(v);
        onChange(v);
    };

    const base = 'inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-medium transition-all duration-300';

    return (
        <div
            role="tablist"
            aria-label="I want to hire help or I'm looking for work"
            className="inline-flex items-center gap-1 p-1 rounded-full border border-white/25 backdrop-blur-md"
            style={{ backgroundColor: 'rgba(6,31,29,0.35)' }}
        >
            <button
                id="toggle-hiring"
                role="tab"
                aria-selected={value === 'hire'}
                onClick={() => setValue('hire')}
                className={`${base} ${value === 'hire' ? 'bg-teal text-white shadow-lg' : 'text-white/85 hover:text-white'}`}
            >
                <HomeIcon />
                <span>I Want to Hire Help</span>
            </button>
            <button
                id="toggle-working"
                role="tab"
                aria-selected={value === 'work'}
                onClick={() => setValue('work')}
                className={`${base} ${value === 'work' ? 'bg-copper-light text-espresso shadow-lg' : 'text-white/85 hover:text-white'}`}
            >
                <BriefcaseIcon />
                <span>I'm Looking for Work</span>
            </button>
        </div>
    );
}
