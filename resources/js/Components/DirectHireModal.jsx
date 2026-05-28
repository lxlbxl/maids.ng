import { useState, useEffect, useRef } from 'react';

/**
 * DirectHireModal — Streamlined hiring flow for a specific maid.
 *
 * Shown when an employer clicks "Hire [Name]" from either the Browse or Profile page.
 * Collects only mandatory data (name, phone, email, location), creates an account
 * if needed, and redirects directly to the matching fee payment page.
 *
 * Props:
 *   maid      — { id, name, avatar, role, location, rate, availability_status, verified }
 *   onClose   — function to close the modal
 */
export default function DirectHireModal({ maid, onClose }) {
    const [step, setStep] = useState(0); // 0 = contact info, 1 = location, 2 = confirming
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [form, setForm] = useState({
        contact_name: '',
        contact_phone: '',
        contact_email: '',
        location: '',
    });

    const overlayRef = useRef(null);
    const firstName = maid?.name?.split(' ')[0] || 'this helper';

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Close on overlay click
    const handleOverlayClick = (e) => {
        if (e.target === overlayRef.current) onClose();
    };

    // Close on Escape key
    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [onClose]);

    const updateField = (field, value) => {
        setForm(prev => ({ ...prev, [field]: value }));
        setError('');
    };

    // Validate step 0 (contact info)
    const isStep0Valid = () =>
        form.contact_name.trim().length >= 2 &&
        form.contact_phone.trim().length >= 10 &&
        form.contact_email.includes('@');

    // Validate step 1 (location)
    const isStep1Valid = () => form.location.trim().length >= 3;

    const handleNext = () => {
        if (step === 0 && !isStep0Valid()) {
            setError('Please fill in your name, a valid phone number, and email.');
            return;
        }
        if (step === 1 && !isStep1Valid()) {
            setError('Please enter your city/state (e.g. Lekki, Lagos).');
            return;
        }
        setError('');
        setStep(prev => prev + 1);
    };

    const handleSubmit = async () => {
        if (!isStep0Valid() || !isStep1Valid()) {
            setError('Please complete all required fields.');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const response = await fetch('/onboarding/direct-hire', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    maid_id: maid.id,
                    ...form,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                const firstError = Object.values(data.errors || {})[0]?.[0];
                setError(firstError || 'Please check your details and try again.');
                setLoading(false);
                return;
            }

            if (!response.ok) {
                throw new Error('Server error');
            }

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

    // Step 2: Review + Submit
    if (step === 2) {
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
                <div className="bg-teal/5 border border-teal/10 rounded-brand-lg p-4 flex items-center gap-4 mb-6">
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
                        <span className="text-[10px] bg-success/10 text-success px-2 py-0.5 rounded-full font-mono font-bold">✓ Verified</span>
                    )}
                </div>

                {/* Contact Summary */}
                <div className="bg-gray-50 rounded-brand-lg p-4 mb-6 space-y-2 text-sm">
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
                    <div className="flex justify-between">
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
                    Your account will be created automatically.
                </p>

                <button
                    onClick={handleSubmit}
                    disabled={loading}
                    className="w-full bg-teal text-white py-4 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all shadow-lg shadow-teal/20 disabled:opacity-60 flex items-center justify-center gap-2"
                >
                    {loading ? (
                        <>
                            <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            Setting things up...
                        </>
                    ) : `Proceed to Payment →`}
                </button>

                <button onClick={() => setStep(0)} className="w-full text-muted text-xs mt-3 hover:text-espresso transition-colors">
                    ← Edit Details
                </button>
            </ModalShell>
        );
    }

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
                        {maid.availability_status === 'available' && (
                            <span className="text-[10px] text-success font-bold flex items-center gap-1">
                                <span className="w-1.5 h-1.5 bg-success rounded-full inline-block animate-pulse"></span>
                                Available Now
                            </span>
                        )}
                    </div>
                </div>

                {/* Step Indicator */}
                <div className="flex items-center gap-2">
                    {['Your Info', 'Location', 'Confirm'].map((label, i) => (
                        <div key={label} className="flex items-center gap-2 flex-1">
                            <div className={`w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold transition-all ${
                                i < step ? 'bg-teal text-white' : i === step ? 'bg-teal text-white ring-2 ring-teal/30' : 'bg-gray-100 text-muted'
                            }`}>
                                {i < step ? '✓' : i + 1}
                            </div>
                            <span className={`text-[10px] font-mono uppercase tracking-wide hidden sm:block ${i === step ? 'text-teal font-bold' : 'text-muted'}`}>
                                {label}
                            </span>
                            {i < 2 && <div className={`flex-1 h-px ${i < step ? 'bg-teal' : 'bg-gray-100'}`} />}
                        </div>
                    ))}
                </div>
            </div>

            {/* Step 0: Contact Info */}
            {step === 0 && (
                <div className="space-y-4">
                    <div>
                        <h2 className="font-display text-xl text-espresso font-light mb-1">Just a few details</h2>
                        <p className="text-muted text-sm">We'll use these to set up your account and match you with {firstName}.</p>
                    </div>

                    <div>
                        <label className="block text-xs text-muted font-mono uppercase tracking-wide mb-1">Your Full Name *</label>
                        <input
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

            {/* Step 1: Location */}
            {step === 1 && (
                <div className="space-y-4">
                    <div>
                        <h2 className="font-display text-xl text-espresso font-light mb-1">Where are you located?</h2>
                        <p className="text-muted text-sm">So we can confirm {firstName} can work in your area.</p>
                    </div>

                    <div>
                        <label className="block text-xs text-muted font-mono uppercase tracking-wide mb-1">Your City & State *</label>
                        <input
                            type="text"
                            value={form.location}
                            onChange={e => updateField('location', e.target.value)}
                            placeholder="e.g. Lekki, Lagos"
                            autoFocus
                            className="w-full h-12 bg-white border-2 border-gray-200 rounded-brand-md px-4 text-sm text-espresso focus:border-teal focus:ring-2 focus:ring-teal/10 outline-none transition-all"
                        />
                    </div>

                    {/* Maid location hint */}
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
                    onClick={handleNext}
                    className="flex-1 bg-teal text-white py-3 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all shadow-md shadow-teal/20"
                >
                    {step === 1 ? 'Review & Confirm →' : 'Continue →'}
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
                {/* Close Button */}
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
