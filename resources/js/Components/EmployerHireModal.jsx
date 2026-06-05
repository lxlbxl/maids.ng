import { useState, useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';

/**
 * EmployerHireModal — Streamlined hiring modal for LOGGED-IN employers.
 *
 * Unlike DirectHireModal (which handles guests + auth), this is exclusively
 * for the Employer Portal and skips contact-info collection entirely.
 *
 * Flow:
 *   Step 0 — Location confirmation (pre-filled from employer profile if available)
 *   Step 1 — Review & Confirm → triggers /onboarding/direct-hire
 *
 * Props:
 *   maid         — { id, name, avatar, role, location, rate, availability_status, verified }
 *   onClose      — fn to close the modal
 *   preferenceId — optional: existing preference ID to link the hire to
 */
export default function EmployerHireModal({ maid, onClose, preferenceId }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    const [step, setStep] = useState(0); // 0 = location, 1 = confirm
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [location, setLocation] = useState(user?.location || '');
    const [startDate, setStartDate] = useState('');

    const overlayRef = useRef(null);
    const firstName = maid?.name?.split(' ')[0] || 'this helper';
    const isUnavailable = maid?.availability_status && maid.availability_status !== 'available';

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Close on overlay click
    const handleOverlayClick = (e) => {
        if (e.target === overlayRef.current) onClose();
    };

    // Close on Escape
    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [onClose]);

    const isLocationValid = () => location.trim().length >= 3;

    const handleNext = () => {
        if (!isLocationValid()) {
            setError('Please enter your location (e.g. Lekki, Lagos).');
            return;
        }
        setError('');
        setStep(1);
    };

    const handleSubmit = async () => {
        if (!isLocationValid()) {
            setError('Please go back and confirm your location.');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const payload = {
                maid_id: maid.id,
                location,
                user_id: user.id,
                ...(preferenceId ? { preference_id: preferenceId } : {}),
                ...(startDate ? { preferred_start_date: startDate } : {}),
            };

            const response = await fetch('/onboarding/direct-hire', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (response.status === 422) {
                const data = await response.json();
                const firstError = Object.values(data.errors || {})[0]?.[0];
                setError(firstError || 'Please check your details and try again.');
                setLoading(false);
                return;
            }

            if (!response.ok) throw new Error('Server error');

            const data = await response.json();
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                setError('Something went wrong. Please try again.');
                setLoading(false);
            }
        } catch (err) {
            console.error('Employer hire error:', err);
            setError('A network error occurred. Please check your connection.');
            setLoading(false);
        }
    };

    return (
        <div
            ref={overlayRef}
            onClick={handleOverlayClick}
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            style={{ animation: 'fade-in 0.2s ease both' }}
        >
            <div
                className="bg-white rounded-brand-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto relative"
                style={{ animation: 'slide-up 0.25s ease both' }}
            >
                {/* Portal Header Bar */}
                <div className="bg-espresso px-6 py-4 rounded-t-brand-xl flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-brand-md overflow-hidden bg-teal/20 flex-shrink-0">
                            {maid.avatar ? (
                                <img src={maid.avatar} alt={maid.name} className="w-full h-full object-cover" />
                            ) : (
                                <div className="w-full h-full flex items-center justify-center text-sm font-bold text-teal">
                                    {maid.name?.charAt(0)}
                                </div>
                            )}
                        </div>
                        <div>
                            <p className="text-[10px] text-teal font-mono uppercase tracking-widest">Hire via Portal</p>
                            <p className="text-white font-bold text-sm leading-tight">{maid.name}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        {/* Step dots */}
                        <div className="flex items-center gap-1.5">
                            {['Location', 'Confirm'].map((label, i) => (
                                <div
                                    key={label}
                                    className={`w-2 h-2 rounded-full transition-all ${
                                        i <= step ? 'bg-teal' : 'bg-white/20'
                                    }`}
                                    title={label}
                                />
                            ))}
                        </div>
                        <button
                            onClick={onClose}
                            className="w-7 h-7 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white/60 hover:text-white transition-colors text-xs"
                            aria-label="Close"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <div className="p-6">
                    {/* Unavailability notice */}
                    {isUnavailable && (
                        <div className="bg-amber-50 border border-amber-200 rounded-brand-md p-3 mb-5 flex gap-2 items-start text-xs text-amber-800">
                            <span className="text-base leading-none flex-shrink-0">⚠️</span>
                            <span>
                                <strong>{firstName}</strong> is currently <strong className="capitalize">{maid.availability_status}</strong>.
                                You can still proceed — our team will contact {firstName} to confirm availability.
                            </span>
                        </div>
                    )}

                    {/* ── Step 0: Location ─────────────────────────────── */}
                    {step === 0 && (
                        <div>
                            <div className="mb-5">
                                <h2 className="font-display text-2xl text-espresso font-light mb-1">
                                    Confirm Your Location
                                </h2>
                                <p className="text-muted text-sm">
                                    We'll verify that {firstName} can work in your area.
                                </p>
                            </div>

                            {/* Employer greeting */}
                            <div className="bg-teal/5 border border-teal/10 rounded-brand-md p-3 mb-5 flex gap-2 items-center text-xs text-teal">
                                <span className="text-base leading-none flex-shrink-0">👋</span>
                                <span>Welcome back, <strong>{user?.name?.split(' ')[0]}</strong>! Let's get {firstName} hired.</span>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs text-muted font-mono uppercase tracking-wide mb-1">
                                        Your Location *
                                    </label>
                                    <input
                                        id="employer-hire-location"
                                        type="text"
                                        value={location}
                                        onChange={e => { setLocation(e.target.value); setError(''); }}
                                        placeholder="e.g. Lekki, Lagos"
                                        autoFocus
                                        className="w-full h-12 bg-white border-2 border-gray-200 rounded-brand-md px-4 text-sm text-espresso focus:border-teal focus:ring-2 focus:ring-teal/10 outline-none transition-all"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs text-muted font-mono uppercase tracking-wide mb-1">
                                        Preferred Start Date <span className="normal-case text-muted/60">(optional)</span>
                                    </label>
                                    <input
                                        id="employer-hire-start-date"
                                        type="date"
                                        value={startDate}
                                        min={new Date().toISOString().split('T')[0]}
                                        onChange={e => setStartDate(e.target.value)}
                                        className="w-full h-12 bg-white border-2 border-gray-200 rounded-brand-md px-4 text-sm text-espresso focus:border-teal focus:ring-2 focus:ring-teal/10 outline-none transition-all"
                                    />
                                </div>

                                {/* Maid location hint */}
                                <div className="bg-teal/5 border border-teal/10 rounded-brand-md p-3 text-xs text-muted flex items-start gap-2">
                                    <span className="text-teal text-base leading-none flex-shrink-0">📍</span>
                                    <span>
                                        {maid.name} is based in <strong className="text-espresso">{maid.location}</strong>.
                                        Confirm your location so we can check compatibility.
                                    </span>
                                </div>
                            </div>

                            {error && (
                                <div className="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-brand-md px-4 py-3 mt-4">
                                    {error}
                                </div>
                            )}

                            <button
                                id="employer-hire-next-btn"
                                onClick={handleNext}
                                className="mt-6 w-full bg-teal text-white py-3.5 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all shadow-md shadow-teal/20"
                            >
                                Review & Confirm →
                            </button>
                        </div>
                    )}

                    {/* ── Step 1: Confirm ───────────────────────────────── */}
                    {step === 1 && (
                        <div>
                            <div className="text-center mb-6">
                                <div className="w-12 h-12 bg-teal/10 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <span className="text-xl">✅</span>
                                </div>
                                <h2 className="font-display text-2xl text-espresso font-light">Confirm Hire Request</h2>
                                <p className="text-muted text-sm mt-1">Review before proceeding to payment</p>
                            </div>

                            {/* Summary card */}
                            <div className="bg-gray-50 rounded-brand-lg p-5 mb-5 space-y-3 text-sm border border-gray-100">
                                {/* Maid row */}
                                <div className="flex items-center gap-3 pb-3 border-b border-gray-100">
                                    <div className="w-10 h-10 rounded-brand-md overflow-hidden bg-teal/10 flex-shrink-0">
                                        {maid.avatar ? (
                                            <img src={maid.avatar} alt={maid.name} className="w-full h-full object-cover" />
                                        ) : (
                                            <div className="w-full h-full flex items-center justify-center font-bold text-teal text-sm">
                                                {maid.name?.charAt(0)}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="font-bold text-espresso truncate">{maid.name}</p>
                                        <p className="text-xs text-muted">{maid.role || 'Domestic Helper'} · {maid.location}</p>
                                    </div>
                                    {maid.verified && (
                                        <span className="text-[10px] bg-success/10 text-success px-2 py-0.5 rounded-full font-mono font-bold flex-shrink-0">✓ Verified</span>
                                    )}
                                </div>

                                {/* Details */}
                                <div className="flex justify-between">
                                    <span className="text-muted">Account</span>
                                    <span className="font-medium text-espresso">{user?.name}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted">Email</span>
                                    <span className="font-medium text-espresso text-right truncate ml-4">{user?.email}</span>
                                </div>
                                <div className="flex justify-between border-t border-gray-100 pt-3 mt-1">
                                    <span className="text-muted">Your Location</span>
                                    <span className="font-medium text-espresso">{location}</span>
                                </div>
                                {startDate && (
                                    <div className="flex justify-between">
                                        <span className="text-muted">Preferred Start</span>
                                        <span className="font-medium text-espresso">
                                            {new Date(startDate).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' })}
                                        </span>
                                    </div>
                                )}
                                {maid.rate > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-muted">Expected Salary</span>
                                        <span className="font-bold text-teal">₦{Number(maid.rate).toLocaleString()}/mo</span>
                                    </div>
                                )}
                            </div>

                            {error && (
                                <div className="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-brand-md px-4 py-3 mb-4">
                                    {error}
                                </div>
                            )}

                            <p className="text-xs text-muted text-center mb-4">
                                You'll be taken to pay the matching fee to finalise hiring {firstName}.
                            </p>

                            <button
                                id="employer-hire-confirm-btn"
                                onClick={handleSubmit}
                                disabled={loading}
                                className="w-full bg-teal text-white py-4 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all shadow-lg shadow-teal/20 disabled:opacity-60 flex items-center justify-center gap-2"
                            >
                                {loading ? (
                                    <>
                                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                        Setting up your hire...
                                    </>
                                ) : 'Proceed to Payment →'}
                            </button>

                            <button
                                onClick={() => setStep(0)}
                                className="w-full text-muted text-xs mt-3 hover:text-espresso transition-colors"
                            >
                                ← Edit Location
                            </button>
                        </div>
                    )}

                    <p className="text-center text-[10px] text-muted mt-5 font-mono uppercase tracking-widest">
                        🔒 Secured by Paystack · No Charge Until Confirmed
                    </p>
                </div>
            </div>

            <style>{`
                @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
                @keyframes slide-up { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
            `}</style>
        </div>
    );
}
