import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Reviews({ auth, reviews, stats, filters }) {
    return (
        <AdminLayout>
            <Head title="Review Oversight | Mission Control" />
            
            <div className="mb-10">
                <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Review Oversight</h1>
                <p className="text-white/40 text-sm">Monitor platform reviews, flag inappropriate content, and maintain quality standards.</p>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-3 gap-4 mb-8">
                {[
                    { label: 'Total Reviews', value: stats?.total || 0, icon: '⭐' },
                    { label: 'Average Rating', value: stats?.average_rating || '—', icon: '📊' },
                    { label: 'Flagged', value: stats?.flagged || 0, icon: '🚩' },
                ].map(stat => (
                    <div key={stat.label} className="bg-[#121214] border border-white/5 rounded-brand-lg p-5">
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-lg">{stat.icon}</span>
                            <span className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30">{stat.label}</span>
                        </div>
                        <p className="text-3xl font-bold text-white">{stat.value}</p>
                    </div>
                ))}
            </div>

            {/* Reviews Table */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-8 py-5">Reviewer</th>
                                <th className="px-8 py-5">Helper</th>
                                <th className="px-8 py-5 text-center">Rating</th>
                                <th className="px-8 py-5">Comment</th>
                                <th className="px-8 py-5">Date</th>
                                <th className="px-8 py-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {reviews?.data?.length > 0 ? reviews.data.map(review => (
                                <tr key={review.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-8 py-5 text-white font-medium text-sm">{review.employer?.name || '—'}</td>
                                    <td className="px-8 py-5 text-white/60 text-sm">{review.maid?.name || '—'}</td>
                                    <td className="px-8 py-5 text-center">
                                        <span className="text-amber-400">{'⭐'.repeat(review.rating)}</span>
                                    </td>
                                    <td className="px-8 py-5 text-white/40 text-xs max-w-sm truncate">{review.comment || '—'}</td>
                                    <td className="px-8 py-5 text-white/30 text-xs">{new Date(review.created_at).toLocaleDateString()}</td>
                                    <td className="px-8 py-5 text-right">
                                        <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button className="p-2 bg-copper/10 hover:bg-copper/20 rounded border border-copper/10 text-copper/60 hover:text-copper transition-all text-xs">
                                                🚩
                                            </button>
                                            <button className="p-2 bg-danger/5 hover:bg-danger/20 rounded border border-danger/10 text-danger/40 hover:text-danger transition-all text-xs">
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={6} className="px-8 py-16 text-center text-white/30">
                                        <div className="text-3xl mb-3">⭐</div>
                                        <p>No reviews found.</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {reviews?.links?.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {reviews.links.map((link, k) => (
                        <Link key={k} href={link.url || '#'}
                            className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
