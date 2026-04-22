import { Head, Link } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function Bookings({ auth, bookings }) {
    return (
        <MaidLayout user={auth?.user}>
            <Head title="My Work Assignments | Helper" />
            
            <div className="mb-8 flex items-center justify-between">
                <div>
                    <h1 className="font-display text-3xl font-light text-espresso">My Work Assignments</h1>
                    <p className="text-muted mt-2">View and manage your current and upcoming jobs.</p>
                </div>
            </div>

            <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 overflow-hidden">
                {bookings.data.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gray-50 border-b border-gray-200 font-mono text-[10px] tracking-[0.1em] uppercase text-muted">
                                <tr>
                                    <th className="px-6 py-4 font-medium">Employer</th>
                                    <th className="px-6 py-4 font-medium">Location</th>
                                    <th className="px-6 py-4 font-medium">Start Date</th>
                                    <th className="px-6 py-4 font-medium">Status</th>
                                    <th className="px-6 py-4 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {bookings.data.map(booking => (
                                    <tr key={booking.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4">
                                            <span className="font-medium text-espresso">{booking.employer?.name}</span>
                                        </td>
                                        <td className="px-6 py-4 text-muted">
                                            {booking.employer?.employer_profile?.location || 'Lagos, NG'}
                                        </td>
                                        <td className="px-6 py-4 text-muted">
                                            {booking.start_date ? new Date(booking.start_date).toLocaleDateString() : 'TBD'}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2 py-1 rounded-full text-[11px] font-medium uppercase tracking-[0.05em] ${(booking.status === 'completed' || booking.status === 'active') ? 'bg-success/10 text-success' : booking.status === 'cancelled' ? 'bg-danger/10 text-danger' : 'bg-gray-100 text-muted'}`}>
                                                {booking.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <Link href={`/maid/bookings/${booking.id}`} className="text-teal hover:underline font-medium">
                                                Job Details
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
                        <h3 className="font-display text-xl text-espresso mb-1">No assignments yet</h3>
                        <p className="text-muted text-sm">You haven't been matched with an employer yet.</p>
                        <Link href="/maid/profile" className="text-teal text-sm font-semibold hover:underline mt-4 inline-block">Complete your profile to get matches →</Link>
                    </div>
                )}
            </div>

            {/* Pagination */}
            {bookings.links && bookings.links.length > 3 && (
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
