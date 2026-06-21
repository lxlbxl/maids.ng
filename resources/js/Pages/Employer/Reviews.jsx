import { Head, Link, useForm, usePage } from '@inertiajs/react';
import EmployerLayout from '@/Layouts/EmployerLayout';
import { useState, useEffect, useRef } from 'react';

const RATING_LABELS = { 1: 'Poor', 2: 'Fair', 3: 'Good', 4: 'Very Good', 5: 'Excellent' };

function StarPicker({ value, onChange }) {
    const [hovered, setHovered] = useState(0);
    return (
        <div className="flex gap-1">
            {[1, 2, 3, 4, 5].map(star => (
                <button
                    key={star}
                    type="button"
                    onClick={() => onChange(star)}
                    onMouseEnter={() => setHovered(star)}
                    onMouseLeave={() => setHovered(0)}
                    className={`text-4xl transition-all hover:scale-110 leading-none select-none
                        ${star <= (hovered || value) ? 'text-copper' : 'text-gray-200 dark:text-white/10'}`}
                    aria-label={`${star} star${star !== 1 ? 's' : ''}`}
                >
                    ★
                </button>
            ))}
        </div>
    );
}

function ReviewModal({ reviewableBookings, onClose }) {
    const overlayRef = useRef(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        booking_id: reviewableBookings.length === 1 ? String(reviewableBookings[0].id) : '',
        maid_id:    reviewableBookings.length === 1 ? String(reviewableBookings[0].maid_id) : '',
        rating:     0,
        comment:    '',
    });

    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [onClose]);

    const handleBookingChange = (bookingId) => {
        const booking = reviewableBookings.find(b => String(b.id) === String(bookingId));
        setData(prev => ({
            ...prev,
            booking_id: bookingId,
            maid_id: booking ? String(booking.maid_id) : '',
        }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('employer.reviews.create'), {
            onSuccess: () => { reset(); onClose(); },
        });
    };

    const commentLen = data.comment.length;
    const isValid = data.booking_id && data.maid_id && data.rating > 0 && commentLen >= 20;
    const selectedBooking = reviewableBookings.find(b => String(b.id) === String(data.booking_id));

    return (
        <div
            ref={overlayRef}
            onClick={(e) => { if (e.target === overlayRef.current) onClose(); }}
            className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
        >
            <div
                className="bg-white dark:bg-[#1c1c1e] rounded-t-2xl sm:rounded-brand-xl shadow-2xl w-full sm:max-w-lg max-h-[92vh] overflow-y-auto"
                style={{ animation: 'slide-up 0.25s ease both' }}
            >
                {/* Header */}
                <div className="sticky top-0 bg-white dark:bg-[#1c1c1e] border-b border-gray-100 dark:border-white/10 px-6 py-4 flex items-center justify-between z-10 rounded-t-2xl sm:rounded-t-brand-xl">
                    <div>
                        <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal">Leave a Review</p>
                        <h2 className="font-display text-xl text-espresso dark:text-[#f0ede8] font-light mt-0.5">Rate Your Helper</h2>
                    </div>
                    <button onClick={onClose} aria-label="Close"
                        className="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 dark:bg-white/10 hover:bg-gray-200 dark:hover:bg-white/20 text-muted transition-colors text-sm">
                        ✕
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    {/* Engagement selector */}
                    <div>
                        <label className="block text-xs font-mono uppercase tracking-widest text-muted dark:text-gray-400 mb-2">
                            Select Engagement *
                        </label>
                        {reviewableBookings.length === 1 ? (
                            <div className="flex items-center gap-3 bg-teal/5 border border-teal/10 rounded-brand-md px-4 py-3">
                                <div className="w-9 h-9 bg-teal/10 rounded-full flex items-center justify-center text-teal font-bold text-sm flex-shrink-0">
                                    {selectedBooking?.maid_name?.charAt(0) || '?'}
                                </div>
                                <div>
                                    <p className="font-semibold text-espresso dark:text-[#f0ede8] text-sm">{selectedBooking?.maid_name}</p>
                                    {selectedBooking?.start_date && (
                                        <p className="text-xs text-muted dark:text-gray-400">Started {selectedBooking.start_date}</p>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <select
                                value={data.booking_id}
                                onChange={e => handleBookingChange(e.target.value)}
                                className="w-full h-11 bg-white dark:bg-[#2a2a2c] border-2 border-gray-200 dark:border-white/10 rounded-brand-md px-3 text-sm text-espresso dark:text-[#f0ede8] focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none"
                                required
                            >
                                <option value="">Choose an engagement to review…</option>
                                {reviewableBookings.map(b => (
                                    <option key={b.id} value={b.id}>
                                        {b.maid_name}{b.start_date ? ` · started ${b.start_date}` : ''}
                                    </option>
                                ))}
                            </select>
                        )}
                        {errors.booking_id && <p className="text-rose-500 text-xs mt-1">{errors.booking_id}</p>}
                    </div>

                    {/* Star rating */}
                    <div>
                        <label className="block text-xs font-mono uppercase tracking-widest text-muted dark:text-gray-400 mb-3">
                            Your Rating *
                        </label>
                        <StarPicker value={data.rating} onChange={v => setData('rating', v)} />
                        {data.rating > 0 && (
                            <p className="text-sm font-semibold text-copper mt-2">{RATING_LABELS[data.rating]}</p>
                        )}
                        {errors.rating && <p className="text-rose-500 text-xs mt-1">{errors.rating}</p>}
                    </div>

                    {/* Comment */}
                    <div>
                        <label className="block text-xs font-mono uppercase tracking-widest text-muted dark:text-gray-400 mb-2">
                            Your Experience * <span className="normal-case text-muted/60">(min 20 chars)</span>
                        </label>
                        <textarea
                            value={data.comment}
                            onChange={e => setData('comment', e.target.value)}
                            rows={5}
                            maxLength={1000}
                            placeholder="Describe your experience — their work ethic, punctuality, skills, and anything that stood out…"
                            className="w-full bg-white dark:bg-[#2a2a2c] border-2 border-gray-200 dark:border-white/10 rounded-brand-md px-4 py-3 text-sm text-espresso dark:text-[#f0ede8] placeholder:text-muted/50 focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none resize-none transition-all"
                        />
                        <div className="flex justify-between mt-1">
                            {errors.comment ? (
                                <p className="text-rose-500 text-xs">{errors.comment}</p>
                            ) : (
                                <p className={`text-xs ${commentLen < 20 ? 'text-muted dark:text-gray-400' : 'text-success'}`}>
                                    {commentLen < 20 ? `${20 - commentLen} more characters needed` : '✓ Minimum length met'}
                                </p>
                            )}
                            <p className="text-xs text-muted dark:text-gray-400">{commentLen}/1000</p>
                        </div>
                    </div>

                    {/* Sentinel notice */}
                    <div className="bg-copper/5 border border-copper/15 rounded-brand-md p-4 flex gap-3 items-start">
                        <span className="text-xl flex-shrink-0 mt-0.5">🛡️</span>
                        <div>
                            <p className="text-xs font-semibold text-espresso dark:text-[#f0ede8] mb-1">Sentinel Review Process</p>
                            <p className="text-xs text-muted dark:text-gray-400 leading-relaxed">
                                Your review is screened by our Sentinel AI for authenticity and quality. Only reviews that meet our
                                community guidelines will appear on the helper's public profile.
                            </p>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-3 pt-1">
                        <button type="button" onClick={onClose}
                            className="flex-1 border border-gray-200 dark:border-white/10 text-muted dark:text-gray-400 py-3 rounded-brand-md text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 transition-all">
                            Cancel
                        </button>
                        <button type="submit"
                            disabled={!isValid || processing}
                            className="flex-[2] bg-teal text-white py-3 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all shadow-md shadow-teal/20 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                            {processing ? (
                                <>
                                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                    Submitting…
                                </>
                            ) : 'Submit Review →'}
                        </button>
                    </div>
                </form>
            </div>
            <style>{`@keyframes slide-up { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }`}</style>
        </div>
    );
}

