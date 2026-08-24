/**
 * Directive 07 (MNG-HERO-01) — quantified social proof, not self-declared trust.
 * Falsifiability rule: every claim here must be checkable by a stranger in
 * under 10 seconds, or it doesn't ship.
 *
 * Google rating renders ONLY when live-sourced props are provided (wire the
 * Places API server-side, cache 24h, pass via Inertia). Until then we lead
 * with the strongest already-published metric.
 */
function Star({ className = 'w-3.5 h-3.5' }) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" className={className} aria-hidden="true">
            <path d="M12 2l2.9 6.26 6.85.72-5.12 4.61 1.43 6.73L12 16.9l-6.06 3.42 1.43-6.73L2.25 8.98l6.85-.72L12 2z" />
        </svg>
    );
}

export default function ProofEyebrow({ proof = {}, className = '' }) {
    const { rating, reviewCount, reviewsUrl, familiesMatched = '2,000+' } = proof;

    // One true number beats three weak ones (Directive 07).
    if (!rating || !reviewCount) {
        return (
            <p className={`text-sm font-medium tracking-wide text-white/90 ${className}`}>
                <span className="inline-flex items-center gap-1.5">
                    <Star className="w-3.5 h-3.5 text-copper-light" />
                    {familiesMatched} families helped across Nigeria
                </span>
            </p>
        );
    }

    return (
        <a
            href={reviewsUrl}
            target="_blank"
            rel="noopener noreferrer"
            className={`inline-flex items-center gap-1.5 text-sm font-medium tracking-wide text-white/90 hover:text-white transition-colors ${className}`}
        >
            <Star className="w-3.5 h-3.5 text-copper-light" />
            <span>{rating} on Google · {reviewCount} reviews · {familiesMatched}+ homes staffed</span>
        </a>
    );
}
