import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Verifications({ auth, pendingVerifications }) {
    const { post, processing } = useForm();

    const handleApprove = (id) => {
        if (confirm('Verify this helper? This will grant them full access to client matches.')) {
            post(route('admin.verifications.approve', id));
        }
    };

    return (
        <AdminLayout>
            <Head title="Verification Hub | Mission Control" />
            
            <div className="mb-10">
                <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Verification Hub</h1>
                <p className="text-white/40 text-sm">Review identity and background credentials for new household helpers.</p>
            </div>

            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="p-8 border-b border-white/5 flex items-center justify-between">
                    <h2 className="font-mono text-[10px] uppercase tracking-widest text-teal font-bold">Pending Review Queue</h2>
                    <span className="text-xs text-white/20">{pendingVerifications.total} total pending</span>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-8 py-5">Helper Identity</th>
                                <th className="px-8 py-5 text-center">NIN Status</th>
                                <th className="px-8 py-5 text-center">Background</th>
                                <th className="px-8 py-5">Submissions</th>
                                <th className="px-8 py-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {pendingVerifications.data.map(user => (
                                <tr key={user.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-8 py-5">
                                        <div className="flex items-center gap-4">
                                            <div className="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-xl">👤</div>
                                            <div>
                                                <p className="font-bold text-white leading-tight">{user.name}</p>
                                                <p className="text-[10px] font-mono text-white/30 truncate max-w-[150px]">{user.email}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-8 py-5 text-center">
                                        <span className={`px-2 py-1 rounded text-[9px] font-mono uppercase tracking-widest ${user.maid_profile?.nin_verified ? 'bg-teal/10 text-teal' : 'bg-copper/10 text-copper'}`}>
                                            {user.maid_profile?.nin_verified ? 'Verified' : 'Pending'}
                                        </span>
                                    </td>
                                    <td className="px-8 py-5 text-center">
                                         <span className={`px-2 py-1 rounded text-[9px] font-mono uppercase tracking-widest ${user.maid_profile?.background_verified ? 'bg-teal/10 text-teal' : 'bg-white/5 text-white/20'}`}>
                                            {user.maid_profile?.background_verified ? 'Cleared' : 'Not Started'}
                                        </span>
                                    </td>
                                    <td className="px-8 py-5">
                                        <div className="flex gap-2">
                                            <button className="bg-white/5 hover:bg-white/10 p-2 rounded border border-white/5 transition-colors" title="View Identity Card">🪪</button>
                                            <button className="bg-white/5 hover:bg-white/10 p-2 rounded border border-white/5 transition-colors" title="View Reference Letter">📄</button>
                                        </div>
                                    </td>
                                    <td className="px-8 py-5 text-right">
                                        <div className="flex justify-end gap-3">
                                            <button 
                                                onClick={() => handleApprove(user.id)}
                                                disabled={processing}
                                                className="bg-teal/20 text-teal border border-teal/30 px-4 py-2 rounded text-[10px] font-mono uppercase tracking-widest font-bold hover:bg-teal hover:text-espresso transition-all"
                                            >
                                                Approve
                                            </button>
                                            <button 
                                                disabled={processing}
                                                className="bg-white/5 text-white/40 border border-white/10 px-4 py-2 rounded text-[10px] font-mono uppercase tracking-widest font-bold hover:bg-danger/20 hover:text-danger hover:border-danger/30 transition-all"
                                            >
                                                Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {pendingVerifications.data.length === 0 && (
                    <div className="p-20 text-center space-y-4">
                        <span className="text-4xl">🏝️</span>
                        <p className="text-white/40 font-mono text-[10px] uppercase tracking-widest font-bold italic">The review queue is currently empty.</p>
                    </div>
                )}
            </div>

            {/* Pagination links */}
            {pendingVerifications.links && pendingVerifications.links.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {pendingVerifications.links.map((link, k) => (
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
