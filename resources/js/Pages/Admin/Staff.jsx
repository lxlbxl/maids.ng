import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Staff({ auth, staff, stats, filters }) {
    const { post, processing } = useForm();

    const handleStatusToggle = (id, currentStatus) => {
        const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
        post(route('admin.users.status', { id, status: newStatus }));
    };

    return (
        <AdminLayout>
            <Head title="Staff Control | Mission Control" />
            
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Staff Control</h1>
                    <p className="text-white/40 text-sm">Manage administrative access and monitor staff activity.</p>
                </div>
            </div>

            {/* Insight Hub */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
                {[
                    { label: 'Total Admins', value: stats?.total_staff || 0, icon: '👮', color: 'text-white' },
                    { label: 'Currently Active', value: stats?.active_now || 0, icon: '⚡', color: 'text-teal' },
                    { label: 'Total Audit Actions', value: stats?.audit_actions || 0, icon: '📝', color: 'text-success' },
                ].map(stat => (
                    <div key={stat.label} className="bg-[#121214] border border-white/5 rounded-brand-lg p-5 hover:border-teal/20 transition-all">
                        <div className="flex items-center justify-between mb-2">
                            <span className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">{stat.label}</span>
                            <span className="text-sm opacity-50">{stat.icon}</span>
                        </div>
                        <p className={`text-2xl font-bold ${stat.color}`}>{stat.value}</p>
                    </div>
                ))}
            </div>

            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-8 py-5">Staff Member</th>
                                <th className="px-8 py-5">Role</th>
                                <th className="px-8 py-5 text-center">Status</th>
                                <th className="px-8 py-5">Contact</th>
                                <th className="px-8 py-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {staff?.data?.map(member => (
                                <tr key={member.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-8 py-5">
                                        <div className="flex items-center gap-4">
                                            <div className="w-10 h-10 rounded-full bg-[#1c1c1e] text-lg flex items-center justify-center border border-white/5 shadow-inner">
                                                {member.name?.charAt(0) || 'S'}
                                            </div>
                                            <div>
                                                <p className="font-bold text-white leading-tight">{member.name || 'Unknown Staff'}</p>
                                                <p className="text-[10px] font-mono text-white/20 uppercase">ID: STF-{member.id}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-8 py-5">
                                        <span className="text-[11px] font-mono uppercase tracking-widest text-teal font-bold">
                                            {member.roles?.[0]?.name || 'Staff'}
                                        </span>
                                    </td>
                                    <td className="px-8 py-5 text-center">
                                        <button 
                                            onClick={() => handleStatusToggle(member.id, member.status)}
                                            disabled={processing}
                                            className={`px-3 py-1 rounded-full text-[9px] font-mono font-bold uppercase tracking-widest transition-all ${member.status === 'active' ? 'bg-teal/10 text-teal' : 'bg-danger/10 text-danger'}`}
                                        >
                                            {member.status}
                                        </button>
                                    </td>
                                    <td className="px-8 py-5 font-mono text-[10px] text-white/40">
                                        {member.email}<br/>
                                        {member.phone}
                                    </td>
                                    <td className="px-8 py-5 text-right">
                                        <Link href={`/admin/users/${member.id}`} className="p-2 bg-white/5 hover:bg-white/10 rounded border border-white/5 text-white/40 hover:text-white transition-all opacity-0 group-hover:opacity-100">
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
            {staff?.links?.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {staff.links.map((link, k) => (
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
