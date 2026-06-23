import { Head, Link, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

function SortIcon({ field, sort, dir }) {
    if (field !== sort) return <span className="text-white/10 ml-1">⇅</span>;
    return <span className="text-teal ml-1">{dir === 'asc' ? '▲' : '▼'}</span>;
}

export default function Notifications({ auth, notifications, filters = {} }) {
    const [showCompose, setShowCompose] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ title: '', message: '', target: 'all' });
    const [filterState, setFilterState] = useState({
        search: filters.search || '',
        type: filters.type || '',
        sort: filters.sort || 'newest',
    });
    const sortDir = filterState.sort === 'oldest' ? 'asc' : 'desc';

    const applyFilters = () => {
        const p = {}; Object.entries(filterState).forEach(([k, v]) => { if (v) p[k] = v; });
        router.get('/admin/notifications', p, { preserveState: true, replace: true });
    };
    const clearFilters = () => {
        setFilterState({ search: '', type: '', sort: 'newest' });
        router.get('/admin/notifications', {}, { preserveState: true, replace: true });
    };
    const toggleSort = () => {
        const ns = filterState.sort === 'oldest' ? 'newest' : 'oldest';
        setFilterState(s => ({ ...s, sort: ns }));
        router.get('/admin/notifications', { ...filterState, sort: ns }, { preserveState: true, replace: true });
    };

    const handleSend = (e) => {
        e.preventDefault();
        post('/admin/notifications', { onSuccess: () => { reset(); setShowCompose(false); } });
    };
    const thClass = "px-6 py-4 font-bold cursor-pointer hover:text-white transition-colors select-none";

    return (<AdminLayout><Head title="Notifications | Mission Control" />
        <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div><h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Broadcast Center</h1><p className="text-white/40 text-sm">Send platform-wide notifications and announcements.</p></div>
            <button onClick={() => setShowCompose(!showCompose)} className="bg-teal/10 border border-teal/20 px-6 py-3 rounded-brand-md text-[10px] font-mono uppercase tracking-widest text-teal hover:bg-teal/20 transition-all font-bold">{showCompose ? 'Cancel' : 'New Broadcast'}</button>
        </div>

        {showCompose && (
            <form onSubmit={handleSend} className="bg-[#121214] border border-teal/20 rounded-brand-xl p-6 mb-8">
                <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-teal mb-6 font-bold">Compose Broadcast</h3>
                <div className="space-y-4">
                    <div><label className="block text-xs text-white/40 mb-2 font-mono uppercase">Title</label><input type="text" value={data.title} onChange={e => setData('title', e.target.value)} className="w-full bg-white/5 border border-white/10 rounded-brand-md px-4 py-3 text-white text-sm focus:border-teal" placeholder="Notification title..." required /></div>
                    <div><label className="block text-xs text-white/40 mb-2 font-mono uppercase">Message</label><textarea rows={4} value={data.message} onChange={e => setData('message', e.target.value)} className="w-full bg-white/5 border border-white/10 rounded-brand-md px-4 py-3 text-white text-sm focus:border-teal" placeholder="Notification message..." required /></div>
                    <div><label className="block text-xs text-white/40 mb-2 font-mono uppercase">Target</label><select value={data.target} onChange={e => setData('target', e.target.value)} className="bg-white/5 border border-white/10 rounded-brand-md px-4 py-3 text-white text-sm focus:border-teal"><option value="all" className="bg-[#121214]">All Users</option><option value="maids" className="bg-[#121214]">Helpers Only</option><option value="employers" className="bg-[#121214]">Employers Only</option></select></div>
                    <button type="submit" disabled={processing} className="bg-teal text-white px-8 py-3 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all disabled:opacity-50">{processing ? 'Sending...' : 'Send Broadcast'}</button>
                </div>
            </form>
        )}

        <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-4 mb-4">
            <div className="flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[180px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Search</label><input type="text" value={filterState.search} onChange={e => setFilterState(s => ({ ...s, search: e.target.value }))} onKeyDown={e => e.key === 'Enter' && applyFilters()} placeholder="Title or message..." className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white placeholder-white/20 focus:border-teal outline-none" /></div>
                <div className="w-[150px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Type</label><select value={filterState.type} onChange={e => setFilterState(s => ({ ...s, type: e.target.value }))} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none"><option value="">All</option><option value="verification">Verification</option><option value="assignment">Assignment</option><option value="broadcast">Broadcast</option><option value="salary">Salary</option><option value="general">General</option></select></div>
                <div className="flex gap-2"><button onClick={applyFilters} className="h-10 px-4 bg-teal text-white text-xs font-bold rounded-brand-md hover:bg-teal/80">Apply</button><button onClick={clearFilters} className="h-10 px-3 bg-white/5 text-white/40 text-xs font-bold rounded-brand-md hover:bg-white/10">Clear</button></div>
            </div>
        </div>

        <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
            <div className="px-6 py-4 border-b border-white/5"><h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 font-bold">Broadcast History</h3></div>
            <table className="w-full text-left text-sm border-collapse">
                <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                    <tr>
                        <th className={thClass} onClick={toggleSort}>Date <SortIcon field="date" sort={filterState.sort === 'oldest' ? 'oldest' : 'newest'} dir={sortDir} /></th>
                        <th className="px-6 py-4 font-bold">Title</th>
                        <th className="px-6 py-4 font-bold">Message</th>
                        <th className="px-6 py-4 font-bold text-center">Type</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-white/5">
                    {notifications?.data?.length > 0 ? notifications.data.map(notif => (
                        <tr key={notif.id} className="hover:bg-white/[0.02] transition-colors">
                            <td className="px-6 py-4 text-white/40 text-xs">{new Date(notif.created_at).toLocaleDateString()}</td>
                            <td className="px-6 py-4 text-white font-medium text-xs">{notif.title}</td>
                            <td className="px-6 py-4 text-white/40 text-xs line-clamp-1 max-w-[300px]">{notif.message}</td>
                            <td className="px-6 py-4 text-center"><span className="inline-flex px-2 py-0.5 bg-white/5 text-white/40 rounded-full text-[9px] font-mono uppercase border border-white/5">{notif.type || 'broadcast'}</span></td>
                        </tr>
                    )) : (<tr><td colSpan={4} className="px-6 py-16 text-center text-white/30"><div className="text-3xl mb-3">📡</div><p>No broadcasts sent yet.</p></td></tr>)}
                </tbody>
            </table>
        </div>

        {notifications?.links?.length > 3 && (
            <div className="mt-8 flex justify-center gap-1">{notifications.links.map((link, k) => (
                <Link key={k} href={link.url || '#'} className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
            ))}</div>
        )}
    </AdminLayout>);
}
