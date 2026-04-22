import { Head, Link } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function MaidDashboard({ auth, profile, profileInsights = { score: 0, tips: [] }, bookings = [], stats = {} }) {
    return (
        <MaidLayout user={auth?.user}>
            <Head title="Helper Dashboard" />
            
            <div className="mb-8">
                <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-1">Overview</p>
                <h1 className="font-display text-3xl font-light text-espresso">Mission Briefing</h1>
                <p className="text-muted mt-2">Welcome back. Here's your current performance and activity.</p>
            </div>

            {/* Performance Stats */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                {[
                    { label: 'Avg Rating', value: profile?.rating || '—', color: 'copper' },
                    { label: 'Active Jobs', value: stats.active_bookings || 0, color: 'teal' },
                    { label: 'Completed', value: stats.completed_bookings || 0, color: 'teal' },
                    { label: 'Total Jobs', value: stats.total_bookings || 0, color: 'teal' },
                ].map(s => (
                    <div key={s.label} className="bg-white rounded-brand-lg p-5 border border-gray-200 shadow-brand-1">
                        <p className="font-mono text-[10px] tracking-[0.1em] text-muted uppercase mb-1">{s.label}</p>
                        <p className="text-2xl font-bold text-espresso">{s.value}</p>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {/* Profile Strength & AI Tips */}
                <div className="space-y-6">
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6">
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="font-display text-xl text-espresso">Profile Strength</h2>
                            <span className="bg-teal text-white text-[10px] font-mono px-2 py-1 rounded shadow-sm">{profileInsights.score}%</span>
                        </div>
                        
                        <div className="mb-6 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div 
                                className="h-full bg-teal transition-all duration-1000" 
                                style={{ width: `${profileInsights.score}%` }}
                            ></div>
                        </div>

                        <div className="space-y-4">
                            <h3 className="font-mono text-[10px] uppercase tracking-widest text-muted">Sentinel Agent Recommendations:</h3>
                            {profileInsights.tips.length > 0 ? (
                                <ul className="space-y-3">
                                    {profileInsights.tips.map((tip, i) => (
                                        <li key={i} className="flex items-start gap-3 text-sm text-espresso bg-ivory/50 p-3 rounded-brand-md border border-gray-100">
                                            <span className="text-teal mt-0.5">→</span>
                                            {tip}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="bg-success/5 p-4 rounded-brand-md border border-success/10 flex items-center gap-3">
                                    <span className="text-xl">✨</span>
                                    <p className="text-sm text-success">Your profile is optimized! The Sentinel Agent has no further recommendations.</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Quick Profile View */}
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="font-display text-xl text-espresso">Public Identity</h2>
                            <Link href="/maid/profile" className="text-teal text-xs font-semibold hover:underline">Edit Info</Link>
                        </div>
                        <div className="flex items-center gap-2 mb-4">
                            <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-tighter ${profile?.availability_status === 'available' ? 'bg-success text-white' : 'bg-gray-100 text-muted'}`}>
                                {profile?.availability_status}
                            </span>
                            {profile?.nin_verified && <span className="bg-teal text-white px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-tighter">NIN Verified</span>}
                        </div>
                        <p className="text-muted text-sm leading-relaxed italic border-l-2 border-gray-100 pl-4 py-1 mb-6">
                            "{profile?.bio || 'Add a bio to attract more employers.'}"
                        </p>
                        <div className="flex flex-wrap gap-2">
                             {(profile?.skills || []).map(s => <span key={s} className="bg-gray-100 text-espresso text-[11px] px-2.5 py-1 rounded font-medium border border-gray-200">{s}</span>)}
                        </div>
                    </div>
                </div>

                {/* Recent Jobs */}
                <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 className="font-display text-xl text-espresso">Current Assignments</h2>
                        <Link href="/maid/bookings" className="text-teal text-xs font-semibold hover:underline">See All</Link>
                    </div>
                    {bookings.length > 0 ? (
                        <div className="divide-y divide-gray-100">
                            {bookings.map(b => (
                                <div key={b.id} className="p-6 hover:bg-gray-50 transition-all flex items-center justify-between group">
                                    <div>
                                        <p className="font-semibold text-espresso mb-1 group-hover:text-teal transition-colors">Employer: {b.employer_name}</p>
                                        <p className="text-xs text-muted">Starts: {b.start_date} · Salary: ₦{b.agreed_salary?.toLocaleString()}</p>
                                    </div>
                                    <div className="flex flex-col items-end gap-2">
                                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-[0.05em] ${b.status === 'active' ? 'bg-success text-white' : 'bg-gray-100 text-muted'}`}>
                                            {b.status}
                                        </span>
                                        <Link href={`/maid/bookings/${b.id}`} className="text-[10px] font-mono text-muted group-hover:text-teal uppercase tracking-widest">Details →</Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="p-12 text-center">
                            <div className="w-16 h-16 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">📋</div>
                            <h3 className="font-display text-lg text-espresso mb-1">No active jobs</h3>
                            <p className="text-muted text-sm">When employers select you, they'll appear here.</p>
                        </div>
                    )}
                </div>
            </div>
        </MaidLayout>
    );
}
