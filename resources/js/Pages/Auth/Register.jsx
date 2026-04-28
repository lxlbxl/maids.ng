import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '', role: 'employer', location: '' });

    const submit = (e) => { e.preventDefault(); post('/register'); };

    return (
        <>
            <Head title="Create Account" />
            <div className="min-h-screen bg-ivory flex items-center justify-center px-6 py-12">
                <div className="w-full max-w-md">
                    <div className="text-center mb-8">
                        <Link href="/"><img src="/maids-logo.png" alt="Maids.ng" className="h-10 mx-auto mb-6" /></Link>
                        <h1 className="font-display text-3xl font-light text-espresso">Create Your <em className="italic text-copper">Account</em></h1>
                    </div>

                    <form onSubmit={submit} className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1 space-y-4">
                        {/* Role Selector */}
                        <div className="grid grid-cols-2 gap-3 mb-2">
                            {[{ value: 'employer', label: 'I need a helper', icon: '🏠' }, { value: 'maid', label: 'I am a helper', icon: '✨' }].map(r => (
                                <button key={r.value} type="button" onClick={() => setData('role', r.value)}
                                    className={`p-3 rounded-brand-md border-2 text-center text-sm transition-all ${data.role === r.value ? 'border-teal bg-teal-ghost' : 'border-gray-200 hover:border-teal/30'}`}>
                                    <span className="text-lg block">{r.icon}</span>
                                    <span className={data.role === r.value ? 'text-teal font-medium' : 'text-muted'}>{r.label}</span>
                                </button>
                            ))}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-espresso mb-1">Full Name</label>
                            <input type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                className="w-full h-11 border-2 border-gray-200 rounded-brand-md px-4 text-sm focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none" placeholder="Your full name" />
                            {errors.name && <p className="text-danger text-xs mt-0.5">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-1">Email</label>
                            <input type="email" value={data.email} onChange={e => setData('email', e.target.value)}
                                className="w-full h-11 border-2 border-gray-200 rounded-brand-md px-4 text-sm focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none" placeholder="you@email.com" />
                            {errors.email && <p className="text-danger text-xs mt-0.5">{errors.email}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-1">Phone Number</label>
                            <input type="text" value={data.phone} onChange={e => setData('phone', e.target.value)}
                                className="w-full h-11 border-2 border-gray-200 rounded-brand-md px-4 text-sm focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none" placeholder="08012345678" />
                            {errors.phone && <p className="text-danger text-xs mt-0.5">{errors.phone}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-1">Location</label>
                            <input type="text" value={data.location} onChange={e => setData('location', e.target.value)}
                                className="w-full h-11 border-2 border-gray-200 rounded-brand-md px-4 text-sm focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none" placeholder="e.g. Lekki, Lagos" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-1">Password</label>
                            <input type="password" value={data.password} onChange={e => setData('password', e.target.value)}
                                className="w-full h-11 border-2 border-gray-200 rounded-brand-md px-4 text-sm focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none" placeholder="Min 8 characters" />
                            {errors.password && <p className="text-danger text-xs mt-0.5">{errors.password}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-1">Confirm Password</label>
                            <input type="password" value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)}
                                className="w-full h-11 border-2 border-gray-200 rounded-brand-md px-4 text-sm focus:border-teal focus:ring-2 focus:ring-teal/20 outline-none" placeholder="Repeat password" />
                        </div>

                        <button type="submit" disabled={processing}
                            className="w-full bg-teal text-white py-3 rounded-brand-md font-medium hover:bg-teal-dark transition-all hover:scale-[1.01] shadow-brand-1 disabled:opacity-50 mt-2">
                            {processing ? 'Creating...' : 'Create Account'}
                        </button>
                    </form>

                    <p className="text-center text-sm text-muted mt-6">
                        Already have an account? <Link href="/login" className="text-teal font-medium hover:text-teal-dark">Sign In</Link>
                    </p>
                </div>
            </div>
        </>
    );
}
