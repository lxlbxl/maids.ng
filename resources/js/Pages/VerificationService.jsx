import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';

const STEPS = [
    { id: 'intro', title: 'Identity is the Foundation of Trust', subtitle: 'Our Gatekeeper AI connects directly to the National Identity Database (NIMC) to ensure your helper is exactly who they claim to be.' },
    { id: 'maid_info', title: 'Helper\'s Identity Details', subtitle: 'Please ensure you have their correct 11-digit NIN and official name as it appears on their ID.' },
    { id: 'requester_info', title: 'Your Contact Details', subtitle: 'We\'ll send a permanent copy of the verification report to your email.' },
    { id: 'payment', title: 'Review & Secure Payment', subtitle: 'Results are usually available within seconds after successful payment.' },
];

export default function VerificationService({ fee }) {
    const [stepIndex, setStepIndex] = useState(0);
    const [isProcessing, setIsProcessing] = useState(false);
    
    const displayFee = fee || 2000;
    
    const { data, setData, post, errors, processing } = useForm({
        maid_nin: '',
        maid_first_name: '',
        maid_last_name: '',
        requester_name: '',
        requester_email: '',
        requester_phone: '',
    });

    const currentStep = STEPS[stepIndex];
    const progress = ((stepIndex + 1) / STEPS.length) * 100;

    const nextStep = () => {
        if (stepIndex < STEPS.length - 1) {
            setStepIndex(stepIndex + 1);
        } else {
            handleInitializePayment();
        }
    };

    const prevStep = () => {
        if (stepIndex > 0) setStepIndex(stepIndex - 1);
    };

    const handleInitializePayment = async () => {
        setIsProcessing(true);
        try {
            // Basic client-side validation
            if (data.maid_nin.length !== 11 || !/^\d+$/.test(data.maid_nin)) {
                alert('NIN must be exactly 11 digits.');
                setIsProcessing(false);
                return;
            }

            const response = await fetch(route('standalone-verification.initialize'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            if (response.status === 422) {
                const errors = await response.json();
                const firstError = Object.values(errors.errors || {})[0]?.[0] || 'Validation failed. Please check your inputs.';
                alert(firstError);
                setIsProcessing(false);
                return;
            }

            if (!response.ok) {
                throw new Error('Server returned ' + response.status);
            }

            const result = await response.json();
            
            if (result.success) {
                if (result.gateway === 'flutterwave') {
                    // Check if Flutterwave is loaded
                    if (typeof window.FlutterwaveCheckout !== 'function') {
                        alert('Payment gateway (Flutterwave) is still loading. Please wait a moment and try again.');
                        setIsProcessing(false);
                        return;
                    }

                    window.FlutterwaveCheckout({
                        public_key: result.key,
                        tx_ref: result.reference,
                        amount: result.amount,
                        currency: "NGN",
                        payment_options: "card, banktransfer, ussd",
                        customer: {
                            email: result.email,
                            name: data.requester_name,
                            phone_number: data.requester_phone,
                        },
                        callback: function (response) {
                            window.location.href = route('standalone-verification.verify', { reference: result.reference });
                        },
                        onclose: function() {
                            setIsProcessing(false);
                        }
                    });
                } else {
                    // Check if Paystack is loaded
                    if (typeof window.PaystackPop === 'undefined') {
                        alert('Payment gateway (Paystack) is still loading. Please wait a moment and try again.');
                        setIsProcessing(false);
                        return;
                    }

                    const handler = window.PaystackPop.setup({
                        key: result.key,
                        email: result.email,
                        amount: result.amount * 100, // Paystack requires kobo
                        ref: result.reference,
                        callback: function(response) {
                            window.location.href = route('standalone-verification.verify', { reference: response.reference });
                        },
                        onClose: function() {
                            setIsProcessing(false);
                        }
                    });
                    handler.openIframe();
                }
            } else {
                alert('Failed to initialize payment: ' + (result.message || 'Unknown error'));
                setIsProcessing(false);
            }
        } catch (error) {
            console.error('Payment initialization error:', error);
            alert('Could not initialize payment. Please check your internet connection.');
            setIsProcessing(false);
        }
    };

    return (
        <>
            <Head title="Verify Identity — NIN Verification Service | Maids.ng" />
            <div className="min-h-screen bg-ivory font-body flex flex-col">
                {/* Progress Bar */}
                <div className="fixed top-0 left-0 right-0 h-1 bg-gray-200 z-50">
                    <div className="h-full bg-teal transition-all duration-500 ease-out" style={{ width: `${progress}%` }} />
                </div>

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

                <div className="flex-1 flex items-center justify-center px-6 py-16">
                    <div className="max-w-xl w-full">
                        {/* Step Content */}
                        <div className="text-center mb-10">
                            <div className="w-16 h-16 bg-teal/10 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl border-2 border-teal/20">
                                {stepIndex === 0 ? '🛡️' : stepIndex === 1 ? '👤' : stepIndex === 2 ? '📧' : '💳'}
                            </div>
                            <h1 className="font-display text-3xl text-espresso mb-2">{currentStep.title}</h1>
                            <p className="text-muted">{currentStep.subtitle}</p>
                        </div>

                        <div className="bg-white rounded-brand-xl border border-gray-100 shadow-brand-2 p-8">
                            {stepIndex === 0 && (
                                <div className="space-y-6">
                                    <div className="space-y-4">
                                        <div className="flex items-start gap-4">
                                            <div className="w-8 h-8 rounded-full bg-teal/10 flex items-center justify-center text-teal flex-shrink-0 mt-1">1</div>
                                            <div className="flex-1">
                                                <p className="text-sm font-bold text-espresso mb-1">Official Database Link</p>
                                                <p className="text-xs text-muted leading-relaxed">We match provided details directly against National Identity Management Commission (NIMC) records.</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-4">
                                            <div className="w-8 h-8 rounded-full bg-teal/10 flex items-center justify-center text-teal flex-shrink-0 mt-1">2</div>
                                            <div className="flex-1">
                                                <p className="text-sm font-bold text-espresso mb-1">Instant Photo ID</p>
                                                <p className="text-xs text-muted leading-relaxed">The official photo on record is pulled to verify physical appearance and prevent identity theft.</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-4">
                                            <div className="w-8 h-8 rounded-full bg-teal/10 flex items-center justify-center text-teal flex-shrink-0 mt-1">3</div>
                                            <div className="flex-1">
                                                <p className="text-sm font-bold text-espresso mb-1">Detailed Risk Report</p>
                                                <p className="text-xs text-muted leading-relaxed">Receive a professional PDF report with age, gender, and match confidence scores.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="pt-4 space-y-4">
                                        <button onClick={nextStep} className="w-full bg-teal text-white py-4 rounded-brand-md font-bold text-sm hover:bg-teal-dark transition-all shadow-lg hover:scale-[1.01]">
                                            Securely Verify for ₦{Number(displayFee).toLocaleString()}
                                        </button>
                                        <p className="text-[10px] text-center text-muted">Results are typically ready in under 60 seconds.</p>
                                    </div>
                                </div>
                            )}

                            {stepIndex === 1 && (
                                <div className="space-y-6">
                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-xs font-mono uppercase tracking-widest text-muted mb-2">11-Digit NIN</label>
                                            <input 
                                                type="text" maxLength={11}
                                                value={data.maid_nin} onChange={e => setData('maid_nin', e.target.value)}
                                                className="w-full border-2 border-gray-100 rounded-brand-md px-4 py-3.5 text-lg font-mono tracking-widest text-center focus:border-teal outline-none"
                                                placeholder="00000000000" required
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-xs font-mono uppercase tracking-widest text-muted mb-2">First Name</label>
                                                <input 
                                                    type="text"
                                                    value={data.maid_first_name} onChange={e => setData('maid_first_name', e.target.value)}
                                                    className="w-full border-2 border-gray-100 rounded-brand-md px-4 py-3 text-sm focus:border-teal outline-none"
                                                    placeholder="Adaeze" required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-mono uppercase tracking-widest text-muted mb-2">Last Name</label>
                                                <input 
                                                    type="text"
                                                    value={data.maid_last_name} onChange={e => setData('maid_last_name', e.target.value)}
                                                    className="w-full border-2 border-gray-100 rounded-brand-md px-4 py-3 text-sm focus:border-teal outline-none"
                                                    placeholder="Okonkwo" required
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex gap-4">
                                        <button onClick={prevStep} className="flex-1 bg-gray-100 text-espresso py-4 rounded-brand-md text-sm font-bold hover:bg-gray-200">Back</button>
                                        <button onClick={nextStep} disabled={!data.maid_nin || !data.maid_first_name || !data.maid_last_name} className="flex-1 bg-teal text-white py-4 rounded-brand-md font-bold text-sm hover:bg-teal/90 disabled:opacity-50 transition-all">Continue</button>
                                    </div>
                                </div>
                            )}

                            {stepIndex === 2 && (
                                <div className="space-y-6">
                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-xs font-mono uppercase tracking-widest text-muted mb-2">Your Full Name</label>
                                            <input 
                                                type="text"
                                                value={data.requester_name} onChange={e => setData('requester_name', e.target.value)}
                                                className="w-full border-2 border-gray-100 rounded-brand-md px-4 py-3 text-sm focus:border-teal outline-none"
                                                placeholder="e.g. John Doe" required
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-mono uppercase tracking-widest text-muted mb-2">Your Email Address</label>
                                            <input 
                                                type="email"
                                                value={data.requester_email} onChange={e => setData('requester_email', e.target.value)}
                                                className="w-full border-2 border-gray-100 rounded-brand-md px-4 py-3 text-sm focus:border-teal outline-none"
                                                placeholder="john@example.com" required
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-mono uppercase tracking-widest text-muted mb-2">Your Phone Number</label>
                                            <input 
                                                type="tel"
                                                value={data.requester_phone} onChange={e => setData('requester_phone', e.target.value)}
                                                className="w-full border-2 border-gray-100 rounded-brand-md px-4 py-3 text-sm focus:border-teal outline-none"
                                                placeholder="080..." required
                                            />
                                        </div>
                                    </div>
                                    <div className="flex gap-4">
                                        <button onClick={prevStep} className="flex-1 bg-gray-100 text-espresso py-4 rounded-brand-md text-sm font-bold hover:bg-gray-200">Back</button>
                                        <button onClick={nextStep} disabled={!data.requester_email || !data.requester_name || !data.requester_phone} className="flex-1 bg-teal text-white py-4 rounded-brand-md font-bold text-sm hover:bg-teal/90 disabled:opacity-50 transition-all">Review Summary</button>
                                    </div>
                                </div>
                            )}

                            {stepIndex === 3 && (
                                <div className="space-y-6">
                                    <div className="bg-ivory/50 rounded-brand-lg p-6 border border-gray-100 space-y-4">
                                        <div className="flex justify-between items-center text-sm">
                                            <span className="text-muted">Helper to Verify</span>
                                            <span className="text-espresso font-bold">{data.maid_first_name} {data.maid_last_name}</span>
                                        </div>
                                        <div className="flex justify-between items-center text-sm">
                                            <span className="text-muted">NIN</span>
                                            <span className="text-espresso font-mono">{data.maid_nin}</span>
                                        </div>
                                        <div className="border-t border-gray-100 pt-4 flex justify-between items-center">
                                            <span className="text-espresso font-bold">Total Fee</span>
                                            <span className="text-2xl font-display text-teal">₦{Number(displayFee).toLocaleString()}</span>
                                        </div>
                                    </div>

                                    <div className="flex gap-4">
                                        <button onClick={prevStep} disabled={isProcessing} className="flex-1 bg-gray-100 text-espresso py-4 rounded-brand-md text-sm font-bold hover:bg-gray-200 disabled:opacity-50">Back</button>
                                        <button 
                                            onClick={handleInitializePayment} 
                                            disabled={isProcessing}
                                            className="flex-1 bg-espresso text-white py-4 rounded-brand-md font-bold text-sm hover:bg-espresso/90 disabled:opacity-50 transition-all flex items-center justify-center gap-2 shadow-xl shadow-espresso/20"
                                        >
                                            {isProcessing ? (
                                                <>
                                                    <svg className="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    Processing...
                                                </>
                                            ) : (
                                                <>Secure Payment & Verify</>
                                            )}
                                        </button>
                                    </div>
                                    <p className="text-[10px] text-muted text-center italic">Payments are securely processed by Paystack. Your data is protected under NDPR guidelines.</p>
                                </div>
                            )}
                        </div>

                        {/* Trust badges */}
                        <div className="mt-12 flex items-center justify-center gap-8 text-[10px] text-muted font-mono uppercase tracking-widest">
                            <span>🔒 256-bit Encrypted</span>
                            <span>🛡️ NDPR Compliant</span>
                            <span>⚡ Instant Results</span>
                        </div>

                        {/* Upsell Section */}
                        <div className="mt-16 bg-white/40 border border-teal/10 rounded-brand-2xl p-8 backdrop-blur-sm relative overflow-hidden group">
                            <div className="absolute top-0 right-0 w-32 h-32 bg-teal/5 rounded-full -mr-16 -mt-16 blur-2xl group-hover:bg-teal/10 transition-all" />
                            <div className="relative z-10 flex flex-col md:flex-row items-center gap-6">
                                <div className="text-center md:text-left flex-1">
                                    <h3 className="font-display text-xl text-espresso mb-2">Verified the Person, but not the <em className="italic">Skills?</em></h3>
                                    <p className="text-xs text-muted leading-relaxed">
                                        Identity verification is just the first step. Our <strong>Full Match Service</strong> finds you helpers who have been vetted for character, domestic competency, and past reliability.
                                    </p>
                                </div>
                                <Link href="/onboarding" className="bg-white border border-teal/20 text-teal px-6 py-3 rounded-brand-md text-xs font-bold hover:bg-teal hover:text-white transition-all shadow-sm flex-shrink-0 whitespace-nowrap">
                                    Switch to Full Match →
                                </Link>
                            </div>
                        </div>

                        <div className="mt-12 text-center pb-20">
                            <Link href="/" className="text-xs text-muted hover:text-espresso underline">Return to Homepage</Link>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
