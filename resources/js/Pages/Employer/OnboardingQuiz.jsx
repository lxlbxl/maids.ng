import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

const STEPS = [
    {
        id: 'help_type', title: 'What type of help do you need?', subtitle: 'Select all that apply', multi: true,
        options: [
            { value: 'housekeeping', label: 'Housekeeping', icon: '🏠', desc: 'Cleaning, laundry, organizing' },
            { value: 'cooking', label: 'Cooking', icon: '👩‍🍳', desc: 'Meal preparation & planning' },
            { value: 'nanny', label: 'Nanny', icon: '👶', desc: 'Childcare & babysitting' },
            { value: 'elderly-care', label: 'Elderly Care', icon: '🧓', desc: 'Companion & daily care' },
            { value: 'driver', label: 'Driver', icon: '🚗', desc: 'Transportation & errands' },
            { value: 'live-in', label: 'Live-in Helper', icon: '🏡', desc: 'Full-time household help' },
        ]
    },
    {
        id: 'schedule', title: 'What schedule works best?', subtitle: 'Choose your preferred schedule', multi: false,
        options: [
            { value: 'full-time', label: 'Full Time', icon: '☀️', desc: 'Monday – Saturday' },
            { value: 'part-time', label: 'Part Time', icon: '🌤️', desc: 'A few hours/days per week' },
            { value: 'weekends', label: 'Weekends Only', icon: '🌙', desc: 'Saturday & Sunday' },
            { value: 'one-time', label: 'One-Time', icon: '⚡', desc: 'Single occasion help' },
        ]
    },
    {
        id: 'urgency', title: 'How soon do you need help?', subtitle: 'This helps us prioritize your match', multi: false,
        options: [
            { value: 'immediately', label: 'Immediately', icon: '🔥', desc: 'Within 24-48 hours' },
            { value: 'this-week', label: 'This Week', icon: '📅', desc: 'Within 7 days' },
            { value: 'this-month', label: 'This Month', icon: '📆', desc: 'Within 30 days' },
            { value: 'flexible', label: 'I\'m Flexible', icon: '🕰️', desc: 'No rush, finding the right fit' },
        ]
    },
    { id: 'location', title: 'Where are you located?', subtitle: 'Enter your city and state', type: 'input' },
    { id: 'budget', title: 'What\'s your monthly budget?', subtitle: 'Help us find helpers in your range', type: 'budget' },
    { id: 'contact_name', title: 'What\'s your name?', subtitle: 'So we can personalize your matches', type: 'input', placeholder: 'e.g. Adaeze Okonkwo' },
    { id: 'contact_phone', title: 'Your phone number?', subtitle: 'We\'ll send match updates via SMS', type: 'input', placeholder: 'e.g. 08012345678' },
    { id: 'contact_email', title: 'Your email address?', subtitle: 'For your match results and receipts', type: 'input', placeholder: 'e.g. you@email.com' },
];

