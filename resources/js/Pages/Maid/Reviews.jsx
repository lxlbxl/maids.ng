import { Head, Link } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function Reviews({ auth, reviews, sentinelLogs = [] }) {
    return (
        <MaidLayout user={auth?.user}>
            <Head title="My Ratings | Maids.ng" />
            
            <div className="mb-8">
                <h1 className="font-display text-3xl font-light text-espresso">My Ratings & Reviews</h1>
                <p className="text-muted mt-2">See what employers are saying about your work. Good reviews help you get hired faster.</p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Performance Tips Sidebar */}
                <div className="lg:col-span-1 space-y-6">
                    <div className="bg-espresso text-white rounded-brand-lg p-8 shadow-brand-2 sticky top-8">
                        <div className="flex items-center gap-4 mb-6">
                            <div className="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center border border-white/20 text-2xl">⭐</div>
                            <div>
                                <h3 className="font-display text-xl leading-tight">How to Get Better Ratings</h3>
                            </div>
                        </div>

                        <p className="text-sm text-white/70 leading-relaxed mb-8">
                            Employers rate you after each job. The higher your rating, the more job offers you will receive from Maids.ng.
                        </p>

                        <div className="space-y-4 text-sm text-white/80">
                            <div className="flex items-start gap-3 bg-white/5 border border-white/10 p-4 rounded-brand-lg">
                                <span className="text-lg">🕐</span>
                                <p>Always be on time. Employers notice this.</p>
                            </div>
                            <div className="flex items-start gap-3 bg-white/5 border border-white/10 p-4 rounded-brand-lg">
                                <span className="text-lg">🧹</span>
                                <p>Do your job well and do not leave anything half done.</p>
                            </div>
                            <div className="flex items-start gap-3 bg-white/5 border border-white/10 p-4 rounded-brand-lg">
                                <span className="text-lg">💬</span>
                                <p>Talk to your employer politely and ask questions when you are not sure.</p>
                            </div>
                        </div>

                        {sentinelLogs.length > 0 && (
                            <div className="mt-8">
                                <h4 className="text-[10px] font-mono uppercase tracking-[0.2em] text-white/40 mb-4">Recent Notes From Our Team:</h4>
                                <div className="space-y-3">
                                    {sentinelLogs.map((log) => (
                                        <div key={log.id} className="bg-white/5 border border-white/10 p-4 rounded-brand-lg">
                                            <div className="flex items-center justify-between mb-2">
                                                <span className="text-[10px] font-bold uppercase tracking-widest text-teal">
                                                    {log.decision}
                                                </span>
                                                <span className="text-[10px] text-white/40">{new Date(log.created_at).toLocaleDateString()}</span>
                                            </div>
                                            <p className="text-xs text-white/80 italic leading-relaxed">"{log.reasoning}"</p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Reviews List */}
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h2 className="font-display text-xl text-espresso">What Employers Said</h2>
                                <p className="text-xs text-muted mt-1">{reviews?.total || 0} total {reviews?.total === 1 ? 'review' : 'reviews'}</p>
                            </div>
                        </div>
                        
                        {reviews?.data?.length > 0 ? (
                            <div className="divide-y divide-gray-100">
                                {reviews.data.map(review => (
                                    <div key={review.id} className="p-8 hover:bg-gray-50 transition-all">
                                        <div className="flex items-center justify-between mb-4">
                                            <div>
                                                <p className="font-bold text-espresso">{review.employer?.name}</p>
                                                <p className="text-xs text-muted">{new Date(review.created_at).toLocaleDateString('en-NG', { day: 'numeric', month: 'long', year: 'numeric' })}</p>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                {[...Array(5)].map((_, i) => (
                                                    <span key={i} className={`text-lg ${i < review.rating ? 'text-copper' : 'text-gray-200'}`}>★</span>
                                                ))}
                                                <span className="text-sm text-muted ml-1">({review.rating}/5)</span>
                                            </div>
                                        </div>
                                        <p className="text-sm text-espresso leading-relaxed italic border-l-2 border-teal/30 pl-4 py-1">
                                            "{review.comment || 'No comment was left.'}"
                                        </p>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="p-16 text-center text-muted">
                                <div className="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">⭐</div>
                                <h3 className="font-display text-xl text-espresso mb-2">No Reviews Yet</h3>
                                <p className="text-sm max-w-xs mx-auto">
                                    When an employer rates you after a job, their review will appear here. Keep doing great work!
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Pagination */}
                    {reviews?.links && reviews.links.length > 3 && (
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
