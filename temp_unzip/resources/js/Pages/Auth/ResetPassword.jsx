import { Head, useForm, Link } from '@inertiajs/react';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors } = useForm({
        token: token,
        email: email || '',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('password.store'));
    };

    return (
        <>
            <Head title="Reset Password | Maids.ng" />
            <div className="min-h-screen bg-ivory font-body flex items-center justify-center px-4">
                <div className="w-full max-w-md">
                    {/* Logo */}
                    <div className="text-center mb-10">
                        <Link href="/">
                            <img src="/maids-logo.png" alt="Maids.ng" className="h-10 mx-auto mb-6" />
                        </Link>
                        <h1 className="font-display text-3xl text-espresso font-light">Reset Your Password</h1>
                        <p className="text-muted text-sm mt-2">Enter your new password below to regain access.</p>
                    </div>

                    {/* Form */}
                    <div className="bg-white rounded-brand-xl border border-gray-100 shadow-brand-2 p-8">
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div>
                                <label className="block text-sm font-medium text-espresso mb-2">Email Address</label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={e => setData('email', e.target.value)}
                                    className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20"
                                    required
                                />
                                {errors.email && <p className="text-danger text-xs mt-1">{errors.email}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-espresso mb-2">New Password</label>
                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={e => setData('password', e.target.value)}
                                    className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20"
                                    required
                                    minLength={8}
                                />
                                {errors.password && <p className="text-danger text-xs mt-1">{errors.password}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-espresso mb-2">Confirm New Password</label>
                                <input
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={e => setData('password_confirmation', e.target.value)}
                                    className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20"
                                    required
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-teal text-white py-3.5 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all disabled:opacity-50 shadow-lg shadow-teal/20"
                            >
                                {processing ? 'Resetting...' : 'Reset Password'}
                            </button>
                        </form>
                    </div>

                    <p className="text-center text-muted text-sm mt-8">
                        Remembered your password? <Link href="/login" className="text-teal font-medium hover:underline">Sign In</Link>
                    </p>
                </div>
            </div>
        </>
    );
}
