import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function Notifications({ auth, notifications }) {
    const [showCompose, setShowCompose] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        title: '',
        message: '',
        target: 'all',
    });

    const handleSend = (e) => {
        e.preventDefault();
        post('/admin/notifications', {
            onSuccess: () => { reset(); setShowCompose(false); },
        });
    };

    return (
        <AdminLayout>
            <Head title="Notifications | Mission Control" />
            
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Broadcast Center</h1>
                    <p className="text-white/40 text-sm">Send platform-wide notifications and announcements.</p>
                </div>
                <button onClick={() => setShowCompose(!showCompose)} className="bg-teal/10 border border-teal/20 px-6 py-3 rounded-brand-md text-[10px] font-mono uppercase tracking-widest text-teal hover:bg-teal/20 transition-all font-bold">
                    📡 {showCompose ? 'Cancel' : 'New Broadcast'}
                </button>
            </div>

            {/* Compose Form */}
            {showCompose && (
                <form onSubmit={handleSend} className="bg-[#121214] border border-teal/20 rounded-brand-xl p-6 mb-8 shadow-[0_0_30px_rgba(45,164,142,0.1)]">
                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-teal mb-6 font-bold">📡 Compose Broadcast</h3>
                    <div className="space-y-4">
                        <div>
                            <label className="block text-xs text-white/40 mb-2 font-mono uppercase tracking-widest">Title</label>
                            <input type="text" value={data.title} onChange={e => setData('title', e.target.value)} className="w-full bg-white/5 border border-white/10 rounded-brand-md px-4 py-3 text-white text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" placeholder="Notification title..." required />
                        </div>
                        <div>
                            <label className="block text-xs text-white/40 mb-2 font-mono uppercase tracking-widest">Message</label>
                            <textarea rows={4} value={data.message} onChange={e => setData('message', e.target.value)} className="w-full bg-white/5 border border-white/10 rounded-brand-md px-4 py-3 text-white text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" placeholder="Notification message..." required />
                        </div>
                        <div>
                            <label className="block text-xs text-white/40 mb-2 font-mono uppercase tracking-widest">Target Audience</label>
                            <select value={data.target} onChange={e => setData('target', e.target.value)} className="bg-white/5 border border-white/10 rounded-brand-md px-4 py-3 text-white text-sm focus:border-teal">
                                <option value="all" className="bg-[#121214]">All Users</option>
                                <option value="maids" className="bg-[#121214]">Helpers Only</option>
                                <option value="employers" className="bg-[#121214]">Employers Only</option>
                            </select>
                        </div>
                        <button type="submit" disabled={processing} className="bg-teal text-white px-8 py-3 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all disabled:opacity-50">
                            {processing ? 'Sending...' : '📡 Send Broadcast'}
                        </button>
                    </div>
                </form>
            )}

            {/* Notification History */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="px-8 py-5 border-b border-white/5">
                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 font-bold">Broadcast History</h3>
                </div>
                {notifications?.data?.length > 0 ? (
                    <div className="divide-y divide-white/5">
                        {notifications.data.map(notif => (
                            <div key={notif.id} className="px-8 py-5 hover:bg-white/[0.02] transition-colors">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-white font-medium text-sm">{notif.title}</p>
                                        <p className="text-white/40 text-xs mt-1 line-clamp-2">{notif.message}</p>
                                    </div>
                                    <div className="text-right flex-shrink-0">
                                        <span className="bg-white/5 text-white/40 px-2 py-0.5 rounded-full text-[9px] font-mono uppercase">{notif.type || 'broadcast'}</span>
                                        <p className="text-white/20 text-[10px] font-mono mt-1">{new Date(notif.created_at).toLocaleDateString()}</p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="px-8 py-16 text-center text-white/30">
                        <div className="text-3xl mb-3">📡</div>
                        <p>No broadcasts sent yet.</p>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
