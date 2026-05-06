import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function VerificationTransactionDetail({ auth, verification }) {
    const { post, processing } = useForm();

    const handleStatusUpdate = (status) => {
        if (confirm(`Mark this verification as "${status}"?`)) {
            post(`/admin/verification-transactions/${verification.id}`, {
                verification_status: status,
            });
        }
    };

    const qoreData = verification?.qoreid_data || {};
    const isSuccess = verification?.verification_status === 'success';

    return (
        <AdminLayout>
            <Head title={`Verification ${verification?.payment_reference} | Mission Control`} />

            <div className="mb-8">
                <Link href="/admin/verification-transactions" className="text-white/40 hover:text-white text-sm transition-colors mb-4 inline-block">← Back to Transactions</Link>
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-display text-3xl font-light tracking-tight text-white mb-1">{verification?.payment_reference}</h1>
                        <p className="text-white/30 text-sm font-mono">{verification?.external_reference ? `QoreID: ${verification.external_reference}` : 'No external reference'}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className={`px-4 py-1.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-widest border ${verification?.payment_status === 'paid' ? 'bg-success/10 text-success border-success/20' :
                                verification?.payment_status === 'failed' ? 'bg-danger/10 text-danger border-danger/20' :
                                    'bg-copper/10 text-copper border-copper/20'
                            }`}>
                            Payment: {verification?.payment_status || 'pending'}
                        </span>
                        <span className={`px-4 py-1.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-widest border ${verification?.verification_status === 'success' ? 'bg-success/10 text-success border-success/20' :
                                verification?.verification_status === 'failed' ? 'bg-danger/10 text-danger border-danger/20' :
                                    'bg-copper/10 text-copper border-copper/20'
                            }`}>
                            Verification: {verification?.verification_status || 'pending'}
                        </span>
                    </div>
                </div>
            </div>

            {/* Action Buttons */}
            <div className="flex gap-3 mb-8">
                <button
                    onClick={() => handleStatusUpdate('success')}
                    disabled={processing}
                    className="bg-success/20 text-success border border-success/30 px-6 py-2.5 rounded-brand-md text-[10px] font-mono uppercase tracking-widest font-bold hover:bg-success hover:text-espresso transition-all"
                >
                    Mark Success
                </button>
                <button
                    onClick={() => handleStatusUpdate('failed')}
                    disabled={processing}
                    className="bg-danger/20 text-danger border border-danger/30 px-6 py-2.5 rounded-brand-md text-[10px] font-mono uppercase tracking-widest font-bold hover:bg-danger hover:text-white transition-all"
                >
                    Mark Failed
                </button>
                {verification?.payment_reference && (
                    <a
                        href={`/verification-report/${verification.payment_reference}`}
                        target="_blank"
                        className="bg-white/5 text-white/40 border border-white/10 px-6 py-2.5 rounded-brand-md text-[10px] font-mono uppercase tracking-widest font-bold hover:bg-white/10 transition-all"
                    >
                        View Public Report
                    </a>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Requester Info */}
                <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Requester</h3>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Name</span>
                            <span className="text-white text-sm font-medium">{verification?.requester_name || '—'}</span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Email</span>
                            <span className="text-white text-sm font-medium">{verification?.requester_email || '—'}</span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Account</span>
                            <span className="text-white text-sm font-medium">{verification?.requester?.name || 'Guest'}</span>
                        </div>
                        {verification?.requester && (
                            <div className="flex items-center justify-between border-b border-white/5 pb-3">
                                <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Account ID</span>
                                <Link href={`/admin/users/${verification.requester.id}`} className="text-teal text-sm font-mono hover:underline">
                                    #{verification.requester.id}
                                </Link>
                            </div>
                        )}
                    </div>
                </div>

                {/* Subject Info */}
                <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Subject (NIN Holder)</h3>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Full Name</span>
                            <span className="text-white text-sm font-medium">{verification?.maid_first_name} {verification?.maid_middle_name} {verification?.maid_last_name}</span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">NIN</span>
                            <span className="text-white text-sm font-mono">{verification?.maid_nin ? `${verification.maid_nin.slice(0, 3)}****${verification.maid_nin.slice(-3)}` : '—'}</span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">DOB</span>
                            <span className="text-white text-sm">{verification?.maid_dob || qoreData?.dob || '—'}</span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Gender</span>
                            <span className="text-white text-sm capitalize">{verification?.maid_gender || qoreData?.gender || '—'}</span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Phone</span>
                            <span className="text-white text-sm">{qoreData?.phone || verification?.maid_phone || '—'}</span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Email</span>
                            <span className="text-white text-sm">{qoreData?.email || verification?.maid_email || '—'}</span>
                        </div>
                    </div>
                </div>

                {/* Payment & Verification */}
                <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Payment & Verification</h3>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Amount</span>
                            <span className="text-white text-sm font-bold">₦{Number(verification?.amount || 0).toLocaleString()}</span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Gateway</span>
                            <span className="text-white text-sm capitalize">{verification?.gateway || '—'}</span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Confidence</span>
                            <span className={`text-sm font-bold ${verification?.confidence_score >= 80 ? 'text-success' : verification?.confidence_score >= 50 ? 'text-copper' : 'text-danger'}`}>
                                {verification?.confidence_score || 0}%
                            </span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Name Match</span>
                            <span className={`text-sm font-bold ${verification?.name_matched ? 'text-success' : 'text-danger'}`}>
                                {verification?.name_matched ? 'Yes' : 'No'}
                            </span>
                        </div>
                        <div className="flex items-center justify-between border-b border-white/5 pb-3">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Created</span>
                            <span className="text-white text-sm">{verification?.created_at ? new Date(verification.created_at).toLocaleString() : '—'}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">Updated</span>
                            <span className="text-white text-sm">{verification?.updated_at ? new Date(verification.updated_at).toLocaleString() : '—'}</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* QoreID Response Data */}
            {Object.keys(qoreData).length > 0 && (
                <div className="mt-6 bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">QoreID NIN Premium Response</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {qoreData?.photo && (
                            <div className="md:col-span-1">
                                <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 mb-2">Photo</p>
                                <div className="aspect-square bg-white/5 rounded-brand-lg border border-white/10 overflow-hidden">
                                    <img src={qoreData.photo} alt="NIN Photo" className="w-full h-full object-cover" />
                                </div>
                            </div>
                        )}
                        <div className={qoreData?.photo ? 'md:col-span-2' : 'md:col-span-3'}>
                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 mb-3">Full Response Data</p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {[
                                    { label: 'NIN', value: qoreData?.nin },
                                    { label: 'First Name', value: qoreData?.firstname },
                                    { label: 'Last Name', value: qoreData?.lastname },
                                    { label: 'Middle Name', value: qoreData?.middlename },
                                    { label: 'Title', value: qoreData?.title },
                                    { label: 'DOB', value: qoreData?.birthdate },
                                    { label: 'Gender', value: qoreData?.gender },
                                    { label: 'Phone', value: qoreData?.phone },
                                    { label: 'Email', value: qoreData?.email },
                                    { label: 'Height', value: qoreData?.height },
                                    { label: 'Profession', value: qoreData?.profession },
                                    { label: 'Marital Status', value: qoreData?.maritalStatus },
                                    { label: 'Employment', value: qoreData?.employmentStatus },
                                    { label: 'Birth State', value: qoreData?.birthState },
                                    { label: 'Birth Country', value: qoreData?.birthCountry },
                                    { label: 'Religion', value: qoreData?.religion },
                                    { label: 'Nationality', value: qoreData?.nationality },
                                    { label: 'LGA of Origin', value: qoreData?.lgaOfOrigin },
                                    { label: 'State of Origin', value: qoreData?.stateOfOrigin },
                                    { label: 'Language (N)', value: qoreData?.nspokenlang },
                                    { label: 'Language (O)', value: qoreData?.ospokenlang },
                                    { label: 'Parent Lastname', value: qoreData?.parentLastname },
                                ].filter(item => item.value).map(item => (
                                    <div key={item.label} className="flex justify-between bg-white/[0.02] rounded px-3 py-2">
                                        <span className="text-[10px] font-mono uppercase tracking-widest text-white/30">{item.label}</span>
                                        <span className="text-white text-xs font-medium">{item.value}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Optional Fields Submitted */}
            {verification?.optional_fields && Object.keys(verification.optional_fields).length > 0 && (
                <div className="mt-6 bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Optional Fields Submitted</h3>
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                        {Object.entries(verification.optional_fields).map(([key, value]) => (
                            <div key={key} className="flex justify-between bg-white/[0.02] rounded px-3 py-2">
                                <span className="text-[10px] font-mono uppercase tracking-widest text-white/30">{key}</span>
                                <span className="text-white text-xs font-medium">{String(value)}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}