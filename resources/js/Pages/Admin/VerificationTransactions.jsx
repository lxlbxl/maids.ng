import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function VerificationTransactions({ auth, transactions, stats, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [paymentFilter, setPaymentFilter] = useState(filters?.payment_status || '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from || '');
    const [dateTo, setDateTo] = useState(filters?.date_to || '');

    const buildUrl = () => {
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (statusFilter) params.set('status', statusFilter);
        if (paymentFilter) params.set('payment_status', paymentFilter);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        const qs = params.toString();
        return `/admin/verification-transactions${qs ? '?' + qs : ''}`;
    };

    return (
        <AdminLayout>
            <Head title="Verification Transactions | Mission Control" />

            <div className="mb-10">
                <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Verification Transactions</h1>
                <p className="text-white/40 text-sm">Track all standalone NIN verification requests, payments, and results.</p>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-6">
                    <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">Total Transactions</p>
                    <p className="text-3xl font-bold text-white">{stats?.total || 0}</p>
                </div>
                <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-6">
                    <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">Total Revenue</p>
                    <p className="text-3xl font-bold text-teal">₦{(stats?.total_revenue || 0).toLocaleString()}</p>
                </div>
                <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-6">
                    <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">Completed</p>
                    <p className="text-3xl font-bold text-success">{stats?.completed || 0}</p>
                </div>
                <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-6">
                    <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">Pending Payment</p>
                    <p className="text-3xl font-bold text-copper">{stats?.pending_payment || 0}</p>
                </div>
            </div>

            {/* Filters */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-6 mb-6">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label className="block text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">Search</label>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="NIN, name, reference..."
                            className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-md px-4 py-2.5 text-white text-sm focus:border-teal/50 outline-none"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">Verification Status</label>
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-md px-4 py-2.5 text-white text-sm focus:border-teal/50 outline-none appearance-none"
                        >
                            <option value="">All</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">Payment Status</label>
                        <select
                            value={paymentFilter}
                            onChange={(e) => setPaymentFilter(e.target.value)}
                            className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-md px-4 py-2.5 text-white text-sm focus:border-teal/50 outline-none appearance-none"
                        >
                            <option value="">All</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">From Date</label>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-md px-4 py-2.5 text-white text-sm focus:border-teal/50 outline-none"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">To Date</label>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-md px-4 py-2.5 text-white text-sm focus:border-teal/50 outline-none"
                        />
                    </div>
                </div>
                <div className="mt-4 flex gap-3">
                    <a
                        href={buildUrl()}
                        className="bg-teal/20 text-teal border border-teal/30 px-6 py-2.5 rounded-brand-md text-[10px] font-mono uppercase tracking-widest font-bold hover:bg-teal hover:text-espresso transition-all"
                    >
                        Apply Filters
                    </a>
                    <a
                        href="/admin/verification-transactions"
                        className="bg-white/5 text-white/40 border border-white/10 px-6 py-2.5 rounded-brand-md text-[10px] font-mono uppercase tracking-widest font-bold hover:bg-white/10 transition-all"
                    >
                        Clear
                    </a>
                    <a
                        href="/admin/verification-transactions/export"
                        className="bg-white/5 text-white/40 border border-white/10 px-6 py-2.5 rounded-brand-md text-[10px] font-mono uppercase tracking-widest font-bold hover:bg-white/10 transition-all ml-auto"
                    >
                        Export CSV
                    </a>
                </div>
            </div>

            {/* Transactions Table */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-6 py-4">Reference</th>
                                <th className="px-6 py-4">Requester</th>
                                <th className="px-6 py-4">Subject</th>
                                <th className="px-6 py-4 text-center">Payment</th>
                                <th className="px-6 py-4 text-center">Verification</th>
                                <th className="px-6 py-4 text-right">Amount</th>
                                <th className="px-6 py-4">Date</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {transactions?.data?.length > 0 ? transactions.data.map(tx => (
                                <tr key={tx.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-6 py-4 font-mono text-[10px] text-teal/80">{tx.payment_reference}</td>
                                    <td className="px-6 py-4">
                                        <p className="text-white font-medium text-sm">{tx.requester_name || tx.requester?.name || '—'}</p>
                                        <p className="text-[10px] font-mono text-white/30">{tx.requester_email || tx.requester?.email || '—'}</p>
                                    </td>
                                    <td className="px-6 py-4">
                                        <p className="text-white text-sm">{tx.maid_first_name} {tx.maid_last_name}</p>
                                        <p className="text-[10px] font-mono text-white/30">{tx.maid_nin ? `${tx.maid_nin.slice(0, 3)}****${tx.maid_nin.slice(-3)}` : '—'}</p>
                                    </td>
                                    <td className="px-6 py-4 text-center">
                                        <span className={`px-2 py-1 rounded text-[9px] font-mono uppercase tracking-widest ${tx.payment_status === 'paid' ? 'bg-success/10 text-success' :
                                                tx.payment_status === 'failed' ? 'bg-danger/10 text-danger' :
                                                    'bg-copper/10 text-copper'
                                            }`}>
                                            {tx.payment_status || 'pending'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-center">
                                        <span className={`px-2 py-1 rounded text-[9px] font-mono uppercase tracking-widest ${tx.verification_status === 'success' ? 'bg-success/10 text-success' :
                                                tx.verification_status === 'failed' ? 'bg-danger/10 text-danger' :
                                                    'bg-copper/10 text-copper'
                                            }`}>
                                            {tx.verification_status || 'pending'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right font-bold text-white">₦{Number(tx.amount || 0).toLocaleString()}</td>
                                    <td className="px-6 py-4 text-white/30 text-xs">{tx.created_at ? new Date(tx.created_at).toLocaleDateString() : '—'}</td>
                                    <td className="px-6 py-4 text-right">
                                        <Link
                                            href={`/admin/verification-transactions/${tx.id}`}
                                            className="inline-flex items-center gap-1 p-2 bg-white/5 hover:bg-white/10 rounded border border-white/5 text-white/40 hover:text-white transition-all opacity-0 group-hover:opacity-100"
                                        >
                                            👁️ View
                                        </Link>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={8} className="px-8 py-16 text-center text-white/30">
                                        <div className="text-3xl mb-3">🛡️</div>
                                        <p>No verification transactions found.</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {transactions?.links && transactions.links.length > 3 && (
                    <div className="p-6 border-t border-white/5 flex justify-center gap-1">
                        {transactions.links.map((link, k) => (
                            <Link
                                key={k}
                                href={link.url || '#'}
                                className={`px-3 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active
                                        ? 'bg-teal text-white border-teal shadow-[0_0_15px_rgba(45,164,142,0.3)]'
                                        : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'
                                    } ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}