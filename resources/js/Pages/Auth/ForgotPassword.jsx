import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword() {
    const { data, setData, post, processing } = useForm({ email: '' });
    const submit = (e) => { e.preventDefault(); post('/forgot-password'); };

    return (
        <>
            <Head title="Forgot Password" />
            <div className="min-h-screen bg-ivory flex items-center justify-center px-6">
                <div className="w-full max-w-md text-center">
                    <Link href="/"><img src="/maids-logo.png" alt="Maids.ng" className="h-10 mx-auto mb-6" /></Link>
                    <h1 className="font-display text-3xl font-light text-espresso mb-2">Reset <em className="italic text-copper">Password</em></h1>
                    <p className="text-muted text-sm mb-8">Enter your email to receive a reset link</p>
                    <form onSubmit={submit} className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1 space-y-5 text-left">
                        <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} placeholder="you@email.com"
                            className="w-full h-12 border-2 border-gray-200 rounded-brand-md px-4 text-sm focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none" />
                        <button type="submit" disabled={processing} className="w-full bg-teal text-white py-3 rounded-brand-md font-medium hover:bg-teal-dark transition-all shadow-brand-1 disabled:opacity-50">
                            {processing ? 'Sending...' : 'Send Reset Link'}
                        </button>
                    </form>
                    <p className="text-sm text-muted mt-6"><Link href="/login" className="text-teal font-medium">← Back to Login</Link></p>
                </div>
            </div>
        </>
    );
}
