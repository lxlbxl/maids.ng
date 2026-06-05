import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function VerificationReport({ verification, showProcessing }) {
    const [currentStatus, setCurrentStatus] = useState(verification.verification_status);
    const [confidence, setConfidence] = useState(verification.confidence_score);
    const [nameMatched, setNameMatched] = useState(verification.name_matched);
    const [isPolling, setIsPolling] = useState(showProcessing || false);

    useEffect(() => {
        if (!isPolling) return;

        const pollInterval = setInterval(async () => {
            try {
                const response = await fetch(
                    route('standalone-verification.status', verification.payment_reference),
                    { headers: { Accept: 'application/json' } }
                );

                if (response.ok) {
                    const data = await response.json();
                    setCurrentStatus(data.verification_status);
                    setConfidence(data.confidence_score);
                    setNameMatched(data.name_matched);

                    if (data.verification_status === 'success' || data.verification_status === 'failed') {
                        setIsPolling(false);
                        window.location.reload();
                    }
                }
            } catch (e) {
                console.error('Status poll error:', e);
            }
        }, 3000);

        return () => clearInterval(pollInterval);
    }, [isPolling]);

    const data = verification.verification_data?.data || {};
    const isSuccess = currentStatus === 'success' || verification.verification_status === 'success';
    const isProcessing = currentStatus === 'processing' || currentStatus === 'pending';
    const isServiceUnavailable = currentStatus === 'service_unavailable';
    const isFailed = currentStatus === 'failed' && !isServiceUnavailable;

    const handlePrint = () => {
        window.print();
    };

    return (
        <>
            <Head title={`Verification Report - ${verification.maid_first_name} ${verification.maid_last_name}`} />
            <div className="min-h-screen bg-ivory py-12 px-6 print:p-0 print:bg-white">
                <div className="max-w-3xl mx-auto">
                    <div className="bg-white rounded-brand-xl shadow-brand-3 border border-gray-100 overflow-hidden print:shadow-none print:border-none">
                        {/* Header */}
                        <div className="bg-espresso p-8 text-white flex justify-between items-center">
                            <div>
                                <img src="/maids-logo.png" alt="Maids.ng" className="h-8 mb-4 brightness-0 invert" />
                                <h1 className="text-xl font-display uppercase tracking-[0.2em] opacity-80">Identity Verification Report</h1>
                            </div>
                            <div className="text-right">
                                <p className="text-[10px] font-mono text-white/40 uppercase tracking-widest mb-1">Reference No</p>
                                <p className="font-mono text-lg">{verification.payment_reference}</p>
                            </div>
                        </div>

                        <div className="p-8 space-y-8">
                            {/* Status Banner */}
                            {isProcessing ? (
                                <div className="p-6 rounded-brand-lg border flex items-center gap-4 bg-blue-50 border-blue-200">
                                    <div className="w-12 h-12 rounded-full flex items-center justify-center bg-blue-500 text-white">
                                        <svg className="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 className="font-bold text-lg text-blue-700">Verifying Identity...</h2>
                                        <p className="text-sm text-blue-600">
                                            Our Gatekeeper AI is checking the NIMC database. This usually takes less than 60 seconds.
                                        </p>
                                        {isPolling && (
                                            <p className="text-xs text-blue-500 mt-1 animate-pulse">Checking for results...</p>
                                        )}
                                    </div>
                                </div>
                            ) : isServiceUnavailable ? (
                                <div className="p-6 rounded-brand-lg border flex items-center gap-4 bg-yellow-50 border-yellow-200">
                                    <div className="w-12 h-12 rounded-full flex items-center justify-center text-2xl bg-yellow-500 text-white">
                                        &#9888;
                                    </div>
                                    <div>
                                        <h2 className="font-bold text-lg text-yellow-700">Verification Service Delay</h2>
                                        <p className="text-sm text-yellow-600">
                                            Our verification provider is temporarily unavailable. Your request has been queued and will be processed automatically when the service is restored.
                                        </p>
                                        <p className="text-xs text-yellow-500 mt-2">
                                            No additional charges will be made. You will receive an email with the results when completed. Reference: {verification.payment_reference}
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <div className={`p-6 rounded-brand-lg border flex items-center gap-4 ${isSuccess ? 'bg-success/5 border-success/20' : 'bg-error/5 border-error/20'}`}>
                                    <div className={`w-12 h-12 rounded-full flex items-center justify-center text-2xl ${isSuccess ? 'bg-success text-white' : 'bg-error text-white'}`}>
                                        {isSuccess ? '\u2713' : '\u2715'}
                                    </div>
                                    <div>
                                        <h2 className={`font-bold text-lg ${isSuccess ? 'text-success' : 'text-error'}`}>
                                            {isSuccess ? 'Verification Successful' : 'Verification Failed'}
                                        </h2>
                                        <p className="text-sm text-muted">
                                            {isSuccess
                                                ? 'The identity details provided match the records in the National Identity Database (NIMC).'
                                                : 'The details provided could not be matched with any record in the National Identity Database.'}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* Main Content — only show when not processing */}
                            {!isProcessing && (
                                <>
                                    <div className="grid md:grid-cols-3 gap-8">
                                        {/* Photo */}
                                        <div className="space-y-3">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-muted">Identity Image</p>
                                            <div className="aspect-square bg-gray-50 rounded-brand-lg border border-gray-100 flex items-center justify-center overflow-hidden">
                                                {data.photo ? (
                                                    <img src={data.photo} alt="Identity Photo" className="w-full h-full object-cover" />
                                                ) : (
                                                    <span className="text-4xl">{isServiceUnavailable ? '\uD83D\uDD12' : '\uD83D\uDC64'}</span>
                                                )}
                                            </div>
                                            <p className="text-[10px] text-muted text-center italic">Image from National Database</p>
                                        </div>

                                        {/* Details */}
                                        <div className="md:col-span-2 space-y-6">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-muted border-b border-gray-50 pb-2">Verified Subject Details</p>
                                            <div className="grid grid-cols-2 gap-y-4 gap-x-8">
                                                <div>
                                                    <p className="text-[10px] text-muted uppercase">First Name</p>
                                                    <p className="text-espresso font-medium">{data.first_name || verification.maid_first_name}</p>
                                                </div>
                                                <div>
                                                    <p className="text-[10px] text-muted uppercase">Last Name</p>
                                                    <p className="text-espresso font-medium">{data.last_name || verification.maid_last_name}</p>
                                                </div>
                                                <div>
                                                    <p className="text-[10px] text-muted uppercase">NIN (Masked)</p>
                                                    <p className="text-espresso font-medium">{verification.maid_nin.slice(0, 3)}****{verification.maid_nin.slice(-3)}</p>
                                                </div>
                                                <div>
                                                    <p className="text-[10px] text-muted uppercase">Date of Birth</p>
                                                    <p className="text-espresso font-medium">{data.dob || '\u2014'}</p>
                                                </div>
                                                <div>
                                                    <p className="text-[10px] text-muted uppercase">Gender</p>
                                                    <p className="text-espresso font-medium capitalize">{data.gender || '\u2014'}</p>
                                                </div>
                                                <div>
                                                    <p className="text-[10px] text-muted uppercase">Match Confidence</p>
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                            <div className="h-full bg-teal" style={{ width: `${confidence || 0}%` }} />
                                                        </div>
                                                        <span className="text-xs font-mono font-bold text-teal">{confidence || 0}%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Additional Info */}
                                    <div className="bg-gray-50 p-6 rounded-brand-lg space-y-4">
                                        <p className="text-[10px] font-mono uppercase tracking-widest text-muted">Verification Metadata</p>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                            <div className="flex justify-between border-b border-gray-200/50 pb-2">
                                                <span className="text-muted">Verification Provider</span>
                                                <span className="font-medium">NIMC via QoreID</span>
                                            </div>
                                            <div className="flex justify-between border-b border-gray-200/50 pb-2">
                                                <span className="text-muted">Timestamp</span>
                                                <span className="font-medium">{new Date(verification.updated_at).toLocaleString()}</span>
                                            </div>
                                            <div className="flex justify-between border-b border-gray-200/50 pb-2">
                                                <span className="text-muted">Agent in Charge</span>
                                                <span className="font-medium text-teal">Gatekeeper</span>
                                            </div>
                                            <div className="flex justify-between border-b border-gray-200/50 pb-2">
                                                <span className="text-muted">Requested By</span>
                                                <span className="font-medium">{verification.requester_name || verification.requester?.name || '\u2014'}</span>
                                            </div>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>

                        {/* Footer */}
                        <div className="p-8 border-t border-gray-100 bg-ivory/30 text-center">
                            <p className="text-xs text-muted max-w-lg mx-auto">
                                This report is generated by Maids.ng automated verification system.
                                It is based on information provided by the Nigerian National Identity Management Commission (NIMC).
                            </p>
                            <div className="mt-6 flex justify-center gap-4 print:hidden">
                                {!isProcessing && (
                                    <button
                                        onClick={handlePrint}
                                        className="px-6 py-3 bg-espresso text-white rounded-brand-md text-sm font-bold hover:bg-espresso/90 transition-all shadow-lg"
                                    >
                                        Download PDF Report
                                    </button>
                                )}
                                <Link
                                    href="/verify-service"
                                    className="px-6 py-3 bg-white border border-gray-200 text-espresso rounded-brand-md text-sm font-bold hover:bg-gray-50 transition-all"
                                >
                                    Verify Another
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div className="mt-8 text-center print:hidden">
                        <Link href="/" className="text-muted text-sm hover:text-espresso underline">Return to Homepage</Link>
                    </div>
                </div>
            </div>
        </>
    );
}
