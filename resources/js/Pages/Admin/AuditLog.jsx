import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function AuditLog({ auth, logs }) {
    return (
        <AdminLayout>
            <Head title="Intelligence Feed | Mission Control" />
            
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Central Intelligence Feed</h1>
                    <p className="text-white/40 text-sm font-light">Comprehensive real-time reporting of all autonomous agent operations.</p>
                </div>
                <div className="flex gap-4">
                    <button className="bg-white/5 border border-white/10 px-4 py-2 text-[10px] font-mono uppercase tracking-widest text-white/60 hover:text-white transition-all">Filter: All Agents</button>
                    <button className="bg-white/5 border border-white/10 px-4 py-2 text-[10px] font-mono uppercase tracking-widest text-white/60 hover:text-white transition-all">Confidence: {'>'}0%</button>
                </div>
            </div>

            <div className="bg-[#121214] border border-white/5 rounded-brand-lg overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-8 py-5 font-bold">Status</th>
                                <th className="px-8 py-5 font-bold">Agent [ID]</th>
                                <th className="px-8 py-5 font-bold">Protocol / Action</th>
                                <th className="px-8 py-5 font-bold">Logic Reasoning</th>
                                <th className="px-8 py-5 font-bold text-center">Confidence</th>
                                <th className="px-8 py-5 font-bold text-right">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {logs.data.map(log => (
                                <tr key={log.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-8 py-5">
                                        <div className={`w-2 h-2 rounded-full ${log.decision === 'approved' ? 'bg-teal shadow-[0_0_8px_rgba(45,164,142,0.8)]' : log.decision === 'rejected' ? 'bg-danger shadow-[0_0_8px_rgba(235,87,87,0.8)]' : 'bg-copper animate-pulse'}`}></div>
                                    </td>
                                    <td className="px-8 py-5 font-mono text-[11px] font-bold text-white/80 uppercase tracking-tighter">
                                        {log.agent_name} <span className="text-white/10">[#{log.id}]</span>
                                    </td>
                                    <td className="px-8 py-5 text-white/60 font-mono text-[10px] uppercase">
                                        {log.action}
                                    </td>
                                    <td className="px-8 py-5">
                                        <p className="text-xs text-white/40 italic leading-relaxed max-w-sm line-clamp-1 group-hover:line-clamp-none transition-all">"{log.reasoning}"</p>
                                    </td>
                                    <td className="px-8 py-5 text-center">
                                        <span className={`font-mono text-[11px] ${log.confidence_score >= 90 ? 'text-teal font-bold' : log.confidence_score >= 70 ? 'text-white/60' : 'text-copper italic'}`}>
                                            {log.confidence_score}%
                                        </span>
                                    </td>
                                    <td className="px-8 py-5 text-right font-mono text-[10px] text-white/20 uppercase tracking-widest leading-tight">
                                        {new Date(log.created_at).toLocaleDateString()}<br/>
                                        {new Date(log.created_at).toLocaleTimeString()}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {logs.links && logs.links.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {logs.links.map((link, k) => (
                        <Link
                            key={k}
                            href={link.url || '#'}
                            className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal shadow-[0_0_15px_rgba(45,164,142,0.3)]' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed hidden' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
