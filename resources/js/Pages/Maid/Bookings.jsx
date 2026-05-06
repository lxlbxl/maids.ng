import { Head, Link } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function Bookings({ auth, bookings }) {
    const statusLabel = (status) => {
        if (status === 'active') return '✅ Active';
        if (status === 'completed') return '✔️ Done';
        if (status === 'cancelled') return '❌ Cancelled';
        if (status === 'pending') return '⏳ Waiting';
        return status;
    };

    const statusClass = (status) => {
        if (status === 'completed' || status === 'active') return 'bg-success/10 text-success';
        if (status === 'cancelled') return 'bg-danger/10 text-danger';
        return 'bg-gray-100 text-muted';
    };

    return (
        <MaidLayout user={auth?.user}>
            <Head title="My Jobs | Maids.ng" />
            
            <div className="mb-8 flex items-center justify-between">
                <div>
                    <h1 className="font-display text-3xl font-light text-espresso">My Jobs</h1>
                    <p className="text-muted mt-2">See all the jobs you have now and the ones you finished before.</p>
                </div>
            </div>

            <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 overflow-hidden">
                {bookings?.data?.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gray-50 border-b border-gray-200 font-mono text-[10px] tracking-[0.1em] uppercase text-muted">
                                <tr>
                                    <th className="px-6 py-4 font-medium">Who I Work For</th>
                                    <th className="px-6 py-4 font-medium">Where</th>
                                    <th className="px-6 py-4 font-medium">Start Date</th>
                                    <th className="px-6 py-4 font-medium">Job Status</th>
                                    <th className="px-6 py-4 font-medium text-right">More Info</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {bookings.data.map(booking => (
                                    <tr key={booking.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4">
                                            <span className="font-medium text-espresso">{booking.employer?.name || 'Unknown'}</span>
                                        </td>
                                        <td className="px-6 py-4 text-muted">
                                            {booking.employer?.employer_profile?.location || 'Lagos, NG'}
                                        </td>
                                        <td className="px-6 py-4 text-muted">
                                            {booking.start_date ? new Date(booking.start_date).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Not set yet'}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2 py-1 rounded-full text-[11px] font-medium ${statusClass(booking.status)}`}>
                                                {statusLabel(booking.status)}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <Link href={`/maid/bookings/${booking.id}`} className="text-teal hover:underline font-medium">
                                                View Details
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="p-12 text-center">
                        <div className="w-16 h-16 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">📋</div>
                        <h3 className="font-display text-xl text-espresso mb-2">No Jobs Yet</h3>
                        <p className="text-muted text-sm">You have not been matched with an employer yet.</p>
                        <Link href="/maid/profile" className="text-teal text-sm font-semibold hover:underline mt-4 inline-block">Update your profile to get hired faster →</Link>
                    </div>
                )}
            </div>

            {/* Pagination */}
            {bookings?.links && bookings.links.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {bookings.links.map((link, k) => (
                        <Link
                            key={k}
                            href={link.url || '#'}
                            className={`px-4 py-2 text-sm rounded-brand-md border ${link.active ? 'bg-teal text-white border-teal' : 'bg-white text-muted border-gray-200 hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </MaidLayout>
    );
}