export default function OnboardingQuiz({ guaranteeFee = 5000 }) {
    const [step, setStep] = useState(0);
    const [answers, setAnswers] = useState({ help_types: [], schedule: '', urgency: '', location: '', budget_min: 15000, budget_max: 80000, contact_name: '', contact_phone: '', contact_email: '' });
    const [matches, setMatches] = useState(null);
    const [preferenceId, setPreferenceId] = useState(null);
    const [loading, setLoading] = useState(false);
    const [userId, setUserId] = useState(null);
    const [accountCreated, setAccountCreated] = useState(false);
    const [accountMessage, setAccountMessage] = useState('');
    const [guaranteeLoading, setGuaranteeLoading] = useState(false);
    const quizStartTime = useRef(Date.now());
    const hasTrackedStart = useRef(false);

    const current = STEPS[step];
    const progress = ((step + 1) / STEPS.length) * 100;

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ── sendBeacon: Track quiz start on mount ──
    useEffect(() => {
        if (!hasTrackedStart.current) {
            hasTrackedStart.current = true;
            sendBeaconEvent('quiz_start', {
                step: 0,
                total_steps: STEPS.length,
            });
        }

        // ── sendBeacon: Track quiz abandonment on page leave ──
        const handleBeforeUnload = () => {
            if (!matches && !loading) {
                // Quiz not completed — track abandonment
                sendBeaconEvent('quiz_abandon', {
                    step: step,
                    total_steps: STEPS.length,
                    progress: Math.round(progress),
                    duration_seconds: Math.round((Date.now() - quizStartTime.current) / 1000),
                    has_contact: !!(answers.contact_name && answers.contact_email),
                });
            }
        };

        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [step, progress, matches, loading, answers]);

    /**
     * Send a beacon event for analytics tracking.
     * Uses navigator.sendBeacon for reliable delivery on page unload.
     */
    const sendBeaconEvent = (eventType, data = {}) => {
        const payload = JSON.stringify({
            event_type: eventType,
            page_url: window.location.pathname,
            event_data: data,
            _token: csrfToken(),
        });

        if (navigator.sendBeacon) {
            const blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon('/api/user-events', blob);
        } else {
            // Fallback: regular fetch
            fetch('/api/user-events', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: payload,
                keepalive: true,
            }).catch(() => { });
        }
    };

    const handleOptionSelect = (value) => {
        if (current.multi) {
            setAnswers(prev => ({
                ...prev,
                help_types: prev.help_types.includes(value)
                    ? prev.help_types.filter(v => v !== value)
                    : [...prev.help_types, value]
            }));
        } else {
            setAnswers(prev => ({ ...prev, [current.id]: value }));
            setTimeout(() => nextStep(), 400);
        }
    };

    const handleInputChange = (field, value) => {
        setAnswers(prev => ({ ...prev, [field]: value }));
    };

    /**
     * Auto-create account once all contact fields are filled (after email step).
     * This happens in the background while the user sees the "Finding Matches..." state.
     */
    const createAccountIfNeeded = async () => {
        if (accountCreated || !answers.contact_name || !answers.contact_email) return null;

        try {
            const response = await fetch('/onboarding/create-account', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                body: JSON.stringify({
                    contact_name: answers.contact_name,
                    contact_email: answers.contact_email,
                    contact_phone: answers.contact_phone,
                }),
            });
            const data = await response.json();
            setUserId(data.user_id);
            setAccountCreated(true);
            setAccountMessage(data.message);
            return data.user_id;
        } catch (err) {
            console.warn('Account creation failed:', err);
            return null;
        }
    };

    const nextStep = () => {
        if (step < STEPS.length - 1) {
            setStep(step + 1);
        } else {
            submitQuiz();
        }
    };

    const prevStep = () => { if (step > 0) setStep(step - 1); };

    const submitQuiz = async () => {
        setLoading(true);
        try {
            // Create account first (if not already done), then search
            const createdUserId = await createAccountIfNeeded();
            const effectiveUserId = createdUserId || userId;

            const response = await fetch('/onboarding/find-matches', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                body: JSON.stringify({
                    ...answers,
                    user_id: effectiveUserId,
                }),
            });
            const data = await response.json();
            setMatches(data.matches || []);
            setPreferenceId(data.preference_id);

            // Track quiz completion
            sendBeaconEvent('quiz_complete', {
                total_steps: STEPS.length,
                duration_seconds: Math.round((Date.now() - quizStartTime.current) / 1000),
                matches_count: data.matches?.length || 0,
                has_contact: true,
            });
        } catch (err) {
            // Fallback: show demo matches
            setMatches([
                { id: 1, name: 'Blessing Okafor', role: 'Live-in Helper', location: 'Ajah, Lagos', rating: 4.8, rate: 45000, skills: ['cleaning', 'cooking'], match: 92, verified: true },
                { id: 2, name: 'Grace Adeyemi', role: 'Nanny', location: 'Ikeja, Lagos', rating: 4.9, rate: 55000, skills: ['childcare', 'cooking'], match: 87, verified: true },
                { id: 3, name: 'Joy Nwosu', role: 'Housekeeper', location: 'Lekki, Lagos', rating: 4.95, rate: 65000, skills: ['deep-cleaning', 'cooking'], match: 81, verified: true },
            ]);

            // Still track completion even with fallback
            sendBeaconEvent('quiz_complete', {
                total_steps: STEPS.length,
                duration_seconds: Math.round((Date.now() - quizStartTime.current) / 1000),
                matches_count: 3,
                has_contact: true,
                fallback: true,
            });
        }
        setLoading(false);
    };

    const selectMaid = (maidId) => {
        router.post('/employer/matching/select', { preference_id: preferenceId, maid_id: maidId });
    };

    /**
     * Activate Guarantee Match — redirects to the payment page.
     */
    const handleGuaranteeMatch = async () => {
        if (!preferenceId) return;
        setGuaranteeLoading(true);
        try {
            const response = await fetch('/onboarding/guarantee-match', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                body: JSON.stringify({ preference_id: preferenceId }),
            });

            if (response.status === 422) {
                const errors = await response.json();
                alert(Object.values(errors.errors || {})[0]?.[0] || 'Validation failed.');
                setGuaranteeLoading(false);
                return;
            }

            if (!response.ok) {
                throw new Error('Server error');
            }

            const data = await response.json();
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                setGuaranteeLoading(false);
                alert('Could not start payment. Please try again.');
            }
        } catch (err) {
            console.error('Guarantee match error:', err);
            alert('An error occurred. Please check your connection.');
            setGuaranteeLoading(false);
        }
    };

    // ── Matches Results View ──
    if (matches) {
        // Has matches — show them
        if (matches.length > 0) {
            return (
                <>
                    <Head title="Your Matches" />
                    <div className="min-h-screen bg-ivory py-12 px-6">
                        <div className="max-w-4xl mx-auto">
                            <div className="text-center mb-12">
                                <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-2">Your Matches</p>
                                <h1 className="font-display text-4xl md:text-5xl font-light text-espresso mb-3">
                                    We Found <em className="italic text-copper">{matches.length}</em> Helpers For You
                                </h1>
                                <p className="text-muted">Select a helper to proceed with booking</p>
                                {accountMessage && (
                                    <div className="mt-4 inline-flex items-center gap-2 bg-teal-ghost text-teal text-sm font-medium px-4 py-2 rounded-full">
                                        <span>✓</span> {accountMessage}
                                    </div>
                                )}
                            </div>

                            <div className="grid gap-5">
                                {matches.map((maid) => (
                                    <div key={maid.id} className="bg-white rounded-brand-xl p-6 border border-gray-200 shadow-brand-1 hover:shadow-brand-3 hover:-translate-y-1 transition-all duration-300 flex flex-col md:flex-row md:items-center gap-6">
                                        <div className="w-16 h-16 rounded-full bg-gradient-to-br from-teal-pale to-teal flex-shrink-0 flex items-center justify-center text-white text-xl font-bold relative">
                                            {maid.name.charAt(0)}
                                            {maid.verified && (
                                                <span className="absolute -bottom-0.5 -right-0.5 w-5 h-5 bg-success rounded-full border-2 border-white text-[9px] flex items-center justify-center">✓</span>
                                            )}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-start justify-between gap-4">
                                                <div>
                                                    <h3 className="font-semibold text-espresso text-lg">{maid.name}</h3>
                                                    <p className="text-muted text-sm">{maid.role} · {maid.location}</p>
                                                </div>
                                                <div className="bg-teal-ghost text-teal font-mono text-sm font-bold px-3 py-1.5 rounded-full flex-shrink-0">
                                                    {maid.match}% match
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-4 mt-3">
                                                <span className="text-copper text-sm">{'★'.repeat(Math.round(maid.rating))} {maid.rating}</span>
                                                <span className="font-mono text-espresso font-medium">₦{maid.rate?.toLocaleString()}<span className="text-muted text-xs font-normal">/mo</span></span>
                                            </div>
                                            <div className="flex flex-wrap gap-1.5 mt-3">
                                                {(maid.skills || []).slice(0, 4).map(s => (
                                                    <span key={s} className="bg-teal-ghost text-teal text-[11px] font-medium px-2.5 py-1 rounded-full">{s}</span>
                                                ))}
                                            </div>
                                        </div>
                                        <button onClick={() => selectMaid(maid.id)} className="bg-teal text-white px-6 py-3 rounded-brand-md font-medium text-sm hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-1 flex-shrink-0">
                                            Select & Continue →
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </>
            );
        }

        // ── Zero Results: Guarantee Match Upsell ──
        return (
            <>
                <Head title="Guarantee Match — Maids.ng" />
                <div className="min-h-screen bg-ivory py-12 px-6">
                    <div className="max-w-3xl mx-auto">
                        {/* Header */}
                        <div className="text-center mb-10" style={{ animation: 'fade-up 0.5s ease both' }}>
                            <a href="/"><img src="/maids-logo.png" alt="Maids.ng" className="h-8 mx-auto mb-6" /></a>
                            <p className="text-5xl mb-4">🔍</p>
                            <h1 className="font-display text-3xl md:text-4xl font-light text-espresso mb-3">
                                We Don't Have an Exact Match <em className="italic text-copper">Yet</em>
                            </h1>
                            <p className="text-muted max-w-lg mx-auto">
                                No worries — our <strong className="text-espresso">Guarantee Match</strong> service means
                                we'll personally find and assign a verified helper for you, or your money back.
                            </p>
                            {accountMessage && (
                                <div className="mt-4 inline-flex items-center gap-2 bg-teal-ghost text-teal text-sm font-medium px-4 py-2 rounded-full">
                                    <span>✓</span> {accountMessage}
                                </div>
                            )}
                        </div>

                        {/* Value Proposition Cards */}
                        <div className="grid sm:grid-cols-2 gap-4 mb-8" style={{ animation: 'fade-up 0.5s 0.1s ease both' }}>
                            {[
                                { icon: '🔍', title: 'We Search For You', desc: 'Our team actively sources helpers matching your exact preferences, location, and budget.' },
                                { icon: '⏱️', title: '14-Day Guarantee', desc: 'If we can\'t find a match within 14 days, you get a full refund — no questions asked.' },
                                { icon: '💰', title: 'No Double Payment', desc: 'Once matched, you won\'t pay the regular matching fee again. This covers everything.' },
                                { icon: '✅', title: 'Fully Verified', desc: 'Every matched helper undergoes full background verification before being assigned to you.' },
                            ].map(card => (
                                <div key={card.title} className="bg-white rounded-brand-xl p-6 border border-gray-200 shadow-brand-1 hover:shadow-brand-2 transition-shadow">
                                    <span className="text-2xl mb-3 block">{card.icon}</span>
                                    <h3 className="font-semibold text-espresso text-sm mb-1">{card.title}</h3>
                                    <p className="text-muted text-xs leading-relaxed">{card.desc}</p>
                                </div>
                            ))}
                        </div>

                        {/* CTA Section */}
                        <div className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-2 text-center mb-6" style={{ animation: 'fade-up 0.5s 0.2s ease both' }}>
                            <div className="flex items-center justify-center gap-2 mb-4">
                                <span className="text-2xl">🛡️</span>
                                <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-copper font-bold">Guarantee Match Service</p>
                            </div>

                            <div className="mb-6">
                                <div className="flex items-baseline justify-center gap-1 mb-1">
                                    <span className="font-mono text-4xl font-bold text-espresso">₦{guaranteeFee.toLocaleString()}</span>
                                    <span className="text-muted text-sm">one-time</span>
                                </div>
                                <p className="text-muted text-xs">Includes matching fee • No extra charges when assigned</p>
                            </div>

                            <button
                                onClick={handleGuaranteeMatch}
                                disabled={guaranteeLoading}
                                className="w-full max-w-md mx-auto bg-gradient-to-r from-copper to-copper/90 text-white py-4 rounded-brand-md font-medium text-base hover:opacity-90 transition-all hover:scale-[1.01] shadow-brand-2 disabled:opacity-50 block"
                            >
                                {guaranteeLoading ? (
                                    <span className="flex items-center justify-center gap-2">
                                        <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                        Activating...
                                    </span>
                                ) : `Activate Guarantee Match — ₦${guaranteeFee.toLocaleString()}`}
                            </button>
                            <p className="text-center text-[11px] text-muted mt-3">🔒 Secured by Paystack · Full refund if no match in 14 days</p>
                        </div>

                        {/* Money-back guarantee notice */}
                        <div className="bg-gradient-to-br from-copper/5 to-copper/10 rounded-brand-xl p-6 border border-copper/20 mb-6" style={{ animation: 'fade-up 0.5s 0.3s ease both' }}>
                            <div className="flex items-start gap-3">
                                <span className="text-3xl flex-shrink-0">💯</span>
                                <div>
                                    <h4 className="font-semibold text-espresso text-sm mb-1">100% Money-Back Guarantee</h4>
                                    <p className="text-muted text-xs leading-relaxed">
                                        We're confident we'll find your perfect helper. But if we can't match you within 14 days of your payment,
                                        you'll receive a full, automatic refund — no forms, no hassle, no questions asked.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Secondary: Try Again */}
                        <div className="text-center" style={{ animation: 'fade-up 0.5s 0.4s ease both' }}>
                            <p className="text-muted text-sm mb-3">Or adjust your preferences:</p>
                            <button onClick={() => { setMatches(null); setStep(0); }} className="text-teal hover:text-teal-dark font-medium text-sm transition-colors">
                                ← Try Again with Different Preferences
                            </button>
                        </div>
                    </div>
                </div>

                <style>{`
                    @keyframes fade-up { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
                `}</style>
            </>
        );
    }

    // ── Quiz View ──
    return (
        <>
            <Head title="Find Your Perfect Helper" />
            <div className="min-h-screen bg-ivory flex flex-col">
                {/* Progress Bar */}
                <div className="fixed top-0 left-0 right-0 h-1 bg-gray-200 z-50">
                    <div className="h-full bg-gradient-to-r from-teal to-copper transition-all duration-500 ease-out" style={{ width: `${progress}%` }} />
                </div>

                {/* Header */}
                <div className="pt-8 pb-4 px-6 text-center">
                    <a href="/">
                        <img src="/maids-logo.png" alt="Maids.ng" className="h-8 mx-auto" />
                    </a>
                    <p className="font-mono text-[10px] tracking-[0.12em] text-muted mt-3">
                        STEP {step + 1} OF {STEPS.length}
                    </p>
                </div>

                {/* Question Area */}
                <div className="flex-1 flex items-center justify-center px-6 pb-20">
                    <div className="max-w-2xl w-full">
                        <div className="text-center mb-10" key={step}>
                            <h1 className="font-display text-3xl md:text-4xl font-light text-espresso mb-2" style={{ animation: 'fade-up 0.5s ease both' }}>
                                {current.title}
                            </h1>
                            <p className="text-muted">{current.subtitle}</p>
                        </div>

                        {/* Option Cards */}
                        {current.options && (
                            <div className={`grid gap-3 ${current.options.length <= 4 ? 'grid-cols-2' : 'grid-cols-2 md:grid-cols-3'}`} style={{ animation: 'fade-up 0.5s 0.1s ease both' }}>
                                {current.options.map((opt) => {
                                    const isSelected = current.multi
                                        ? answers.help_types.includes(opt.value)
                                        : answers[current.id] === opt.value;
                                    return (
                                        <button key={opt.value} onClick={() => handleOptionSelect(opt.value)}
                                            className={`p-5 rounded-brand-lg border-2 text-left transition-all duration-200 hover:scale-[1.02]
                                                ${isSelected ? 'border-teal bg-teal-ghost shadow-brand-2' : 'border-gray-200 bg-white hover:border-teal/30 shadow-brand-1'}`}>
                                            <div className="text-2xl mb-2">{opt.icon}</div>
                                            <h3 className={`font-semibold text-sm ${isSelected ? 'text-teal' : 'text-espresso'}`}>{opt.label}</h3>
                                            <p className="text-muted text-xs mt-0.5">{opt.desc}</p>
                                        </button>
                                    );
                                })}
                            </div>
                        )}

                        {/* Text Input */}
                        {current.type === 'input' && (
                            <div style={{ animation: 'fade-up 0.5s 0.1s ease both' }}>
                                <input type={current.id === 'contact_email' ? 'email' : 'text'}
                                    value={answers[current.id] || ''}
                                    onChange={(e) => handleInputChange(current.id, e.target.value)}
                                    placeholder={current.placeholder || (current.id === 'location' ? 'e.g. Lekki, Lagos' : '')}
                                    className="w-full h-14 bg-white border-2 border-gray-200 rounded-brand-md px-5 text-base text-espresso focus:border-teal focus:ring-2 focus:ring-teal/20 transition-all outline-none"
                                    autoFocus
                                />
                            </div>
                        )}

                        {/* Budget Slider */}
                        {current.type === 'budget' && (
                            <div className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1" style={{ animation: 'fade-up 0.5s 0.1s ease both' }}>
                                <div className="flex justify-between items-baseline mb-6">
                                    <span className="font-mono text-sm text-teal">₦{answers.budget_min?.toLocaleString()}</span>
                                    <span className="text-muted text-sm">to</span>
                                    <span className="font-mono text-sm text-teal">₦{answers.budget_max?.toLocaleString()}</span>
                                </div>
                                <div className="space-y-6">
                                    <div>
                                        <label className="text-xs text-muted mb-2 block">Minimum Budget</label>
                                        <input type="range" min="15000" max="200000" step="5000" value={answers.budget_min}
                                            onChange={e => handleInputChange('budget_min', Number(e.target.value))}
                                            className="w-full accent-teal" />
                                    </div>
                                    <div>
                                        <label className="text-xs text-muted mb-2 block">Maximum Budget</label>
                                        <input type="range" min="15000" max="200000" step="5000" value={answers.budget_max}
                                            onChange={e => handleInputChange('budget_max', Number(e.target.value))}
                                            className="w-full accent-teal" />
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Navigation */}
                        <div className="flex items-center justify-between mt-10">
                            <button onClick={prevStep} disabled={step === 0}
                                className={`text-sm font-medium transition-colors ${step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-muted hover:text-teal'}`}>
                                ← Back
                            </button>
                            <button onClick={nextStep} disabled={loading}
                                className="bg-teal text-white px-8 py-3 rounded-brand-md font-medium text-sm hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-1 disabled:opacity-50">
                                {loading ? (
                                    <span className="flex items-center gap-2">
                                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                        Finding Matches...
                                    </span>
                                ) : step === STEPS.length - 1 ? 'Find My Matches →' : 'Continue →'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <style>{`
                @keyframes fade-up { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
            `}</style>
        </>
    );
}
