import { Head, Link } from '@inertiajs/react';
import EmployerLayout from '@/Layouts/EmployerLayout';

export default function Bookings({ auth, bookings }) {
    return (
        <EmployerLayout user={auth?.user}>
            <Head title="My Bookings | Employer" />
            
            <div className="mb-8 flex items-center justify-between">
                <div>
                    <h1 className="font-display text-3xl font-light text-espresso">My Bookings</h1>
                    <p className="text-muted mt-2">Manage your current and past helper bookings.</p>
                </div>
            </div>

            <div className="bg-white rounded-brand-lg border border-gray-200 overflow-hidden shadow-brand-1">
                {bookings.data.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gray-50 border-b border-gray-200 font-mono text-[10px] tracking-[0.1em] uppercase text-muted">
                                <tr>
                                    <th className="px-6 py-4 font-medium">Helper</th>
                                    <th className="px-6 py-4 font-medium">Start Date</th>
                                    <th className="px-6 py-4 font-medium">Salary</th>
                                    <th className="px-6 py-4 font-medium">Status</th>
                                    <th className="px-6 py-4 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {bookings.data.map(booking => (
                                    <tr key={booking.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                {booking.maid?.avatar ? (
                                                    <img src={`/storage/${booking.maid.avatar}`} alt="" className="w-8 h-8 rounded-full object-cover"/>
                                                ) : (
                                                    <div className="w-8 h-8 bg-teal/10 rounded-full flex items-center justify-center text-teal font-medium text-xs">
                                                        {booking.maid?.name?.charAt(0) || '?'}
                                                    </div>
                                                )}
                                                <span className="font-medium text-espresso">{booking.maid?.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-muted">
                                            {booking.start_date ? new Date(booking.start_date).toLocaleDateString() : '-'}
                                        </td>
                                        <td className="px-6 py-4 text-muted">
                                            ₦{booking.agreed_salary ? booking.agreed_salary.toLocaleString() : '-'}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2 py-1 rounded-full text-[11px] font-medium uppercase tracking-[0.05em] ${(booking.status === 'completed' || booking.status === 'active') ? 'bg-success/10 text-success' : booking.status === 'cancelled' ? 'bg-danger/10 text-danger' : 'bg-gray-100 text-muted'}`}>
                                                {booking.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <Link href={`/employer/bookings/${booking.id}`} className="text-teal hover:underline font-medium">
                                                Manage
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="p-10 text-center">
                        <div className="w-16 h-16 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">📅</div>
                        <h3 className="font-display text-xl text-espresso mb-2">No bookings yet</h3>
                        <p className="text-muted text-sm mb-5">You haven't hired any helpers yet.</p>
                        <Link href="/onboarding" className="bg-teal text-white px-6 py-2.5 rounded-brand-md text-sm font-medium hover:bg-teal-dark transition-all">
                            Find a Helper
                        </Link>
                    </div>
                )}
            </div>

            {/* Pagination */}
            {bookings.links && bookings.links.length > 3 && (
                <div className="mt-6 flex flex-wrap justify-center gap-1">
                    {bookings.links.map((link, k) => (
                        <Link
                            key={k}
                            href={link.url || '#'}
                            className={`px-3 py-1 text-sm rounded-md border ${link.active ? 'bg-teal text-white border-teal' : 'bg-white text-muted border-gray-200 hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </EmployerLayout>
    );
}
