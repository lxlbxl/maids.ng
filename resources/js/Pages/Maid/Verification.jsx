import { Head, useForm } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function Verification({ auth, profile, agentLogs = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        nin: profile?.nin || '',
    });

    const handleSubmitNin = (e) => {
        e.preventDefault();
        post(route('maid.verification.nin'));
    };

    const handleVerify = () => {
        post(route('maid.verification.nin.verify'));
    };

    const checklistItems = [
        {
            name: 'NIN Verification',
            description: 'Your National Identity Number (NIN)',
            status: profile?.is_verified ? 'done' : (profile?.nin ? 'waiting' : 'not_started'),
        },
        {
            name: 'Background Check',
            description: 'Criminal record check',
            status: profile?.background_verified ? 'done' : 'waiting',
        },
        {
            name: 'Reference Check',
            description: 'Contact from a past employer',
            status: 'coming_soon',
        },
    ];

    const statusBadge = (status) => {
        switch (status) {
            case 'done': return <span className="text-[10px] font-mono uppercase tracking-widest px-2 py-1 rounded bg-success/10 text-success">✅ Done</span>;
            case 'waiting': return <span className="text-[10px] font-mono uppercase tracking-widest px-2 py-1 rounded bg-copper/10 text-copper">⏳ Waiting</span>;
            case 'coming_soon': return <span className="text-[10px] font-mono uppercase tracking-widest px-2 py-1 rounded bg-gray-100 text-muted">Coming Soon</span>;
            default: return <span className="text-[10px] font-mono uppercase tracking-widest px-2 py-1 rounded bg-gray-100 text-muted">Not Started</span>;
        }
    };

    return (
        <MaidLayout user={auth?.user}>
            <Head title="Verify My Identity | Maids.ng" />
            
            <div className="mb-8">
                <h1 className="font-display text-3xl font-light text-espresso">Verify My Identity</h1>
                <p className="text-muted mt-2">Verifying your identity helps employers trust you more and gives you access to better jobs.</p>
            </div>

            {/* Why Verify Banner */}
            {!profile?.is_verified && (
                <div className="bg-teal/5 border border-teal/20 rounded-brand-lg p-5 mb-8 flex items-start gap-4">
                    <span className="text-2xl">💡</span>
                    <div>
                        <p className="font-semibold text-espresso text-sm">Why should I verify?</p>
                        <p className="text-muted text-sm mt-1">Verified helpers get <strong>more job offers</strong>, appear higher in search results, and employers trust them more. It only takes a few minutes!</p>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {/* NIN Form */}
                <div className="space-y-6">
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6">
                        <div className="flex items-center gap-3 mb-6">
                            <div className="w-10 h-10 bg-teal/5 text-teal rounded-full flex items-center justify-center text-xl">🆔</div>
                            <div>
                                <h2 className="font-display text-xl text-espresso">NIN Number</h2>
                                <p className="text-xs text-muted">Your National Identity Number</p>
                            </div>
                        </div>

                        {profile?.is_verified ? (
                            <div className="bg-success/5 border border-success/20 p-6 rounded-brand-lg flex flex-col items-center text-center">
                                <div className="w-16 h-16 bg-success text-white rounded-full flex items-center justify-center text-3xl mb-4 shadow-sm">✓</div>
                                <h3 className="text-success font-bold text-lg mb-1">Your Identity is Confirmed! 🎉</h3>
                                <p className="text-sm text-success/80">Your NIN has been checked and approved. Employers can now see that you are a verified helper.</p>
                            </div>
                        ) : (
                            <form onSubmit={handleSubmitNin} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-espresso mb-2">Enter Your NIN (11 numbers)</label>
                                    <input 
                                        type="text" 
                                        value={data.nin}
                                        onChange={e => setData('nin', e.target.value)}
                                        placeholder="e.g. 12345678901"
                                        className="w-full bg-ivory border border-gray-200 rounded-brand-md px-4 py-3 focus:border-teal focus:ring-1 focus:ring-teal outline-none transition-all text-espresso text-sm tracking-widest"
                                        maxLength="11"
                                    />
                                    {errors.nin && <p className="text-danger text-xs mt-1">{errors.nin}</p>}
                                    <p className="text-xs text-muted mt-2">Your NIN is an 11-digit number on your NIMC slip or National ID card.</p>
                                </div>

                                <div className="flex gap-3">
                                    <button 
                                        type="submit"
                                        disabled={processing}
                                        className="flex-1 bg-espresso text-white py-3 rounded-brand-md text-sm font-medium hover:bg-espresso/90 transition-all shadow-brand-1"
                                    >
                                        {processing ? 'Saving...' : 'Save My NIN'}
                                    </button>
                                    <button 
                                        type="button"
                                        onClick={handleVerify}
                                        disabled={processing || !profile?.nin}
                                        className="flex-1 bg-teal text-white py-3 rounded-brand-md text-sm font-medium hover:bg-teal-dark transition-all shadow-brand-1 disabled:opacity-50"
                                    >
                                        {processing ? 'Checking...' : 'Verify My NIN'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>

                    {/* Verification Checklist */}
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h3 className="font-display text-lg text-espresso">My Verification Checklist</h3>
                            <p className="text-xs text-muted mt-1">Complete all steps to become fully verified</p>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {checklistItems.map((item, i) => (
                                <div key={i} className="px-6 py-4 flex items-center justify-between gap-4">
                                    <div>
                                        <span className="text-sm font-medium text-espresso">{item.name}</span>
                                        <p className="text-xs text-muted">{item.description}</p>
                                    </div>
                                    {statusBadge(item.status)}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Info Sidebar */}
                <div className="bg-espresso text-white rounded-brand-lg p-8 shadow-brand-2 h-fit lg:sticky lg:top-8">
                    <div className="flex items-center gap-4 mb-8">
                        <div className="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center border border-white/20 text-2xl">🛡️</div>
                        <div>
                            <h3 className="font-display text-xl">Your Details Are Safe</h3>
                            <p className="text-white/50 text-xs mt-1">We take your privacy seriously</p>
                        </div>
                    </div>

                    <p className="text-sm text-white/70 leading-relaxed mb-8">
                        We only use your NIN to confirm that you are who you say you are. We do not share your NIN with anyone else, including employers.
                    </p>

                    <div className="space-y-4">
                        <div className="bg-white/5 border border-white/10 p-4 rounded-brand-lg flex items-start gap-3">
                            <span className="text-lg">🔒</span>
                            <p className="text-xs text-white/80 leading-relaxed">Your NIN is stored securely and is never shown to employers.</p>
                        </div>
                        <div className="bg-white/5 border border-white/10 p-4 rounded-brand-lg flex items-start gap-3">
                            <span className="text-lg">⚡</span>
                            <p className="text-xs text-white/80 leading-relaxed">Verification usually takes less than 5 minutes.</p>
                        </div>
                        <div className="bg-white/5 border border-white/10 p-4 rounded-brand-lg flex items-start gap-3">
                            <span className="text-lg">⭐</span>
                            <p className="text-xs text-white/80 leading-relaxed">Verified helpers earn more and get hired faster on Maids.ng.</p>
                        </div>
                    </div>

                    {agentLogs.length > 0 && (
                        <div className="mt-8">
                            <h4 className="text-[10px] font-mono uppercase tracking-[0.2em] text-white/40 mb-4">Recent Verification Activity:</h4>
                            <div className="space-y-3">
                                {agentLogs.map((log) => (
                                    <div key={log.id} className="bg-white/5 border border-white/10 p-4 rounded-brand-lg">
                                        <div className="flex items-center justify-between mb-2">
                                            <span className={`text-[10px] font-bold uppercase tracking-widest ${log.decision === 'approved' ? 'text-success' : 'text-danger'}`}>
                                                {log.decision === 'approved' ? '✅ Approved' : '❌ Not Approved'}
                                            </span>
                                            <span className="text-[10px] text-white/40">{new Date(log.created_at).toLocaleDateString()}</span>
                                        </div>
                                        <p className="text-xs text-white/80 italic leading-relaxed">{log.reasoning}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </MaidLayout>
    );
}
