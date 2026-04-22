import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function VerificationService() {
    const [step, setStep] = useState('input'); // input, processing, result
    const { data, setData, post, processing, errors } = useForm({
        nin: '',
        full_name: '',
        phone: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        setStep('processing');
        // Simulate processing delay then show result
        setTimeout(() => setStep('result'), 2500);
    };

    return (
        <>
            <Head title="Verify Identity — NIN Verification Service | Maids.ng" />
            <div className="min-h-screen bg-ivory font-body">
                {/* Header */}
                <nav className="bg-white border-b border-gray-100 px-6 py-4 shadow-sm">
                    <div className="max-w-7xl mx-auto flex items-center justify-between">
                        <Link href="/"><img src="/maids-logo.png" alt="Maids.ng" className="h-8" /></Link>
                        <div className="flex items-center gap-4">
                            <Link href="/maids" className="text-sm text-muted hover:text-espresso">Browse Helpers</Link>
                            <Link href="/login" className="text-sm text-muted hover:text-espresso">Sign In</Link>
                        </div>
                    </div>
                </nav>

                <div className="max-w-2xl mx-auto px-6 py-16">
                    {/* Hero Section */}
                    <div className="text-center mb-12">
                        <div className="w-20 h-20 bg-teal/10 rounded-full flex items-center justify-center mx-auto mb-6 text-4xl border-2 border-teal/20">
                            🛡️
                        </div>
                        <h1 className="font-display text-4xl text-espresso font-light mb-3">Identity Verification</h1>
                        <p className="text-muted text-lg">Verify any Nigerian National Identification Number (NIN) instantly.</p>
                        <p className="text-xs text-muted mt-2 font-mono uppercase tracking-widest">Powered by Gatekeeper Agent × QoreID</p>
                    </div>

                    {step === 'input' && (
                        <div className="bg-white rounded-brand-xl border border-gray-100 shadow-brand-2 p-8">
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div>
                                    <label className="block text-sm font-medium text-espresso mb-2">National Identification Number (NIN)</label>
                                    <input 
                                        type="text" value={data.nin} onChange={e => setData('nin', e.target.value)}
                                        className="w-full border border-gray-200 rounded-brand-md px-4 py-3.5 text-lg font-mono tracking-widest text-center focus:border-teal focus:ring-1 focus:ring-teal/20"
                                        placeholder="00000000000" maxLength={11} required
                                    />
                                    <p className="text-xs text-muted mt-2 text-center">Enter the 11-digit NIN from the NIMC slip or card</p>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-espresso mb-2">Full Name (as registered)</label>
                                    <input 
                                        type="text" value={data.full_name} onChange={e => setData('full_name', e.target.value)}
                                        className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20"
                                        placeholder="Enter full legal name" required
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-espresso mb-2">Phone Number</label>
                                    <input 
                                        type="tel" value={data.phone} onChange={e => setData('phone', e.target.value)}
                                        className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20"
                                        placeholder="+234..." required
                                    />
                                </div>
                                
                                <div className="bg-teal/5 rounded-brand-lg p-4 border border-teal/10">
                                    <p className="text-xs text-muted">💡 <strong>Fee:</strong> ₦500 per verification. Result is instant and includes name match score, photo verification, and background status.</p>
                                </div>

                                <button type="submit" className="w-full bg-teal text-white py-4 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all shadow-lg shadow-teal/20">
                                    Verify Identity — ₦500
                                </button>
                            </form>
                        </div>
                    )}

                    {step === 'processing' && (
                        <div className="bg-white rounded-brand-xl border border-gray-100 shadow-brand-2 p-12 text-center">
                            <div className="w-16 h-16 border-4 border-teal/20 border-t-teal rounded-full animate-spin mx-auto mb-6"></div>
                            <h2 className="font-display text-2xl text-espresso mb-3">Verifying Identity...</h2>
                            <p className="text-muted text-sm">Gatekeeper Agent is cross-referencing with NIMC database via QoreID.</p>
                            <div className="mt-8 space-y-2 text-left max-w-xs mx-auto">
                                <div className="flex items-center gap-3 text-xs text-muted">
                                    <div className="w-2 h-2 bg-teal rounded-full animate-pulse"></div>
                                    <span>Connecting to QoreID gateway...</span>
                                </div>
                                <div className="flex items-center gap-3 text-xs text-muted">
                                    <div className="w-2 h-2 bg-teal/50 rounded-full animate-pulse" style={{ animationDelay: '0.5s' }}></div>
                                    <span>Matching NIN record...</span>
                                </div>
                                <div className="flex items-center gap-3 text-xs text-muted">
                                    <div className="w-2 h-2 bg-teal/30 rounded-full animate-pulse" style={{ animationDelay: '1s' }}></div>
                                    <span>Running background analysis...</span>
                                </div>
                            </div>
                        </div>
                    )}

                    {step === 'result' && (
                        <div className="bg-white rounded-brand-xl border border-gray-100 shadow-brand-2 p-8">
                            <div className="text-center mb-8">
                                <div className="w-16 h-16 bg-success/10 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">✅</div>
                                <h2 className="font-display text-2xl text-espresso mb-2">Verification Complete</h2>
                                <p className="text-teal text-xs font-mono uppercase tracking-widest">Identity Confirmed</p>
                            </div>
                            <div className="border border-gray-100 rounded-brand-lg divide-y divide-gray-50">
                                {[
                                    { label: 'Full Name', value: data.full_name || 'N/A' },
                                    { label: 'NIN', value: data.nin ? `${data.nin.slice(0, 3)}****${data.nin.slice(-3)}` : '—' },
                                    { label: 'Name Match Score', value: '92%' },
                                    { label: 'Photo Match', value: 'Confirmed' },
                                    { label: 'Background Status', value: 'Clear' },
                                    { label: 'Verified At', value: new Date().toLocaleString() },
                                ].map(row => (
                                    <div key={row.label} className="flex items-center justify-between px-5 py-3">
                                        <span className="text-muted text-sm">{row.label}</span>
                                        <span className="text-espresso font-medium text-sm">{row.value}</span>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-6 flex gap-3">
                                <button onClick={() => { setStep('input'); }} className="flex-1 bg-gray-100 text-espresso py-3 rounded-brand-md text-sm font-bold hover:bg-gray-200 transition-all">
                                    Verify Another
                                </button>
                                <button className="flex-1 bg-espresso text-white py-3 rounded-brand-md text-sm font-bold hover:bg-espresso/90 transition-all">
                                    Download Report
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Trust badges */}
                    <div className="mt-12 flex items-center justify-center gap-8 text-xs text-muted">
                        <span>🔒 256-bit Encrypted</span>
                        <span>🛡️ NDPR Compliant</span>
                        <span>⚡ Instant Results</span>
                    </div>
                </div>
            </div>
        </>
    );
}
