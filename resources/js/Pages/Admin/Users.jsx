import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Users({ auth, users, roles }) {
    const { post, delete: destroy, processing } = useForm();

    const handleStatusUpdate = (id, currentStatus) => {
        const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
        post(route('admin.users.status', { id, status: newStatus }));
    };

    const handleRoleUpdate = (id, role) => {
        post(route('admin.users.role', { id, role }));
    };

    const handleDelete = (id) => {
        if (confirm('Permanently remove this user? This action cannot be undone.')) {
            destroy(route('admin.users.destroy', id));
        }
    };

    return (
        <AdminLayout>
            <Head title="People Management | Mission Control" />
            
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">People Management</h1>
                    <p className="text-white/40 text-sm">Oversee all platform participants, roles, and account statuses.</p>
                </div>
                <button className="bg-white/5 border border-white/10 px-6 py-3 rounded-brand-md text-[10px] font-mono uppercase tracking-widest text-white/60 hover:text-white hover:bg-white/10 transition-all font-bold">
                    + Register Human Entity
                </button>
            </div>

            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-8 py-5">User</th>
                                <th className="px-8 py-5">Role/Identity</th>
                                <th className="px-8 py-5 text-center">Status</th>
                                <th className="px-8 py-5">Contact</th>
                                <th className="px-8 py-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {users.data.map(user => (
                                <tr key={user.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-8 py-5">
                                        <div className="flex items-center gap-4">
                                            <div className="w-10 h-10 rounded-full bg-[#1c1c1e] text-lg flex items-center justify-center border border-white/5 shadow-inner">
                                                {user.name.charAt(0)}
                                            </div>
                                            <div>
                                                <p className="font-bold text-white leading-tight">{user.name}</p>
                                                <p className="text-[10px] font-mono text-white/20 uppercase">ID: ENT-{user.id}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-8 py-5">
                                        <select 
                                            value={user.roles[0]?.name}
                                            onChange={(e) => handleRoleUpdate(user.id, e.target.value)}
                                            disabled={processing}
                                            className="bg-transparent border-none text-[11px] font-mono uppercase tracking-widest text-teal font-bold focus:ring-0 cursor-pointer"
                                        >
                                            {roles.map(role => (
                                                <option key={role.id} value={role.name} className="bg-espresso">{role.name}</option>
                                            ))}
                                        </select>
                                    </td>
                                    <td className="px-8 py-5 text-center">
                                        <button 
                                            onClick={() => handleStatusUpdate(user.id, user.status)}
                                            disabled={processing}
                                            className={`px-3 py-1 rounded-full text-[9px] font-mono font-bold uppercase tracking-widest transition-all ${user.status === 'active' ? 'bg-teal/10 text-teal' : 'bg-danger/10 text-danger'}`}
                                        >
                                            {user.status}
                                        </button>
                                    </td>
                                    <td className="px-8 py-5 font-mono text-[10px] text-white/40">
                                        {user.email}<br/>
                                        {user.phone}
                                    </td>
                                    <td className="px-8 py-5 text-right">
                                        <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <Link href={route('admin.users.show', user.id)} className="p-2 bg-white/5 hover:bg-white/10 rounded border border-white/5 text-white/40 hover:text-white transition-all">👁️</Link>
                                            <button 
                                                onClick={() => handleDelete(user.id)}
                                                disabled={processing}
                                                className="p-2 bg-danger/5 hover:bg-danger/20 rounded border border-danger/10 text-danger/40 hover:text-danger transition-all"
                                            >
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination links */}
            {users.links && users.links.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {users.links.map((link, k) => (
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
