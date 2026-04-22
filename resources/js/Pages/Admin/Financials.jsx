import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Financials({ auth, payments, stats }) {
    return (
        <AdminLayout>
            <Head title="Financial Control | Mission Control" />
            
            <div className="mb-10">
                <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Financial Control</h1>
                <p className="text-white/40 text-sm italic">Real-time ledger of platform revenue and Treasurer-supervised escrow flows.</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-8 space-y-2">
                    <p className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">Total Revenue (Match Fees)</p>
                    <p className="text-3xl font-bold text-teal">₦{stats.total_revenue.toLocaleString()}</p>
                </div>
                <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-8 space-y-2 relative overflow-hidden group">
                    <div className="absolute top-0 right-0 w-24 h-24 bg-teal/5 rounded-full blur-2xl -mr-8 -mt-8 group-hover:bg-teal/10 transition-all"></div>
                    <p className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">Active Escrow Balance</p>
                    <p className="text-3xl font-bold text-white">₦{stats.escrow_balance.toLocaleString()}</p>
                    <p className="text-[10px] text-white/20 italic">Supervised by Treasurer Agent</p>
                </div>
                <div className="bg-copper/10 border border-copper/20 rounded-brand-lg p-8 space-y-2">
                    <p className="font-mono text-[9px] uppercase tracking-[0.2em] text-copper/60 font-bold">Pending Payouts</p>
                    <p className="text-3xl font-bold text-copper">{stats.pending_payouts}</p>
                    <p className="text-[10px] text-copper/40 italic">Awaiting completion of employment contracts</p>
                </div>
            </div>

            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="p-8 border-b border-white/5 flex items-center justify-between bg-[#0a0a0b]">
                    <h2 className="font-mono text-[10px] uppercase tracking-widest text-white/40 font-bold">Match Fee Ledger</h2>
                    <a 
                        href={route('admin.export.financials')} 
                        className="text-[10px] font-mono uppercase text-teal hover:underline tracking-widest font-bold"
                    >
                        Export CSV →
                    </a>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-8 py-5">Transaction ID</th>
                                <th className="px-8 py-5">Employer</th>
                                <th className="px-8 py-5">Amount</th>
                                <th className="px-8 py-5 text-center">Status</th>
                                <th className="px-8 py-5 text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {payments.data.map(payment => (
                                <tr key={payment.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-8 py-5 font-mono text-[11px] text-white/40">
                                        TXN-{payment.id}
                                    </td>
                                    <td className="px-8 py-5">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center text-xs">👤</div>
                                            <p className="font-bold text-white mb-0.5">{payment.employer?.name || 'Unknown'}</p>
                                        </div>
                                    </td>
                                    <td className="px-8 py-5 font-bold text-white">
                                        ₦{payment.amount.toLocaleString()}
                                    </td>
                                    <td className="px-8 py-5 text-center">
                                        <span className={`px-2 py-1 rounded text-[9px] font-mono uppercase tracking-widest ${payment.status === 'paid' ? 'bg-teal/10 text-teal' : 'bg-copper/10 text-copper'}`}>
                                            {payment.status}
                                        </span>
                                    </td>
                                    <td className="px-8 py-5 text-right font-mono text-[10px] text-white/20 uppercase tracking-widest">
                                        {new Date(payment.created_at).toLocaleDateString()}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {payments.data.length === 0 && (
                    <div className="p-20 text-center space-y-4">
                        <span className="text-4xl">🧾</span>
                        <p className="text-white/40 font-mono text-[10px] uppercase tracking-widest font-bold italic">No financial records found in the current period.</p>
                    </div>
                )}
            </div>

            {/* Pagination links */}
            {payments.links && payments.links.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {payments.links.map((link, k) => (
                        <Link
                            key={k}
                            href={link.url || '#'}
                            className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal shadow-[0_0_15px_rgba(45,164,142,0.3)]' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed hidden' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
