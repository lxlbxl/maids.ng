import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="Where Trust Begins" />

            {/* ── Navigation ── */}
            <nav className="fixed top-0 w-full z-50 bg-white/90 backdrop-blur-md border-b border-gray-200/60">
                <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <img src="/maids-logo.png" alt="Maids.ng" className="h-8" />
                    </div>
                    <div className="hidden md:flex items-center gap-8">
                        <a href="#how" className="text-sm text-gray-500 hover:text-teal transition-colors">How It Works</a>
                        <a href="#services" className="text-sm text-gray-500 hover:text-teal transition-colors">Services</a>
                        <a href="#trust" className="text-sm text-gray-500 hover:text-teal transition-colors">Trust & Safety</a>
                    </div>
                    <div className="flex items-center gap-3">
                        {auth?.user ? (
                            <Link href={auth.user.roles?.includes('admin') ? '/admin/dashboard' : auth.user.roles?.includes('employer') ? '/employer/dashboard' : '/maid/dashboard'}
                                className="bg-teal text-white px-5 py-2.5 rounded-brand-md text-sm font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-1">
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link href="/login" className="text-sm text-teal font-medium hover:text-teal-dark transition-colors">Log In</Link>
                                <Link href="/onboarding" className="bg-teal text-white px-5 py-2.5 rounded-brand-md text-sm font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-1">
                                    Find a Helper
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </nav>

            {/* ── Hero Section ── */}
            <section className="min-h-screen flex items-center bg-espresso relative overflow-hidden pt-20">
                <div className="absolute inset-0 opacity-[0.04]" style={{backgroundImage: 'repeating-linear-gradient(45deg, #FAF7F2 0, #FAF7F2 1px, transparent 0, transparent 50%), repeating-linear-gradient(-45deg, #FAF7F2 0, #FAF7F2 1px, transparent 0, transparent 50%)', backgroundSize: '28px 28px'}} />
                <div className="absolute top-[-300px] right-[-200px] w-[800px] h-[800px] rounded-full" style={{background: 'radial-gradient(circle, rgba(15,85,86,0.35) 0%, transparent 65%)'}} />

                <div className="max-w-7xl mx-auto px-6 py-20 relative z-10">
                    <p className="font-mono text-xs tracking-[0.16em] uppercase text-teal-light mb-5 animate-fade-up">
                        Nigeria's Most Trusted Domestic Help Platform
                    </p>
                    <h1 className="font-display text-5xl md:text-7xl lg:text-8xl font-light leading-none text-ivory mb-6">
                        Where <em className="italic text-copper-light">Trust</em><br />Begins<em className="italic text-copper-light">.</em>
                    </h1>
                    <p className="font-display text-lg md:text-xl italic font-light text-ivory/50 max-w-lg leading-relaxed mb-10">
                        Building trust for Nigerian families. Building pride for every helper.
                    </p>

                    <div className="flex flex-col sm:flex-row gap-4">
                        <Link href="/onboarding" className="bg-teal text-white px-8 py-4 rounded-brand-md text-base font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-2 text-center">
                            Find Your Perfect Helper →
                        </Link>
                        <Link href="/register" className="border border-white/30 text-ivory px-8 py-4 rounded-brand-md text-base font-medium hover:bg-white/[0.08] transition-all text-center">
                            Register as a Helper
                        </Link>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mt-16 pt-10 border-t border-white/[0.08]">
                        {[
                            { label: 'Verified Helpers', value: '500+' },
                            { label: 'Families Served', value: '2,000+' },
                            { label: 'Match Rate', value: '96%' },
                            { label: 'Repeat Clients', value: '85%' },
                        ].map((stat) => (
                            <div key={stat.label}>
                                <p className="font-mono text-[9px] tracking-[0.12em] uppercase text-white/30 mb-1.5">{stat.label}</p>
                                <p className="text-ivory font-medium text-sm">{stat.value}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── How It Works ── */}
            <section id="how" className="py-24 px-6 bg-ivory">
                <div className="max-w-5xl mx-auto text-center mb-16">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-3">How It Works</p>
                    <h2 className="font-display text-4xl md:text-5xl font-light text-espresso mb-4">
                        Three Steps to <em className="italic">Peace of Mind</em>
                    </h2>
                    <p className="text-muted text-lg max-w-md mx-auto">From quiz to matched helper in under 5 minutes.</p>
                </div>
                <div className="max-w-5xl mx-auto grid md:grid-cols-3 gap-6">
                    {[
                        { step: '01', title: 'Tell Us What You Need', desc: 'Answer 8 quick questions about your household, schedule, and budget preferences.', icon: '✨' },
                        { step: '02', title: 'Get Matched Instantly', desc: 'Our AI-powered algorithm finds the top helpers in your area with real match scores.', icon: '🎯' },
                        { step: '03', title: 'Connect & Start', desc: 'Pay a one-time ₦5,000 matching fee, get your helper\'s contact, and schedule your start date.', icon: '🤝' },
                    ].map((item) => (
                        <div key={item.step} className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1 hover:shadow-brand-2 hover:-translate-y-1.5 transition-all duration-300 group">
                            <div className="text-3xl mb-4">{item.icon}</div>
                            <p className="font-mono text-[10px] tracking-[0.1em] text-teal mb-2">{item.step}</p>
                            <h3 className="font-display text-2xl font-semibold text-teal mb-3">{item.title}</h3>
                            <p className="text-muted text-sm leading-relaxed">{item.desc}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* ── Services ── */}
            <section id="services" className="py-24 px-6 bg-linen">
                <div className="max-w-5xl mx-auto text-center mb-16">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-3">Our Services</p>
                    <h2 className="font-display text-4xl md:text-5xl font-light text-espresso">
                        Help for <em className="italic">Every</em> Home
                    </h2>
                </div>
                <div className="max-w-5xl mx-auto grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    {[
                        { title: 'Housekeeping', desc: 'Deep cleaning, laundry, ironing, and home organization.', icon: '🏠' },
                        { title: 'Cooking', desc: 'Nigerian, Continental, and specialty cuisine preparation.', icon: '👩‍🍳' },
                        { title: 'Nanny Services', desc: 'Professional childcare from newborns to teenagers.', icon: '👶' },
                        { title: 'Elderly Care', desc: 'Compassionate companionship and daily assistance.', icon: '🧓' },
                        { title: 'Live-in Helpers', desc: 'Full-time household management and support.', icon: '🏡' },
                        { title: 'Drivers', desc: 'School runs, errands, and family transportation.', icon: '🚗' },
                    ].map((s) => (
                        <div key={s.title} className="bg-white rounded-brand-lg p-6 border border-gray-200 hover:shadow-brand-2 hover:-translate-y-1 transition-all duration-300">
                            <div className="text-2xl mb-3">{s.icon}</div>
                            <h3 className="font-semibold text-espresso mb-1">{s.title}</h3>
                            <p className="text-muted text-sm">{s.desc}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* ── Trust & Safety ── */}
            <section id="trust" className="py-24 px-6 bg-teal text-white">
                <div className="max-w-5xl mx-auto text-center mb-16">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-white/50 mb-3">Trust & Safety</p>
                    <h2 className="font-display text-4xl md:text-5xl font-light">
                        Your Family's <em className="italic">Safety</em> Comes First
                    </h2>
                </div>
                <div className="max-w-4xl mx-auto grid md:grid-cols-2 gap-6">
                    {[
                        { title: 'NIN Verification', desc: 'Every helper undergoes National Identity Number verification through QoreID.', icon: '🛡️' },
                        { title: 'Background Checks', desc: 'Criminal background and reference checks before any match is made.', icon: '🔍' },
                        { title: '10-Day Guarantee', desc: 'Not satisfied? Get a full replacement or refund within 10 days.', icon: '✅' },
                        { title: 'Secure Payments', desc: 'All transactions processed through Paystack with full encryption.', icon: '🔐' },
                    ].map((t) => (
                        <div key={t.title} className="bg-white/[0.08] rounded-brand-xl p-8 border border-white/10 backdrop-blur-sm">
                            <div className="text-2xl mb-3">{t.icon}</div>
                            <h3 className="font-semibold text-lg mb-2">{t.title}</h3>
                            <p className="text-white/70 text-sm leading-relaxed">{t.desc}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* ── CTA ── */}
            <section className="py-24 px-6 bg-ivory text-center">
                <div className="max-w-2xl mx-auto">
                    <h2 className="font-display text-4xl md:text-5xl font-light text-espresso mb-4">
                        Ready to Find Your <em className="italic text-teal">Perfect Helper</em>?
                    </h2>
                    <p className="text-muted mb-8">Join 2,000+ Nigerian families who trust Maids.ng</p>
                    <Link href="/onboarding" className="inline-block bg-teal text-white px-10 py-4 rounded-brand-md text-base font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-2">
                        Start Matching Now →
                    </Link>
                </div>
            </section>

            {/* ── Footer ── */}
            <footer className="bg-espresso text-ivory/60 py-16 px-6">
                <div className="max-w-5xl mx-auto grid md:grid-cols-4 gap-10 text-sm">
                    <div>
                        <img src="/maids-logo.png" alt="Maids.ng" className="h-8 mb-4 brightness-0 invert" />
                        <p className="text-xs leading-relaxed">Nigeria's most trusted platform for verified domestic helpers.</p>
                    </div>
                    <div>
                        <h4 className="font-semibold text-ivory mb-3">Platform</h4>
                        <div className="space-y-2">
                            <a href="/onboarding" className="block hover:text-teal-light transition-colors">Find a Helper</a>
                            <a href="/register" className="block hover:text-teal-light transition-colors">Become a Helper</a>
                            <a href="/maids" className="block hover:text-teal-light transition-colors">Browse Helpers</a>
                        </div>
                    </div>
                    <div>
                        <h4 className="font-semibold text-ivory mb-3">Company</h4>
                        <div className="space-y-2">
                            <a href="#" className="block hover:text-teal-light transition-colors">About Us</a>
                            <a href="#" className="block hover:text-teal-light transition-colors">Contact</a>
                            <a href="#" className="block hover:text-teal-light transition-colors">Blog</a>
                        </div>
                    </div>
                    <div>
                        <h4 className="font-semibold text-ivory mb-3">Contact</h4>
                        <div className="space-y-2">
                            <p>📞 08012345678</p>
                            <p>✉️ hello@maids.ng</p>
                            <p>📍 Lagos, Nigeria</p>
                        </div>
                    </div>
                </div>
                <div className="max-w-5xl mx-auto mt-12 pt-8 border-t border-white/10 text-xs text-center text-ivory/30">
                    © {new Date().getFullYear()} Maids.ng. All rights reserved.
                </div>
            </footer>

            <style>{`
                @keyframes fade-up { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
                .animate-fade-up { animation: fade-up 0.9s cubic-bezier(0, 0, 0.2, 1) both; }
            `}</style>
        </>
    );
}
