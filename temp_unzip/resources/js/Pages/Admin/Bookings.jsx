import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Bookings({ auth, bookings, stats, filters }) {
    const statusColors = {
        pending: 'bg-copper/10 text-copper',
        accepted: 'bg-teal/10 text-teal',
        active: 'bg-success/10 text-success',
        completed: 'bg-white/10 text-white/60',
        cancelled: 'bg-danger/10 text-danger',
        rejected: 'bg-danger/10 text-danger',
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

            {/* Bookings Table */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-8 py-5">Booking ID</th>
                                <th className="px-8 py-5">Employer</th>
                                <th className="px-8 py-5">Helper</th>
                                <th className="px-8 py-5">Schedule</th>
                                <th className="px-8 py-5">Amount</th>
                                <th className="px-8 py-5 text-center">Status</th>
                                <th className="px-8 py-5">Date</th>
                                <th className="px-8 py-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {bookings?.data?.length > 0 ? bookings.data.map(booking => (
                                <tr key={booking.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-8 py-5 font-mono text-[10px] text-white/40">BK-{String(booking.id).padStart(4, '0')}</td>
                                    <td className="px-8 py-5">
                                        <p className="text-white font-medium text-sm">{booking.employer?.name || '—'}</p>
                                    </td>
                                    <td className="px-8 py-5">
                                        <p className="text-white font-medium text-sm">{booking.maid?.name || '—'}</p>
                                    </td>
                                    <td className="px-8 py-5 text-white/40 text-xs font-mono uppercase">{booking.schedule_type || '—'}</td>
                                    <td className="px-8 py-5 font-bold text-white">₦{Number(booking.agreed_salary || 0).toLocaleString()}</td>
                                    <td className="px-8 py-5 text-center">
                                        <span className={`px-3 py-1 rounded-full text-[9px] font-mono font-bold uppercase tracking-widest ${statusColors[booking.status] || 'bg-white/10 text-white/40'}`}>
                                            {booking.status}
                                        </span>
                                    </td>
                                    <td className="px-8 py-5 text-white/40 text-xs">{booking.start_date ? new Date(booking.start_date).toLocaleDateString() : '—'}</td>
                                    <td className="px-8 py-5 text-right">
                                        <Link href={`/admin/bookings/${booking.id}`} className="p-2 bg-white/5 hover:bg-white/10 rounded border border-white/5 text-white/40 hover:text-white transition-all opacity-0 group-hover:opacity-100">
                                            👁️
                                        </Link>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={8} className="px-8 py-16 text-center text-white/30">
                                        <div className="text-3xl mb-3">📅</div>
                                        <p>No bookings found.</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {bookings?.links?.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {bookings.links.map((link, k) => (
                        <Link
                            key={k}
                            href={link.url || '#'}
                            className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal shadow-[0_0_15px_rgba(45,164,142,0.3)]' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
