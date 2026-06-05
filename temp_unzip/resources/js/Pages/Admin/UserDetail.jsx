import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function UserDetail({ auth, userId, user }) {
    const role = user?.roles?.[0]?.name || 'user';
    const profile = user?.maid_profile || user?.employer_preferences;

    const roleColors = {
        admin: 'bg-teal/10 text-teal',
        maid: 'bg-copper/10 text-copper',
        employer: 'bg-success/10 text-success',
    };

    return (
        <AdminLayout>
            <Head title={`${user?.name || 'User'} — Detail | Mission Control`} />
            
            <div className="mb-8">
                <Link href="/admin/users" className="text-white/40 hover:text-white text-sm transition-colors mb-4 inline-block">← Back to People</Link>
                <div className="flex items-center gap-4">
                    <h1 className="font-display text-4xl font-light tracking-tight text-white">{user?.name}</h1>
                    <span className={`px-3 py-1 rounded-full text-[10px] font-mono font-bold uppercase tracking-widest ${roleColors[role] || 'bg-white/10 text-white/40'}`}>
                        {role}
                    </span>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    {/* User Info */}
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-6 font-bold">Account Information</h3>
                        <div className="flex items-start gap-6">
                            <div className="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center text-3xl font-bold text-white/60 border border-white/10">
                                {user?.name?.charAt(0)}
                            </div>
                            <div className="flex-1 grid grid-cols-2 gap-4">
                                {[
                                    { label: 'Full Name', value: user?.name },
                                    { label: 'Email', value: user?.email },
                                    { label: 'Phone', value: user?.phone || '—' },
                                    { label: 'Location', value: user?.location || '—' },
                                    { label: 'Status', value: user?.status || 'active' },
                                    { label: 'Joined', value: user?.created_at ? new Date(user.created_at).toLocaleDateString() : '—' },
                                ].map(item => (
                                    <div key={item.label}>
                                        <p className="font-mono text-[9px] uppercase tracking-widest text-white/30 mb-1">{item.label}</p>
                                        <p className="text-white text-sm">{item.value}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Role-specific Details */}
                    {role === 'maid' && user?.maid_profile && (
                        <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                            <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Helper Profile</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <div><p className="font-mono text-[9px] text-white/30 mb-1">TYPE</p><p className="text-white text-sm">{user.maid_profile.maid_type || '—'}</p></div>
                                <div><p className="font-mono text-[9px] text-white/30 mb-1">RATING</p><p className="text-white text-sm">⭐ {Number(user.maid_profile.rating || 0).toFixed(1)}</p></div>
                                <div><p className="font-mono text-[9px] text-white/30 mb-1">EXPERIENCE</p><p className="text-white text-sm">{user.maid_profile.experience_years || 0} years</p></div>
                                <div><p className="font-mono text-[9px] text-white/30 mb-1">EXPECTED SALARY</p><p className="text-white text-sm">₦{Number(user.maid_profile.expected_salary || 0).toLocaleString()}</p></div>
                            </div>
                        </div>
                    )}

                    {role === 'employer' && user?.employer_preferences && (
                        <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                            <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Employer Preferences</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <div><p className="font-mono text-[9px] text-white/30 mb-1">BUDGET</p><p className="text-white text-sm">₦{Number(user.employer_preferences.budget_range || 0).toLocaleString()}</p></div>
                                <div><p className="font-mono text-[9px] text-white/30 mb-1">SCHEDULE</p><p className="text-white text-sm">{user.employer_preferences.schedule_type || '—'}</p></div>
                                <div><p className="font-mono text-[9px] text-white/30 mb-1">LOCATION</p><p className="text-white text-sm">{user.employer_preferences.location || '—'}</p></div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                        <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Quick Info</h3>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-white/40 text-sm">ID</span>
                                <span className="font-mono text-[10px] text-white/60">ENT-{userId}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-white/40 text-sm">Role</span>
                                <span className={`px-2 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase ${roleColors[role]}`}>{role}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-white/40 text-sm">Account Status</span>
                                <span className={`px-2 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase ${user?.status === 'active' ? 'bg-teal/10 text-teal' : 'bg-danger/10 text-danger'}`}>
                                    {user?.status || 'active'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {role === 'maid' && (
                        <Link href={`/admin/maids/${userId}`} className="block bg-teal/10 border border-teal/20 rounded-brand-xl p-6 text-center hover:bg-teal/20 transition-all">
                            <p className="text-teal font-bold text-sm">View Full Helper Profile →</p>
                        </Link>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
