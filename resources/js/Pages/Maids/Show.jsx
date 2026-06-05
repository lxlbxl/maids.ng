import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DirectHireModal from '@/Components/DirectHireModal';
import EmployerHireModal from '@/Components/EmployerHireModal';

export default function Show({ maid }) {
    const { auth } = usePage().props;
    const isEmployer = !!auth?.user && (auth.user.roles?.includes('employer') || auth.user.roles?.includes('admin'));
    const [hireModalOpen, setHireModalOpen] = useState(false);
    // Calculate rating breakdown from reviews
    const reviews = maid.reviews || [];
    const totalReviews = reviews.length;
    const ratingsBreakdown = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
    
    reviews.forEach(r => {
        const ratingVal = Math.round(r.rating);
        if (ratingsBreakdown[ratingVal] !== undefined) {
            ratingsBreakdown[ratingVal]++;
        }
    });

    const getPercentage = (count) => {
        return totalReviews > 0 ? (count / totalReviews) * 100 : 0;
    };

    return (
        <>
            <Head title={`${maid.name} — Helper Profile | Maids.ng`} />
            
            <div className="min-h-screen bg-ivory font-body pb-16">
                {/* Header */}
                <nav className="bg-white border-b border-gray-100 px-6 py-4 shadow-sm sticky top-0 z-30">
                    <div className="max-w-7xl mx-auto flex items-center justify-between">
                        <Link href="/">
                            <img src="/maids-logo.png" alt="Maids.ng" className="h-8" />
                        </Link>
                        <div className="flex items-center gap-4">
                            <Link href="/maids" className="text-sm text-muted hover:text-espresso transition-colors">← Back to Search</Link>
                            <button onClick={() => setHireModalOpen(true)} className="bg-teal text-white px-5 py-2 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all">Hire {maid.name?.split(' ')[0]}</button>
                        </div>
                    </div>
                </nav>

                <div className="max-w-7xl mx-auto px-6 py-10">
                    {/* Profile Hero Card */}
                    <div className="bg-white rounded-brand-xl border border-gray-100 shadow-brand-3 overflow-hidden mb-8">
                        <div className="flex flex-col md:flex-row">
                            {/* Large Portrait Photo */}
                            <div className="w-full md:w-[320px] md:h-[400px] h-[300px] flex-shrink-0 relative bg-gradient-to-br from-teal/10 to-teal/5">
                                {maid.avatar ? (
                                    <img 
                                        src={maid.avatar} 
                                        alt={maid.name} 
                                        className="w-full h-full object-cover" 
                                    />
                                ) : (
                                    <div className="w-full h-full flex items-center justify-center bg-teal text-white">
                                        <span className="text-8xl font-bold font-display">{maid.name?.charAt(0)}</span>
                                    </div>
                                )}
                                
                                {/* Status Badge on Image */}
                                {maid.availability_status === 'available' ? (
                                    <span className="absolute top-4 left-4 bg-success text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-md flex items-center gap-1.5">
                                        <span className="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                                        Available Now
                                    </span>
                                ) : (
                                    <span className="absolute top-4 left-4 bg-gray-500 text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-md flex items-center gap-1.5">
                                        <span className="w-2 h-2 bg-white/60 rounded-full"></span>
                                        {maid.availability_status || 'Unavailable'}
                                    </span>
                                )}
                            </div>

                            {/* Right Info Section */}
                            <div className="flex-1 p-8 flex flex-col justify-between">
                                <div>
                                    {/* Monospace Badge Row */}
                                    <div className="flex flex-wrap items-center gap-2 mb-3">
                                        <span className="text-teal text-xs font-mono uppercase tracking-widest font-bold">
                                            {maid.role || 'Domestic Helper'}
                                        </span>
                                        {maid.verified && (
                                            <span className="bg-success/10 text-success text-[10px] font-mono px-2.5 py-0.5 rounded-full uppercase tracking-wider font-bold">
                                                ✓ Verified Profile
                                            </span>
                                        )}
                                    </div>

                                    {/* Name */}
                                    <h1 className="font-display text-4xl text-espresso font-light mb-3 tracking-tight">
                                        {maid.name}
                                    </h1>

                                    {/* Quick Location */}
                                    <p className="text-muted text-sm flex items-center gap-1.5 mb-6">
                                        <svg className="w-4 h-4 text-teal/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Based in <strong>{maid.location}</strong></span>
                                    </p>

                                    {/* Bio Excerpt or Subtext */}
                                    {maid.bio ? (
                                        <p className="text-muted text-sm leading-relaxed mb-6 italic line-clamp-3">
                                            "{maid.bio}"
                                        </p>
                                    ) : (
                                        <p className="text-muted text-sm leading-relaxed mb-6 italic">
                                            "Dedicated, reliable, and verified professional helper ready to support your household needs."
                                        </p>
                                    )}
                                </div>

                                {/* Quick Stats Grid */}
                                <div className="grid grid-cols-3 gap-4 border-t border-gray-100 pt-6">
                                    <div>
                                        <span className="block text-[10px] text-muted font-mono uppercase tracking-wider mb-1">Rating</span>
                                        <div className="flex items-center gap-1">
                                            <span className="text-amber-400 text-lg">⭐</span>
                                            <span className="text-lg font-bold text-espresso">{maid.rating}</span>
                                            <span className="text-xs text-muted">({maid.total_reviews})</span>
                                        </div>
                                    </div>
                                    <div>
                                        <span className="block text-[10px] text-muted font-mono uppercase tracking-wider mb-1">Experience</span>
                                        <span className="text-lg font-bold text-espresso">{maid.experience_years} Years</span>
                                    </div>
                                    <div>
                                        <span className="block text-[10px] text-muted font-mono uppercase tracking-wider mb-1">Expected Rate</span>
                                        <span className="text-lg font-bold text-teal">
                                            {maid.expected_salary > 0 ? `₦${Number(maid.expected_salary).toLocaleString()}` : 'Negotiable'}
                                            {maid.expected_salary > 0 && <span className="text-xs text-muted font-normal">/mo</span>}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Quick Info Chips Row */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                        <div className="bg-white p-4 rounded-brand-lg border border-gray-100 shadow-brand-1 flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-teal/5 flex items-center justify-center text-teal text-lg">👤</div>
                            <div>
                                <p className="text-[10px] text-muted font-mono uppercase tracking-wide">Gender</p>
                                <p className="text-sm font-bold text-espresso capitalize">{maid.gender || 'Not Specified'}</p>
                            </div>
                        </div>
                        <div className="bg-white p-4 rounded-brand-lg border border-gray-100 shadow-brand-1 flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-teal/5 flex items-center justify-center text-teal text-lg">💼</div>
                            <div>
                                <p className="text-[10px] text-muted font-mono uppercase tracking-wide">Experience</p>
                                <p className="text-sm font-bold text-espresso">{maid.experience_years} Years</p>
                            </div>
                        </div>
                        <div className="bg-white p-4 rounded-brand-lg border border-gray-100 shadow-brand-1 flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-teal/5 flex items-center justify-center text-teal text-lg">⚡</div>
                            <div>
                                <p className="text-[10px] text-muted font-mono uppercase tracking-wide">Availability</p>
                                <p className="text-sm font-bold text-espresso capitalize">{maid.availability_status || 'available'}</p>
                            </div>
                        </div>
                        <div className="bg-white p-4 rounded-brand-lg border border-gray-100 shadow-brand-1 flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-teal/5 flex items-center justify-center text-teal text-lg">📅</div>
                            <div>
                                <p className="text-[10px] text-muted font-mono uppercase tracking-wide">Schedule</p>
                                <p className="text-sm font-bold text-espresso capitalize">{maid.schedule_preference || 'Full-Time'}</p>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Left/Middle Column: Maid Info Details */}
                        <div className="lg:col-span-2 space-y-8">
                            {/* Bio */}
                            {maid.bio && (
                                <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-1 p-6">
                                    <h3 className="font-display text-lg text-espresso mb-4 border-b border-gray-50 pb-3 flex items-center gap-2">
                                        <span>📝</span> About
                                    </h3>
                                    <p className="text-muted text-sm leading-relaxed whitespace-pre-line">{maid.bio}</p>
                                </div>
                            )}

                            {/* Personal Details & Job Preferences */}
                            <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-1 p-6">
                                <h3 className="font-display text-lg text-espresso mb-4 border-b border-gray-50 pb-3 flex items-center gap-2">
                                    <span>📋</span> Personal Details & Preferences
                                </h3>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                                    <div>
                                        <span className="text-muted block mb-1">Gender</span>
                                        <strong className="text-espresso capitalize">{maid.gender || 'Not Specified'}</strong>
                                    </div>
                                    <div>
                                        <span className="text-muted block mb-1">Current Location</span>
                                        <strong className="text-espresso">{maid.location}</strong>
                                    </div>
                                    <div>
                                        <span className="text-muted block mb-1">Schedule Preference</span>
                                        <strong className="text-espresso capitalize">{maid.schedule_preference || 'Flexible'}</strong>
                                    </div>
                                    <div>
                                        <span className="text-muted block mb-1">Areas Open to Work (Willing States)</span>
                                        <div className="flex flex-wrap gap-1 mt-1">
                                            {maid.willing_states && maid.willing_states.length > 0 ? (
                                                maid.willing_states.map(state => (
                                                    <span key={state} className="bg-espresso/5 text-espresso text-xs px-2.5 py-1 rounded-brand-md capitalize font-medium">
                                                        {state}
                                                    </span>
                                                ))
                                            ) : (
                                                <strong className="text-espresso">{maid.location} Only</strong>
                                            )}
                                        </div>
                                    </div>
                                    {maid.help_types && maid.help_types.length > 0 && (
                                        <div className="sm:col-span-2">
                                            <span className="text-muted block mb-1">Service Focus (Job Roles)</span>
                                            <div className="flex flex-wrap gap-1.5 mt-1">
                                                {maid.help_types.map(role => (
                                                    <span key={role} className="bg-teal/5 text-teal text-xs px-3 py-1 rounded-brand-md capitalize font-semibold border border-teal/10">
                                                        {role}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Skills */}
                            {maid.skills?.length > 0 && (
                                <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-1 p-6">
                                    <h3 className="font-display text-lg text-espresso mb-4 border-b border-gray-50 pb-3 flex items-center gap-2">
                                        <span>🛠️</span> Skills & Specialties
                                    </h3>
                                    <div className="flex flex-wrap gap-2">
                                        {maid.skills.map(skill => (
                                            <span key={skill} className="bg-teal/5 text-teal px-4 py-2 rounded-full text-sm capitalize font-medium border border-teal/10">
                                                {skill}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Languages */}
                            {maid.languages?.length > 0 && (
                                <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-1 p-6">
                                    <h3 className="font-display text-lg text-espresso mb-4 border-b border-gray-50 pb-3 flex items-center gap-2">
                                        <span>🗣️</span> Languages Spoken
                                    </h3>
                                    <div className="flex flex-wrap gap-2">
                                        {maid.languages.map(lang => (
                                            <span key={lang} className="bg-espresso/5 text-espresso px-4 py-2 rounded-full text-sm capitalize font-medium border border-espresso/10">
                                                {lang}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Reviews Section */}
                            <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-1 p-6">
                                <h3 className="font-display text-lg text-espresso mb-4 border-b border-gray-50 pb-3 flex items-center gap-2">
                                    <span>⭐</span> Reviews & Ratings ({totalReviews})
                                </h3>

                                {/* Ratings Distribution Summary */}
                                <div className="bg-teal/5 rounded-brand-lg p-6 mb-6 flex flex-col sm:flex-row gap-6 items-center border border-teal/10">
                                    <div className="text-center sm:text-left flex-shrink-0">
                                        <p className="text-5xl font-extrabold text-espresso font-display">{maid.rating}</p>
                                        <div className="flex items-center justify-center sm:justify-start gap-1 my-1 text-amber-400 text-lg">
                                            {'⭐'.repeat(Math.round(maid.rating))}
                                        </div>
                                        <p className="text-xs text-muted font-mono uppercase tracking-wider">{totalReviews} Reviews Total</p>
                                    </div>
                                    
                                    <div className="flex-1 w-full space-y-2">
                                        {[5, 4, 3, 2, 1].map(star => {
                                            const count = ratingsBreakdown[star];
                                            const pct = getPercentage(count);
                                            return (
                                                <div key={star} className="flex items-center gap-3 text-xs">
                                                    <span className="w-3 text-right text-espresso font-bold">{star}</span>
                                                    <span className="text-amber-400">⭐</span>
                                                    <div className="flex-1 bg-gray-200 h-2.5 rounded-full overflow-hidden">
                                                        <div 
                                                            className="bg-amber-400 h-full rounded-full transition-all duration-500" 
                                                            style={{ width: `${pct}%` }}
                                                        ></div>
                                                    </div>
                                                    <span className="w-8 text-right text-muted">{count}</span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {totalReviews > 0 ? (
                                    <div className="space-y-4">
                                        {reviews.map((review, i) => (
                                            <div key={i} className="border-b border-gray-50 last:border-0 pb-4 last:pb-0">
                                                <div className="flex items-center gap-3 mb-2">
                                                    <div className="w-8 h-8 bg-teal/10 rounded-full flex items-center justify-center text-xs font-bold text-teal">
                                                        {review.employer?.charAt(0) || 'A'}
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-semibold text-espresso">{review.employer || 'Anonymous'}</p>
                                                        <p className="text-[10px] text-muted">{review.date}</p>
                                                    </div>
                                                    <div className="ml-auto text-amber-400 text-xs">
                                                        {'⭐'.repeat(review.rating)}
                                                    </div>
                                                </div>
                                                <p className="text-sm text-muted ml-11 leading-relaxed">{review.comment}</p>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-muted text-sm text-center py-8">No reviews yet for this helper.</p>
                                )}
                            </div>
                        </div>

                        {/* Right Column: Sidebar Actions */}
                        <div className="space-y-6">
                            {/* Booking CTA Card */}
                            <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-2 p-6 sticky top-24">
                                <div className="mb-4">
                                    <span className="bg-teal/10 text-teal text-[10px] font-mono px-2 py-0.5 rounded-full uppercase tracking-wider font-bold">
                                        Perfect Match?
                                    </span>
                                </div>
                                <h3 className="font-display text-xl text-espresso mb-2">Ready to Book?</h3>
                                <p className="text-muted text-sm mb-6">
                                    Get started with our AI-powered matching to hire {maid.name?.split(' ')[0]} and secure your household helper.
                                </p>
                                
                                <div className="bg-gray-50 rounded-brand-md p-4 mb-6 border border-gray-100">
                                    <div className="flex justify-between items-center text-sm">
                                        <span className="text-muted">Expected Salary:</span>
                                        <strong className="text-espresso text-base">
                                            {maid.expected_salary > 0 ? `₦${Number(maid.expected_salary).toLocaleString()}/mo` : 'Negotiable'}
                                        </strong>
                                    </div>
                                </div>

                                <button
                                    onClick={() => setHireModalOpen(true)}
                                    className="block w-full bg-teal text-white text-center py-3.5 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all shadow-lg shadow-teal/20"
                                >
                                    Hire {maid.name?.split(' ')[0]} Now
                                </button>
                                
                                <div className="flex flex-col gap-2 mt-4 text-xs text-muted">
                                    <button className="flex items-center justify-center gap-1.5 w-full py-2 border border-gray-200 rounded-brand-md hover:bg-gray-50 transition-colors">
                                        <span>♡</span> Save Profile
                                    </button>
                                    <button className="flex items-center justify-center gap-1.5 w-full py-2 text-rose-500 border border-rose-100 rounded-brand-md hover:bg-rose-50/50 transition-colors">
                                        <span>⚠️</span> Report Profile
                                    </button>
                                </div>
                                
                                <p className="text-center text-[9px] text-muted/70 mt-4 font-mono uppercase tracking-widest">
                                    POWERED BY CONCIERGE & SCOUT AGENTS
                                </p>
                            </div>

                            {/* Trust & Safety Checklist Card */}
                            <div className="bg-white rounded-brand-lg border border-gray-100 shadow-brand-1 p-6">
                                <div className="flex items-center gap-2 mb-4 border-b border-gray-50 pb-3">
                                    <span className="text-lg">🛡️</span>
                                    <span className="text-xs font-mono uppercase tracking-widest text-teal font-bold">Trust & Safety Checklist</span>
                                </div>
                                <ul className="space-y-4 text-xs">
                                    <li className="flex items-start gap-3">
                                        <span className="text-base leading-none">{maid.nin_verified ? '✅' : '⏳'}</span>
                                        <div>
                                            <p className="font-bold text-espresso">Identity Verification (NIN)</p>
                                            <p className="text-muted text-[11px]">{maid.nin_verified ? 'National Identity Number verified successfully.' : 'Verification currently in progress.'}</p>
                                        </div>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <span className="text-base leading-none">{maid.background_verified ? '✅' : '⏳'}</span>
                                        <div>
                                            <p className="font-bold text-espresso">Criminal Background Check</p>
                                            <p className="text-muted text-[11px]">{maid.background_verified ? 'Clear criminal and background screening records.' : 'Background screening underway.'}</p>
                                        </div>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <span className="text-base leading-none">✅</span>
                                        <div>
                                            <p className="font-bold text-espresso">Guaranteed Secure Payments</p>
                                            <p className="text-muted text-[11px]">All hiring fees and salaries are processed through Paystack with escrows.</p>
                                        </div>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <span className="text-base leading-none">✅</span>
                                        <div>
                                            <p className="font-bold text-espresso">Dedicated Dispute Resolution</p>
                                            <p className="text-muted text-[11px]">Access to our Referee Agent to resolve any household disputes.</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {hireModalOpen && (
                isEmployer ? (
                    <EmployerHireModal
                        maid={{
                            id: maid.id,
                            name: maid.name,
                            avatar: maid.avatar,
                            role: maid.role,
                            location: maid.location,
                            availability_status: maid.availability_status,
                            verified: maid.verified,
                            rate: maid.expected_salary,
                        }}
                        onClose={() => setHireModalOpen(false)}
                    />
                ) : (
                    <DirectHireModal
                        maid={{
                            id: maid.id,
                            name: maid.name,
                            avatar: maid.avatar,
                            role: maid.role,
                            location: maid.location,
                            availability_status: maid.availability_status,
                            verified: maid.verified,
                            rate: maid.expected_salary,
                        }}
                        onClose={() => setHireModalOpen(false)}
                    />
                )
            )}
        </>
    );
}
