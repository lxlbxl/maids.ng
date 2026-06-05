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

                    {role === 'employer' && user?.employer_preferences && user.employer_preferences.length > 0 && (
                        (() => {
                            const pref = user.employer_preferences[0];
                            return (
                                <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-6">
                                    <h3 className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/30 mb-4 font-bold">Employer Preferences</h3>
                                    <div className="grid grid-cols-2 md:grid-cols-3 gap-6">
                                        <div><p className="font-mono text-[9px] text-white/30 mb-1">BUDGET</p><p className="text-white text-sm">₦{Number(pref.budget_min || 0).toLocaleString()} - ₦{Number(pref.budget_max || 0).toLocaleString()}</p></div>
                                        <div><p className="font-mono text-[9px] text-white/30 mb-1">SCHEDULE</p><p className="text-white text-sm">{pref.schedule || '—'}</p></div>
                                        <div><p className="font-mono text-[9px] text-white/30 mb-1">LOCATION</p><p className="text-white text-sm">{pref.city || '—'}, {pref.state || '—'}</p></div>
                                        <div><p className="font-mono text-[9px] text-white/30 mb-1">URGENCY</p><p className="text-white text-sm capitalize">{pref.urgency || '—'}</p></div>
                                        <div><p className="font-mono text-[9px] text-white/30 mb-1">MATCHING STATUS</p><p className="text-teal text-sm font-bold uppercase tracking-widest">{pref.matching_status || 'pending'}</p></div>
                                        <div><p className="font-mono text-[9px] text-white/30 mb-1">CONTACT</p><p className="text-white text-xs">{pref.contact_name || '—'}<br/>{pref.contact_phone || '—'}</p></div>
                                    </div>
                                    {pref.help_types && (
                                        <div className="mt-6 pt-4 border-t border-white/5">
                                            <p className="font-mono text-[9px] uppercase tracking-widest text-white/30 mb-2">Requested Help Types</p>
                                            <div className="flex flex-wrap gap-2">
                                                {(typeof pref.help_types === 'string' ? JSON.parse(pref.help_types) : pref.help_types).map(type => (
                                                    <span key={type} className="bg-white/5 text-white/70 px-2 py-1 rounded text-[10px] uppercase tracking-wider">{type}</span>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {pref.notes && (
                                        <div className="mt-4 pt-4 border-t border-white/5">
                                            <p className="font-mono text-[9px] uppercase tracking-widest text-white/30 mb-2">Additional Notes</p>
                                            <p className="text-white/60 text-sm leading-relaxed">{pref.notes}</p>
                                        </div>
                                    )}
                                </div>
                            );
                        })()
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
