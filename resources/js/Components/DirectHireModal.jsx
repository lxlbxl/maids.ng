import { useState, useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';

/**
 * DirectHireModal — Streamlined hiring flow for a specific maid.
 *
 * Shown when an employer clicks "Select & Continue" from the match list,
 * or "Hire [Name]" from the Browse / Profile page.
 *
 * Props:
 *   maid        — { id, name, avatar, role, location, rate, availability_status, verified }
 *   onClose     — function to close the modal
 *   prefillData — optional quiz answers from OnboardingQuiz (contact_name, contact_phone,
 *                 contact_email, location). When supplied, already-collected fields are
 *                 skipped so the user never re-enters info they just provided.
 */
export default function DirectHireModal({ maid, onClose, prefillData = null }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const isLoggedIn = !!user && (user.roles?.includes('employer') || user.roles?.includes('admin'));

    // Determine what information we already have
    const hasContactInfo = isLoggedIn || (
        prefillData &&
        prefillData.contact_name?.trim().length >= 2 &&
        prefillData.contact_phone?.trim().length >= 10 &&
        prefillData.contact_email?.includes('@')
    );

    // Logged-in users always confirm location (they may be in a different area now).
    // Guests with prefill location from the quiz skip the location step.
    const hasLocation = !isLoggedIn && prefillData?.location?.trim().length >= 3;

    // Build the minimal step list based on what we already know
    const steps = hasContactInfo && hasLocation
        ? ['Confirm']
        : hasContactInfo
            ? ['Location', 'Confirm']
            : ['Your Info', 'Location', 'Confirm'];

    const [step, setStep] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [form, setForm] = useState({
        contact_name: user?.name || prefillData?.contact_name || '',
        contact_phone: user?.phone || prefillData?.contact_phone || '',
        contact_email: user?.email || prefillData?.contact_email || '',
        location: prefillData?.location || '',
    });

    const overlayRef = useRef(null);
    const firstName = maid?.name?.split(' ')[0] || 'this helper';
    const isUnavailable = maid?.availability_status && maid.availability_status !== 'available';

    const currentStepLabel = steps[step];
    const isContactStep = currentStepLabel === 'Your Info';
    const isLocationStep = currentStepLabel === 'Location';
    const isConfirmStep = currentStepLabel === 'Confirm';

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    const handleOverlayClick = (e) => {
        if (e.target === overlayRef.current) onClose();
    };

    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [onClose]);

    const updateField = (field, value) => {
        setForm(prev => ({ ...prev, [field]: value }));
        setError('');
    };

    const isContactValid = () =>
        form.contact_name.trim().length >= 2 &&
        form.contact_phone.trim().length >= 10 &&
        form.contact_email.includes('@');

    const isLocationValid = () => form.location.trim().length >= 3;

    const handleNext = () => {
        if (isContactStep && !isContactValid()) {
            setError('Please fill in your name, a valid phone number, and email.');
            return;
        }
        if (isLocationStep && !isLocationValid()) {
            setError('Please enter your city/state (e.g. Lekki, Lagos).');
            return;
        }
        setError('');
        setStep(prev => prev + 1);
    };

    const handleSubmit = async () => {
        if (!isLocationValid()) {
            setError('Please enter your location before proceeding.');
            return;
        }
        if (!isLoggedIn && !isContactValid()) {
            setError('Please complete all required fields.');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const payload = {
                maid_id: maid.id,
                location: form.location,
                ...(isLoggedIn
                    ? { user_id: user.id }
                    : {
                        contact_name: form.contact_name,
                        contact_phone: form.contact_phone,
                        contact_email: form.contact_email,
                    }),
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
            console.error('Direct hire error:', err);
            setError('A network error occurred. Please check your connection and try again.');
            setLoading(false);
        }
    };

    // ── Confirm Step ────────────────────────────────────────────────────────────
    if (isConfirmStep) {
        return (
            <ModalShell overlayRef={overlayRef} onOverlayClick={handleOverlayClick} onClose={onClose}>
                <div className="text-center mb-6">
                    <div className="w-14 h-14 bg-teal/10 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span className="text-2xl">✅</span>
                    </div>
                    <h2 className="font-display text-2xl text-espresso font-light">Confirm Your Request</h2>
                    <p className="text-muted text-sm mt-1">Review your details before proceeding to payment</p>
                </div>

                {/* Maid Summary */}
                <div className="bg-teal/5 border border-teal/10 rounded-brand-lg p-4 flex items-center gap-4 mb-5">
                    <div className="w-16 h-16 rounded-brand-lg overflow-hidden flex-shrink-0 bg-teal/10">
                        {maid.avatar ? (
                            <img src={maid.avatar} alt={maid.name} className="w-full h-full object-cover" />
                        ) : (
                            <div className="w-full h-full flex items-center justify-center text-2xl font-bold text-teal">
                                {maid.name?.charAt(0)}
                            </div>
                        )}
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-[10px] text-teal font-mono uppercase tracking-widest">{maid.role || 'Domestic Helper'}</p>
                        <p className="font-bold text-espresso text-base">{maid.name}</p>
                        <p className="text-xs text-muted">{maid.location}</p>
                    </div>
                    {maid.verified && (
                        <span className="text-[10px] bg-success/10 text-success px-2 py-0.5 rounded-full font-mono font-bold flex-shrink-0">✓ Verified</span>
                    )}
                </div>

                {/* Unavailability notice */}
                {isUnavailable && (
                    <div className="bg-amber-50 border border-amber-200 rounded-brand-md p-3 mb-4 flex gap-2 items-start text-xs text-amber-800">
                        <span className="text-base leading-none flex-shrink-0">⚠️</span>
                        <span>
                            <strong>{firstName}</strong> is currently marked as <strong className="capitalize">{maid.availability_status}</strong>.
                            You can still proceed — our team will contact {firstName} to confirm availability.
                        </span>
                    </div>
                )}

                {/* Prefill banner — shown when data came from onboarding quiz */}
                {prefillData && !isLoggedIn && (
                    <div className="bg-teal/5 border border-teal/10 rounded-brand-md p-3 mb-4 flex gap-2 items-center text-xs text-teal">
                        <span className="text-base leading-none flex-shrink-0">✓</span>
                        <span>Your details from the quiz have been carried over automatically.</span>
                    </div>
                )}

                {/* Contact / User Summary */}
                <div className="bg-gray-50 rounded-brand-lg p-4 mb-5 space-y-2 text-sm">
                    {isLoggedIn ? (
                        <>
                            <div className="flex justify-between">
                                <span className="text-muted">Account</span>
                                <span className="font-medium text-espresso truncate ml-4 text-right">{user.name}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted">Email</span>
                                <span className="font-medium text-espresso truncate ml-4 text-right">{user.email}</span>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="flex justify-between">
                                <span className="text-muted">Name</span>
                                <span className="font-medium text-espresso">{form.contact_name}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted">Phone</span>
                                <span className="font-medium text-espresso">{form.contact_phone}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted">Email</span>
                                <span className="font-medium text-espresso truncate ml-4 text-right">{form.contact_email}</span>
                            </div>
                        </>
                    )}
                    <div className="flex justify-between border-t border-gray-100 pt-2 mt-2">
                        <span className="text-muted">Your Location</span>
                        <span className="font-medium text-espresso">{form.location}</span>
                    </div>
                </div>

                {error && (
                    <div className="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-brand-md px-4 py-3 mb-4">
                        {error}
                    </div>
                )}

                <p className="text-xs text-muted text-center mb-4">
                    You'll be taken to pay the matching fee to finalize hiring {firstName}.
                    {!isLoggedIn && ' Your account will be created automatically.'}
                </p>

                <button
                    onClick={handleSubmit}
                    disabled={loading}
                    id="direct-hire-confirm-btn"
                    className="w-full bg-teal text-white py-4 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all shadow-lg shadow-teal/20 disabled:opacity-60 flex items-center justify-center gap-2"
                >
                    {loading ? (
                        <>
                            <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            Setting things up...
                        </>
                    ) : `Proceed to Payment →`}
                </button>

                {steps.length > 1 && (
                    <button onClick={() => setStep(s => s - 1)} className="w-full text-muted text-xs mt-3 hover:text-espresso transition-colors">
                        ← Edit Details
                    </button>
                )}
            </ModalShell>
        );
    }

    // ── Steps: Your Info / Location ─────────────────────────────────────────────
    return (
        <ModalShell overlayRef={overlayRef} onOverlayClick={handleOverlayClick} onClose={onClose}>
            {/* Header */}
            <div className="mb-6">
                <div className="flex items-center gap-3 mb-4">
                    <div className="w-12 h-12 rounded-brand-lg overflow-hidden flex-shrink-0 bg-teal/10">
                        {maid.avatar ? (
                            <img src={maid.avatar} alt={maid.name} className="w-full h-full object-cover" />
                        ) : (
                            <div className="w-full h-full flex items-center justify-center text-xl font-bold text-teal">
                                {maid.name?.charAt(0)}
                            </div>
                        )}
                    </div>
                    <div>
                        <p className="text-[10px] text-teal font-mono uppercase tracking-widest">Hiring</p>
                        <p className="font-bold text-espresso text-base leading-tight">{maid.name}</p>
                        {isUnavailable ? (
                            <span className="text-[10px] text-amber-600 font-bold capitalize">{maid.availability_status}</span>
                        ) : (
                            <span className="text-[10px] text-success font-bold flex items-center gap-1">
                                <span className="w-1.5 h-1.5 bg-success rounded-full inline-block animate-pulse"></span>
                                Available Now
                            </span>
                        )}
                    </div>
                </div>

                {/* Step Indicator */}
                {steps.length > 1 && (
                    <div className="flex items-center gap-2">
                        {steps.map((label, i) => (
                            <div key={label} className="flex items-center gap-2 flex-1">
                                <div className={`w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold transition-all ${
                                    i < step ? 'bg-teal text-white' : i === step ? 'bg-teal text-white ring-2 ring-teal/30' : 'bg-gray-100 text-muted'
                                }`}>
                                    {i < step ? '✓' : i + 1}
                                </div>
                                <span className={`text-[10px] font-mono uppercase tracking-wide hidden sm:block ${i === step ? 'text-teal font-bold' : 'text-muted'}`}>
                                    {label}
                                </span>
                                {i < steps.length - 1 && <div className={`flex-1 h-px ${i < step ? 'bg-teal' : 'bg-gray-100'}`} />}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Unavailability banner */}
            {isUnavailable && (
                <div className="bg-amber-50 border border-amber-200 rounded-brand-md p-3 mb-4 flex gap-2 items-start text-xs text-amber-800">
                    <span className="text-base leading-none flex-shrink-0">⚠️</span>
                    <span>
                        <strong>{firstName}</strong> is currently <strong className="capitalize">{maid.availability_status}</strong>.
                        You can still express interest — our team will reach out to confirm if they become available.
                    </span>
                </div>
            )}

            {/* Logged-in greeting */}
            {isLoggedIn && (
                <div className="bg-teal/5 border border-teal/10 rounded-brand-md p-3 mb-4 flex gap-2 items-center text-xs text-teal">
                    <span className="text-base leading-none flex-shrink-0">👋</span>
                    <span>Welcome back, <strong>{user.name?.split(' ')[0]}</strong>! Just confirm your location to proceed.</span>
                </div>
            )}

            {/* ── Guest Step: Contact Info ──────────────────────────────────────── */}
            {isContactStep && (
                <div className="space-y-4">
                    <div>
                        <h2 className="font-display text-xl text-espresso font-light mb-1">Just a few details</h2>
                        <p className="text-muted text-sm">We'll use these to set up your account and match you with {firstName}.</p>
                    </div>

                    <div>
                        <label className="block text-xs text-muted font-mono uppercase tracking-wide mb-1">Your Full Name *</label>
                        <input
                            id="direct-hire-name"
                            type="text"
                            value={form.contact_name}
                            onChange={e => updateField('contact_name', e.target.value)}
                            placeholder="e.g. Adaeze Okonkwo"
                            autoFocus
                            className="w-full h-12 bg-white border-2 border-gray-200 rounded-brand-md px-4 text-sm text-espresso focus:border-teal focus:ring-2 focus:ring-teal/10 outline-none transition-all"
                        />
                    </div>

                    <div>
                        <label className="block text-xs text-muted font-mono uppercase tracking-wide mb-1">Phone Number *</label>
                        <input
                            id="direct-hire-phone"
                            type="tel"
                            value={form.contact_phone}
                            onChange={e => updateField('contact_phone', e.target.value)}
                            placeholder="e.g. 08012345678"
                            className="w-full h-12 bg-white border-2 border-gray-200 rounded-brand-md px-4 text-sm text-espresso focus:border-teal focus:ring-2 focus:ring-teal/10 outline-none transition-all"
                        />
                    </div>

                    <div>
                        <label className="block text-xs text-muted font-mono uppercase tracking-wide mb-1">Email Address *</label>
                        <input
                            id="direct-hire-email"
                            type="email"
                            value={form.contact_email}
                            onChange={e => updateField('contact_email', e.target.value)}
                            placeholder="e.g. you@email.com"
                            className="w-full h-12 bg-white border-2 border-gray-200 rounded-brand-md px-4 text-sm text-espresso focus:border-teal focus:ring-2 focus:ring-teal/10 outline-none transition-all"
                        />
                        <p className="text-[10px] text-muted mt-1">Your login details will be sent here</p>
                    </div>
                </div>
            )}

            {/* ── Location Step ─────────────────────────────────────────────────── */}
            {isLocationStep && (
                <div className="space-y-4">
                    <div>
                        <h2 className="font-display text-xl text-espresso font-light mb-1">Where are you located?</h2>
                        <p className="text-muted text-sm">So we can confirm {firstName} can work in your area.</p>
                    </div>

                    <div>
                        <label className="block text-xs text-muted font-mono uppercase tracking-wide mb-1">Your City & State *</label>
                        <input
                            id="direct-hire-location"
                            type="text"
                            value={form.location}
                            onChange={e => updateField('location', e.target.value)}
                            placeholder="e.g. Lekki, Lagos"
                            autoFocus
                            className="w-full h-12 bg-white border-2 border-gray-200 rounded-brand-md px-4 text-sm text-espresso focus:border-teal focus:ring-2 focus:ring-teal/10 outline-none transition-all"
                        />
                    </div>

                    <div className="bg-teal/5 border border-teal/10 rounded-brand-md p-3 text-xs text-muted flex items-start gap-2">
                        <span className="text-teal text-base leading-none flex-shrink-0">📍</span>
                        <span>
                            {maid.name} is based in <strong className="text-espresso">{maid.location}</strong>.
                            Enter your location so we can confirm compatibility.
                        </span>
                    </div>
                </div>
            )}

            {/* Error */}
            {error && (
                <div className="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-brand-md px-4 py-3 mt-4">
                    {error}
                </div>
            )}

            {/* Navigation */}
            <div className="flex items-center gap-3 mt-6">
                {step > 0 && (
                    <button
                        onClick={() => setStep(prev => prev - 1)}
                        className="px-5 py-3 border border-gray-200 rounded-brand-md text-sm text-muted hover:text-espresso hover:border-gray-300 transition-all"
                    >
                        ← Back
                    </button>
                )}
                <button
                    id={isLocationStep ? 'direct-hire-location-next' : 'direct-hire-next'}
                    onClick={handleNext}
                    className="flex-1 bg-teal text-white py-3 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all shadow-md shadow-teal/20"
                >
                    {isLocationStep ? 'Review & Confirm →' : 'Continue →'}
                </button>
            </div>

            <p className="text-center text-[10px] text-muted mt-4 font-mono uppercase tracking-widest">
                🔒 Secured by Paystack · No Charge Until Confirmed
            </p>
        </ModalShell>
    );
}

/** Reusable modal shell */
function ModalShell({ overlayRef, onOverlayClick, onClose, children }) {
    return (
        <div
            ref={overlayRef}
            onClick={onOverlayClick}
            className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            style={{ animation: 'fade-in 0.2s ease both' }}
        >
            <div
                className="bg-white rounded-brand-xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto p-6 relative"
                style={{ animation: 'slide-up 0.25s ease both' }}
            >
                <button
                    onClick={onClose}
                    className="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-muted transition-colors text-sm"
                    aria-label="Close"
                >
                    ✕
                </button>

                {children}
            </div>

            <style>{`
                @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
                @keyframes slide-up { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
            `}</style>
        </div>
    );
}
