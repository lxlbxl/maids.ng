import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function GuaranteeMatchPayment({ preference, guaranteeFee = 5000, paystackKey, defaultGateway }) {
    const [loading, setLoading] = useState(false);

    const handlePayment = async () => {
        setLoading(true);
        try {
            const resp = await fetch('/employer/matching-fee/initialize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    preference_id: preference.id,
                    payment_type: 'guarantee_match',
                }),
            });

            if (resp.status === 422) {
                const errors = await resp.json();
                alert(Object.values(errors.errors || {})[0]?.[0] || 'Validation failed.');
                setLoading(false);
                return;
            }

            if (!resp.ok) {
                throw new Error('Server returned ' + resp.status);
            }

            const data = await resp.json();
            
            if (data.success) {
                if (data.gateway === 'flutterwave') {
                    if (typeof window.FlutterwaveCheckout !== 'function') {
                        alert('Payment gateway (Flutterwave) is still loading. Please wait a moment and try again.');
                        setLoading(false);
                        return;
                    }

                    window.FlutterwaveCheckout({
                        public_key: trim(data.key),
                        tx_ref: data.reference,
                        amount: data.amount,
                        currency: "NGN",
                        payment_options: "card, banktransfer, ussd",
                        customer: {
                            email: data.email,
                            name: data.name,
                            phone_number: data.phone,
                        },
                        callback: function (response) {
                            window.location.href = `/employer/matching-fee/verify?reference=${data.reference}`;
                        },
                        onclose: function() {
                            setLoading(false);
                        }
                    });
                } else {
                    if (typeof window.PaystackPop === 'undefined') {
                        alert('Payment gateway (Paystack) is still loading. Please wait a moment and try again.');
                        setLoading(false);
                        return;
                    }

                    const handler = window.PaystackPop.setup({
                        key: trim(data.key),
                        email: data.email,
                        amount: data.amount * 100, // Paystack requires kobo
                        ref: data.reference,
                        callback: function(response) {
                            window.location.href = `/employer/matching-fee/verify?reference=${response.reference}`;
                        },
                        onClose: function() {
                            setLoading(false);
                        }
                    });
                    handler.openIframe();
                }
            } else {
                alert('Failed to initialize payment: ' + (data.message || 'Unknown error'));
                setLoading(false);
            }
        } catch (e) {
            console.error(e);
            alert('An error occurred. Please check your connection.');
            setLoading(false);
        }
    };

    const trim = (str) => (str || '').trim();

    return (
        <>
            <Head title="Guarantee Match — Maids.ng" />
            <div className="min-h-screen bg-ivory py-12 px-6">
                <div className="max-w-3xl mx-auto">
                    {/* Header */}
                    <div className="text-center mb-10">
                        <a href="/"><img src="/maids-logo.png" alt="Maids.ng" className="h-8 mx-auto mb-6" /></a>
                        <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-copper mb-2">Guarantee Match</p>
                        <h1 className="font-display text-3xl md:text-4xl font-light text-espresso">
                            Activate Your <em className="italic text-copper">Guarantee Match</em>
                        </h1>
                        <p className="text-muted mt-3 max-w-lg mx-auto text-sm">
                            We'll personally source and assign a verified helper matching your exact requirements — or your money back.
                        </p>
                    </div>

                    <div className="grid md:grid-cols-2 gap-6">
                        {/* How It Works */}
                        <div className="space-y-5">
                            <div className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1">
                                <p className="font-mono text-[10px] tracking-[0.1em] text-teal uppercase mb-5">How It Works</p>
                                <div className="space-y-5">
                                    {[
                                        { step: '01', icon: '💳', title: 'Pay Once', desc: 'One-time fee covers everything — no hidden charges, no additional matching fee later.' },
                                        { step: '02', icon: '🔍', title: 'We Search For You', desc: 'Our team actively sources helpers matching your exact preferences and location.' },
                                        { step: '03', icon: '✅', title: 'Get Matched', desc: 'Once found, your helper is assigned directly — you don\'t pay any matching fee again.' },
                                        { step: '04', icon: '🛡️', title: '14-Day Guarantee', desc: 'If we can\'t find a match within 14 days, you get a full refund — no questions asked.' },
                                    ].map(item => (
                                        <div key={item.step} className="flex gap-4">
                                            <div className="flex-shrink-0 w-10 h-10 rounded-full bg-teal-ghost flex items-center justify-center text-lg">
                                                {item.icon}
                                            </div>
                                            <div className="flex-1">
                                                <div className="flex items-baseline gap-2">
                                                    <span className="font-mono text-[10px] text-teal font-bold">{item.step}</span>
                                                    <h4 className="font-semibold text-espresso text-sm">{item.title}</h4>
                                                </div>
                                                <p className="text-muted text-xs mt-0.5">{item.desc}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Your Preferences Summary */}
                            {preference && (
                                <div className="bg-white rounded-brand-xl p-6 border border-gray-200 shadow-brand-1">
                                    <p className="font-mono text-[10px] tracking-[0.1em] text-teal uppercase mb-3">Your Requirements</p>
                                    <div className="space-y-2 text-sm">
                                        {preference.location && (
                                            <div className="flex justify-between"><span className="text-muted">Location</span><span className="text-espresso">{preference.location}</span></div>
                                        )}
                                        {preference.help_types && (
                                            <div className="flex justify-between"><span className="text-muted">Help Type</span>
                                                <span className="text-espresso">{(Array.isArray(preference.help_types) ? preference.help_types : []).join(', ')}</span>
                                            </div>
                                        )}
                                        {preference.schedule && (
                                            <div className="flex justify-between"><span className="text-muted">Schedule</span><span className="text-espresso capitalize">{preference.schedule}</span></div>
                                        )}
                                        {(preference.budget_min || preference.budget_max) && (
                                            <div className="flex justify-between"><span className="text-muted">Budget</span>
                                                <span className="font-mono text-espresso">₦{(preference.budget_min || 0).toLocaleString()} – ₦{(preference.budget_max || 0).toLocaleString()}</span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Payment Card */}
                        <div className="space-y-5">
                            <div className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1">
                                <p className="font-mono text-[10px] tracking-[0.1em] text-teal uppercase mb-4">Payment Summary</p>
                                <div className="space-y-3 mb-6">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted">Guarantee Match Fee</span>
                                        <span className="font-mono font-medium text-espresso">₦{guaranteeFee.toLocaleString()}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted">Active Sourcing (14 days)</span>
                                        <span className="text-success text-xs font-medium">Included</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted">Background Verification</span>
                                        <span className="text-success text-xs font-medium">Included</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted">Future Matching Fee</span>
                                        <span className="text-success text-xs font-medium">Waived ✓</span>
                                    </div>
                                    <hr className="border-gray-100" />
                                    <div className="flex justify-between font-semibold">
                                        <span>Total (One-Time)</span>
                                        <span className="font-mono text-teal text-lg">₦{guaranteeFee.toLocaleString()}</span>
                                    </div>
                                </div>

                                <button onClick={handlePayment} disabled={loading}
                                    className="w-full bg-gradient-to-r from-copper to-copper/90 text-white py-4 rounded-brand-md font-medium hover:opacity-90 transition-all hover:scale-[1.01] shadow-brand-2 disabled:opacity-50">
                                    {loading ? 'Processing...' : `Activate Guarantee Match — ₦${guaranteeFee.toLocaleString()}`}
                                </button>
                                <p className="text-center text-[11px] text-muted mt-3">🔒 Secured by Paystack · 256-bit encryption</p>
                            </div>

                            {/* Guarantee Badge */}
                            <div className="bg-gradient-to-br from-copper/5 to-copper/10 rounded-brand-xl p-6 border border-copper/20">
                                <div className="flex items-start gap-3">
                                    <span className="text-3xl">🛡️</span>
                                    <div>
                                        <h4 className="font-semibold text-espresso text-sm mb-1">100% Money-Back Guarantee</h4>
                                        <p className="text-muted text-xs leading-relaxed">
                                            If we don't find a matching helper within 14 days of your payment, you'll receive a full refund
                                            automatically — no questions asked, no forms to fill.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* What's Included */}
                            <div className="bg-teal-ghost rounded-brand-xl p-6 border border-teal-pale">
                                <h4 className="font-semibold text-teal text-sm mb-3">What's Included</h4>
                                <ul className="space-y-2 text-sm text-espresso">
                                    {[
                                        'Dedicated team actively sourcing your helper',
                                        'Verified background check on matched helper',
                                        'No additional matching fee when assigned',
                                        '14-day money-back guarantee',
                                        'Priority customer support',
                                        'Free replacement within 7 days if unsatisfied',
                                    ].map(item => (
                                        <li key={item} className="flex items-center gap-2">
                                            <span className="text-success text-xs">✓</span> {item}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>

                    {/* Bottom CTA */}
                    <div className="text-center mt-10">
                        <a href="/onboarding" className="text-sm text-muted hover:text-teal transition-colors">
                            ← Start Over with Different Preferences
                        </a>
                    </div>
                </div>
            </div>
        </>
    );
}
