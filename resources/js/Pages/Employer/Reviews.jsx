import { Head, Link } from '@inertiajs/react';
import EmployerLayout from '@/Layouts/EmployerLayout';

export default function Reviews({ auth, reviews }) {
    return (
        <EmployerLayout user={auth?.user}>
            <Head title="Service Reviews | Employer" />
            
            <div className="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 className="font-display text-3xl font-light text-espresso">Service Reviews</h1>
                    <p className="text-muted mt-2">Your feedback helps our Sentinel Agent maintain platform quality.</p>
                </div>
            </div>

            <div className="bg-ivory rounded-brand-lg border border-teal/10 p-6 mb-10 flex items-center gap-6 shadow-sm">
                <div className="w-12 h-12 bg-teal text-white rounded-full flex items-center justify-center text-xl shadow-brand-1">🛡️</div>
                <div>
                    <h3 className="font-display text-lg text-espresso mb-1">Meet the Sentinel Agent</h3>
                    <p className="text-sm text-muted">
                        Every review you post is analyzed by our Sentinel AI. It monitors helper performance in real-time to ensure only the best stay on our platform.
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6">
                {reviews.data.length > 0 ? reviews.data.map(review => (
                    <div key={review.id} className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6 relative overflow-hidden">
                        <div className="flex flex-col md:flex-row gap-6">
                            <div className="flex-shrink-0">
                                {review.maid?.avatar ? (
                                    <img src={`/storage/${review.maid.avatar}`} alt="" className="w-16 h-16 rounded-brand-md object-cover"/>
                                ) : (
                                    <div className="w-16 h-16 bg-teal/5 text-teal rounded-brand-md flex items-center justify-center text-2xl font-light">
                                        {review.maid?.name?.charAt(0)}
                                    </div>
                                )}
                            </div>
                            <div className="flex-1">
                                <div className="flex items-center justify-between mb-2">
                                    <div>
                                        <h3 className="font-semibold text-espresso">{review.maid?.name}</h3>
                                        <div className="flex items-center gap-1 mt-0.5">
                                            {[...Array(5)].map((_, i) => (
                                                <span key={i} className={`text-sm ${i < review.rating ? 'text-copper' : 'text-gray-200'}`}>★</span>
                                            ))}
                                        </div>
                                    </div>
                                    <span className="text-[10px] font-mono text-muted uppercase tracking-widest bg-gray-50 px-2 py-1 rounded">
                                        {new Date(review.created_at).toLocaleDateString()}
                                    </span>
                                </div>
                                <p className="text-espresso text-sm leading-relaxed mb-4 italic">"{review.comment}"</p>
                                
                                <div className="flex items-center justify-between pt-4 border-t border-gray-50">
                                    <Link href={`/employer/bookings/${review.booking_id}`} className="text-xs text-teal font-medium hover:underline">View Booking Details</Link>
                                    <div className="flex items-center gap-2">
                                        <span className="w-2 h-2 bg-success rounded-full"></span>
                                        <span className="text-[10px] font-mono text-muted uppercase tracking-tighter">Analyzed by Sentinel</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )) : (
                    <div className="bg-white rounded-brand-lg border border-gray-200 p-12 text-center">
                        <div className="w-16 h-16 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">⭐</div>
                        <h3 className="font-display text-xl text-espresso mb-2">No reviews yet</h3>
                        <p className="text-muted text-sm">You'll be able to leave reviews once your bookings are completed.</p>
                    </div>
                )}
            </div>

            {/* Pagination */}
            {reviews.links && reviews.links.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
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
        </EmployerLayout>
    );
}