export default function Reviews({ auth, reviews, reviewableBookings = [] }) {
    const [showModal, setShowModal] = useState(false);
    const { flash } = usePage().props;

    return (
        <EmployerLayout user={auth?.user}>
            <Head title="Service Reviews | Employer" />

            {/* Header */}
            <div className="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-1">Feedback</p>
                    <h1 className="font-display text-3xl font-light text-espresso dark:text-[#f0ede8]">Service Reviews</h1>
                    <p className="text-muted dark:text-gray-400 mt-2">Your honest feedback helps maintain platform quality.</p>
                </div>
                {reviewableBookings.length > 0 && (
                    <button
                        onClick={() => setShowModal(true)}
                        className="flex items-center gap-2 bg-teal text-white px-5 py-2.5 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all shadow-brand-1 flex-shrink-0"
                    >
                        ✍️ Leave a Review
                    </button>
                )}
            </div>

            {/* Flash success */}
            {flash?.success && (
                <div className="mb-6 bg-success/10 border border-success/20 text-success rounded-brand-md px-4 py-3 text-sm flex items-start gap-2">
                    <span className="flex-shrink-0 font-bold">✓</span>
                    <span>{flash.success}</span>
                </div>
            )}

            {/* Sentinel Agent banner */}
            <div className="bg-ivory dark:bg-[#1c1c1e] rounded-brand-lg border border-teal/10 p-5 mb-8 flex items-start gap-4 shadow-sm">
                <div className="w-11 h-11 bg-teal text-white rounded-full flex items-center justify-center text-xl shadow-brand-1 flex-shrink-0">🛡️</div>
                <div>
                    <h3 className="font-semibold text-espresso dark:text-[#f0ede8] text-sm mb-1">Sentinel Agent Quality Process</h3>
                    <p className="text-xs text-muted dark:text-gray-400 leading-relaxed">
                        Every submitted review is assessed by our Sentinel AI before appearing on a helper's public profile.
                        Reviews that violate guidelines are held for manual moderation. Only authentic, approved reviews affect a helper's public rating.
                    </p>
                </div>
            </div>

            {/* Pending reviews CTA */}
            {reviewableBookings.length > 0 && (
                <div className="mb-8">
                    <div className="flex items-center gap-2 mb-3">
                        <h2 className="font-display text-lg text-espresso dark:text-[#f0ede8]">Awaiting Your Feedback</h2>
                        <span className="bg-copper/10 text-copper text-xs font-mono px-2 py-0.5 rounded-full font-bold">
                            {reviewableBookings.length}
                        </span>
                    </div>
                    <div className="grid gap-3">
                        {reviewableBookings.map(booking => (
                            <div key={booking.id}
                                className="bg-white dark:bg-[#1c1c1e] rounded-brand-lg border border-gray-200 dark:border-white/10 p-4 flex items-center justify-between gap-4 shadow-brand-1">
                                <div className="flex items-center gap-3 min-w-0">
                                    <div className="w-10 h-10 bg-teal/10 rounded-full flex items-center justify-center text-teal font-bold text-sm flex-shrink-0">
                                        {booking.maid_name?.charAt(0) || '?'}
                                    </div>
                                    <div className="min-w-0">
                                        <p className="font-semibold text-espresso dark:text-[#f0ede8] text-sm truncate">{booking.maid_name}</p>
                                        <p className="text-xs text-muted dark:text-gray-400">
                                            {booking.status === 'completed' ? '✓ Completed' : '● Active'}
                                            {booking.start_date ? ` · ${booking.start_date}` : ''}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    onClick={() => setShowModal(true)}
                                    className="flex-shrink-0 bg-teal/10 text-teal text-xs font-bold px-4 py-2 rounded-brand-md hover:bg-teal hover:text-white transition-all"
                                >
                                    ✍️ Review
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Submitted reviews */}
            <div>
                {reviews.data.length > 0 && (
                    <h2 className="font-display text-lg text-espresso dark:text-[#f0ede8] mb-4">Your Submitted Reviews</h2>
                )}

                {reviews.data.length > 0 ? (
                    <div className="grid gap-5">
                        {reviews.data.map(review => (
                            <div key={review.id}
                                className="bg-white dark:bg-[#1c1c1e] rounded-brand-xl border border-gray-200 dark:border-white/10 shadow-brand-1 p-6 relative overflow-hidden">

                                {review.is_flagged && (
                                    <div className="absolute top-0 right-0 bg-amber-400 text-white text-[9px] font-mono font-bold uppercase tracking-widest px-3 py-1 rounded-bl-brand-md">
                                        Under Review
                                    </div>
                                )}

                                <div className="flex flex-col md:flex-row gap-5">
                                    <div className="flex-shrink-0">
                                        {review.maid?.avatar ? (
                                            <img src={`/storage/${review.maid.avatar}`} alt=""
                                                className="w-14 h-14 rounded-brand-md object-cover" />
                                        ) : (
                                            <div className="w-14 h-14 bg-teal/10 text-teal rounded-brand-md flex items-center justify-center text-2xl font-bold">
                                                {review.maid?.name?.charAt(0)}
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex-1 min-w-0">
                                        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-2 mb-3">
                                            <div>
                                                <h3 className="font-semibold text-espresso dark:text-[#f0ede8]">{review.maid?.name}</h3>
                                                <div className="flex items-center gap-1 mt-1">
                                                    {[...Array(5)].map((_, i) => (
                                                        <span key={i} className={`text-base leading-none ${i < review.rating ? 'text-copper' : 'text-gray-200 dark:text-white/10'}`}>★</span>
                                                    ))}
                                                    <span className="text-xs text-muted dark:text-gray-400 ml-1 font-semibold">{RATING_LABELS[review.rating]}</span>
                                                </div>
                                            </div>
                                            <span className="text-[10px] font-mono text-muted dark:text-gray-400 uppercase tracking-widest bg-gray-50 dark:bg-white/5 px-2 py-1 rounded self-start flex-shrink-0">
                                                {new Date(review.created_at).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' })}
                                            </span>
                                        </div>

                                        <p className="text-espresso dark:text-[#f0ede8] text-sm leading-relaxed italic mb-4">
                                            "{review.comment}"
                                        </p>

                                        <div className="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-gray-50 dark:border-white/5">
                                            <Link href={`/employer/bookings/${review.booking_id}`}
                                                className="text-xs text-teal font-medium hover:underline">
                                                View Engagement Details →
                                            </Link>
                                            <div className="flex items-center gap-1.5">
                                                {review.is_flagged ? (
                                                    <>
                                                        <span className="w-2 h-2 bg-amber-400 rounded-full"></span>
                                                        <span className="text-[10px] font-mono text-amber-600 uppercase tracking-tighter">Flagged by Sentinel</span>
                                                    </>
                                                ) : (
                                                    <>
                                                        <span className="w-2 h-2 bg-success rounded-full animate-pulse"></span>
                                                        <span className="text-[10px] font-mono text-muted dark:text-gray-400 uppercase tracking-tighter">Approved by Sentinel</span>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="bg-white dark:bg-[#1c1c1e] rounded-brand-lg border border-gray-200 dark:border-white/10 p-12 text-center">
                        <div className="w-16 h-16 bg-gray-50 dark:bg-white/5 text-gray-300 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">⭐</div>
                        <h3 className="font-display text-xl text-espresso dark:text-[#f0ede8] mb-2">No reviews yet</h3>
                        <p className="text-muted dark:text-gray-400 text-sm">
                            {reviewableBookings.length > 0
                                ? 'You have completed engagements waiting for your feedback.'
                                : 'Reviews become available once your engagements are completed.'}
                        </p>
                        {reviewableBookings.length > 0 && (
                            <button onClick={() => setShowModal(true)}
                                className="mt-5 bg-teal text-white px-6 py-2.5 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all">
                                Leave Your First Review →
                            </button>
                        )}
                    </div>
                )}
            </div>

            {/* Pagination */}
            {reviews.links && reviews.links.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {reviews.links.map((link, k) => (
                        <Link key={k} href={link.url || '#'}
                            className={`px-4 py-2 text-sm rounded-brand-md border
                                ${link.active ? 'bg-teal text-white border-teal' : 'bg-white dark:bg-[#1c1c1e] text-muted border-gray-200 dark:border-white/10 hover:bg-gray-50'}
                                ${!link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}

            {showModal && (
                <ReviewModal
                    reviewableBookings={reviewableBookings}
                    onClose={() => setShowModal(false)}
                />
            )}
        </EmployerLayout>
    );
}
