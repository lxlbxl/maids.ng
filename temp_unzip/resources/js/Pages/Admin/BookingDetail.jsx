import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function BookingDetail({ auth, booking }) {
    const { post, processing } = useForm();

    const statusColors = {
        pending: 'bg-copper/10 text-copper border-copper/20',
        accepted: 'bg-teal/10 text-teal border-teal/20',
        active: 'bg-success/10 text-success border-success/20',
        completed: 'bg-white/10 text-white/60 border-white/10',
        cancelled: 'bg-danger/10 text-danger border-danger/20',
    };

    const handleStatusUpdate = (status) => {
        if (confirm(`Update booking status to "${status}"?`)) {
            post(`/admin/bookings/${booking.id}/status?status=${status}`);
        }
    };

    return (
        <AdminLayout>
            <Head title={`Booking BK-${String(booking?.id).padStart(4, '0')} | Mission Control`} />
            
            <div className="mb-8">
                <Link href="/admin/bookings" className="text-white/40 hover:text-white text-sm transition-colors mb-4 inline-block">← Back to Bookings</Link>
                <div className="flex items-center gap-4">
                    <h1 className="font-display text-4xl font-light tracking-tight text-white">Booking BK-{String(booking?.id).padStart(4, '0')}</h1>
                    <span className={`px-4 py-1.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-widest border ${statusColors[booking?.status] || 'bg-white/10 text-white/40'}`}>
                        {booking?.status}
                    </span>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main Details */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Parties */}
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Involved Parties</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="bg-white/[0.03] rounded-brand-lg p-5 border border-white/5">
                                <p className="font-mono text-[9px] uppercase tracking-widest text-teal mb-3">Employer</p>
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-full bg-teal/10 flex items-center justify-center text-teal font-bold">
                                        {booking?.employer?.name?.charAt(0) || 'E'}
                                    </div>
                                    <div>
                                        <p className="text-white font-medium">{booking?.employer?.name || '—'}</p>
                                        <p className="text-white/30 text-xs font-mono">{booking?.employer?.email}</p>
                                    </div>
                                </div>
                            </div>
                            <div className="bg-white/[0.03] rounded-brand-lg p-5 border border-white/5">
                                <p className="font-mono text-[9px] uppercase tracking-widest text-copper mb-3">Helper</p>
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-full bg-copper/10 flex items-center justify-center text-copper font-bold">
                                        {booking?.maid?.name?.charAt(0) || 'H'}
                                    </div>
                                    <div>
                                        <p className="text-white font-medium">{booking?.maid?.name || '—'}</p>
                                        <p className="text-white/30 text-xs font-mono">{booking?.maid?.email}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Booking Info */}
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Booking Details</h3>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                            {[
                                { label: 'Schedule', value: booking?.schedule_type || '—' },
                                { label: 'Start Date', value: booking?.start_date ? new Date(booking.start_date).toLocaleDateString() : '—' },
                                { label: 'End Date', value: booking?.end_date ? new Date(booking.end_date).toLocaleDateString() : 'Ongoing' },
                                { label: 'Agreed Salary', value: `₦${Number(booking?.agreed_salary || 0).toLocaleString()}` },
                            ].map(item => (
                                <div key={item.label}>
                                    <p className="font-mono text-[9px] uppercase tracking-widest text-white/30 mb-1">{item.label}</p>
                                    <p className="text-white font-medium">{item.value}</p>
                                </div>
                            ))}
                        </div>
                        {booking?.notes && (
                            <div className="mt-6 pt-4 border-t border-white/5">
                                <p className="font-mono text-[9px] uppercase tracking-widest text-white/30 mb-2">Notes</p>
                                <p className="text-white/60 text-sm">{booking.notes}</p>
                            </div>
                        )}
                    </div>

                    {/* Review */}
                    {booking?.review && (
                        <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                            <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Review</h3>
                            <div className="flex items-center gap-2 mb-2">
                                <span className="text-amber-400">{'⭐'.repeat(booking.review.rating)}</span>
                                <span className="text-white/40 text-xs">({booking.review.rating}/5)</span>
                            </div>
                            <p className="text-white/60 text-sm">{booking.review.comment}</p>
                        </div>
                    )}
                </div>

                {/* Sidebar — Actions */}
                <div className="space-y-6">
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Quick Actions</h3>
                        <div className="space-y-2">
                            {booking?.status === 'pending' && (
                                <>
                                    <button onClick={() => handleStatusUpdate('accepted')} disabled={processing} className="w-full bg-teal/10 text-teal px-4 py-3 rounded-brand-md text-sm font-bold hover:bg-teal/20 transition-all border border-teal/20">
                                        ✓ Approve Booking
                                    </button>
                                    <button onClick={() => handleStatusUpdate('rejected')} disabled={processing} className="w-full bg-danger/10 text-danger px-4 py-3 rounded-brand-md text-sm font-bold hover:bg-danger/20 transition-all border border-danger/20">
                                        ✗ Reject Booking
                                    </button>
                                </>
                            )}
                            {booking?.status === 'active' && (
                                <button onClick={() => handleStatusUpdate('completed')} disabled={processing} className="w-full bg-success/10 text-success px-4 py-3 rounded-brand-md text-sm font-bold hover:bg-success/20 transition-all border border-success/20">
                                    ✓ Mark Complete
                                </button>
                            )}
                            {!['completed', 'cancelled'].includes(booking?.status) && (
                                <button onClick={() => handleStatusUpdate('cancelled')} disabled={processing} className="w-full bg-white/5 text-white/40 px-4 py-3 rounded-brand-md text-sm hover:bg-white/10 transition-all border border-white/5">
                                    Cancel Booking
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="bg-teal/5 border border-teal/10 rounded-brand-xl p-6">
                        <p className="font-mono text-[9px] uppercase text-teal mb-2 font-bold">Payment Status</p>
                        <p className="text-white text-lg font-bold">{booking?.payment_status || 'Pending'}</p>
                        <p className="text-white/40 text-xs mt-1">Managed by Treasurer Agent</p>
                    </div>

                    {/* Timeline placeholder */}
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Timeline</h3>
                        <div className="space-y-3 text-xs text-white/40">
                            <div className="flex items-center gap-3">
                                <div className="w-2 h-2 bg-teal rounded-full"></div>
                                <span>Booking created</span>
                                <span className="ml-auto font-mono text-[10px]">{booking?.created_at ? new Date(booking.created_at).toLocaleDateString() : '—'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
