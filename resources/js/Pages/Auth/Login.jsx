import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: false });

    const submit = (e) => { e.preventDefault(); post('/login'); };

    return (
        <>
            <Head title="Log In" />
            <div className="min-h-screen bg-ivory flex items-center justify-center px-6">
                <div className="w-full max-w-md">
                    <div className="text-center mb-8">
                        <Link href="/"><img src="/maids-logo.png" alt="Maids.ng" className="h-10 mx-auto mb-6" /></Link>
                        <h1 className="font-display text-3xl font-light text-espresso">Welcome <em className="italic text-copper">Back</em></h1>
                        <p className="text-muted text-sm mt-1">Sign in to your account</p>
                    </div>

                    <form onSubmit={submit} className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1 space-y-5">
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-1.5">Email</label>
                            <input type="email" value={data.email} onChange={e => setData('email', e.target.value)}
                                className="w-full h-12 border-2 border-gray-200 rounded-brand-md px-4 text-sm focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none transition-all"
                                placeholder="you@email.com" autoFocus />
                            {errors.email && <p className="text-danger text-xs mt-1">{errors.email}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-1.5">Password</label>
                            <input type="password" value={data.password} onChange={e => setData('password', e.target.value)}
                                className="w-full h-12 border-2 border-gray-200 rounded-brand-md px-4 text-sm focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none transition-all"
                                placeholder="••••••••" />
                            {errors.password && <p className="text-danger text-xs mt-1">{errors.password}</p>}
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" checked={data.remember} onChange={e => setData('remember', e.target.checked)} className="rounded border-gray-300 text-teal focus:ring-teal" />
                                <span className="text-muted">Remember me</span>
                            </label>
                            <Link href="/forgot-password" className="text-teal hover:text-teal-dark font-medium">Forgot password?</Link>
                        </div>
                        <button type="submit" disabled={processing}
                            className="w-full bg-teal text-white py-3 rounded-brand-md font-medium hover:bg-teal-dark transition-all hover:scale-[1.01] shadow-brand-1 disabled:opacity-50">
                            {processing ? 'Signing in...' : 'Sign In'}
                        </button>
                    </form>

                    <p className="text-center text-sm text-muted mt-6">
                        Don't have an account? <Link href="/register" className="text-teal font-medium hover:text-teal-dark">Register</Link>
                    </p>
                </div>
            </div>
        </>
    );
}
