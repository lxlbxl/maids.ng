import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function MatchingPayment({ preference, maid, matchingFee = 5000, paystackKey }) {
    const [loading, setLoading] = useState(false);

    const handlePayment = async () => {
        setLoading(true);
        try {
            const resp = await fetch('/employer/matching-fee/initialize', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept': 'application/json' },
                body: JSON.stringify({ preference_id: preference.id }),
            });
            const data = await resp.json();
            // In production, open Paystack popup. For now, redirect to verify.
            window.location.href = `/employer/matching-fee/verify?reference=${data.reference}`;
        } catch (e) {
            setLoading(false);
        }
    };

    return (
        <>
            <Head title="Complete Your Match" />
            <div className="min-h-screen bg-ivory py-12 px-6">
                <div className="max-w-3xl mx-auto">
                    <div className="text-center mb-10">
                        <a href="/"><img src="/maids-logo.png" alt="Maids.ng" className="h-8 mx-auto mb-6" /></a>
                        <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-2">Almost There</p>
                        <h1 className="font-display text-3xl md:text-4xl font-light text-espresso">
                            Complete Your <em className="italic text-copper">Match</em>
                        </h1>
                    </div>

                    <div className="grid md:grid-cols-2 gap-6">
                        {/* Selected Maid Card */}
                        <div className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1">
                            <p className="font-mono text-[10px] tracking-[0.1em] text-teal uppercase mb-4">Your Selected Helper</p>
                            {maid ? (
                                <div>
                                    <div className="flex items-center gap-4 mb-4">
                                        <div className="w-16 h-16 rounded-full bg-gradient-to-br from-teal-pale to-teal flex items-center justify-center text-white text-xl font-bold relative">
                                            {maid.name?.charAt(0)}
                                            {maid.verified && <span className="absolute -bottom-0.5 -right-0.5 w-5 h-5 bg-success rounded-full border-2 border-white text-[9px] flex items-center justify-center">✓</span>}
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-espresso text-lg">{maid.name}</h3>
                                            <p className="text-muted text-sm">{maid.role}</p>
                                        </div>
                                    </div>
                                    <div className="space-y-2 text-sm">
                                        <div className="flex justify-between"><span className="text-muted">Location</span><span className="text-espresso">{maid.location}</span></div>
                                        <div className="flex justify-between"><span className="text-muted">Rating</span><span className="text-copper">{'★'.repeat(Math.round(maid.rating || 0))} {maid.rating}</span></div>
                                        <div className="flex justify-between"><span className="text-muted">Monthly Rate</span><span className="font-mono text-espresso font-medium">₦{maid.rate?.toLocaleString()}</span></div>
                                    </div>
                                    <div className="flex flex-wrap gap-1.5 mt-4">
                                        {(maid.skills || []).map(s => <span key={s} className="bg-teal-ghost text-teal text-[11px] px-2.5 py-1 rounded-full">{s}</span>)}
                                    </div>
                                </div>
                            ) : (
                                <p className="text-muted">No helper selected</p>
                            )}
                        </div>

                        {/* Payment Summary */}
                        <div className="space-y-5">
                            <div className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1">
                                <p className="font-mono text-[10px] tracking-[0.1em] text-teal uppercase mb-4">Payment Summary</p>
                                <div className="space-y-3 mb-6">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted">One-time Matching Fee</span>
                                        <span className="font-mono font-medium text-espresso">₦{matchingFee.toLocaleString()}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted">Background Verification</span>
                                        <span className="text-success text-xs font-medium">Included</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted">10-Day Guarantee</span>
                                        <span className="text-success text-xs font-medium">Included</span>
                                    </div>
                                    <hr className="border-gray-100" />
                                    <div className="flex justify-between font-semibold">
                                        <span>Total</span>
                                        <span className="font-mono text-teal text-lg">₦{matchingFee.toLocaleString()}</span>
                                    </div>
                                </div>

                                <button onClick={handlePayment} disabled={loading}
                                    className="w-full bg-teal text-white py-4 rounded-brand-md font-medium hover:bg-teal-dark transition-all hover:scale-[1.01] shadow-brand-2 disabled:opacity-50">
                                    {loading ? 'Processing...' : `Pay ₦${matchingFee.toLocaleString()} Securely →`}
                                </button>
                                <p className="text-center text-[11px] text-muted mt-3">🔒 Secured by Paystack · 256-bit encryption</p>
                            </div>

                            {/* What's Included */}
                            <div className="bg-teal-ghost rounded-brand-xl p-6 border border-teal-pale">
                                <h4 className="font-semibold text-teal text-sm mb-3">What's Included</h4>
                                <ul className="space-y-2 text-sm text-espresso">
                                    {['Access to helper\'s full contact details', 'Verified background check report', '10-day money-back guarantee', 'Priority customer support', 'Free replacement if unsatisfied'].map(item => (
                                        <li key={item} className="flex items-center gap-2">
                                            <span className="text-success text-xs">✓</span> {item}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
