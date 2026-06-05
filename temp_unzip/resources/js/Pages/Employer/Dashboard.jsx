import { Head, Link } from '@inertiajs/react';
import EmployerLayout from '@/Layouts/EmployerLayout';

export default function EmployerDashboard({ auth, preferences = [], bookings = [], payments = [], stats = {} }) {
    return (
        <EmployerLayout user={auth?.user}>
            <Head title="Employer Dashboard" />
            
            <div className="mb-8">
                <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-1">Overview</p>
                <h1 className="font-display text-3xl font-light text-espresso">Mission Control</h1>
                <p className="text-muted mt-2">Monitor your matches, bookings, and agent activities.</p>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                {[
                    { label: 'Total Bookings', value: stats.total_bookings || 0 },
                    { label: 'Active Bookings', value: stats.active_bookings || 0 },
                    { label: 'Matches Found', value: preferences?.length || 0 },
                    { label: 'Payments', value: payments?.length || 0 },
                ].map(s => (
                    <div key={s.label} className="bg-white rounded-brand-lg p-5 border border-gray-200 shadow-brand-1">
                        <p className="font-mono text-[10px] tracking-[0.1em] text-muted uppercase mb-1">{s.label}</p>
                        <p className="text-2xl font-bold text-espresso">{s.value}</p>
                    </div>
                ))}
            </div>

            {/* Matched Helpers */}
            <div className="mb-10">
                <div className="flex items-center justify-between mb-4">
                    <h2 className="font-display text-xl text-espresso">Your Matches</h2>
                    <Link href="/onboarding" className="text-teal text-sm font-medium hover:text-teal-dark">+ Scout New Helper</Link>
                </div>
                {preferences.length > 0 ? (
                    <div className="grid gap-4">
                        {preferences.map(p => (
                            <div key={p.id} className="bg-white rounded-brand-lg p-5 border border-gray-200 shadow-brand-1 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div className="flex items-start gap-4">
                                    {p.maid?.avatar ? (
                                        <img src={`/storage/${p.maid.avatar}`} alt="" className="w-12 h-12 rounded-full object-cover"/>
                                    ) : (
                                        <div className="w-12 h-12 bg-teal/10 rounded-full flex items-center justify-center text-teal font-medium">
                                            {p.maid?.name?.charAt(0) || '?'}
                                        </div>
                                    )}
                                    <div>
                                        <div className="flex items-center gap-3 mb-1">
                                            <h3 className="font-semibold text-espresso">{p.maid?.name || 'Awaiting match...'}</h3>
                                            <span className={`px-2 py-0.5 rounded-full text-[11px] font-medium uppercase tracking-[0.05em] ${p.matching_status === 'paid' ? 'bg-success/10 text-success' : p.matching_status === 'matched' ? 'bg-copper-pale text-copper' : 'bg-gray-100 text-muted'}`}>
                                                {p.matching_status}
                                            </span>
                                        </div>
                                        <p className="text-muted text-sm">{p.help_types?.join(', ')} · {p.location}</p>
                                        {p.matching_status === 'paid' && p.maid?.phone && (
                                            <p className="text-teal text-sm mt-2 font-medium bg-teal/5 inline-block px-3 py-1 rounded-md">📞 {p.maid.phone} · ✉️ {p.maid.email}</p>
                                        )}
                                    </div>
                                </div>
                                {p.matching_status === 'matched' && (
                                    <Link href={`/employer/matching/payment/${p.id}`} className="bg-copper text-white px-5 py-2.5 rounded-brand-md text-sm font-medium hover:bg-copper/80 transition-all text-center flex-shrink-0">
                                        Pay ₦5,000 & Unlock
                                    </Link>
                                )}
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="bg-white rounded-brand-lg p-10 text-center border border-gray-200">
                        <div className="w-16 h-16 bg-teal/5 text-teal rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">🔍</div>
                        <h3 className="font-display text-xl text-espresso mb-2">No matches yet</h3>
                        <p className="text-muted text-sm mb-5">Our AI Scout can find your perfect helper in minutes.</p>
                        <Link href="/onboarding" className="bg-teal text-white px-6 py-2.5 rounded-brand-md text-sm font-medium hover:bg-teal-dark transition-all">
                            Start Matching →
                        </Link>
                    </div>
                )}
            </div>
            
            {/* Recent Bookings */}
            <div>
                <div className="flex items-center justify-between mb-4">
                    <h2 className="font-display text-xl text-espresso">Recent Bookings</h2>
                    {bookings.length > 0 && <Link href="/employer/bookings" className="text-teal text-sm font-medium hover:text-teal-dark">View All</Link>}
                </div>
                {bookings.length > 0 ? (
                    <div className="bg-white rounded-brand-lg border border-gray-200 overflow-hidden shadow-brand-1">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gray-50 border-b border-gray-200 font-mono text-[10px] tracking-[0.1em] uppercase text-muted">
                                <tr>
                                    <th className="px-5 py-3 font-medium">Maid</th>
                                    <th className="px-5 py-3 font-medium">Start Date</th>
                                    <th className="px-5 py-3 font-medium">Status</th>
                                    <th className="px-5 py-3 font-medium text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {bookings.map(b => (
                                    <tr key={b.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-5 py-4 font-medium text-espresso">{b.maid_name}</td>
                                        <td className="px-5 py-4 text-muted">{b.start_date || '-'}</td>
                                        <td className="px-5 py-4">
                                            <span className={`px-2 py-1 rounded-full text-[11px] font-medium uppercase tracking-[0.05em] ${(b.status === 'completed' || b.status === 'active') ? 'bg-success/10 text-success' : b.status === 'cancelled' ? 'bg-danger/10 text-danger' : 'bg-gray-100 text-muted'}`}>
                                                {b.status}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <Link href={`/employer/bookings/${b.id}`} className="text-teal hover:underline font-medium">Details</Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="bg-white rounded-brand-lg p-8 text-center border border-gray-200">
                        <p className="text-muted text-sm">You haven't booked any helpers yet.</p>
                    </div>
                )}
            </div>
        </EmployerLayout>
    );
}
