import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

function SortIcon({ field, sort, dir }) {
    if (field !== sort) return <span className="text-white/10 ml-1">⇅</span>;
    return <span className="text-teal ml-1">{dir === 'asc' ? '▲' : '▼'}</span>;
}

export default function Financials({ auth, payments, stats, filters = {} }) {
    const [filterState, setFilterState] = useState({
        search: filters.search || '',
        status: filters.status || '',
        sort: filters.sort || 'newest',
    });
    const sortDir = filterState.sort === 'oldest' ? 'asc' : 'desc';

    const applyFilters = () => {
        const p = {}; Object.entries(filterState).forEach(([k, v]) => { if (v) p[k] = v; });
        router.get('/admin/payments', p, { preserveState: true, replace: true });
    };
    const clearFilters = () => {
        setFilterState({ search: '', status: '', sort: 'newest' });
        router.get('/admin/payments', {}, { preserveState: true, replace: true });
    };
    const toggleSort = () => {
        const ns = filterState.sort === 'oldest' ? 'newest' : 'oldest';
        setFilterState(s => ({ ...s, sort: ns }));
        router.get('/admin/payments', { ...filterState, sort: ns }, { preserveState: true, replace: true });
    };
    const thClass = "px-6 py-4 font-bold cursor-pointer hover:text-white transition-colors select-none";

    return (<AdminLayout><Head title="Financial Control | Mission Control" />
        <div className="mb-10"><h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Financial Control</h1><p className="text-white/40 text-sm italic">Real-time ledger of platform revenue.</p></div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-6"><p className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">Total Revenue</p><p className="text-2xl font-bold text-teal">₦{stats.total_revenue.toLocaleString()}</p></div>
            <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-6"><p className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">Escrow Balance</p><p className="text-2xl font-bold text-white">₦{stats.escrow_balance.toLocaleString()}</p></div>
            <div className="bg-amber-500/10 border border-amber-500/20 rounded-brand-lg p-6"><p className="font-mono text-[9px] uppercase tracking-[0.2em] text-amber-400/60 font-bold">Pending Payouts</p><p className="text-2xl font-bold text-amber-400">{stats.pending_payouts}</p></div>
            <div className="bg-emerald-500/5 border border-emerald-500/20 rounded-brand-lg p-6"><p className="font-mono text-[9px] uppercase tracking-[0.2em] text-emerald-400/60 font-bold">Verification Revenue</p><p className="text-2xl font-bold text-emerald-400">₦{(stats.verification_revenue || 0).toLocaleString()}</p></div>
        </div>

        <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-4 mb-4">
            <div className="flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[180px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Search</label><input type="text" value={filterState.search} onChange={e => setFilterState(s => ({ ...s, search: e.target.value }))} onKeyDown={e => e.key === 'Enter' && applyFilters()} placeholder="Employer name..." className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white placeholder-white/20 focus:border-teal outline-none" /></div>
                <div className="w-[140px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Status</label><select value={filterState.status} onChange={e => setFilterState(s => ({ ...s, status: e.target.value }))} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none"><option value="">All</option><option value="paid">Paid</option><option value="pending">Pending</option><option value="refunded">Refunded</option></select></div>
                <div className="flex gap-2"><button onClick={applyFilters} className="h-10 px-4 bg-teal text-white text-xs font-bold rounded-brand-md hover:bg-teal/80">Apply</button><button onClick={clearFilters} className="h-10 px-3 bg-white/5 text-white/40 text-xs font-bold rounded-brand-md hover:bg-white/10">Clear</button></div>
            </div>
        </div>

        <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
            <div className="p-6 border-b border-white/5 flex items-center justify-between bg-[#0a0a0b]">
                <h2 className="font-mono text-[10px] uppercase tracking-widest text-white/40 font-bold">Match Fee Ledger</h2>
                <a href={route('admin.export.financials')} className="text-[10px] font-mono uppercase text-teal hover:underline tracking-widest font-bold">Export CSV →</a>
            </div>
            <div className="overflow-x-auto"><table className="w-full text-left text-sm border-collapse">
                <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                    <tr>
                        <th className={thClass} onClick={toggleSort}>Date <SortIcon field="date" sort={filterState.sort === 'oldest' ? 'oldest' : 'newest'} dir={sortDir} /></th>
                        <th className="px-6 py-4 font-bold">Transaction</th>
                        <th className="px-6 py-4 font-bold">Employer</th>
                        <th className="px-6 py-4 font-bold text-right">Amount</th>
                        <th className="px-6 py-4 font-bold text-center">Status</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-white/5">
                    {payments.data.map(payment => (
                        <tr key={payment.id} className="hover:bg-white/[0.02] transition-colors group">
                            <td className="px-6 py-4 text-white/40 text-xs">{new Date(payment.created_at).toLocaleDateString()}</td>
                            <td className="px-6 py-4 font-mono text-[11px] text-white/40">TXN-{payment.id}</td>
                            <td className="px-6 py-4"><p className="font-bold text-white text-xs">{payment.employer?.name || 'Unknown'}</p></td>
                            <td className="px-6 py-4 font-bold text-white text-right">₦{payment.amount.toLocaleString()}</td>
                            <td className="px-6 py-4 text-center"><span className={`inline-flex px-2 py-0.5 rounded text-[9px] font-mono uppercase tracking-widest border ${payment.status === 'paid' ? 'bg-teal/10 text-teal border-teal/20' : payment.status === 'pending' ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-white/10 text-white/40 border-white/5'}`}>{payment.status}</span></td>
                        </tr>
                    ))}
                    {payments.data.length === 0 && (<tr><td colSpan={5} className="px-6 py-20 text-center text-white/30"><div className="text-3xl mb-3">💰</div><p>No transactions found.</p></td></tr>)}
                </tbody>
            </table></div>
        </div>

        {payments?.links?.length > 3 && (
            <div className="mt-8 flex justify-center gap-1">{payments.links.map((link, k) => (
                <Link key={k} href={link.url || '#'} className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
            ))}</div>
        )}
    </AdminLayout>);
}
