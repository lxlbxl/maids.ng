import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function AdminDashboard({ auth, stats, agentHealth = [], escalationCount = 0, escrowTotal = 0, recentActivity = [] }) {
    return (
        <AdminLayout>
            <Head title="Mission Control | Admin" />
            
            {/* Header Section */}
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Platform Command Center</h1>
                    <div className="flex items-center gap-4">
                        <span className="flex items-center gap-2 text-[10px] font-mono uppercase tracking-[0.2em] text-white/40">
                            <span className="w-2 h-2 rounded-full bg-teal animate-pulse"></span>
                            System Status: Nominal
                        </span>
                        <span className="text-white/10 text-xs">|</span>
                        <span className="text-[10px] font-mono uppercase tracking-[0.2em] text-white/40">
                            Agent Load: Optimizing
                        </span>
                    </div>
                </div>
                
                {escalationCount > 0 && (
                    <Link href="/admin/escalations" className="bg-danger/20 border border-danger/30 px-6 py-3 rounded-brand-md flex items-center gap-4 group hover:bg-danger/30 transition-all">
                        <div className="w-10 h-10 bg-danger text-white rounded-full flex items-center justify-center text-xl shadow-[0_0_15px_rgba(235,87,87,0.4)] group-hover:scale-110 transition-transform">⚠️</div>
                        <div>
                            <p className="text-[10px] font-mono uppercase tracking-widest text-danger-light font-bold">Priority Escalations</p>
                            <p className="text-sm font-bold text-white">{escalationCount} Action Required</p>
                        </div>
                    </Link>
                )}
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-4 gap-8 mb-10">
                {/* Agent Health Monitoring Gauges */}
                <div className="xl:col-span-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {['Sentinel', 'Treasurer', 'Referee', 'Gatekeeper'].map(agentName => {
                        const data = agentHealth.find(a => a.agent_name === agentName) || { decision_count: 0, avg_confidence: 0 };
                        return (
                            <div key={agentName} className="bg-[#121214] border border-white/5 rounded-brand-lg p-6 hover:border-teal/20 transition-all group">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="font-mono text-[10px] uppercase tracking-widest text-white/30">{agentName} Agent</h3>
                                    <span className="text-xs group-hover:scale-125 transition-transform">{agentName === 'Sentinel' ? '🕵️' : agentName === 'Treasurer' ? '💰' : agentName === 'Referee' ? '⚖️' : '🛡️'}</span>
                                </div>
                                <div className="space-y-4">
                                    <div>
                                        <div className="flex justify-between items-end mb-1">
                                            <p className="text-2xl font-light text-white">{data.decision_count}</p>
                                            <p className="text-[9px] font-mono text-white/20 uppercase">Decisions</p>
                                        </div>
                                        <div className="w-full h-0.5 bg-white/5 rounded-full">
                                            <div className="h-full bg-white/20 w-full"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div className="flex justify-between items-end mb-1">
                                            <p className="text-xl font-bold text-teal">{Math.round(data.avg_confidence)}%</p>
                                            <p className="text-[9px] font-mono text-white/20 uppercase">Confidence</p>
                                        </div>
                                        <div className="w-full h-1 bg-white/5 rounded-full overflow-hidden">
                                            <div className="h-full bg-teal shadow-[0_0_8px_rgba(45,164,142,0.8)]" style={{ width: `${data.avg_confidence}%` }}></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Financial Overview (The Alex/Business Focus) */}
                <div className="bg-teal text-espresso rounded-brand-lg p-6 shadow-[0_0_30px_rgba(45,164,142,0.15)] flex flex-col justify-between">
                    <div>
                        <p className="font-mono text-[10px] uppercase tracking-[0.2em] text-espresso/40 font-bold mb-4">Escrow Management</p>
                        <h2 className="text-3xl font-bold mb-1">₦{escrowTotal?.toLocaleString()}</h2>
                        <p className="text-xs text-espresso/60 font-medium italic">Funds supervised by Treasurer Agent</p>
                    </div>
                    <div className="mt-8 border-t border-espresso/10 pt-4">
                        <div className="flex justify-between items-center text-[10px] font-mono uppercase font-bold text-espresso/50">
                            <span>Target</span>
                            <span>₦1.5M/mo</span>
                        </div>
                        <div className="w-full h-1 bg-espresso/5 rounded-full mt-2 overflow-hidden">
                            <div className="h-full bg-espresso/20 w-[65%]"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Central Intelligence Feed */}
                <div className="lg:col-span-2 bg-[#121214] border border-white/5 rounded-brand-lg overflow-hidden flex flex-col">
                    <div className="px-8 py-6 border-b border-white/5 flex items-center justify-between">
                        <h2 className="font-display text-xl">Intelligence Feed</h2>
                        <Link href="/admin/audit" className="text-[10px] font-mono uppercase tracking-[0.2em] text-teal hover:underline">Full Log Report →</Link>
                    </div>
                    <div className="flex-1 min-h-[400px]">
                        {recentActivity.length > 0 ? (
                            <div className="divide-y divide-white/5">
                                {recentActivity.map((log) => (
                                    <div key={log.id} className="px-8 py-4 hover:bg-white/5 transition-colors flex items-center gap-6">
                                        <div className="w-10 h-10 rounded shadow-inner bg-[#0a0a0b] border border-white/5 flex items-center justify-center text-lg">{log.agent_name === 'Sentinel' ? '🕵️' : log.agent_name === 'Treasurer' ? '💰' : log.agent_name === 'Referee' ? '⚖️' : '🛡️'}</div>
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-1">
                                                <span className="text-[10px] font-mono text-teal uppercase font-bold tracking-widest">{log.agent_name}</span>
                                                <span className="text-white/20 text-[10px]">•</span>
                                                <span className="text-[10px] font-mono text-white/40 uppercase">{log.action}</span>
                                            </div>
                                            <p className="text-xs text-white/60 line-clamp-1 italic">"{log.reasoning}"</p>
                                        </div>
                                        <div className="text-right">
                                            <p className={`text-xs font-bold uppercase tracking-tighter ${log.decision === 'approved' ? 'text-teal' : log.decision === 'rejected' ? 'text-danger' : 'text-copper'}`}>{log.decision}</p>
                                            <p className="text-[9px] font-mono text-white/20 uppercase">{new Date(log.created_at).toLocaleTimeString()}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="h-full flex flex-col items-center justify-center text-white/20 gap-4">
                                <span className="text-4xl">📡</span>
                                <p className="text-sm font-mono uppercase tracking-widest">Awaiting Agent Transmission...</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Platform Health Matrix */}
                <div className="space-y-6">
                    <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-8">
                        <h2 className="font-display text-xl mb-6">User Acquisition</h2>
                        <div className="space-y-6">
                            {[
                                { label: 'Helpers', value: stats.total_maids, limit: 100, color: 'bg-teal' },
                                { label: 'Employers', value: stats.total_employers, limit: 50, color: 'bg-white/20' },
                            ].map(item => (
                                <div key={item.label}>
                                    <div className="flex justify-between items-end mb-2">
                                        <p className="font-mono text-[10px] uppercase tracking-widest text-white/40">{item.label}</p>
                                        <p className="text-xs font-bold">{item.value}/{item.limit}</p>
                                    </div>
                                    <div className="w-full h-1 bg-white/5 rounded-full overflow-hidden">
                                        <div className={`h-full ${item.color}`} style={{ width: `${(item.value / item.limit) * 100}%` }}></div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="bg-copper/5 border border-copper/10 rounded-brand-lg p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <span className="text-xl">🛠️</span>
                            <h3 className="font-display text-lg text-copper">System Load</h3>
                        </div>
                        <p className="text-xs text-white/50 leading-relaxed mb-6 italic">
                            Agent processing latency is currently within normal operating parameters (avg 240ms). No interventions required.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {['Auto-Scale', 'AI Throttling', 'Manual Logic'].map(tag => (
                                <span key={tag} className="text-[9px] font-mono uppercase tracking-[0.15em] px-2 py-1 bg-white/5 text-white/40 rounded border border-white/10">{tag}</span>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
