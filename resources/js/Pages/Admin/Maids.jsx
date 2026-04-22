import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Maids({ auth, maids, stats, filters }) {
    const { post, processing } = useForm();

    const handleStatusToggle = (id, currentStatus) => {
        const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
        post(`/admin/maids/${id}/status?status=${newStatus}`);
    };

    return (
        <AdminLayout>
            <Head title="Helper Management | Mission Control" />
            
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Helper Management</h1>
                    <p className="text-white/40 text-sm">Monitor all registered helpers, verification status, and performance.</p>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-3 gap-4 mb-8">
                {[
                    { label: 'Total Helpers', value: stats?.total || 0, icon: '👥', color: 'text-white' },
                    { label: 'Active', value: stats?.active || 0, icon: '🟢', color: 'text-teal' },
                    { label: 'Verified', value: stats?.verified || 0, icon: '🛡️', color: 'text-success' },
                ].map(stat => (
                    <div key={stat.label} className="bg-[#121214] border border-white/5 rounded-brand-lg p-5">
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-lg">{stat.icon}</span>
                            <span className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">{stat.label}</span>
                        </div>
                        <p className={`text-3xl font-bold ${stat.color}`}>{stat.value}</p>
                    </div>
                ))}
            </div>

            {/* Helpers Table */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-8 py-5">Helper</th>
                                <th className="px-8 py-5">Location</th>
                                <th className="px-8 py-5 text-center">Rating</th>
                                <th className="px-8 py-5 text-center">Verified</th>
                                <th className="px-8 py-5 text-center">Status</th>
                                <th className="px-8 py-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {maids?.data?.map(maid => (
                                <tr key={maid.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-8 py-5">
                                        <div className="flex items-center gap-4">
                                            <div className="w-10 h-10 rounded-full bg-[#1c1c1e] text-lg flex items-center justify-center border border-white/5 shadow-inner">
                                                {maid.name?.charAt(0)}
                                            </div>
                                            <div>
                                                <p className="font-bold text-white leading-tight">{maid.name}</p>
                                                <p className="text-[10px] font-mono text-teal uppercase tracking-widest">
                                                    {maid.maid_profile?.maid_type || 'Helper'}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-8 py-5 text-white/40 text-xs">📍 {maid.maid_profile?.location || maid.location || '—'}</td>
                                    <td className="px-8 py-5 text-center">
                                        <span className="text-amber-400">⭐</span>
                                        <span className="text-white ml-1">{maid.maid_profile?.rating ? Number(maid.maid_profile.rating).toFixed(1) : '—'}</span>
                                    </td>
                                    <td className="px-8 py-5 text-center">
                                        {maid.maid_profile?.nin_verified ? (
                                            <span className="bg-success/10 text-success px-2 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase">✓ Yes</span>
                                        ) : (
                                            <span className="bg-danger/10 text-danger px-2 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase">✗ No</span>
                                        )}
                                    </td>
                                    <td className="px-8 py-5 text-center">
                                        <button
                                            onClick={() => handleStatusToggle(maid.id, maid.status)}
                                            disabled={processing}
                                            className={`px-3 py-1 rounded-full text-[9px] font-mono font-bold uppercase tracking-widest transition-all ${maid.status === 'active' ? 'bg-teal/10 text-teal' : 'bg-danger/10 text-danger'}`}
                                        >
                                            {maid.status}
                                        </button>
                                    </td>
                                    <td className="px-8 py-5 text-right">
                                        <Link href={`/admin/maids/${maid.id}`} className="p-2 bg-white/5 hover:bg-white/10 rounded border border-white/5 text-white/40 hover:text-white transition-all opacity-0 group-hover:opacity-100">
                                            👁️
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {maids?.links?.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {maids.links.map((link, k) => (
                        <Link
                            key={k}
                            href={link.url || '#'}
                            className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal shadow-[0_0_15px_rgba(45,164,142,0.3)]' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
