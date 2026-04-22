import { Head } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function Earnings({ auth, payoutLogs, stats }) {
    return (
        <MaidLayout user={auth?.user}>
            <Head title="My Earnings | Helper" />
            
            <div className="mb-8">
                <h1 className="font-display text-3xl font-light text-espresso">My Earnings</h1>
                <p className="text-muted mt-2">Track your payouts and earnings history supervised by the Treasurer Agent.</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                <div className="bg-espresso text-white rounded-brand-lg p-6 shadow-brand-2">
                    <p className="font-mono text-[10px] uppercase tracking-widest text-white/50 mb-1">Total Earned</p>
                    <p className="text-3xl font-bold">₦{stats.total_earned?.toLocaleString()}</p>
                </div>
                <div className="bg-white rounded-brand-lg p-6 border border-gray-200 shadow-brand-1">
                    <p className="font-mono text-[10px] uppercase tracking-widest text-muted mb-1">Pending Payouts</p>
                    <p className="text-3xl font-bold text-espresso">₦{stats.pending_payout?.toLocaleString()}</p>
                </div>
            </div>

            <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 className="font-display text-lg text-espresso">Payout History</h2>
                    <span className="bg-teal/5 text-teal text-[10px] font-mono px-2 py-0.5 rounded-full uppercase tracking-widest">Treasurer Supervised</span>
                </div>
                
                {payoutLogs.data.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gray-50 font-mono text-[10px] tracking-[0.1em] uppercase text-muted">
                                <tr>
                                    <th className="px-6 py-4 font-medium">Transaction ID</th>
                                    <th className="px-6 py-4 font-medium">Amount</th>
                                    <th className="px-6 py-4 font-medium">Date</th>
                                    <th className="px-6 py-4 font-medium">Status</th>
                                    <th className="px-6 py-4 font-medium">Agent Reasoning</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {payoutLogs.data.map(log => (
                                    <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4 font-mono text-xs text-muted">TX-{log.id}</td>
                                        <td className="px-6 py-4 font-bold text-espresso">Calculated by AI</td>
                                        <td className="px-6 py-4 text-muted">{new Date(log.created_at).toLocaleDateString()}</td>
                                        <td className="px-6 py-4">
                                            <span className="bg-success text-white px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-tighter">
                                                Processed
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-xs text-muted italic line-clamp-1 max-w-xs">{log.reasoning}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="p-12 text-center text-muted">
                        <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">💰</div>
                        <p className="text-sm">No payout records found yet.</p>
                        <p className="text-xs mt-1">Payouts are automatically triggered by the Treasurer Agent upon booking completion.</p>
                    </div>
                )}
            </div>

            {/* Pagination */}
            {payoutLogs.links && payoutLogs.links.length > 3 && (
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
