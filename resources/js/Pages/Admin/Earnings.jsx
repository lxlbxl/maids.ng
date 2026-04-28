import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Earnings({ auth, monthlyRevenue, stats }) {
    return (
        <AdminLayout>
            <Head title="Earnings Report | Mission Control" />
            
            <div className="mb-10">
                <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Earnings Report</h1>
                <p className="text-white/40 text-sm">Platform revenue analysis supervised by the Treasurer Agent.</p>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                {[
                    { label: 'Total Revenue', value: `₦${Number(stats?.total_revenue || 0).toLocaleString()}`, icon: '💰', color: 'text-teal' },
                    { label: 'This Month', value: `₦${Number(stats?.this_month || 0).toLocaleString()}`, icon: '📅', color: 'text-success' },
                    { label: 'Bookings Value', value: `₦${Number(stats?.total_bookings_value || 0).toLocaleString()}`, icon: '📊', color: 'text-white' },
                    { label: 'Pending Payouts', value: `₦${Number(stats?.pending_payouts || 0).toLocaleString()}`, icon: '⏳', color: 'text-copper' },
                ].map(stat => (
                    <div key={stat.label} className="bg-[#121214] border border-white/5 rounded-brand-lg p-5">
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-lg">{stat.icon}</span>
                            <span className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">{stat.label}</span>
                        </div>
                        <p className={`text-2xl font-bold ${stat.color}`}>{stat.value}</p>
                    </div>
                ))}
            </div>

            {/* Monthly Revenue Table */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="px-8 py-5 border-b border-white/5 flex items-center justify-between">
                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 font-bold">Monthly Revenue Breakdown</h3>
                    <span className="bg-teal/5 text-teal text-[10px] font-mono px-2 py-0.5 rounded-full uppercase tracking-widest">Treasurer Supervised</span>
                </div>
                {monthlyRevenue?.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                                <tr>
                                    <th className="px-8 py-5">Month</th>
                                    <th className="px-8 py-5 text-right">Revenue</th>
                                    <th className="px-8 py-5">Visual</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-white/5">
                                {monthlyRevenue.map((row, i) => {
                                    const maxRevenue = Math.max(...monthlyRevenue.map(r => Number(r.total)));
                                    const pct = maxRevenue > 0 ? (Number(row.total) / maxRevenue * 100) : 0;
                                    return (
                                        <tr key={i} className="hover:bg-white/[0.02] transition-colors">
                                            <td className="px-8 py-5 text-white font-medium">{row.month}</td>
                                            <td className="px-8 py-5 text-right font-bold text-teal font-mono">₦{Number(row.total).toLocaleString()}</td>
                                            <td className="px-8 py-5 w-1/2">
                                                <div className="w-full h-3 bg-white/5 rounded-full overflow-hidden">
                                                    <div className="h-full bg-gradient-to-r from-teal to-teal/60 rounded-full shadow-[0_0_10px_rgba(45,164,142,0.3)] transition-all" style={{ width: `${pct}%` }}></div>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="px-8 py-16 text-center text-white/30">
                        <div className="text-3xl mb-3">📊</div>
                        <p>No revenue data available yet.</p>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
