import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function VerificationTransactionDetail({ auth, transaction }) {
    return (
        <AdminLayout>
            <Head title={`VTX-${String(transaction?.id).padStart(4, '0')} | Mission Control`} />
            
            <div className="mb-8">
                <Link href="/admin/verification-transactions" className="text-white/40 hover:text-white text-sm transition-colors mb-4 inline-block">← Back to Transactions</Link>
                <div className="flex items-center gap-4">
                    <h1 className="font-display text-4xl font-light tracking-tight text-white">VTX-{String(transaction?.id).padStart(4, '0')}</h1>
                    <span className={`px-4 py-1.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-widest border ${transaction?.status === 'completed' ? 'bg-success/10 text-success border-success/20' : transaction?.status === 'failed' ? 'bg-danger/10 text-danger border-danger/20' : 'bg-copper/10 text-copper border-copper/20'}`}>
                        {transaction?.status || 'pending'}
                    </span>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Transaction Details</h3>
                    <div className="space-y-4">
                        {[
                            { label: 'Transaction ID', value: `VTX-${String(transaction?.id).padStart(4, '0')}` },
                            { label: 'Requester', value: transaction?.user?.name || '—' },
                            { label: 'NIN', value: transaction?.nin ? `${transaction.nin.slice(0,3)}****${transaction.nin.slice(-3)}` : '—' },
                            { label: 'Amount', value: `₦${Number(transaction?.amount || 500).toLocaleString()}` },
                            { label: 'Status', value: transaction?.status || 'pending' },
                            { label: 'Date', value: transaction?.created_at ? new Date(transaction.created_at).toLocaleString() : '—' },
                        ].map(item => (
                            <div key={item.label} className="flex items-center justify-between border-b border-white/5 pb-3 last:border-0">
                                <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">{item.label}</span>
                                <span className="text-white text-sm font-medium">{item.value}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Verification Result</h3>
                    {transaction?.result ? (
                        <pre className="text-white/60 text-xs font-mono whitespace-pre-wrap bg-white/[0.03] p-4 rounded-brand-md border border-white/5">
                            {JSON.stringify(transaction.result, null, 2)}
                        </pre>
                    ) : (
                        <div className="text-center py-12 text-white/30">
                            <div className="text-3xl mb-3">📋</div>
                            <p className="text-sm">No result data available.</p>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
