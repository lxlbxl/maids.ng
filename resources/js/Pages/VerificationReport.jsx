import { Head, Link } from '@inertiajs/react';

export default function VerificationReport({ verification }) {
    const data = verification.verification_data?.data || {};
    const isSuccess = verification.verification_status === 'success';

    const handlePrint = () => {
        window.print();
    };

    return (
        <>
            <Head title={`Verification Report - ${verification.maid_first_name} ${verification.maid_last_name}`} />
            <div className="min-h-screen bg-ivory py-12 px-6 print:p-0 print:bg-white">
                <div className="max-w-3xl mx-auto">
                    {/* Report Card */}
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
                            <div className={`p-6 rounded-brand-lg border flex items-center gap-4 ${
                                isSuccess ? 'bg-success/5 border-success/20' : 'bg-error/5 border-error/20'
                            }`}>
                                <div className={`w-12 h-12 rounded-full flex items-center justify-center text-2xl ${
                                    isSuccess ? 'bg-success text-white' : 'bg-error text-white'
                                }`}>
                                    {isSuccess ? '✓' : '✕'}
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

                            {/* Main Content */}
                            <div className="grid md:grid-cols-3 gap-8">
                                {/* Photo */}
                                <div className="space-y-3">
                                    <p className="text-[10px] font-mono uppercase tracking-widest text-muted">Identity Image</p>
                                    <div className="aspect-square bg-gray-50 rounded-brand-lg border border-gray-100 flex items-center justify-center overflow-hidden">
                                        {data.photo ? (
                                            <img src={data.photo} alt="Identity Photo" className="w-full h-full object-cover" />
                                        ) : (
                                            <span className="text-4xl">👤</span>
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
                                            <p className="text-espresso font-medium">{data.dob || '—'}</p>
                                        </div>
                                        <div>
                                            <p className="text-[10px] text-muted uppercase">Gender</p>
                                            <p className="text-espresso font-medium capitalize">{data.gender || '—'}</p>
                                        </div>
                                        <div>
                                            <p className="text-[10px] text-muted uppercase">Match Confidence</p>
                                            <div className="flex items-center gap-2">
                                                <div className="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                    <div className="h-full bg-teal" style={{ width: `${verification.verification_data?.confidence || 0}%` }} />
                                                </div>
                                                <span className="text-xs font-mono font-bold text-teal">{verification.verification_data?.confidence || 0}%</span>
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
                                        <span className="font-medium">{verification.requester?.name}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="p-8 border-t border-gray-100 bg-ivory/30 text-center">
                            <p className="text-xs text-muted max-w-lg mx-auto">
                                This report is generated by Maids.ng automated verification system. 
                                It is based on information provided by the Nigerian National Identity Management Commission (NIMC).
                            </p>
                            <div className="mt-6 flex justify-center gap-4 print:hidden">
                                <button 
                                    onClick={handlePrint}
                                    className="px-6 py-3 bg-espresso text-white rounded-brand-md text-sm font-bold hover:bg-espresso/90 transition-all shadow-lg"
                                >
                                    Download PDF Report
                                </button>
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
