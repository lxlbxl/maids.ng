import { Head, Link, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

const VERIFICATION_LABELS = {
    verified: { label: 'Verified', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
    pending: { label: 'Pending', color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
    review_required: { label: 'Needs Review', color: 'bg-purple-500/10 text-purple-400 border-purple-500/20' },
    failed: { label: 'Failed', color: 'bg-red-500/10 text-red-400 border-red-500/20' },
    approved: { label: 'Approved', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
    rejected: { label: 'Rejected', color: 'bg-red-500/20 text-red-300 border-red-500/30' },
    none: { label: 'No NIN', color: 'bg-gray-500/10 text-gray-300 border-gray-500/20' },
};

function getStatus(user) {
    if (user.maid_profile?.nin_verified) return 'verified';
    const s = user.nin_verification?.status;
    if (s === 'approved') return 'approved';
    if (s === 'rejected') return 'rejected';
    if (s && s !== 'approved') return s;
    if (!user.maid_profile?.nin) return 'none';
    return 'pending';
}

export default function Verifications({ auth, pendingVerifications, filters = {} }) {
    const { post, processing } = useForm();
    const [reviewUser, setReviewUser] = useState(null);
    const [payload, setPayload] = useState(null);
    const [loadingPayload, setLoadingPayload] = useState(false);
    const [filterState, setFilterState] = useState({
        search: filters.search || '',
        status: filters.status || '',
        sort: filters.sort || 'newest',
    });

    const applyFilters = () => {
        const p = {}; Object.entries(filterState).forEach(([k, v]) => { if (v) p[k] = v; });
        router.get('/admin/verifications', p, { preserveState: true, replace: true });
    };
    const clearFilters = () => {
        setFilterState({ search: '', status: '', sort: 'newest' });
        router.get('/admin/verifications', {}, { preserveState: true, replace: true });
    };

    const openReview = async (user) => {
        setReviewUser(user);
        setLoadingPayload(true);
        try {
            const res = await fetch(`/admin/verifications/${user.id}/payload`);
            const data = await res.json();
            setPayload(data.qoreid_payload || null);
        } catch { setPayload(null); }
        setLoadingPayload(false);
    };

    const handleApprove = (id) => {
        if (confirm('Approve this verification?')) post(route('admin.verifications.approve', id));
    };
    const handleReject = (id) => {
        const reason = prompt('Reason for rejection (optional):');
        post(route('admin.verifications.reject', id), { data: { notes: reason } });
    };

    return (<AdminLayout><Head title="Verification Hub | Mission Control" />
        <div className="mb-10"><h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Verification Hub</h1><p className="text-white/40 text-sm">Review identity credentials for helpers.</p></div>

        <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-4 mb-4">
            <div className="flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[180px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Search</label><input type="text" value={filterState.search} onChange={e => setFilterState(s => ({ ...s, search: e.target.value }))} onKeyDown={e => e.key === 'Enter' && applyFilters()} placeholder="Name or phone..." className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white placeholder-white/20 focus:border-teal outline-none" /></div>
                <div className="w-[170px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Verification</label><select value={filterState.status} onChange={e => setFilterState(s => ({ ...s, status: e.target.value }))} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none"><option value="">All</option><option value="verified">Verified</option><option value="pending">Pending</option><option value="review_required">Needs Review</option><option value="failed">Failed</option><option value="unverified">Unverified</option></select></div>
                <div className="flex gap-2"><button onClick={applyFilters} className="h-10 px-4 bg-teal text-white text-xs font-bold rounded-brand-md hover:bg-teal/80">Apply</button><button onClick={clearFilters} className="h-10 px-3 bg-white/5 text-white/40 text-xs font-bold rounded-brand-md hover:bg-white/10">Clear</button></div>
            </div>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <div className="xl:col-span-2 bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="p-6 border-b border-white/5"><h2 className="font-mono text-[10px] uppercase tracking-widest text-teal font-bold">Verification Queue</h2></div>
                <div className="overflow-x-auto"><table className="w-full text-left text-sm border-collapse">
                    <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                        <tr>
                            <th className="px-6 py-4">Helper</th>
                            <th className="px-6 py-4 text-center">NIN Status</th>
                            <th className="px-6 py-4 text-center">Confidence</th>
                            <th className="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-white/5">
                        {pendingVerifications.data.map(user => {
                            const vs = getStatus(user);
                            const vi = VERIFICATION_LABELS[vs] || VERIFICATION_LABELS.none;
                            return (
                            <tr key={user.id} className={`hover:bg-white/[0.02] transition-colors group ${reviewUser?.id === user.id ? 'bg-white/5' : ''}`}>
                                <td className="px-6 py-4">
                                    <div className="flex items-center gap-3">
                                        <div className="w-9 h-9 rounded-full bg-[#1c1c1e] flex items-center justify-center text-sm border border-white/5 font-bold text-white/60">{user.name?.charAt(0)}</div>
                                        <div><p className="font-bold text-white text-xs">{user.name}</p><p className="text-[10px] font-mono text-teal/60 uppercase">NIN: {user.maid_profile?.nin || '—'}</p></div>
                                    </div>
                                </td>
                                <td className="px-6 py-4 text-center"><span className={`inline-flex px-2 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase border ${vi.color}`}>{vi.label}</span></td>
                                <td className="px-6 py-4 text-center"><span className="text-xs text-white/60">{user.nin_verification?.confidence_score ? user.nin_verification.confidence_score + '%' : '—'}</span></td>
                                <td className="px-6 py-4 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button onClick={() => openReview(user)} className="px-3 py-1.5 bg-purple-500/10 hover:bg-purple-500/20 rounded border border-purple-500/20 text-purple-400 text-xs">Review</button>
                                        <button onClick={() => handleApprove(user.id)} disabled={processing} className="px-3 py-1.5 bg-teal/20 text-teal border border-teal/30 rounded text-[10px] font-mono uppercase font-bold hover:bg-teal hover:text-black transition-all">Approve</button>
                                    </div>
                                </td>
                            </tr>
                        )})}
                    </tbody>
                </table></div>
            </div>

            <div className="space-y-6">
                {reviewUser ? (
                    <div className="bg-[#121214] border border-white/10 rounded-brand-xl p-6 sticky top-8">
                        <div className="flex items-center justify-between mb-6 pb-4 border-b border-white/5">
                            <div>
                                <h2 className="font-display text-lg text-white">{reviewUser.name}</h2>
                                <p className="text-[10px] font-mono text-white/30 uppercase mt-1">NIN: {reviewUser.maid_profile?.nin || '—'}</p>
                            </div>
                            <button onClick={() => { setReviewUser(null); setPayload(null); }} className="text-white/20 hover:text-white">✕</button>
                        </div>

                        <div className="space-y-4 mb-6">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="bg-white/5 p-3 rounded"><p className="font-mono text-[8px] text-white/30 uppercase">Status</p><p className="text-xs font-bold text-white mt-1">{reviewUser.nin_verification?.status || 'pending'}</p></div>
                                <div className="bg-white/5 p-3 rounded"><p className="font-mono text-[8px] text-white/30 uppercase">Confidence</p><p className="text-xs font-bold text-white mt-1">{reviewUser.nin_verification?.confidence_score || 0}%</p></div>
                            </div>

                            {loadingPayload ? (
                                <div className="text-center py-8 text-white/30"><p>Loading QoreID data...</p></div>
                            ) : payload ? (
                                <div>
                                    <p className="font-mono text-[9px] uppercase text-white/30 mb-2">QoreID Response</p>
                                    <pre className="bg-[#0a0a0b] border border-white/10 rounded p-3 text-[10px] text-white/70 overflow-auto max-h-64 whitespace-pre-wrap">{JSON.stringify(payload, null, 2)}</pre>
                                </div>
                            ) : (
                                <div className="text-center py-4 text-white/20 text-xs border border-dashed border-white/10 rounded">No QoreID payload available</div>
                            )}
                        </div>

                        <div className="flex gap-3 pt-4 border-t border-white/5">
                            <button onClick={() => handleApprove(reviewUser.id)} disabled={processing} className="flex-1 py-3 bg-teal text-black rounded text-xs font-bold uppercase hover:brightness-110 transition-all">Approve</button>
                            <button onClick={() => handleReject(reviewUser.id)} disabled={processing} className="flex-1 py-3 bg-white/5 border border-white/10 text-white rounded text-xs font-bold uppercase hover:bg-red-500/20 hover:border-red-500/40 transition-all">Reject</button>
                        </div>
                    </div>
                ) : (
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-8 text-center py-20 opacity-40">
                        <span className="text-4xl block mb-4">📂</span>
                        <p className="text-xs font-mono uppercase tracking-widest">Click "Review" on a helper<br/>to see QoreID data</p>
                    </div>
                )}
            </div>
        </div>

        {pendingVerifications.links && pendingVerifications.links.length > 3 && (
            <div className="mt-8 flex justify-center gap-1">{pendingVerifications.links.map((link, k) => (
                <Link key={k} href={link.url || '#'} className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
            ))}</div>
        )}
    </AdminLayout>);
}
