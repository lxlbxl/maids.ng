import { Head, Link, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function Users({ auth, users, stats, roles, filters = {} }) {
    const { post, delete: destroy, processing } = useForm();
    const [editUser, setEditUser] = useState(null);
    const { data, setData, put, processing: editing } = useForm({});

    const [filterState, setFilterState] = useState({
        search: filters.search || '',
        status: filters.status || '',
        sort: filters.sort || 'newest',
    });

    const applyFilters = () => {
        const params = {};
        Object.entries(filterState).forEach(([k, v]) => { if (v) params[k] = v; });
        router.get('/admin/users', params, { preserveState: true, replace: true });
    };
    const clearFilters = () => {
        setFilterState({ search: '', status: '', sort: 'newest' });
        router.get('/admin/users', {}, { preserveState: true, replace: true });
    };

    const handleStatusUpdate = (id, currentStatus) => {
        post(route('admin.users.status', { id, status: currentStatus === 'active' ? 'suspended' : 'active' }));
    };
    const handleRoleUpdate = (id, role) => { post(route('admin.users.role', { id, role })); };
    const handleDelete = (id) => { if (confirm('Permanently remove this user?')) destroy(route('admin.users.destroy', id)); };

    const openEdit = (user) => {
        setEditUser(user);
        setData({ name: user.name, email: user.email, phone: user.phone || '', password: '' });
    };
    const handleUpdate = (e) => {
        e.preventDefault();
        put(route('admin.users.update', editUser.id), {
            onSuccess: () => setEditUser(null),
        });
    };

    return (
        <AdminLayout>
            <Head title="Employer Hub | Mission Control" />
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div><h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Employer Hub</h1><p className="text-white/40 text-sm">Manage client relationships, bookings, and financial activities.</p></div>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                {[
                    { label: 'Total Employers', value: stats?.total || 0, icon: '👤', color: 'text-white' },
                    { label: 'Live Bookings', value: stats?.active_bookings || 0, icon: '📅', color: 'text-teal' },
                    { label: 'Total Revenue', value: `₦${(stats?.total_spent || 0).toLocaleString()}`, icon: '💰', color: 'text-emerald-400' },
                    { label: 'New Signups', value: stats?.new_signups || 0, icon: '📈', color: 'text-white' },
                ].map(stat => (
                    <div key={stat.label} className="bg-[#121214] border border-white/5 rounded-brand-lg p-5 hover:border-teal/20 transition-all">
                        <div className="flex items-center justify-between mb-2"><span className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">{stat.label}</span><span className="text-sm opacity-50">{stat.icon}</span></div>
                        <p className={`text-2xl font-bold ${stat.color}`}>{stat.value}</p>
                    </div>
                ))}
            </div>

            <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-4 mb-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex-1 min-w-[180px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Search</label><input type="text" value={filterState.search} onChange={e => setFilterState(s => ({ ...s, search: e.target.value }))} onKeyDown={e => e.key === 'Enter' && applyFilters()} placeholder="Name or phone..." className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white placeholder-white/20 focus:border-teal outline-none" /></div>
                    <div className="w-[140px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Status</label><select value={filterState.status} onChange={e => setFilterState(s => ({ ...s, status: e.target.value }))} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none"><option value="">All</option><option value="active">Active</option><option value="suspended">Suspended</option></select></div>
                    <div className="w-[130px]"><label className="block font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-1">Sort By</label><select value={filterState.sort} onChange={e => { setFilterState(s => ({ ...s, sort: e.target.value })); router.get('/admin/users', { ...filterState, sort: e.target.value }, { preserveState: true, replace: true }); }} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none"><option value="newest">Newest</option><option value="oldest">Oldest</option><option value="name_asc">Name A-Z</option><option value="name_desc">Name Z-A</option></select></div>
                    <div className="flex gap-2"><button onClick={applyFilters} className="h-10 px-4 bg-teal text-white text-xs font-bold rounded-brand-md hover:bg-teal/80">Apply</button><button onClick={clearFilters} className="h-10 px-3 bg-white/5 text-white/40 text-xs font-bold rounded-brand-md hover:bg-white/10">Clear</button></div>
                </div>
            </div>

            {/* Edit Modal */}
            {editUser && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60" onClick={() => setEditUser(null)}>
                    <div className="bg-[#121214] border border-white/10 rounded-brand-xl p-6 w-full max-w-md shadow-2xl" onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="font-display text-lg text-white">Edit User</h2>
                            <button onClick={() => setEditUser(null)} className="text-white/20 hover:text-white">✕</button>
                        </div>
                        <form onSubmit={handleUpdate} className="space-y-4">
                            <div><label className="block font-mono text-[9px] uppercase text-white/30 mb-1">Name</label><input type="text" value={data.name} onChange={e => setData('name', e.target.value)} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none" required /></div>
                            <div><label className="block font-mono text-[9px] uppercase text-white/30 mb-1">Email</label><input type="email" value={data.email} onChange={e => setData('email', e.target.value)} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none" /></div>
                            <div><label className="block font-mono text-[9px] uppercase text-white/30 mb-1">Phone</label><input type="text" value={data.phone} onChange={e => setData('phone', e.target.value)} className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white focus:border-teal outline-none" /></div>
                            <div><label className="block font-mono text-[9px] uppercase text-white/30 mb-1">New Password <span className="text-white/20">(leave blank to keep)</span></label><input type="text" value={data.password} onChange={e => setData('password', e.target.value)} placeholder="Set new password..." className="w-full h-10 bg-[#0a0a0b] border border-white/10 rounded-brand-md px-3 text-sm text-white placeholder-white/20 focus:border-teal outline-none" /></div>
                            <button type="submit" disabled={editing} className="w-full py-3 bg-teal text-black rounded-brand-md text-xs font-bold uppercase hover:brightness-110 transition-all">{editing ? 'Saving...' : 'Save Changes'}</button>
                        </form>
                    </div>
                </div>
            )}

            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-6 py-4">User</th>
                                <th className="px-6 py-4">Role</th>
                                <th className="px-6 py-4 text-center">Status</th>
                                <th className="px-6 py-4">Contact</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {users?.data?.length > 0 ? users.data.map(user => (
                                <tr key={user.id} className="hover:bg-white/[0.02] transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded-full bg-[#1c1c1e] text-sm flex items-center justify-center border border-white/5 font-bold text-white/60">{user.name?.charAt(0)}</div>
                                            <div><p className="font-bold text-white text-xs">{user.name}</p><p className="text-[10px] text-white/30">{user.email}</p></div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4">
                                        {user.roles.length > 0 ? user.roles.map(role => (
                                            <span key={role.id} className="inline-flex px-2 py-0.5 bg-teal/10 text-teal rounded-full text-[9px] font-mono font-bold uppercase mr-1 border border-teal/20">{role.name}</span>
                                        )) : <span className="text-white/20 text-xs">No role</span>}
                                    </td>
                                    <td className="px-6 py-4 text-center">
                                        <button onClick={() => handleStatusUpdate(user.id, user.status)} disabled={processing} className={`px-3 py-1 rounded-full text-[9px] font-mono font-bold uppercase cursor-pointer transition-all ${user.status === 'active' ? 'bg-teal/10 text-teal border border-teal/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`}>{user.status}</button>
                                    </td>
                                    <td className="px-6 py-4 text-white/40 text-xs">{user.phone || '—'}</td>
                                    <td className="px-6 py-4 text-right">
                                        <div className="flex items-center justify-end gap-1.5">
                                            <Link href={`/admin/users/${user.id}`} className="px-2.5 py-1.5 bg-white/5 hover:bg-white/10 rounded border border-white/5 text-white/40 hover:text-white text-xs">View</Link>
                                            <button onClick={() => openEdit(user)} className="px-2.5 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 rounded border border-amber-500/20 text-amber-400 text-xs font-bold transition-all">Edit</button>
                                            {roles?.map(role => (
                                                <button key={role.id} onClick={() => handleRoleUpdate(user.id, role.name)} className="px-2 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 rounded border border-emerald-500/20 text-emerald-400 text-xs font-bold transition-all">{role.name}</button>
                                            ))}
                                            <button onClick={() => handleDelete(user.id)} className="px-2 py-1.5 bg-red-500/10 hover:bg-red-500/20 rounded border border-red-500/20 text-red-400 text-xs font-bold">Del</button>
                                        </div>
                                    </td>
                                </tr>
                            )) : (
                                <tr><td colSpan={6} className="px-6 py-16 text-center text-white/30"><div className="text-3xl mb-3">👤</div><p>No users found.</p></td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {users?.links?.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">{users.links.map((link, k) => (
                    <Link key={k} href={link.url || '#'} className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                ))}</div>
            )}
        </AdminLayout>
    );
}
