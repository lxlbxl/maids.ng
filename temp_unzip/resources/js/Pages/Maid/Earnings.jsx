import { Head, Link } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function Earnings({ auth, payoutLogs, stats }) {
    return (
        <MaidLayout user={auth?.user}>
            <Head title="My Money | Maids.ng" />
            
            <div className="mb-8">
                <h1 className="font-display text-3xl font-light text-espresso">My Money</h1>
                <p className="text-muted mt-2">See how much money you have received and what is still waiting to be paid.</p>
            </div>

            {/* Money Summary */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                <div className="bg-espresso text-white rounded-brand-lg p-6 shadow-brand-2">
                    <p className="font-mono text-[10px] uppercase tracking-widest text-white/50 mb-1">Total Money Received</p>
                    <p className="text-3xl font-bold">₦{(stats?.total_earned || 0).toLocaleString()}</p>
                    <p className="text-white/50 text-xs mt-2">All salaries paid to you so far</p>
                </div>
                <div className="bg-white rounded-brand-lg p-6 border border-gray-200 shadow-brand-1">
                    <p className="font-mono text-[10px] uppercase tracking-widest text-muted mb-1">Money Coming Soon</p>
                    <p className="text-3xl font-bold text-espresso">₦{(stats?.pending_payout || 0).toLocaleString()}</p>
                    <p className="text-muted text-xs mt-2">This will be sent to your bank soon</p>
                </div>
            </div>

            {/* Payment History */}
            <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 className="font-display text-lg text-espresso">Payment History</h2>
                        <p className="text-xs text-muted mt-1">All the times you received money</p>
                    </div>
                </div>
                
                {payoutLogs?.data?.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gray-50 font-mono text-[10px] tracking-[0.1em] uppercase text-muted">
                                <tr>
                                    <th className="px-6 py-4 font-medium">Payment #</th>
                                    <th className="px-6 py-4 font-medium">Date Paid</th>
                                    <th className="px-6 py-4 font-medium">Result</th>
                                    <th className="px-6 py-4 font-medium">Note</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {payoutLogs.data.map(log => (
                                    <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4 font-mono text-xs text-muted">#{log.id}</td>
                                        <td className="px-6 py-4 text-muted">{new Date(log.created_at).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' })}</td>
                                        <td className="px-6 py-4">
                                            <span className="bg-success text-white px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-tighter">
                                                ✅ Paid
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-xs text-muted italic line-clamp-1 max-w-xs">{log.reasoning || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="p-12 text-center text-muted">
                        <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">💰</div>
                        <h3 className="font-display text-lg text-espresso mb-2">No Payments Yet</h3>
                        <p className="text-sm">Once you finish a job and get paid, it will show up here.</p>
                        <p className="text-xs mt-2">Payment is sent to your bank account after each job is completed.</p>
                    </div>
                )}
            </div>

            {/* Pagination */}
            {payoutLogs?.links && payoutLogs.links.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {payoutLogs.links.map((link, k) => (
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
