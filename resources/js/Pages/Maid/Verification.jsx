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

    return (
        <MaidLayout user={auth?.user}>
            <Head title="Identity Verification | Helper" />
            
            <div className="mb-8">
                <h1 className="font-display text-3xl font-light text-espresso">Identity Verification</h1>
                <p className="text-muted mt-2">Our Gatekeeper Agent uses top-tier security to verify your documents.</p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {/* NIN Verification Card */}
                <div className="space-y-6">
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6">
                        <div className="flex items-center gap-3 mb-6">
                            <div className="w-10 h-10 bg-teal/5 text-teal rounded-full flex items-center justify-center text-xl">🆔</div>
                            <h2 className="font-display text-xl text-espresso">National Identity Number (NIN)</h2>
                        </div>

                        {profile?.is_verified ? (
                            <div className="bg-success/5 border border-success/20 p-6 rounded-brand-lg flex flex-col items-center text-center">
                                <div className="w-16 h-16 bg-success text-white rounded-full flex items-center justify-center text-3xl mb-4 shadow-sm">✓</div>
                                <h3 className="text-success font-bold text-lg mb-1">Identity Verified</h3>
                                <p className="text-sm text-success/80">Your identity has been confirmed by the Gatekeeper Agent. You are now eligible for premium matches.</p>
                            </div>
                        ) : (
                            <form onSubmit={handleSubmitNin} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-mono uppercase tracking-widest text-muted mb-2">NIN Number (11 Digits)</label>
                                    <input 
                                        type="text" 
                                        value={data.nin}
                                        onChange={e => setData('nin', e.target.value)}
                                        placeholder="Enter your 11-digit NIN"
                                        className="w-full bg-ivory border-gray-200 rounded-brand-md px-4 py-3 focus:border-teal focus:ring-1 focus:ring-teal outline-none transition-all text-espresso text-sm tracking-widest"
                                        maxLength="11"
                                    />
                                    {errors.nin && <p className="text-danger text-xs mt-1">{errors.nin}</p>}
                                </div>

                                <div className="flex gap-3">
                                    <button 
                                        type="submit"
                                        disabled={processing}
                                        className="flex-1 bg-espresso text-white py-3 rounded-brand-md text-sm font-medium hover:bg-espresso/90 transition-all shadow-brand-1"
                                    >
                                        Update NIN
                                    </button>
                                    <button 
                                        type="button"
                                        onClick={handleVerify}
                                        disabled={processing || !profile?.nin}
                                        className="flex-1 bg-teal text-white py-3 rounded-brand-md text-sm font-medium hover:bg-teal-dark transition-all shadow-brand-1 disabled:opacity-50"
                                    >
                                        Verify with Gatekeeper
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>

                    {/* Verification Status List */}
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 font-display text-lg text-espresso">Verification Requirements</div>
                        <div className="divide-y divide-gray-100">
                            {[
                                { name: 'Initial NIN Verification', status: profile?.is_verified ? 'verified' : (profile?.nin ? 'pending' : 'not_started') },
                                { name: 'Criminal Background Check', status: profile?.background_verified ? 'verified' : 'pending_match' },
                                { name: 'Reference Verification', status: 'coming_soon' },
                            ].map((item, i) => (
                                <div key={i} className="px-6 py-4 flex items-center justify-between">
                                    <span className="text-sm text-espresso">{item.name}</span>
                                    <span className={`text-[10px] font-mono uppercase tracking-widest px-2 py-1 rounded ${
                                        item.status === 'verified' ? 'bg-success/10 text-success' : 
                                        item.status === 'pending' ? 'bg-copper/10 text-copper' : 'bg-gray-100 text-muted'
                                    }`}>
                                        {item.status.replace('_', ' ')}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Gatekeeper Insights */}
                <div className="bg-espresso text-white rounded-brand-lg p-8 shadow-brand-2 h-fit lg:sticky lg:top-8">
                    <div className="flex items-center gap-4 mb-8">
                        <div className="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center border border-white/20 text-2xl">🛡️</div>
                        <div>
                            <h3 className="font-display text-xl leading-tight">Gatekeeper<br/><span className="text-white/40 text-sm font-mono tracking-widest uppercase">Autonomous Agent</span></h3>
                        </div>
                    </div>

                    <p className="text-sm text-white/70 leading-relaxed mb-8">
                        The Gatekeeper Agent automatically processes your identity markers. High-confidence matches are approved instantly, while edge cases are escalated to human admins.
                    </p>

                    <div className="space-y-6">
                        <h4 className="text-[10px] font-mono uppercase tracking-[0.2em] text-white/40 mb-4 px-2">Recent Decision Logs:</h4>
                        {agentLogs.length > 0 ? agentLogs.map((log) => (
                            <div key={log.id} className="bg-white/5 border border-white/10 p-4 rounded-brand-lg">
                                <div className="flex items-center justify-between mb-2">
                                    <span className={`text-[10px] font-bold uppercase tracking-widest ${log.decision === 'approved' ? 'text-success' : 'text-danger'}`}>
                                        Decision: {log.decision}
                                    </span>
                                    <span className="text-[10px] text-white/40">{new Date(log.created_at).toLocaleDateString()}</span>
                                </div>
                                <p className="text-xs text-white/80 italic leading-relaxed">"{log.reasoning}"</p>
                                <div className="mt-3 flex items-center gap-2">
                                    <div className="flex-1 h-1 bg-white/10 rounded-full overflow-hidden">
                                        <div className="h-full bg-teal" style={{width: `${log.confidence_score}%`}}></div>
                                    </div>
                                    <span className="text-[10px] font-mono whitespace-nowrap text-white/30">{log.confidence_score}% Match</span>
                                </div>
                            </div>
                        )) : (
                            <div className="py-8 text-center border border-dashed border-white/10 rounded-brand-lg">
                                <p className="text-xs text-white/40">Waiting for first verification trigger...</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </MaidLayout>
    );
}
