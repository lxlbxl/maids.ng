import { Head } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function Reviews({ auth, reviews, sentinelLogs = [] }) {
    return (
        <MaidLayout user={auth?.user}>
            <Head title="Performance & Reviews | Helper" />
            
            <div className="mb-8">
                <h1 className="font-display text-3xl font-light text-espresso">Performance & Reviews</h1>
                <p className="text-muted mt-2">See how employers rate your work and review the Sentinel Agent's quality assessments.</p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Sentinel Agent Insights */}
                <div className="lg:col-span-1 space-y-6">
                    <div className="bg-espresso text-white rounded-brand-lg p-8 shadow-brand-2 sticky top-8">
                        <div className="flex items-center gap-4 mb-8">
                            <div className="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center border border-white/20 text-2xl">🕵️</div>
                            <div>
                                <h3 className="font-display text-xl leading-tight">Sentinel<br/><span className="text-white/40 text-sm font-mono tracking-widest uppercase">Quality Agent</span></h3>
                            </div>
                        </div>

                        <p className="text-sm text-white/70 leading-relaxed mb-8">
                            The Sentinel Agent monitors your performance trends and feedback to maintain platform quality. Positive reviews boost your matching priority.
                        </p>

                        <div className="space-y-6">
                            <h4 className="text-[10px] font-mono uppercase tracking-[0.2em] text-white/40 mb-4 px-2">Quality Reports:</h4>
                            {sentinelLogs.length > 0 ? sentinelLogs.map((log) => (
                                <div key={log.id} className="bg-white/5 border border-white/10 p-4 rounded-brand-lg">
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="text-[10px] font-bold uppercase tracking-widest text-teal">
                                            Status: {log.decision}
                                        </span>
                                        <span className="text-[10px] text-white/40">{new Date(log.created_at).toLocaleDateString()}</span>
                                    </div>
                                    <p className="text-xs text-white/80 italic leading-relaxed">"{log.reasoning}"</p>
                                    <div className="mt-3 flex items-center gap-2">
                                        <div className="flex-1 h-1 bg-white/10 rounded-full overflow-hidden">
                                            <div className="h-full bg-teal" style={{width: `${log.confidence_score}%`}}></div>
                                        </div>
                                        <span className="text-[10px] font-mono whitespace-nowrap text-white/30">{log.confidence_score}% Rating</span>
                                    </div>
                                </div>
                            )) : (
                                <div className="py-8 text-center border border-dashed border-white/10 rounded-brand-lg">
                                    <p className="text-xs text-white/40">Waiting for first review cycle...</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Reviews List */}
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 className="font-display text-xl text-espresso">Employer Feedback</h2>
                            <span className="bg-gray-100 text-muted text-[10px] font-mono px-2 py-0.5 rounded-full uppercase tracking-widest">{reviews.total} Reviews</span>
                        </div>
                        
                        {reviews.data.length > 0 ? (
                            <div className="divide-y divide-gray-100">
                                {reviews.data.map(review => (
                                    <div key={review.id} className="p-8 hover:bg-gray-50 transition-all">
                                        <div className="flex items-center justify-between mb-4">
                                            <div>
                                                <p className="font-bold text-espresso">{review.employer?.name}</p>
                                                <p className="text-xs text-muted">{new Date(review.created_at).toLocaleDateString()}</p>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                {[...Array(5)].map((_, i) => (
                                                    <span key={i} className={`text-sm ${i < review.rating ? 'text-copper' : 'text-gray-200'}`}>★</span>
                                                ))}
                                            </div>
                                        </div>
                                        <p className="text-sm text-espresso leading-relaxed italic border-l-2 border-teal-ghost pl-4 py-1">
                                            "{review.comment || 'No comment provided.'}"
                                        </p>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="p-16 text-center text-muted">
                                <div className="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">⭐</div>
                                <h3 className="font-display text-xl text-espresso mb-2">No reviews yet</h3>
                                <p className="text-sm max-w-xs mx-auto">Complete assignments to start building your reputation on Maids.ng.</p>
                            </div>
                        )}
                    </div>

                    {/* Pagination */}
                    {reviews.links && reviews.links.length > 3 && (
                        <div className="flex justify-center gap-1">
                            {reviews.links.map((link, k) => (
                                <Link
                                    key={k}
                                    href={link.url || '#'}
                                    className={`px-4 py-2 text-sm rounded-brand-md border ${link.active ? 'bg-teal text-white border-teal' : 'bg-white text-muted border-gray-200 hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </MaidLayout>
    );
}
