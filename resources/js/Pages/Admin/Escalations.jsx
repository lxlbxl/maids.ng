import { Head, Link, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

function SortIcon({ field, sort, dir }) {
    if (field !== sort) return <span className="text-white/10 ml-1">⇅</span>;
    return <span className="text-teal ml-1">{dir === 'asc' ? '▲' : '▼'}</span>;
}

export default function Escalations({ auth, escalations, filters = {} }) {
    const { post, processing } = useForm();
    const [filterState, setFilterState] = useState({
        search: filters.search || '',
        agent: filters.agent || '',
        sort: filters.sort || 'newest',
    });
    const sortDir = filterState.sort === 'oldest' ? 'asc' : 'desc';

    const applyFilters = () => {
        const p = {}; Object.entries(filterState).forEach(([k, v]) => { if (v) p[k] = v; });
        router.get('/admin/escalations', p, { preserveState: true, replace: true });
    };
    const clearFilters = () => {
        setFilterState({ search: '', agent: '', sort: 'newest' });
        router.get('/admin/escalations', {}, { preserveState: true, replace: true });
    };
    const toggleSort = () => {
        const ns = filterState.sort === 'oldest' ? 'newest' : 'oldest';
        setFilterState(s => ({ ...s, sort: ns }));
        router.get('/admin/escalations', { ...filterState, sort: ns }, { preserveState: true, replace: true });
    };

    const handleResolve = (logId, resolution) => {
        post(route('admin.escalations.resolve', { id: logId, resolution }));
    };
    const thClass = "px-6 py-4 font-bold cursor-pointer hover:text-white transition-colors select-none";

    return (<AdminLayout><Head title="Intervention Queue | Mission Control" />
        <div className="mb-10"><h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Priority Intervention Queue</h1><p className="text-white/40 text-sm font-light italic">Items requiring human ethical judgment from AI agents.</p></div>

        <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-4 mb-4">
            <div className="flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[180px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Search</label><input type="text" value={filterState.search} onChange={e => setFilterState(s => ({ ...s, search: e.target.value }))} onKeyDown={e => e.key === 'Enter' && applyFilters()} placeholder="Search reasoning..." className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white placeholder-white/20 focus:border-teal outline-none" /></div>
                <div className="w-[160px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Agent</label><select value={filterState.agent} onChange={e => setFilterState(s => ({ ...s, agent: e.target.value }))} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none"><option value="">All Agents</option><option value="Gatekeeper">Gatekeeper</option><option value="Referee">Referee</option><option value="Scout">Scout</option><option value="Sentinel">Sentinel</option><option value="Treasurer">Treasurer</option></select></div>
                <div className="flex gap-2"><button onClick={applyFilters} className="h-10 px-4 bg-teal text-white text-xs font-bold rounded-brand-md hover:bg-teal/80">Apply</button><button onClick={clearFilters} className="h-10 px-3 bg-white/5 text-white/40 text-xs font-bold rounded-brand-md hover:bg-white/10">Clear</button></div>
            </div>
        </div>

        <div className="grid grid-cols-1 gap-6">
            {escalations.data.length > 0 ? (
                escalations.data.map((log) => (
                    <div key={log.id} className="bg-[#121214] border border-white/5 rounded-brand-xl p-8 flex flex-col xl:flex-row gap-8 items-start hover:border-amber-500/30 transition-all group overflow-hidden relative">
                        <div className="absolute top-0 right-0 w-32 h-32 bg-amber-500/5 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-amber-500/10 transition-all"></div>
                        <div className="flex-1 space-y-6 relative z-10">
                            <div className="flex items-center gap-4">
                                <div className="w-12 h-12 bg-white/5 border border-white/10 rounded-full flex items-center justify-center text-2xl shadow-inner">{log.agent_name === 'Referee' ? '⚖️' : log.agent_name === 'Gatekeeper' ? '🛡️' : '🤖'}</div>
                                <div>
                                    <div className="flex items-center gap-3"><h3 className="font-display text-xl">{log.agent_name} Agent Escalation</h3><span className="bg-amber-500 text-white text-[9px] font-mono px-2 py-0.5 rounded font-bold animate-pulse uppercase">Action Required</span></div>
                                    <p className="text-[10px] font-mono text-white/30 uppercase tracking-[0.2em] mt-1">Ref ID: MSG-{log.id} • Protocol: {log.action}</p>
                                </div>
                            </div>
                            <div className="bg-[#0a0a0b] border border-white/5 p-6 rounded-brand-lg"><p className="text-[10px] font-mono uppercase tracking-[0.2em] text-white/20 mb-3">Agent Reasoning:</p><p className="text-sm text-white/70 leading-relaxed italic border-l-2 border-amber-500/40 pl-6 py-1">"{log.reasoning}"</p></div>
                            <div className="grid md:grid-cols-2 gap-4">
                                <div className="bg-white/5 p-4 rounded-brand-lg border border-white/5"><p className="text-[9px] font-mono uppercase tracking-widest text-white/20 mb-1">Subject</p><p className="text-sm font-bold text-teal">{log.subject_type?.split('\\').pop()} #{log.subject_id}</p></div>
                                <div className="bg-white/5 p-4 rounded-brand-lg border border-white/5"><p className="text-[9px] font-mono uppercase tracking-widest text-white/20 mb-1">Confidence</p><div className="flex items-center gap-3"><div className="flex-1 h-1 bg-white/10 rounded-full overflow-hidden"><div className="h-full bg-amber-500" style={{ width: `${log.confidence_score}%` }}></div></div><span className="text-xs font-mono text-amber-400 font-bold">{log.confidence_score}%</span></div></div>
                            </div>
                        </div>
                        <div className="w-full xl:w-80 space-y-4 relative z-10 self-center">
                            <p className="font-mono text-[10px] uppercase tracking-[0.2em] text-center text-white/40 mb-2">Override Protocol</p>
                            <button onClick={() => handleResolve(log.id, 'approve')} disabled={processing} className="w-full py-4 bg-teal text-black rounded-brand-lg text-xs font-bold uppercase tracking-widest hover:brightness-110 transition-all">Force Approval</button>
                            <button onClick={() => handleResolve(log.id, 'reject')} disabled={processing} className="w-full py-4 bg-white/5 border border-white/10 text-white rounded-brand-lg text-xs font-bold uppercase tracking-widest hover:bg-red-500/20 hover:border-red-500/40 transition-all">Issue Hard Rejection</button>
                        </div>
                    </div>
                ))
            ) : (
                <div className="h-[500px] flex flex-col items-center justify-center text-white/10 gap-6 border border-dashed border-white/10 rounded-brand-xl"><span className="text-6xl">🧘</span><div className="text-center"><h3 className="font-display text-2xl mb-2">System Tranquility</h3><p className="font-mono text-[10px] uppercase tracking-widest">No Priority Interrupts Detected</p></div></div>
            )}
        </div>

        {escalations?.links?.length > 3 && (
            <div className="mt-8 flex justify-center gap-1">{escalations.links.map((link, k) => (
                <Link key={k} href={link.url || '#'} className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
            ))}</div>
        )}
    </AdminLayout>);
}
