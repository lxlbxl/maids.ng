import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function Bookings({ auth, bookings, stats, filters = {} }) {
    const [filterState, setFilterState] = useState({
        search: filters.search || '',
        status: filters.status || '',
    });

    const applyFilters = () => {
        const params = {};
        Object.entries(filterState).forEach(([k, v]) => { if (v) params[k] = v; });
        router.get('/admin/bookings', params, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setFilterState({ search: '', status: '' });
        router.get('/admin/bookings', {}, { preserveState: true, replace: true });
    };

    const statusColors = {
        pending: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
        accepted: 'bg-teal/10 text-teal border-teal/20',
        active: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
        completed: 'bg-white/10 text-white/60 border-white/10',
        cancelled: 'bg-red-500/10 text-red-400 border-red-500/20',
        rejected: 'bg-red-500/10 text-red-400 border-red-500/20',
    };

    return (
        <AdminLayout>
            <Head title="Booking Operations | Mission Control" />
            
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Booking Operations</h1>
                    <p className="text-white/40 text-sm">Monitor and manage all platform booking activity.</p>
                </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                {[
                    { label: 'Total Bookings', value: stats?.total || 0, icon: '📅' },
                    { label: 'Active', value: stats?.active || 0, icon: '🟢' },
                    { label: 'Completed', value: stats?.completed || 0, icon: '✅' },
                    { label: 'Cancelled', value: stats?.cancelled || 0, icon: '❌' },
                ].map(stat => (
                    <div key={stat.label} className="bg-[#121214] border border-white/5 rounded-brand-lg p-5">
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-lg">{stat.icon}</span>
                            <span className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">{stat.label}</span>
                        </div>
                        <p className="text-3xl font-bold text-white">{stat.value}</p>
                    </div>
                ))}
            </div>

            {/* Filter Bar */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-4 mb-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex-1 min-w-[200px]">
                        <label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Search</label>
                        <input type="text" value={filterState.search} onChange={e => setFilterState(s => ({ ...s, search: e.target.value }))} onKeyDown={e => e.key === 'Enter' && applyFilters()} placeholder="Employer or helper name..." className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white placeholder-white/20 focus:border-teal outline-none" />
                    </div>
                    <div className="w-[160px]">
                        <label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Status</label>
                        <select value={filterState.status} onChange={e => setFilterState(s => ({ ...s, status: e.target.value }))} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={applyFilters} className="h-10 px-4 bg-teal text-white text-xs font-bold rounded-brand-md hover:bg-teal/80">Apply</button>
                        <button onClick={clearFilters} className="h-10 px-3 bg-white/5 text-white/40 text-xs font-bold rounded-brand-md hover:bg-white/10">Clear</button>
                    </div>
                </div>
            </div>

            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-6 py-4">Booking ID</th>
                                <th className="px-6 py-4">Employer</th>
                                <th className="px-6 py-4">Helper</th>
                                <th className="px-6 py-4">Schedule</th>
                                <th className="px-6 py-4">Amount</th>
                                <th className="px-6 py-4 text-center">Status</th>
                                <th className="px-6 py-4">Date</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {bookings?.data?.length > 0 ? bookings.data.map(booking => (
                                <tr key={booking.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-6 py-4 font-mono text-[10px] text-white/40">BK-{String(booking.id).padStart(4, '0')}</td>
                                    <td className="px-6 py-4"><p className="text-white font-medium text-sm">{booking.employer?.name || '—'}</p></td>
                                    <td className="px-6 py-4"><p className="text-white font-medium text-sm">{booking.maid?.name || '—'}</p></td>
                                    <td className="px-6 py-4 text-white/40 text-xs font-mono uppercase">{booking.schedule_type || '—'}</td>
                                    <td className="px-6 py-4 font-bold text-white">₦{Number(booking.agreed_salary || 0).toLocaleString()}</td>
                                    <td className="px-6 py-4 text-center">
                                        <span className={`inline-flex px-2 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase border ${statusColors[booking.status] || 'bg-white/10 text-white/40 border-white/5'}`}>{booking.status}</span>
                                    </td>
                                    <td className="px-6 py-4 text-white/40 text-xs">{booking.start_date ? new Date(booking.start_date).toLocaleDateString() : '—'}</td>
                                    <td className="px-6 py-4 text-right">
                                        <Link href={`/admin/bookings/${booking.id}`} className="inline-flex items-center gap-1 px-3 py-1.5 bg-white/5 hover:bg-white/10 rounded border border-white/5 text-white/40 hover:text-white text-xs transition-all opacity-0 group-hover:opacity-100">View →</Link>
                                    </td>
                                </tr>
                            )) : (
                                <tr><td colSpan={8} className="px-6 py-16 text-center text-white/30"><div className="text-3xl mb-3">📅</div><p>No bookings found.</p></td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {bookings?.links?.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {bookings.links.map((link, k) => (
                        <Link key={k} href={link.url || '#'} className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
