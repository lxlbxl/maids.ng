import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Escalations({ auth, escalations }) {
    const { post, processing } = useForm();

    const handleResolve = (logId, resolution) => {
        post(route('admin.escalations.resolve', { id: logId, resolution }));
    };

    return (
        <AdminLayout>
            <Head title="Intervention Queue | Mission Control" />
            
            <div className="mb-10">
                <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Priority Intervention Queue</h1>
                <p className="text-white/40 text-sm font-light italic">Items identified by AI agents as high-ambiguity or requiring human ethical judgment.</p>
            </div>

            <div className="grid grid-cols-1 gap-6">
                {escalations.data.length > 0 ? (
                    escalations.data.map((log) => (
                        <div key={log.id} className="bg-[#121214] border border-white/5 rounded-brand-xl p-8 flex flex-col xl:flex-row gap-8 items-start hover:border-copper/30 transition-all group overflow-hidden relative">
                            {/* Decorative Danger Background Accent */}
                            <div className="absolute top-0 right-0 w-32 h-32 bg-copper/5 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-copper/10 transition-all"></div>
                            
                            {/* Target Intel */}
                            <div className="flex-1 space-y-6 relative z-10">
                                <div className="flex items-center gap-4">
                                    <div className="w-12 h-12 bg-white/5 border border-white/10 rounded-full flex items-center justify-center text-2xl shadow-inner">
                                        {log.agent_name === 'Referee' ? '⚖️' : log.agent_name === 'Gatekeeper' ? '🛡️' : '🤖'}
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-3">
                                            <h3 className="font-display text-xl">{log.agent_name} Agent Escalation</h3>
                                            <span className="bg-copper text-white text-[9px] font-mono px-2 py-0.5 rounded tracking-widest font-bold animate-pulse uppercase">Action Required</span>
                                        </div>
                                        <p className="text-[10px] font-mono text-white/30 uppercase tracking-[0.2em] mt-1">Ref ID: MSG-{log.id} • Protocol: {log.action}</p>
                                    </div>
                                </div>

                                <div className="bg-[#0a0a0b] border border-white/5 p-6 rounded-brand-lg">
                                    <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-white/20 mb-3">Agent Reasoning Log:</p>
                                    <p className="text-sm text-white/70 leading-relaxed italic border-l-2 border-copper/40 pl-6 py-1">
                                        "{log.reasoning}"
                                    </p>
                                </div>
                                
                                <div className="grid md:grid-cols-2 gap-4">
                                    <div className="bg-white/5 p-4 rounded-brand-lg border border-white/5">
                                        <p className="text-[9px] font-mono uppercase tracking-widest text-white/20 mb-1">Subject Entity</p>
                                        <p className="text-sm font-bold text-teal">{log.subject_type?.split('\\').pop()} #{log.subject_id}</p>
                                    </div>
                                    <div className="bg-white/5 p-4 rounded-brand-lg border border-white/5 flex flex-col justify-center">
                                         <p className="text-[9px] font-mono uppercase tracking-widest text-white/20 mb-1">Confidence Rating</p>
                                         <div className="flex items-center gap-3">
                                            <div className="flex-1 h-1 bg-white/10 rounded-full overflow-hidden">
                                                <div className="h-full bg-copper" style={{ width: `${log.confidence_score}%` }}></div>
                                            </div>
                                            <span className="text-xs font-mono text-copper font-bold">{log.confidence_score}%</span>
                                         </div>
                                    </div>
                                </div>
                            </div>

                            {/* Human Override Controls */}
                            <div className="w-full xl:w-80 space-y-4 relative z-10 self-center">
                                <p className="font-mono text-[10px] uppercase tracking-[0.2em] text-center text-white/40 mb-2">Override Protocol</p>
                                <button 
                                    onClick={() => handleResolve(log.id, 'approve')}
                                    disabled={processing}
                                    className="w-full py-4 bg-teal text-espresso rounded-brand-lg text-xs font-bold uppercase tracking-widest hover:brightness-110 shadow-[0_0_20px_rgba(45,164,142,0.2)] transition-all"
                                >
                                    Force Approval
                                </button>
                                <button 
                                    onClick={() => handleResolve(log.id, 'reject')}
                                    disabled={processing}
                                    className="w-full py-4 bg-white/5 border border-white/10 text-white rounded-brand-lg text-xs font-bold uppercase tracking-widest hover:bg-danger/20 hover:border-danger/40 transition-all"
                                >
                                    Issue Hard Rejection
                                </button>
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="h-[500px] flex flex-col items-center justify-center text-white/10 gap-6 border border-dashed border-white/10 rounded-brand-xl">
                        <span className="text-6xl">🧘</span>
                        <div className="text-center">
                            <h3 className="font-display text-2xl mb-2">System Tranquility</h3>
                            <p className="font-mono text-[10px] uppercase tracking-widest">No Priority Interrupts Detected</p>
                        </div>
                    </div>
                )}
            </div>
            
            {/* Pagination omitted for brevity, assuming standard pattern applies */}
        </AdminLayout>
    );
}
