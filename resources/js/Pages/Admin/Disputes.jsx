import { Head, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

function SortIcon({ field, sort, dir }) {
    if (field !== sort) return <span className="text-white/10 ml-1">⇅</span>;
    return <span className="text-teal ml-1">{dir === 'asc' ? '▲' : '▼'}</span>;
}

export default function Disputes({ auth, disputes, stats, filters = {} }) {
    const [selectedDispute, setSelectedDispute] = useState(null);
    const [refundProcessing, setRefundProcessing] = useState(false);
    const [toastMessage, setToastMessage] = useState(null);
    const { data, setData, post, processing } = useForm({ notes: '' });
    const [filterState, setFilterState] = useState({
        search: filters.search || '',
        status: filters.status || '',
        sort: filters.sort || 'newest',
    });
    const sortDir = filterState.sort?.endsWith('_asc') ? 'asc' : 'desc';
    const sortKey = filterState.sort?.replace('_asc', '').replace('_desc', '') || 'newest';

    const applyFilters = () => {
        const p = {}; Object.entries(filterState).forEach(([k, v]) => { if (v) p[k] = v; });
        router.get('/admin/disputes', p, { preserveState: true, replace: true });
    };
    const clearFilters = () => {
        setFilterState({ search: '', status: '', sort: 'newest' });
        router.get('/admin/disputes', {}, { preserveState: true, replace: true });
    };
    const toggleSort = (field) => {
        const current = filterState.sort || 'newest';
        const newSort = current === field + '_desc' ? field + '_asc' : field + '_desc';
        setFilterState(s => ({ ...s, sort: newSort }));
        router.get('/admin/disputes', { ...filterState, sort: newSort }, { preserveState: true, replace: true });
    };

    const showToast = useCallback((message, type = 'success') => {
        setToastMessage({ message, type });
        setTimeout(() => setToastMessage(null), 4000);
    }, []);

    const handleResolve = (e) => {
        e.preventDefault();
        post(route('admin.disputes.resolve', selectedDispute.id), {
            onSuccess: () => { setSelectedDispute(null); setData('notes', ''); showToast('Dispute resolved successfully.'); },
            onError: () => showToast('Failed to resolve dispute.', 'error'),
        });
    };

    const handleRefund = () => {
        if (!selectedDispute) return;
        if (!confirm(`Initiate refund for dispute #DISP-${selectedDispute.id}?`)) return;
        setRefundProcessing(true);
        router.post(`/admin/disputes/${selectedDispute.id}/refund`, {}, {
            preserveScroll: true,
            onSuccess: () => { showToast('Refund initiated.'); setRefundProcessing(false); },
            onError: (e) => { showToast(e?.message || 'Failed.', 'error'); setRefundProcessing(false); },
        });
    };

    const thClass = "px-6 py-4 font-bold cursor-pointer hover:text-white transition-colors select-none";

    return (<AdminLayout><Head title="Dispute Management | Mission Control" />
        <AnimatePresence>{toastMessage && (
            <motion.div initial={{ opacity: 0, y: -30, x: '-50%' }} animate={{ opacity: 1, y: 0, x: '-50%' }} exit={{ opacity: 0, y: -30, x: '-50%' }}
                className={`fixed top-6 left-1/2 z-50 px-6 py-3 rounded-brand-lg shadow-brand-3 text-sm font-medium ${toastMessage.type === 'error' ? 'bg-red-900/90 border border-red-500/30 text-red-100' : 'bg-teal/90 border border-teal/30 text-white'}`}>
                {toastMessage.type === 'error' ? '✗ ' : '✓ '}{toastMessage.message}</motion.div>
        )}</AnimatePresence>

        <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div><h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Dispute Resolution</h1><p className="text-white/40 text-sm font-light">Mediating conflicts between employers and helpers.</p></div>
            <div className="flex gap-3">
                <div className="bg-[#121214] border border-white/5 rounded-brand-lg px-4 py-2 text-center"><span className="block font-mono text-[9px] uppercase text-white/30">Pending</span><span className="text-xl font-bold text-amber-400">{stats?.pending || 0}</span></div>
                <div className="bg-[#121214] border border-white/5 rounded-brand-lg px-4 py-2 text-center"><span className="block font-mono text-[9px] uppercase text-white/30">Resolved</span><span className="text-xl font-bold text-teal">{stats?.resolved || 0}</span></div>
            </div>
        </div>

        <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-4 mb-4">
            <div className="flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[180px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Search</label><input type="text" value={filterState.search} onChange={e => setFilterState(s => ({ ...s, search: e.target.value }))} onKeyDown={e => e.key === 'Enter' && applyFilters()} placeholder="Employer name or reason..." className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white placeholder-white/20 focus:border-teal outline-none" /></div>
                <div className="w-[150px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Status</label><select value={filterState.status} onChange={e => setFilterState(s => ({ ...s, status: e.target.value }))} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none"><option value="">All</option><option value="pending">Pending</option><option value="resolved">Resolved</option><option value="escalated">Escalated</option></select></div>
                <div className="flex gap-2"><button onClick={applyFilters} className="h-10 px-4 bg-teal text-white text-xs font-bold rounded-brand-md hover:bg-teal/80">Apply</button><button onClick={clearFilters} className="h-10 px-3 bg-white/5 text-white/40 text-xs font-bold rounded-brand-md hover:bg-white/10">Clear</button></div>
            </div>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <div className="xl:col-span-2 bg-[#121214] border border-white/5 rounded-brand-lg overflow-hidden shadow-2xl">
                <div className="overflow-x-auto"><table className="w-full text-left text-sm border-collapse">
                    <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                        <tr>
                            <th className={thClass} onClick={() => toggleSort('status')}>Status <SortIcon field="status" sort={sortKey} dir={sortDir} /></th>
                            <th className={thClass} onClick={() => toggleSort('priority')}>Priority <SortIcon field="priority" sort={sortKey} dir={sortDir} /></th>
                            <th className={thClass}>Employer</th>
                            <th className={thClass}>Reason</th>
                            <th className="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-white/5">
                        {disputes.data.map(dispute => (
                            <tr key={dispute.id} className={`hover:bg-white/[0.02] transition-colors cursor-pointer group ${selectedDispute?.id === dispute.id ? 'bg-white/5' : ''}`} onClick={() => setSelectedDispute(dispute)}>
                                <td className="px-6 py-4"><span className={`inline-flex px-2 py-0.5 rounded text-[10px] font-mono uppercase tracking-widest border ${dispute.status === 'resolved' ? 'bg-teal/10 text-teal border-teal/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20 animate-pulse'}`}>{dispute.status}</span></td>
                                <td className="px-6 py-4"><span className={`text-[10px] font-mono uppercase ${dispute.priority === 'high' ? 'text-red-400 font-bold' : 'text-white/40'}`}>{dispute.priority}</span></td>
                                <td className="px-6 py-4"><div><span className="font-bold text-white/80 text-xs">{dispute.user?.name}</span><span className="text-[10px] text-white/20 font-mono ml-1">#{dispute.user?.id}</span></div></td>
                                <td className="px-6 py-4"><p className="text-xs text-white/60 line-clamp-1 italic">"{dispute.reason}"</p></td>
                                <td className="px-6 py-4 text-right"><button onClick={(e) => { e.stopPropagation(); setSelectedDispute(dispute); }} className="text-[10px] font-mono uppercase tracking-widest text-teal hover:underline font-bold cursor-pointer">Investigate →</button></td>
                            </tr>
                        ))}
                        {disputes.data.length === 0 && (<tr><td colSpan="5" className="px-6 py-20 text-center"><div className="flex flex-col items-center gap-4 text-white/20"><span className="text-4xl">🕊️</span><p className="text-sm font-mono uppercase tracking-widest">No Active Disputes</p></div></td></tr>)}
                    </tbody>
                </table></div>
            </div>

            <div className="space-y-6">
                {selectedDispute ? (<div className="bg-[#121214] border border-white/10 rounded-brand-xl p-8 sticky top-8 shadow-2xl">
                    <div className="flex items-center justify-between mb-8 pb-4 border-b border-white/5"><h2 className="font-display text-xl">Case #DISP-{selectedDispute.id}</h2><button onClick={() => setSelectedDispute(null)} className="text-white/20 hover:text-white transition-colors">✕</button></div>
                    <div className="space-y-8">
                        <div><p className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-2">Description</p><p className="text-sm text-white/80 leading-relaxed italic border-l-2 border-amber-500/30 pl-4 py-1">"{selectedDispute.description}"</p></div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="bg-white/5 p-4 rounded border border-white/5"><p className="font-mono text-[8px] uppercase tracking-widest text-white/30 mb-1">Employer</p><p className="text-xs font-bold text-white/80">{selectedDispute.user?.name}</p></div>
                            <div className="bg-white/5 p-4 rounded border border-white/5"><p className="font-mono text-[8px] uppercase tracking-widest text-white/30 mb-1">Helper</p><p className="text-xs font-bold text-white/80">{selectedDispute.booking?.maid_profile?.user?.name || 'Assigned'}</p></div>
                        </div>
                        {selectedDispute.status !== 'resolved' ? (<form onSubmit={handleResolve} className="space-y-6 pt-6 border-t border-white/5">
                            <div className="space-y-2"><label className="font-mono text-[9px] uppercase tracking-widest text-white/30">Resolution Notes</label><textarea value={data.notes} onChange={e => setData('notes', e.target.value)} placeholder="Document final mediation decision..." className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-md p-4 text-xs text-white focus:border-teal/50 outline-none h-24 resize-none" required></textarea></div>
                            <div className="flex flex-col gap-3"><button type="submit" disabled={processing} className="w-full bg-teal text-black py-4 rounded-brand-lg text-[10px] font-bold uppercase tracking-widest hover:brightness-110">{processing ? 'Processing...' : 'Resolve & Close Case'}</button><button type="button" onClick={handleRefund} disabled={refundProcessing} className="w-full bg-red-500/10 text-red-400 border border-red-500/20 py-3 rounded-brand-lg text-[10px] font-mono tracking-widest uppercase hover:bg-red-500/20 transition-all disabled:opacity-50 cursor-pointer">{refundProcessing ? 'Processing...' : 'Initiate Refund'}</button></div>
                        </form>) : (<div className="bg-teal/5 border border-teal/20 p-6 rounded-brand-lg text-center"><span className="text-2xl mb-2 block">✅</span><p className="text-xs font-mono text-teal uppercase tracking-widest font-bold">Case Closed</p><p className="text-[10px] text-white/40 mt-2">Resolution logged in platform history.</p></div>)}
                    </div>
                </div>) : (<div className="bg-[#121214] border border-white/5 rounded-brand-xl p-8 text-center py-20 opacity-40"><span className="text-4xl block mb-4">📂</span><p className="text-xs font-mono uppercase tracking-widest">Select a case to begin mediation</p></div>)}
            </div>
        </div>
    </AdminLayout>);
}
