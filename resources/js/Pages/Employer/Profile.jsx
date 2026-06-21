import { Head, useForm, usePage } from '@inertiajs/react';
import EmployerLayout from '@/Layouts/EmployerLayout';
import { useState } from 'react';

export default function Profile({ auth, user }) {
    const [showPasswordForm, setShowPasswordForm] = useState(false);
    const { flash } = usePage().props;

    const profileForm = useForm({
        name:     user?.name     || '',
        phone:    user?.phone    || '',
        location: user?.location || '',
    });

    const passwordForm = useForm({
        current_password:      '',
        password:              '',
        password_confirmation: '',
    });

    const handleProfileSubmit = (e) => {
        e.preventDefault();
        profileForm.post(route('employer.profile.update'));
    };

    const handlePasswordSubmit = (e) => {
        e.preventDefault();
        passwordForm.post(route('employer.profile.change-password'), {
            onSuccess: () => {
                passwordForm.reset();
                setShowPasswordForm(false);
            },
        });
    };

    return (
        <EmployerLayout user={auth?.user}>
            <Head title="My Profile | Employer" />

            <div className="mb-8">
                <h1 className="font-display text-3xl font-light text-espresso dark:text-[#f0ede8]">My Profile</h1>
                <p className="text-muted dark:text-gray-400 mt-2">Manage your account information and preferences.</p>
            </div>

            {/* Flash message */}
            {flash?.success && (
                <div className="mb-6 bg-success/10 border border-success/20 text-success rounded-brand-md px-4 py-3 text-sm flex items-start gap-2">
                    <span className="flex-shrink-0 font-bold">✓</span>
                    <span>{flash.success}</span>
                </div>
            )}

            {/* Profile Header */}
            <div className="bg-white dark:bg-[#1c1c1e] rounded-brand-lg border border-gray-200 dark:border-white/10 shadow-brand-1 p-6 mb-6">
                <div className="flex flex-col md:flex-row items-start md:items-center gap-6">
                    <div className="w-20 h-20 bg-teal/10 rounded-full flex items-center justify-center text-3xl text-teal font-bold border-2 border-teal/20">
                        {user?.name?.charAt(0) || 'E'}
                    </div>
                    <div className="flex-1">
                        <h2 className="text-xl font-bold text-espresso dark:text-[#f0ede8]">{user?.name}</h2>
                        <p className="text-sm text-muted dark:text-gray-400 mt-1">{user?.email}</p>
                        <span className="inline-block mt-2 bg-teal/10 text-teal px-3 py-1 rounded-full text-[10px] font-mono uppercase tracking-widest font-bold">Employer</span>
                    </div>
                    <button className="bg-gray-100 dark:bg-white/10 text-muted dark:text-gray-400 px-4 py-2 rounded-brand-md text-sm hover:bg-gray-200 dark:hover:bg-white/20 transition-all">
                        📷 Change Photo
                    </button>
                </div>
            </div>

            {/* Personal Info Form */}
            <form onSubmit={handleProfileSubmit}>
                <div className="bg-white dark:bg-[#1c1c1e] rounded-brand-lg border border-gray-200 dark:border-white/10 shadow-brand-1 p-6 mb-6">
                    <h3 className="font-display text-lg text-espresso dark:text-[#f0ede8] mb-6 border-b border-gray-100 dark:border-white/5 pb-3">Personal Information</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-espresso dark:text-[#f0ede8] mb-2">Full Name</label>
                            <input type="text" value={profileForm.data.name}
                                onChange={e => profileForm.setData('name', e.target.value)}
                                className="w-full border border-gray-200 dark:border-white/10 rounded-brand-md px-4 py-3 text-sm dark:bg-[#2a2a2c] dark:text-[#f0ede8] focus:border-teal focus:ring-1 focus:ring-teal/20 outline-none" />
                            {profileForm.errors.name && <p className="text-rose-500 text-xs mt-1">{profileForm.errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso dark:text-[#f0ede8] mb-2">Phone Number</label>
                            <input type="tel" value={profileForm.data.phone}
                                onChange={e => profileForm.setData('phone', e.target.value)}
                                className="w-full border border-gray-200 dark:border-white/10 rounded-brand-md px-4 py-3 text-sm dark:bg-[#2a2a2c] dark:text-[#f0ede8] focus:border-teal focus:ring-1 focus:ring-teal/20 outline-none" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso dark:text-[#f0ede8] mb-2">Location</label>
                            <input type="text" value={profileForm.data.location}
                                onChange={e => profileForm.setData('location', e.target.value)}
                                placeholder="e.g. Lekki, Lagos"
                                className="w-full border border-gray-200 dark:border-white/10 rounded-brand-md px-4 py-3 text-sm dark:bg-[#2a2a2c] dark:text-[#f0ede8] focus:border-teal focus:ring-1 focus:ring-teal/20 outline-none" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso dark:text-[#f0ede8] mb-2">Email Address</label>
                            <input type="email" value={user?.email || ''} disabled
                                className="w-full border border-gray-200 dark:border-white/10 rounded-brand-md px-4 py-3 text-sm bg-gray-50 dark:bg-white/5 text-muted cursor-not-allowed" />
                            <p className="text-xs text-muted dark:text-gray-400 mt-1">Email cannot be changed</p>
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end">
                        <button type="submit" disabled={profileForm.processing}
                            className="bg-teal text-white px-8 py-3 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all disabled:opacity-50">
                            {profileForm.processing ? 'Saving…' : 'Save Changes'}
                        </button>
                    </div>
                </div>
            </form>

            {/* Account Security */}
            <div className="bg-white dark:bg-[#1c1c1e] rounded-brand-lg border border-gray-200 dark:border-white/10 shadow-brand-1 p-6">
                <h3 className="font-display text-lg text-espresso dark:text-[#f0ede8] mb-4 border-b border-gray-100 dark:border-white/5 pb-3">🔒 Account Security</h3>

                <div className="flex items-center justify-between py-3 border-b border-gray-50 dark:border-white/5">
                    <div>
                        <p className="text-sm font-medium text-espresso dark:text-[#f0ede8]">Password</p>
                        <p className="text-xs text-muted dark:text-gray-400">Use a strong password to keep your account secure</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowPasswordForm(v => !v)}
                        className="text-teal text-sm font-medium hover:underline transition-colors"
                    >
                        {showPasswordForm ? 'Cancel' : 'Change Password'}
                    </button>
                </div>

                {/* Inline change-password form */}
                {showPasswordForm && (
                    <form onSubmit={handlePasswordSubmit} className="mt-5 space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-xs font-mono uppercase tracking-widest text-muted dark:text-gray-400 mb-1.5">
                                    Current Password *
                                </label>
                                <input
                                    type="password"
                                    value={passwordForm.data.current_password}
                                    onChange={e => passwordForm.setData('current_password', e.target.value)}
                                    autoComplete="current-password"
                                    className="w-full border-2 border-gray-200 dark:border-white/10 rounded-brand-md px-4 py-2.5 text-sm dark:bg-[#2a2a2c] dark:text-[#f0ede8] focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none transition-all"
                                />
                                {passwordForm.errors.current_password && (
                                    <p className="text-rose-500 text-xs mt-1">{passwordForm.errors.current_password}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-xs font-mono uppercase tracking-widest text-muted dark:text-gray-400 mb-1.5">
                                    New Password *
                                </label>
                                <input
                                    type="password"
                                    value={passwordForm.data.password}
                                    onChange={e => passwordForm.setData('password', e.target.value)}
                                    autoComplete="new-password"
                                    placeholder="Min. 8 characters"
                                    className="w-full border-2 border-gray-200 dark:border-white/10 rounded-brand-md px-4 py-2.5 text-sm dark:bg-[#2a2a2c] dark:text-[#f0ede8] focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none transition-all"
                                />
                                {passwordForm.errors.password && (
                                    <p className="text-rose-500 text-xs mt-1">{passwordForm.errors.password}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-xs font-mono uppercase tracking-widest text-muted dark:text-gray-400 mb-1.5">
                                    Confirm New Password *
                                </label>
                                <input
                                    type="password"
                                    value={passwordForm.data.password_confirmation}
                                    onChange={e => passwordForm.setData('password_confirmation', e.target.value)}
                                    autoComplete="new-password"
                                    className="w-full border-2 border-gray-200 dark:border-white/10 rounded-brand-md px-4 py-2.5 text-sm dark:bg-[#2a2a2c] dark:text-[#f0ede8] focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none transition-all"
                                />
                                {passwordForm.errors.password_confirmation && (
                                    <p className="text-rose-500 text-xs mt-1">{passwordForm.errors.password_confirmation}</p>
                                )}
                            </div>
                        </div>

                        <div className="flex items-center gap-3 pt-1">
                            <button
                                type="submit"
                                disabled={passwordForm.processing}
                                className="bg-teal text-white px-6 py-2.5 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all disabled:opacity-50 flex items-center gap-2"
                            >
                                {passwordForm.processing ? (
                                    <>
                                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Updating…
                                    </>
                                ) : '🔒 Update Password'}
                            </button>
                            <button
                                type="button"
                                onClick={() => { setShowPasswordForm(false); passwordForm.reset(); }}
                                className="text-muted dark:text-gray-400 text-sm hover:text-espresso dark:hover:text-[#f0ede8] transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                )}

                <div className="flex items-center justify-between py-3 mt-1">
                    <div>
                        <p className="text-sm font-medium text-espresso dark:text-[#f0ede8]">Account Status</p>
                        <p className="text-xs text-muted dark:text-gray-400">Your account is active and in good standing</p>
                    </div>
                    <span className="bg-success/10 text-success px-3 py-1 rounded-full text-[10px] font-mono uppercase tracking-widest font-bold">Active</span>
                </div>
            </div>
        </EmployerLayout>
    );
}
