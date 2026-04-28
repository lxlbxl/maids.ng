import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function VerificationTransactions({ auth, transactions }) {
    return (
        <AdminLayout>
            <Head title="Verification Transactions | Mission Control" />
            
            <div className="mb-10">
                <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Verification Transactions</h1>
                <p className="text-white/40 text-sm">Track all NIN verification requests processed through the Gatekeeper Agent.</p>
            </div>

            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-8 py-5">TX ID</th>
                                <th className="px-8 py-5">Requester</th>
                                <th className="px-8 py-5">NIN</th>
                                <th className="px-8 py-5 text-center">Status</th>
                                <th className="px-8 py-5">Amount</th>
                                <th className="px-8 py-5">Date</th>
                                <th className="px-8 py-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {transactions?.data?.length > 0 ? transactions.data.map(tx => (
                                <tr key={tx.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-8 py-5 font-mono text-[10px] text-white/40">VTX-{String(tx.id).padStart(4, '0')}</td>
                                    <td className="px-8 py-5 text-white font-medium text-sm">{tx.user?.name || '—'}</td>
                                    <td className="px-8 py-5 font-mono text-xs text-white/40">{tx.nin ? `${tx.nin.slice(0,3)}****` : '—'}</td>
                                    <td className="px-8 py-5 text-center">
                                        <span className={`px-3 py-1 rounded-full text-[9px] font-mono font-bold uppercase tracking-widest ${tx.status === 'completed' ? 'bg-success/10 text-success' : tx.status === 'failed' ? 'bg-danger/10 text-danger' : 'bg-copper/10 text-copper'}`}>
                                            {tx.status || 'pending'}
                                        </span>
                                    </td>
                                    <td className="px-8 py-5 font-bold text-white">₦{Number(tx.amount || 500).toLocaleString()}</td>
                                    <td className="px-8 py-5 text-white/30 text-xs">{tx.created_at ? new Date(tx.created_at).toLocaleDateString() : '—'}</td>
                                    <td className="px-8 py-5 text-right">
                                        <Link href={`/admin/verification-transactions/${tx.id}`} className="p-2 bg-white/5 hover:bg-white/10 rounded border border-white/5 text-white/40 hover:text-white transition-all opacity-0 group-hover:opacity-100">
                                            👁️
                                        </Link>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={7} className="px-8 py-16 text-center text-white/30">
                                        <div className="text-3xl mb-3">🛡️</div>
                                        <p>No verification transactions yet.</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
