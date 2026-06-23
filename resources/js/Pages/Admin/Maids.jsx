import { Head, Link, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

const VERIFICATION_LABELS = {
    verified: { label: 'Verified', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
    none: { label: 'No NIN', color: 'bg-gray-500/10 text-gray-400 border-gray-500/20' },
    pending: { label: 'Pending', color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
    review_required: { label: 'Review Needed', color: 'bg-purple-500/10 text-purple-400 border-purple-500/20' },
    failed: { label: 'Failed', color: 'bg-red-500/10 text-red-400 border-red-500/20' },
};

function getVerificationStatus(maid) {
    if (maid.maid_profile?.nin_verified) return 'verified';
    const ninStatus = maid.nin_verification?.status;
    if (ninStatus && ninStatus !== 'approved') return ninStatus;
    if (!maid.maid_profile?.nin) return 'none';
    return 'pending';
}

export default function Maids({ auth, maids, stats, filters = {} }) {
    const { post, processing } = useForm();
    const [filterState, setFilterState] = useState({
        search: filters.search || '',
        status: filters.status || '',
        verified: filters.verified || '',
        location: filters.location || '',
        sort: filters.sort || 'newest',
    });

    const applyFilters = () => {
        const params = {};
        Object.entries(filterState).forEach(([k, v]) => { if (v) params[k] = v; });
        router.get('/admin/maids', params, { preserveState: true, replace: true });
    };
    const clearFilters = () => {
        setFilterState({ search: '', status: '', verified: '', location: '', sort: 'newest' });
        router.get('/admin/maids', {}, { preserveState: true, replace: true });
    };

    const handleStatusToggle = (id, currentStatus) => {
        post(`/admin/maids/${id}/status?status=${currentStatus === 'active' ? 'suspended' : 'active'}`);
    };

    return (
        <AdminLayout>
            <Head title="Helper Management | Mission Control" />
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div><h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Helper Management</h1><p className="text-white/40 text-sm">Monitor all registered helpers, verification status, and performance.</p></div>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
                {[
                    { label: 'Total Helpers', value: stats?.total || 0, icon: '👥', color: 'text-white' },
                    { label: 'Active Talent', value: stats?.active || 0, icon: '🟢', color: 'text-teal' },
                    { label: 'ID Verified', value: stats?.verified || 0, icon: '🛡️', color: 'text-emerald-400' },
                    { label: 'Pending NIN', value: stats?.pending_verification || 0, icon: '⏳', color: 'text-amber-400' },
                    { label: 'New This Week', value: stats?.new_this_week || 0, icon: '📈', color: 'text-white' },
                ].map(stat => (
                    <div key={stat.label} className="bg-[#121214] border border-white/5 rounded-brand-lg p-5 hover:border-teal/20 transition-all">
                        <div className="flex items-center justify-between mb-2"><span className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">{stat.label}</span><span className="text-sm opacity-50">{stat.icon}</span></div>
                        <p className={`text-2xl font-bold ${stat.color}`}>{stat.value}</p>
                    </div>
                ))}
            </div>

            <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-4 mb-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex-1 min-w-[180px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Search</label><input type="text" value={filterState.search} onChange={e => setFilterState(s => ({ ...s, search: e.target.value }))} onKeyDown={e => e.key === 'Enter' && applyFilters()} placeholder="Name or phone..." className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white placeholder-white/20 focus:border-teal outline-none" /></div>
                    <div className="w-[140px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Status</label><select value={filterState.status} onChange={e => setFilterState(s => ({ ...s, status: e.target.value }))} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none"><option value="">All</option><option value="active">Active</option><option value="suspended">Suspended</option></select></div>
                    <div className="w-[160px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Verification</label><select value={filterState.verified} onChange={e => setFilterState(s => ({ ...s, verified: e.target.value }))} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none"><option value="">All</option><option value="yes">Verified</option><option value="no">Not Verified</option><option value="pending">Pending</option><option value="review_required">Needs Review</option><option value="failed">Failed</option></select></div>
                    <div className="flex-1 min-w-[150px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Location</label><input type="text" value={filterState.location} onChange={e => setFilterState(s => ({ ...s, location: e.target.value }))} onKeyDown={e => e.key === 'Enter' && applyFilters()} placeholder="Filter by location..." className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white placeholder-white/20 focus:border-teal outline-none" /></div>
                    <div className="w-[130px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Sort By</label><select value={filterState.sort} onChange={e => { setFilterState(s => ({ ...s, sort: e.target.value })); router.get('/admin/maids', { ...filterState, sort: e.target.value }, { preserveState: true, replace: true }); }} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none"><option value="newest">Newest</option><option value="oldest">Oldest</option><option value="name_asc">Name A-Z</option><option value="name_desc">Name Z-A</option><option value="rating">Top Rated</option></select></div>
                    <div className="flex gap-2"><button onClick={applyFilters} className="h-10 px-4 bg-teal text-white text-xs font-bold rounded-brand-md hover:bg-teal/80">Apply</button><button onClick={clearFilters} className="h-10 px-3 bg-white/5 text-white/40 text-xs font-bold rounded-brand-md hover:bg-white/10">Clear</button></div>
                </div>
            </div>

            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-6 py-4">Helper</th>
                                <th className="px-6 py-4">Location</th>
                                <th className="px-6 py-4 text-center">Rating</th>
                                <th className="px-6 py-4 text-center">Verification</th>
                                <th className="px-6 py-4 text-center">Status</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {maids?.data?.map(maid => {
                                const v = getVerificationStatus(maid);
                                const vi = VERIFICATION_LABELS[v] || VERIFICATION_LABELS.none;
                                return (
                                <tr key={maid.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-9 h-9 rounded-full bg-[#1c1c1e] text-sm flex items-center justify-center border border-white/5 font-bold text-white/60">{maid.name?.charAt(0)}</div>
                                            <div><p className="font-bold text-white text-xs">{maid.name}</p><p className="text-[10px] font-mono text-teal/60 uppercase">{maid.maid_profile?.nin ? `NIN: ${maid.maid_profile.nin}` : 'No NIN'}</p></div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-white/40 text-xs">📍 {maid.maid_profile?.location || maid.location || '—'}</td>
                                    <td className="px-6 py-4 text-center"><span className="text-amber-400 text-xs">⭐</span><span className="text-white ml-1 text-xs">{maid.maid_profile?.rating ? Number(maid.maid_profile.rating).toFixed(1) : '—'}</span></td>
                                    <td className="px-6 py-4 text-center">
                                        <Link href={`/admin/verifications?status=review_required&search=${encodeURIComponent(maid.name)}`} className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase border cursor-pointer hover:brightness-125 transition-all ${vi.color}`}>{vi.label}</Link>
                                    </td>
                                    <td className="px-6 py-4 text-center">
                                        <button onClick={() => handleStatusToggle(maid.id, maid.status)} disabled={processing} className={`px-3 py-1 rounded-full text-[9px] font-mono font-bold uppercase cursor-pointer transition-all ${maid.status === 'active' ? 'bg-teal/10 text-teal hover:bg-teal/20' : 'bg-red-500/10 text-red-400 hover:bg-red-500/20'}`}>{maid.status}</button>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <Link href={`/admin/maids/${maid.id}`} className="inline-flex items-center gap-1 px-3 py-1.5 bg-white/5 hover:bg-white/10 rounded border border-white/5 text-white/40 hover:text-white text-xs transition-all opacity-0 group-hover:opacity-100">View →</Link>
                                    </td>
                                </tr>
                            )})}
                        </tbody>
                    </table>
                </div>
            </div>

            {maids?.links?.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">{maids.links.map((link, k) => (
                    <Link key={k} href={link.url || '#'} className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                ))}</div>
            )}
        </AdminLayout>
    );
}
