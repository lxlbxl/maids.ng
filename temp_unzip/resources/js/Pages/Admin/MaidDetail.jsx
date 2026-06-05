import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function MaidDetail({ auth, id, user, profile, reviews, bookings }) {
    const { post, processing } = useForm();

    const handleStatusUpdate = (status) => {
        post(`/admin/maids/${id}/status?status=${status}`);
    };

    return (
        <AdminLayout>
            <Head title={`${user?.name || 'Helper'} — Detail | Mission Control`} />
            
            <div className="mb-8">
                <Link href="/admin/maids" className="text-white/40 hover:text-white text-sm transition-colors mb-4 inline-block">← Back to Helpers</Link>
                <h1 className="font-display text-4xl font-light tracking-tight text-white">{user?.name}</h1>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main Content */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Profile Card */}
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Profile Information</h3>
                        <div className="flex items-start gap-6">
                            <div className="w-20 h-20 bg-teal/10 rounded-full flex items-center justify-center text-3xl text-teal font-bold border-2 border-teal/20 overflow-hidden">
                                {user?.avatar ? (
                                    <img src={user.avatar} alt={user.name} className="w-full h-full object-cover" />
                                ) : (
                                    user?.name?.charAt(0)
                                )}
                            </div>
                            <div className="flex-1 grid grid-cols-2 gap-4">
                                {[
                                    { label: 'Email', value: user?.email },
                                    { label: 'Phone', value: user?.phone || '—' },
                                    { label: 'Location', value: profile?.location || user?.location || '—' },
                                    { label: 'Type', value: profile?.maid_type || 'Helper' },
                                    { label: 'Experience', value: `${profile?.experience_years || 0} years` },
                                    { label: 'Expected Salary', value: `₦${Number(profile?.expected_salary || 0).toLocaleString()}` },
                                    { label: 'Rating', value: `⭐ ${Number(profile?.rating || 0).toFixed(1)} (${profile?.total_reviews || 0} reviews)` },
                                    { label: 'Schedule', value: profile?.schedule_preference || '—' },
                                ].map(item => (
                                    <div key={item.label}>
                                        <p className="font-mono text-[9px] uppercase tracking-widest text-white/30 mb-1">{item.label}</p>
                                        <p className="text-white text-sm">{item.value}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                        {profile?.bio && (
                            <div className="mt-6 pt-4 border-t border-white/5">
                                <p className="font-mono text-[9px] uppercase tracking-widest text-white/30 mb-2">Bio</p>
                                <p className="text-white/60 text-sm leading-relaxed">{profile.bio}</p>
                            </div>
                        )}
                    </div>

                    {/* Skills & Languages */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {profile?.skills?.length > 0 && (
                            <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                                <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Skills</h3>
                                <div className="flex flex-wrap gap-2">
                                    {profile.skills.map(skill => (
                                        <span key={skill} className="bg-teal/10 text-teal px-3 py-1.5 rounded-full text-xs font-medium capitalize">{skill}</span>
                                    ))}
                                </div>
                            </div>
                        )}

                        {profile?.languages?.length > 0 && (
                            <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                                <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Languages</h3>
                                <div className="flex flex-wrap gap-2">
                                    {profile.languages.map(lang => (
                                        <span key={lang} className="bg-white/10 text-white px-3 py-1.5 rounded-full text-xs font-medium capitalize">{lang}</span>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Recent Bookings */}
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Booking History ({bookings?.length || 0})</h3>
                        {bookings?.length > 0 ? (
                            <div className="space-y-3">
                                {bookings.slice(0, 10).map(booking => (
                                    <div key={booking.id} className="flex items-center justify-between bg-white/[0.03] rounded-brand-md p-4 border border-white/5">
                                        <div>
                                            <p className="text-white text-sm font-medium">with {booking.employer?.name || '—'}</p>
                                            <p className="text-white/30 text-xs font-mono">BK-{String(booking.id).padStart(4, '0')}</p>
                                        </div>
                                        <span className={`px-2 py-0.5 rounded-full text-[9px] font-mono uppercase ${booking.status === 'completed' ? 'bg-success/10 text-success' : booking.status === 'active' ? 'bg-teal/10 text-teal' : 'bg-white/10 text-white/40'}`}>
                                            {booking.status}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-white/30 text-sm text-center py-6">No bookings yet.</p>
                        )}
                    </div>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Verification Status</h3>
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-white/60 text-sm">NIN Verification</span>
                                {profile?.is_foreigner ? (
                                    <span className="bg-white/10 text-white/60 px-2 py-0.5 rounded-full text-[9px] font-mono font-bold">🌍 Foreigner</span>
                                ) : profile?.nin_verified ? (
                                    <span className="bg-success/10 text-success px-2 py-0.5 rounded-full text-[9px] font-mono font-bold">✓ Verified</span>
                                ) : (
                                    <span className="bg-danger/10 text-danger px-2 py-0.5 rounded-full text-[9px] font-mono font-bold">✗ Pending</span>
                                )}
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-white/60 text-sm">Background Check</span>
                                {profile?.background_verified ? (
                                    <span className="bg-success/10 text-success px-2 py-0.5 rounded-full text-[9px] font-mono font-bold">✓ Clear</span>
                                ) : (
                                    <span className="bg-danger/10 text-danger px-2 py-0.5 rounded-full text-[9px] font-mono font-bold">✗ Pending</span>
                                )}
                            </div>
                        </div>
                    </div>

                    {profile?.nin_report && (
                        <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                            <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">NIN Verification Report</h3>
                            <div className="bg-white/[0.03] border border-white/5 rounded-brand-md p-4 overflow-auto">
                                <pre className="text-[10px] font-mono text-teal/80 whitespace-pre-wrap">
                                    {JSON.stringify(JSON.parse(profile.nin_report), null, 2)}
                                </pre>
                            </div>
                        </div>
                    )}

                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Admin Actions</h3>
                        <div className="space-y-2">
                            {user?.status === 'active' ? (
                                <button onClick={() => handleStatusUpdate('suspended')} disabled={processing} className="w-full bg-danger/10 text-danger px-4 py-3 rounded-brand-md text-sm font-bold hover:bg-danger/20 transition-all border border-danger/20">
                                    Suspend Helper
                                </button>
                            ) : (
                                <button onClick={() => handleStatusUpdate('active')} disabled={processing} className="w-full bg-teal/10 text-teal px-4 py-3 rounded-brand-md text-sm font-bold hover:bg-teal/20 transition-all border border-teal/20">
                                    Activate Helper
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Bank Details */}
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Bank Details</h3>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between"><span className="text-white/30">Bank</span><span className="text-white">{profile?.bank_name || '—'}</span></div>
                            <div className="flex justify-between"><span className="text-white/30">Account</span><span className="text-white font-mono">{profile?.account_number || '—'}</span></div>
                            <div className="flex justify-between"><span className="text-white/30">Name</span><span className="text-white">{profile?.account_name || '—'}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
