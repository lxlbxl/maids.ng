import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

export default function Reviews({ auth, reviews, stats, filters }) {
    const [processingId, setProcessingId] = useState(null);
    const [toastMessage, setToastMessage] = useState(null);

    const showToast = useCallback((message, type = 'success') => {
        setToastMessage({ message, type });
        setTimeout(() => setToastMessage(null), 4000);
    }, []);

    const handleFlag = (review) => {
        if (!confirm(`Flag review by ${review.employer?.name || 'Unknown'} for moderation? This marks it as inappropriate.`)) return;
        setProcessingId(`flag-${review.id}`);
        router.post(`/admin/reviews/${review.id}/flag`, {}, {
            preserveScroll: true,
            onSuccess: () => { showToast('Review flagged for moderation.'); setProcessingId(null); },
            onError: (e) => { showToast(e?.message || 'Failed to flag review.', 'error'); setProcessingId(null); },
        });
    };

    const handleDelete = (review) => {
        if (!confirm(`Permanently delete this review by ${review.employer?.name || 'Unknown'}? This action cannot be undone.`)) return;
        setProcessingId(`del-${review.id}`);
        router.delete(`/admin/reviews/${review.id}`, {
            preserveScroll: true,
            onSuccess: () => { showToast('Review deleted.'); setProcessingId(null); },
            onError: (e) => { showToast(e?.message || 'Failed to delete review.', 'error'); setProcessingId(null); },
        });
    };

    return (
        <AdminLayout>
            <Head title="Review Oversight | Mission Control" />

            {/* Toast */}
            <AnimatePresence>
                {toastMessage && (
                    <motion.div initial={{ opacity: 0, y: -30, x: '-50%' }} animate={{ opacity: 1, y: 0, x: '-50%' }} exit={{ opacity: 0, y: -30, x: '-50%' }}
                        className={`fixed top-6 left-1/2 z-50 px-6 py-3 rounded-brand-lg shadow-brand-3 text-sm font-medium ${toastMessage.type === 'error' ? 'bg-red-900/90 border border-red-500/30 text-red-100' : 'bg-teal/90 border border-teal/30 text-white'}`}
                    >{toastMessage.type === 'error' ? '✗ ' : '✓ '}{toastMessage.message}</motion.div>
                )}
            </AnimatePresence>

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
                                            <button
                                                onClick={() => handleFlag(review)}
                                                disabled={processingId === `flag-${review.id}`}
                                                className="p-2 bg-copper/10 hover:bg-copper/20 rounded border border-copper/10 text-copper/60 hover:text-copper transition-all text-xs cursor-pointer disabled:opacity-50"
                                                title="Flag for moderation"
                                            >
                                                {processingId === `flag-${review.id}` ? '⏳' : '🚩'}
                                            </button>
                                            <button
                                                onClick={() => handleDelete(review)}
                                                disabled={processingId === `del-${review.id}`}
                                                className="p-2 bg-danger/5 hover:bg-danger/20 rounded border border-danger/10 text-danger/40 hover:text-danger transition-all text-xs cursor-pointer disabled:opacity-50"
                                                title="Delete review"
                                            >
                                                {processingId === `del-${review.id}` ? '⏳' : '🗑️'}
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
