import { Head, Link } from '@inertiajs/react';

export default function Show({ maid }) {
    return (
        <>
            <Head title={`${maid.name} — Helper Profile | Maids.ng`} />
            
            <div className="min-h-screen bg-ivory font-body">
                {/* Header */}
                <nav className="bg-white border-b border-gray-100 px-6 py-4 shadow-sm sticky top-0 z-30">
                    <div className="max-w-7xl mx-auto flex items-center justify-between">
                        <Link href="/">
                            <img src="/maids-logo.png" alt="Maids.ng" className="h-8" />
                        </Link>
                        <div className="flex items-center gap-4">
                            <Link href="/maids" className="text-sm text-muted hover:text-espresso transition-colors">← Back to Search</Link>
                            <Link href="/register" className="bg-teal text-white px-5 py-2 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all">Book Now</Link>
                        </div>
                    </div>
                </nav>

                <div className="max-w-4xl mx-auto px-6 py-12">
                    {/* Profile Hero */}
                    <div className="bg-white rounded-brand-xl border border-gray-100 shadow-brand-2 overflow-hidden mb-8">
                        <div className="h-32 bg-gradient-to-r from-espresso via-espresso/90 to-teal relative">
                            <div className="absolute inset-0 opacity-10" style={{ backgroundImage: 'radial-gradient(circle at 1px 1px, white 1px, transparent 0)', backgroundSize: '20px 20px' }}></div>
                        </div>
                        <div className="px-8 pb-8 -mt-12 relative z-10">
                            <div className="flex flex-col md:flex-row items-start gap-6">
                                <div className="w-24 h-24 bg-teal rounded-full flex items-center justify-center text-4xl text-white font-bold border-4 border-white shadow-brand-2">
                                    {maid.name?.charAt(0)}
                                </div>
                                <div className="flex-1">
                                    <div className="flex flex-wrap items-center gap-3 mt-2">
                                        <h1 className="font-display text-3xl text-espresso font-light">{maid.name}</h1>
                                        {maid.verified && (
                                            <span className="bg-success/10 text-success px-3 py-1 rounded-full text-[10px] font-mono uppercase tracking-widest font-bold">✓ Verified</span>
                                        )}
                                    </div>
                                    <p className="text-teal text-xs font-mono uppercase tracking-widest mt-1">{maid.role || 'Domestic Helper'} • 📍 {maid.location}</p>
                                    <div className="flex items-center gap-6 mt-4">
                                        <div>
                                            <span className="text-amber-400 mr-1">⭐</span>
                                            <span className="text-lg font-bold text-espresso">{maid.rating}</span>
                                            <span className="text-sm text-muted ml-1">({maid.total_reviews} reviews)</span>
                                        </div>
                                        <div className="text-sm text-muted">{maid.experience_years} yrs experience</div>
                                        {maid.rate > 0 && (
                                            <div className="text-lg font-bold text-espresso">₦{Number(maid.rate).toLocaleString()}<span className="text-sm text-muted font-normal">/mo</span></div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {/* Left Column */}
                        <div className="md:col-span-2 space-y-6">
                            {/* Bio */}
                            {maid.bio && (
                                <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-1 p-6">
                                    <h3 className="font-display text-lg text-espresso mb-4 border-b border-gray-50 pb-3">About</h3>
                                    <p className="text-muted text-sm leading-relaxed">{maid.bio}</p>
                                </div>
                            )}

                            {/* Skills */}
                            {maid.skills?.length > 0 && (
                                <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-1 p-6">
                                    <h3 className="font-display text-lg text-espresso mb-4 border-b border-gray-50 pb-3">Skills & Specialties</h3>
                                    <div className="flex flex-wrap gap-2">
                                        {maid.skills.map(skill => (
                                            <span key={skill} className="bg-teal/5 text-teal px-4 py-2 rounded-full text-sm capitalize font-medium">{skill}</span>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Reviews */}
                            <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-1 p-6">
                                <h3 className="font-display text-lg text-espresso mb-4 border-b border-gray-50 pb-3">Reviews ({maid.reviews?.length || 0})</h3>
                                {maid.reviews?.length > 0 ? (
                                    <div className="space-y-4">
                                        {maid.reviews.map((review, i) => (
                                            <div key={i} className="border-b border-gray-50 last:border-0 pb-4 last:pb-0">
                                                <div className="flex items-center gap-3 mb-2">
                                                    <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-xs font-bold text-muted">
                                                        {review.employer?.charAt(0) || 'A'}
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-espresso">{review.employer || 'Anonymous'}</p>
                                                        <p className="text-[10px] text-muted">{review.date}</p>
                                                    </div>
                                                    <div className="ml-auto text-amber-400">
                                                        {'⭐'.repeat(review.rating)}
                                                    </div>
                                                </div>
                                                <p className="text-sm text-muted ml-11">{review.comment}</p>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-muted text-sm text-center py-6">No reviews yet.</p>
                                )}
                            </div>
                        </div>

                        {/* Right Column — Booking CTA */}
                        <div className="space-y-6">
                            <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-2 p-6 sticky top-24">
                                <h3 className="font-display text-lg text-espresso mb-4">Ready to Book?</h3>
                                <p className="text-muted text-sm mb-6">Get started with our AI-powered matching to find out if {maid.name?.split(' ')[0]} is the right fit for your household.</p>
                                <Link href="/register" className="block w-full bg-teal text-white text-center py-3.5 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all shadow-lg shadow-teal/20">
                                    Start Matching →
                                </Link>
                                <p className="text-center text-[10px] text-muted mt-3 font-mono uppercase tracking-widest">Powered by Scout Agent</p>
                            </div>

                            <div className="bg-teal/5 rounded-brand-lg border border-teal/10 p-6">
                                <div className="flex items-center gap-2 mb-3">
                                    <span className="text-lg">🛡️</span>
                                    <span className="text-xs font-mono uppercase tracking-widest text-teal font-bold">Trust & Safety</span>
                                </div>
                                <ul className="space-y-2 text-sm text-muted">
                                    <li className="flex items-center gap-2">{maid.verified ? '✅' : '⬜'} Identity Verified</li>
                                    <li className="flex items-center gap-2">{maid.verified ? '✅' : '⬜'} Background Check</li>
                                    <li className="flex items-center gap-2">🔒 Secure Payments</li>
                                    <li className="flex items-center gap-2">⚖️ Dispute Resolution</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
