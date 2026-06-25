import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AmbassadorChatWidget from '@/Components/AmbassadorChatWidget';

export default function Welcome({ auth, appSettings }) {
    const feeLabel = appSettings?.matchingFeeFormatted ?? '₦5,000';
    const [audience, setAudience] = useState('employer'); // 'employer' | 'worker'

    return (
        <>
            <Head>
                <title>Maids.ng — Find Trusted Home Help in Nigeria</title>
                <meta name="description" content="Find verified housekeepers, nannies, cooks and drivers near you. Or register as a helper and get paid jobs in Nigeria. Safe, fast and trusted." />
            </Head>

            {/* ── Navigation ── */}
            <nav className="fixed top-0 w-full z-50 bg-white/90 dark:bg-[#121214]/90 backdrop-blur-md border-b border-gray-200/60 dark:border-white/10 transition-theme">
                <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <img src="/maids-logo.png" alt="Maids.ng" className="h-8 dark:brightness-0 dark:invert transition-all" />
                    </div>
                    <div className="hidden md:flex items-center gap-8">
                        <a href="#how" className="text-sm text-gray-500 dark:text-gray-400 hover:text-teal transition-colors">How It Works</a>
                        <a href="#for-workers" className="text-sm text-gray-500 dark:text-gray-400 hover:text-teal transition-colors">Find Work</a>
                        <Link href="/verify-service" className="text-sm text-teal font-medium hover:text-teal-dark transition-colors">Verify a Helper</Link>
                        <a href="#trust" className="text-sm text-gray-500 dark:text-gray-400 hover:text-teal transition-colors">Safety</a>
                    </div>
                    <div className="flex items-center gap-3">
                        {auth?.user ? (
                            <Link
                                href={auth.user.roles?.includes('admin') ? '/admin/dashboard' : auth.user.roles?.includes('employer') ? '/employer/dashboard' : '/maid/dashboard'}
                                className="bg-teal text-white px-5 py-2.5 rounded-brand-md text-sm font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-1"
                            >
                                My Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link href="/login" className="text-sm text-teal font-medium hover:text-teal-dark transition-colors">Log In</Link>
                                <a href="#get-started" className="bg-teal text-white px-5 py-2.5 rounded-brand-md text-sm font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-1">
                                    Get Started
                                </a>
                            </>
                        )}
                    </div>
                </div>
            </nav>

            {/* ── Hero Section ── */}
            <section id="hero" className="min-h-screen flex items-center bg-espresso relative overflow-hidden pt-20">
                {/* Background texture */}
                <div className="absolute inset-0 opacity-[0.04]" style={{ backgroundImage: 'repeating-linear-gradient(45deg, #FAF7F2 0, #FAF7F2 1px, transparent 0, transparent 50%), repeating-linear-gradient(-45deg, #FAF7F2 0, #FAF7F2 1px, transparent 0, transparent 50%)', backgroundSize: '28px 28px' }} />
                <div className="absolute top-[-300px] right-[-200px] w-[800px] h-[800px] rounded-full" style={{ background: 'radial-gradient(circle, rgba(15,85,86,0.35) 0%, transparent 65%)' }} />

                <div className="max-w-7xl mx-auto px-6 py-20 relative z-10 w-full">

                    {/* Audience toggle — the fork */}
                    <div className="flex justify-center mb-12">
                        <div className="inline-flex items-center gap-1 p-1 bg-white/10 rounded-full border border-white/20 backdrop-blur-sm">
                            <button
                                id="toggle-hiring"
                                onClick={() => setAudience('employer')}
                                className={`px-6 py-2.5 rounded-full text-sm font-medium transition-all duration-300 ${
                                    audience === 'employer'
                                        ? 'bg-teal text-white shadow-lg'
                                        : 'text-white/60 hover:text-white'
                                }`}
                            >
                                👨‍👩‍👧 I Want to Hire Help
                            </button>
                            <button
                                id="toggle-working"
                                onClick={() => setAudience('worker')}
                                className={`px-6 py-2.5 rounded-full text-sm font-medium transition-all duration-300 ${
                                    audience === 'worker'
                                        ? 'bg-copper-light text-espresso shadow-lg'
                                        : 'text-white/60 hover:text-white'
                                }`}
                            >
                                💼 I'm Looking for Work
                            </button>
                        </div>
                    </div>

                    {/* Employer Hero */}
                    {audience === 'employer' && (
                        <div className="text-center max-w-4xl mx-auto animate-fade-up">
                            <p className="font-mono text-xs tracking-[0.16em] uppercase text-teal-light mb-5">
                                Nigeria's Most Trusted Home Help Platform
                            </p>
                            <h1 className="font-display text-5xl md:text-7xl lg:text-8xl font-light leading-none text-ivory mb-6">
                                Your Home,<br />
                                <em className="italic text-copper-light">Finally Sorted.</em>
                            </h1>
                            <p className="text-ivory/60 text-xl leading-relaxed mb-10 max-w-2xl mx-auto">
                                Get a verified, background-checked housekeeper, nanny, or cook — matched to your exact needs. If it's not a great fit in 10 days, we fix it. Free.
                            </p>

                            {/* Trust badges */}
                            <div className="flex flex-wrap justify-center gap-3 mb-10">
                                {[
                                    { icon: '🆔', text: 'ID-Checked Helpers' },
                                    { icon: '🤖', text: 'Smart Matching' },
                                    { icon: '🔄', text: '10-Day Free Replacement' },
                                    { icon: '⭐', text: '96% Satisfaction Rate' },
                                ].map((badge) => (
                                    <div key={badge.text} className="flex items-center gap-2 px-4 py-2 bg-white/10 border border-white/20 rounded-full text-sm text-ivory/80">
                                        <span>{badge.icon}</span>
                                        <span>{badge.text}</span>
                                    </div>
                                ))}
                            </div>

                            <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                <Link href="/onboarding" id="hero-find-helper-btn" className="bg-teal text-white px-10 py-4 rounded-brand-md text-base font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-2">
                                    Find My Helper Now →
                                </Link>
                                <a href="#how" className="border border-white/30 text-ivory px-10 py-4 rounded-brand-md text-base font-medium hover:bg-white/[0.08] transition-all">
                                    See How It Works
                                </a>
                            </div>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mt-16 pt-10 border-t border-white/[0.08] text-center">
                                {[
                                    { label: 'Verified Helpers', value: '500+' },
                                    { label: 'Families Helped', value: '2,000+' },
                                    { label: 'Jobs Matched', value: '3,800+' },
                                    { label: 'Repeat Clients', value: '85%' },
                                ].map((stat) => (
                                    <div key={stat.label}>
                                        <p className="font-mono text-[9px] tracking-[0.12em] uppercase text-white/30 mb-1.5">{stat.label}</p>
                                        <p className="text-ivory font-medium text-lg">{stat.value}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Worker Hero */}
                    {audience === 'worker' && (
                        <div className="text-center max-w-4xl mx-auto animate-fade-up">
                            <p className="font-mono text-xs tracking-[0.16em] uppercase text-copper-light mb-5">
                                Find Good Work Near You
                            </p>
                            <h1 className="font-display text-5xl md:text-7xl lg:text-8xl font-light leading-none text-ivory mb-6">
                                Get a Job<br />
                                <em className="italic text-copper-light">You'll Be Proud Of.</em>
                            </h1>
                            <p className="text-ivory/60 text-xl leading-relaxed mb-10 max-w-2xl mx-auto">
                                Register for free. Get matched with families near you. We protect your rights, help you get paid on time, and never take a cut from your salary.
                            </p>

                            {/* Worker benefits badges */}
                            <div className="flex flex-wrap justify-center gap-3 mb-10">
                                {[
                                    { icon: '🆓', text: 'Free to Register' },
                                    { icon: '💰', text: 'No Salary Cuts' },
                                    { icon: '🛡️', text: 'We Protect Your Rights' },
                                    { icon: '📍', text: 'Jobs Close to You' },
                                ].map((badge) => (
                                    <div key={badge.text} className="flex items-center gap-2 px-4 py-2 bg-white/10 border border-white/20 rounded-full text-sm text-ivory/80">
                                        <span>{badge.icon}</span>
                                        <span>{badge.text}</span>
                                    </div>
                                ))}
                            </div>

                            <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                <Link href="/register/maid" id="hero-register-worker-btn" className="bg-copper-light text-espresso px-10 py-4 rounded-brand-md text-base font-medium hover:opacity-90 transition-all hover:scale-[1.02] shadow-brand-2">
                                    Register for Free →
                                </Link>
                                <a href="#for-workers" className="border border-white/30 text-ivory px-10 py-4 rounded-brand-md text-base font-medium hover:bg-white/[0.08] transition-all">
                                    Learn More
                                </a>
                            </div>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mt-16 pt-10 border-t border-white/[0.08] text-center">
                                {[
                                    { label: 'Helpers Placed', value: '500+' },
                                    { label: 'Avg. Monthly Pay', value: '₦35k+' },
                                    { label: 'Job Success Rate', value: '94%' },
                                    { label: 'Days to First Match', value: '< 7' },
                                ].map((stat) => (
                                    <div key={stat.label}>
                                        <p className="font-mono text-[9px] tracking-[0.12em] uppercase text-white/30 mb-1.5">{stat.label}</p>
                                        <p className="text-ivory font-medium text-lg">{stat.value}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </section>

            {/* ── Choose Your Path ── */}
            <section id="get-started" className="py-20 px-6 bg-white dark:bg-[#0f0f10] transition-theme scroll-mt-16">
                <div className="max-w-4xl mx-auto text-center mb-12">
                    <h2 className="font-display text-3xl md:text-4xl font-light text-espresso dark:text-[#f0ede8] mb-3">
                        What brings you to Maids.ng?
                    </h2>
                    <p className="text-muted dark:text-gray-400">Choose your path below to get started</p>
                </div>
                <div className="max-w-3xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Link href="/onboarding" className="group block bg-ivory dark:bg-[#1c1c1e] border border-gray-200 dark:border-white/10 rounded-brand-xl p-8 text-center hover:border-teal/30 hover:shadow-lg transition-all">
                        <div className="text-5xl mb-4">👨‍👩‍👧</div>
                        <h3 className="font-display text-xl text-espresso dark:text-white mb-2">I Want to Hire Help</h3>
                        <p className="text-muted dark:text-gray-400 text-sm">Find a verified housekeeper, nanny, cook, or driver for your home.</p>
                        <span className="inline-block mt-4 px-6 py-3 bg-teal text-white rounded-brand-md text-sm font-medium group-hover:bg-teal-dark transition-all">Find a Helper →</span>
                    </Link>
                    <Link href="/register/maid" className="group block bg-ivory dark:bg-[#1c1c1e] border border-gray-200 dark:border-white/10 rounded-brand-xl p-8 text-center hover:border-copper/30 hover:shadow-lg transition-all">
                        <div className="text-5xl mb-4">💼</div>
                        <h3 className="font-display text-xl text-espresso dark:text-white mb-2">I'm Looking for Work</h3>
                        <p className="text-muted dark:text-gray-400 text-sm">Register as a helper, get verified, and start getting matched with families near you.</p>
                        <span className="inline-block mt-4 px-6 py-3 bg-copper text-white rounded-brand-md text-sm font-medium group-hover:bg-copper-dark transition-all">Register as Helper →</span>
                    </Link>
                </div>
            </section>

            {/* ── How It Works (Employer track) ── */}
            <section id="how" className="py-24 px-6 bg-ivory dark:bg-[#0f0f10] transition-theme">
                <div className="max-w-5xl mx-auto text-center mb-16">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-3">For Families Hiring</p>
                    <h2 className="font-display text-4xl md:text-5xl font-light text-espresso dark:text-[#f0ede8] mb-4 transition-theme">
                        From Question to <em className="italic">Helper</em> in 3 Steps
                    </h2>
                    <p className="text-muted dark:text-gray-400 text-lg max-w-md mx-auto">No stress. No phone calls. Done in under 5 minutes.</p>
                </div>
                <div className="max-w-5xl mx-auto grid md:grid-cols-3 gap-6">
                    {[
                        {
                            step: '01',
                            title: 'Tell Us What You Need',
                            desc: 'Answer a few quick questions — like where you live, what kind of help you need, and how much you want to pay.',
                            icon: '💬',
                        },
                        {
                            step: '02',
                            title: 'We Find the Best Match',
                            desc: 'Our system searches through verified helpers near you and picks the ones that fit you best. You see their profiles and scores.',
                            icon: '🎯',
                        },
                        {
                            step: '03',
                            title: 'Start and Relax',
                            desc: `Pay a small one-time fee of ${feeLabel}, get your helper's contact, and agree on a start date. If it's not right, we fix it for free.`,
                            icon: '🤝',
                        },
                    ].map((item) => (
                        <div key={item.step} className="bg-white dark:bg-[#1c1c1e] rounded-brand-xl p-8 border border-gray-200 dark:border-white/10 shadow-brand-1 hover:shadow-brand-2 hover:-translate-y-1.5 transition-all duration-300 group">
                            <div className="text-3xl mb-4">{item.icon}</div>
                            <p className="font-mono text-[10px] tracking-[0.1em] text-teal mb-2">{item.step}</p>
                            <h3 className="font-display text-2xl font-semibold text-teal mb-3">{item.title}</h3>
                            <p className="text-muted dark:text-gray-400 text-sm leading-relaxed">{item.desc}</p>
                        </div>
                    ))}
                </div>
                <div className="text-center mt-10">
                    <Link href="/onboarding" id="how-cta-btn" className="inline-block bg-teal text-white px-10 py-4 rounded-brand-md text-base font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-2">
                        Start — It's Free →
                    </Link>
                </div>
            </section>

            {/* ── For Workers Section ── */}
            <section id="for-workers" className="py-24 px-6 bg-espresso relative overflow-hidden">
                <div className="absolute bottom-0 left-0 w-[600px] h-[600px] rounded-full" style={{ background: 'radial-gradient(circle, rgba(15,85,86,0.3) 0%, transparent 65%)' }} />
                <div className="max-w-5xl mx-auto relative z-10">
                    <div className="text-center mb-16">
                        <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-copper-light mb-3">For People Looking for Work</p>
                        <h2 className="font-display text-4xl md:text-5xl font-light text-ivory mb-4">
                            Find Work That <em className="italic text-copper-light">Respects You</em>
                        </h2>
                        <p className="text-ivory/60 text-lg max-w-xl mx-auto">
                            We connect you with good families — and we make sure you are treated fairly, paid on time, and protected.
                        </p>
                    </div>

                    <div className="grid md:grid-cols-2 gap-8 mb-16">
                        {[
                            {
                                icon: '🆓',
                                title: 'Free to Join',
                                desc: 'Registration is completely free. You never pay to be listed or to receive job offers.',
                            },
                            {
                                icon: '💰',
                                title: 'Your Full Salary is Yours',
                                desc: 'We never take a cut from your monthly pay. What the family agrees to pay you — that is what you receive.',
                            },
                            {
                                icon: '📍',
                                title: 'Jobs Near Where You Live',
                                desc: 'Tell us which states or cities you are willing to work in. We only match you with families in those areas.',
                            },
                            {
                                icon: '🛡️',
                                title: 'We Have Your Back',
                                desc: 'If anything goes wrong with an employer, contact us. We investigate disputes and protect workers fairly.',
                            },
                            {
                                icon: '📱',
                                title: 'Track Your Salary',
                                desc: 'Your salary calendar is always on your dashboard. Know when your payment is due — and get reminded if it is late.',
                            },
                            {
                                icon: '⭐',
                                title: 'Build Your Reputation',
                                desc: 'Good reviews from families make it easier to get better jobs. Your hard work follows you and helps you grow.',
                            },
                        ].map((benefit) => (
                            <div key={benefit.title} className="flex gap-5 p-6 bg-white/[0.05] border border-white/10 rounded-brand-xl hover:bg-white/[0.08] transition-all">
                                <div className="text-3xl flex-shrink-0">{benefit.icon}</div>
                                <div>
                                    <h3 className="font-semibold text-ivory text-lg mb-1">{benefit.title}</h3>
                                    <p className="text-ivory/60 text-sm leading-relaxed">{benefit.desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="text-center">
                        <Link href="/register/maid" id="worker-register-btn" className="inline-block bg-copper-light text-espresso px-10 py-4 rounded-brand-md text-base font-semibold hover:opacity-90 transition-all hover:scale-[1.02] shadow-brand-2">
                            Register as a Helper — It's Free →
                        </Link>
                        <p className="text-ivory/40 text-sm mt-4">Takes less than 5 minutes. No money required.</p>
                    </div>
                </div>
            </section>

            {/* ── Services ── */}
            <section id="services" className="py-24 px-6 bg-linen dark:bg-[#1a1a1c] transition-theme">
                <div className="max-w-5xl mx-auto text-center mb-16">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-3">Types of Help Available</p>
                    <h2 className="font-display text-4xl md:text-5xl font-light text-espresso dark:text-[#f0ede8] transition-theme">
                        Whatever Your Home <em className="italic">Needs</em>
                    </h2>
                </div>
                <div className="max-w-5xl mx-auto grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    {[
                        { title: 'Housekeeping', desc: 'Cleaning, laundry, ironing, and keeping the home tidy.', icon: '🏠' },
                        { title: 'Cooking', desc: 'Nigerian dishes, soups, continental food — whatever your family eats.', icon: '👩‍🍳' },
                        { title: 'Nanny / Childcare', desc: 'Caring for children of all ages, from babies to teenagers.', icon: '👶' },
                        { title: 'Care for Elderly', desc: 'Gentle, respectful help for older family members at home.', icon: '🧓' },
                        { title: 'Live-in Helper', desc: 'A full-time helper who lives in the house and manages the home.', icon: '🏡' },
                        { title: 'Driver', desc: 'School runs, errands, market trips, and family travel.', icon: '🚗' },
                    ].map((s) => (
                        <div key={s.title} className="bg-white dark:bg-[#1c1c1e] rounded-brand-xl p-8 border border-espresso/5 dark:border-white/10 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                            <div className="text-3xl mb-4">{s.icon}</div>
                            <h3 className="font-semibold text-lg text-espresso dark:text-[#f0ede8] mb-2">{s.title}</h3>
                            <p className="text-espresso/60 dark:text-gray-400 text-sm leading-relaxed">{s.desc}</p>
                        </div>
                    ))}
                </div>

                {/* Standalone Verification Highlight */}
                <div className="max-w-5xl mx-auto mt-20 p-8 md:p-12 bg-espresso rounded-brand-2xl relative overflow-hidden group">
                    <div className="absolute top-0 right-0 w-64 h-64 bg-teal/10 rounded-full -mr-20 -mt-20 blur-3xl group-hover:bg-teal/20 transition-all duration-500" />
                    <div className="relative z-10 flex flex-col md:flex-row items-center gap-10">
                        <div className="flex-1 text-center md:text-left">
                            <span className="inline-block px-3 py-1 bg-teal/20 text-teal-light rounded-full text-[10px] font-mono uppercase tracking-widest mb-4">For Anyone</span>
                            <h2 className="font-display text-3xl md:text-4xl text-white mb-4">
                                Already Have a <em className="italic text-copper-light">Helper?</em>
                            </h2>
                            <p className="text-ivory/60 text-lg mb-8 leading-relaxed max-w-xl">
                                Not sure if your current helper's ID is real? Run a quick identity check and get a report in seconds. No registration needed.
                            </p>
                            <Link href="/verify-service" id="verify-helper-btn" className="inline-flex items-center gap-2 bg-teal text-white px-8 py-4 rounded-brand-md text-base font-medium hover:bg-teal-dark transition-all hover:scale-[1.05] shadow-lg">
                                Check Your Helper's Identity <span className="text-xl">🛡️</span>
                            </Link>
                        </div>
                        <div className="w-full md:w-1/3 grid grid-cols-2 gap-4">
                            {[
                                { label: 'Results in', value: 'Seconds' },
                                { label: 'Linked to', value: 'NIMC Database' },
                                { label: 'You Get a', value: 'PDF Report' },
                                { label: 'Gives You', value: 'Peace of Mind' },
                            ].map((badge) => (
                                <div key={badge.value} className="bg-white/5 border border-white/10 p-4 rounded-brand-lg backdrop-blur-sm text-center">
                                    <p className="text-[10px] text-white/30 uppercase tracking-tighter">{badge.label}</p>
                                    <p className="text-white font-medium text-xs">{badge.value}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* ── Testimonials ── */}
            <section className="py-24 px-6 bg-ivory dark:bg-[#0f0f10] transition-theme">
                <div className="max-w-5xl mx-auto text-center mb-16">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-3">Real People, Real Stories</p>
                    <h2 className="font-display text-4xl md:text-5xl font-light text-espresso dark:text-[#f0ede8] transition-theme">
                        What Our <em className="italic">Community</em> Says
                    </h2>
                </div>
                <div className="max-w-5xl mx-auto grid md:grid-cols-3 gap-6">
                    {[
                        {
                            name: 'Mrs. Adaeze O.',
                            location: 'Lekki, Lagos',
                            role: 'Family of 4',
                            quote: 'I was nervous about letting a stranger into my home. But the ID check and background report gave me confidence. My housekeeper has been with us for 6 months and she is wonderful.',
                            avatar: '👩🏾',
                            type: 'employer',
                        },
                        {
                            name: 'Blessing T.',
                            location: 'Abuja, FCT',
                            role: 'Housekeeper',
                            quote: 'I registered and within 3 days I had a match. The family is kind and I am paid every month on the exact date. I feel safe and respected. This platform is different.',
                            avatar: '👩🏿',
                            type: 'worker',
                        },
                        {
                            name: 'Mr. Emeka F.',
                            location: 'GRA, Port Harcourt',
                            role: 'Business Owner',
                            quote: 'I needed a reliable nanny quickly. The 10-day guarantee gave me peace of mind. We found the right match on the second try — and they were great about replacing the first one.',
                            avatar: '👨🏾',
                            type: 'employer',
                        },
                    ].map((t) => (
                        <div key={t.name} className="bg-white dark:bg-[#1c1c1e] rounded-brand-xl p-8 border border-gray-200 dark:border-white/10 shadow-brand-1 flex flex-col">
                            <div className="text-4xl mb-4">{t.avatar}</div>
                            <p className="text-espresso/70 dark:text-gray-300 text-sm leading-relaxed mb-6 flex-1 italic">"{t.quote}"</p>
                            <div className="border-t border-gray-100 dark:border-white/10 pt-4">
                                <p className="font-semibold text-espresso dark:text-[#f0ede8] text-sm">{t.name}</p>
                                <p className="text-xs text-muted dark:text-gray-400">{t.role} · {t.location}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {/* ── Trust & Safety ── */}
            <section id="trust" className="py-24 px-6 bg-teal text-white">
                <div className="max-w-5xl mx-auto text-center mb-16">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-white/50 mb-3">Safety & Trust</p>
                    <h2 className="font-display text-4xl md:text-5xl font-light">
                        How We Keep <em className="italic">Everyone</em> Safe
                    </h2>
                    <p className="text-white/60 text-lg mt-4 max-w-xl mx-auto">Plain answers to the questions we get asked most.</p>
                </div>

                {/* Trust cards */}
                <div className="max-w-4xl mx-auto grid md:grid-cols-2 gap-6 mb-16">
                    {[
                        {
                            title: 'We Check Every Helper\'s ID',
                            desc: 'Before a helper joins Maids.ng, we check their National ID (NIN) directly with the government database. This means you know exactly who is coming into your home.',
                            icon: '🆔',
                        },
                        {
                            title: 'We Do Background Checks',
                            desc: 'We also run a background check to look for any criminal history. Helpers with serious records are not allowed on the platform.',
                            icon: '🔍',
                        },
                        {
                            title: '10-Day Free Replacement Promise',
                            desc: 'If your helper is not a good fit in the first 10 days — for any reason — we will find you a replacement for free. No arguments, no stress.',
                            icon: '🔄',
                        },
                        {
                            title: 'Safe & Secure Payments',
                            desc: 'All payments go through Paystack, one of Africa\'s most trusted payment systems. Your card details are never stored on our servers.',
                            icon: '🔐',
                        },
                    ].map((t) => (
                        <div key={t.title} className="bg-white/[0.08] rounded-brand-xl p-8 border border-white/10 backdrop-blur-sm hover:bg-white/[0.12] transition-all">
                            <div className="text-3xl mb-3">{t.icon}</div>
                            <h3 className="font-semibold text-lg mb-2">{t.title}</h3>
                            <p className="text-white/70 text-sm leading-relaxed">{t.desc}</p>
                        </div>
                    ))}
                </div>

                {/* FAQ */}
                <div className="max-w-3xl mx-auto space-y-4">
                    <h3 className="font-display text-2xl text-center mb-8 font-light">Common Questions</h3>
                    {[
                        {
                            q: 'What does "AI Matching" mean?',
                            a: 'It just means our computer system looks at what you need and compares it to all the helpers available — then it picks the best ones for you automatically. Like a very smart filter. You still choose who to hire.',
                        },
                        {
                            q: `Is the ${feeLabel} fee refundable?`,
                            a: `The ${feeLabel} matching fee is not refundable, but your 10-day replacement is. If the match doesn't work out, we find you a new helper at no extra cost.`,
                        },
                        {
                            q: 'Do helpers pay anything to join?',
                            a: 'No. Registering as a helper is completely free. Maids.ng never takes money from a helper\'s salary.',
                        },
                        {
                            q: 'What if there is a problem with my helper?',
                            a: 'Contact us through the app or by email. We have a disputes team who will listen to both sides and help resolve the issue fairly.',
                        },
                    ].map((faq) => (
                        <details key={faq.q} className="group bg-white/[0.06] border border-white/10 rounded-brand-xl overflow-hidden">
                            <summary className="flex items-center justify-between p-6 cursor-pointer text-white font-medium select-none">
                                {faq.q}
                                <span className="ml-4 text-white/50 group-open:rotate-45 transition-transform duration-200 text-xl flex-shrink-0">+</span>
                            </summary>
                            <p className="px-6 pb-6 text-white/60 text-sm leading-relaxed">{faq.a}</p>
                        </details>
                    ))}
                </div>
            </section>

            {/* ── Final CTA ── */}
            <section className="py-24 px-6 bg-ivory dark:bg-[#0f0f10] transition-theme">
                <div className="max-w-5xl mx-auto grid md:grid-cols-2 gap-8">
                    {/* Employer CTA */}
                    <div className="text-center p-10 bg-teal rounded-brand-2xl shadow-brand-2">
                        <div className="text-5xl mb-4">👨‍👩‍👧</div>
                        <h2 className="font-display text-3xl font-light text-white mb-3">
                            Looking to <em className="italic">Hire?</em>
                        </h2>
                        <p className="text-white/70 mb-8 text-sm leading-relaxed">
                            Tell us what you need. We'll find the best match for your family — safely and quickly.
                        </p>
                        <Link href="/onboarding" id="final-cta-hire-btn" className="inline-block bg-white text-teal px-8 py-4 rounded-brand-md text-base font-semibold hover:bg-ivory transition-all hover:scale-[1.02] shadow-md">
                            Find a Helper →
                        </Link>
                    </div>

                    {/* Worker CTA */}
                    <div className="text-center p-10 bg-espresso rounded-brand-2xl shadow-brand-2 relative overflow-hidden">
                        <div className="absolute inset-0 opacity-[0.04]" style={{ backgroundImage: 'repeating-linear-gradient(45deg, #FAF7F2 0, #FAF7F2 1px, transparent 0, transparent 50%)', backgroundSize: '28px 28px' }} />
                        <div className="relative z-10">
                            <div className="text-5xl mb-4">💼</div>
                            <h2 className="font-display text-3xl font-light text-ivory mb-3">
                                Looking for <em className="italic text-copper-light">Work?</em>
                            </h2>
                            <p className="text-ivory/60 mb-8 text-sm leading-relaxed">
                                Register free. Get matched with good families near you. Start earning in under a week.
                            </p>
                            <Link href="/register/maid" id="final-cta-work-btn" className="inline-block bg-copper-light text-espresso px-8 py-4 rounded-brand-md text-base font-semibold hover:opacity-90 transition-all hover:scale-[1.02] shadow-md">
                                Register as a Helper →
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── Footer ── */}
            <footer className="bg-espresso text-ivory/60 py-16 px-6">
                <div className="max-w-5xl mx-auto grid md:grid-cols-5 gap-10 text-sm">
                    <div className="md:col-span-2">
                        <img src="/maids-logo.png" alt="Maids.ng" className="h-8 mb-4 brightness-0 invert" />
                        <p className="text-xs leading-relaxed max-w-xs">
                            Nigeria's most trusted platform connecting families with verified, background-checked domestic helpers.
                        </p>
                    </div>
                    <div>
                        <h4 className="font-semibold text-ivory mb-3">Platform</h4>
                        <div className="space-y-2">
                            <a href="/onboarding" className="block hover:text-teal-light transition-colors">Find a Helper</a>
                            <a href="/register/maid" className="block hover:text-teal-light transition-colors">Register as Helper</a>
                            <a href="/maids" className="block hover:text-teal-light transition-colors">Browse Helpers</a>
                            <Link href="/verify-service" className="block hover:text-teal-light transition-colors">Verify a Helper</Link>
                        </div>
                    </div>
                    <div>
                        <h4 className="font-semibold text-ivory mb-3">Company</h4>
                        <div className="space-y-2">
                            <Link href="/about" className="block hover:text-teal-light transition-colors">About Us</Link>
                            <Link href="/contact" className="block hover:text-teal-light transition-colors">Contact</Link>
                            <Link href="/blog" className="block hover:text-teal-light transition-colors">Blog</Link>
                        </div>
                    </div>
                    <div>
                        <h4 className="font-semibold text-ivory mb-3">Help & Legal</h4>
                        <div className="space-y-2">
                            <Link href="/terms" className="block hover:text-teal-light transition-colors">Terms of Service</Link>
                            <Link href="/privacy" className="block hover:text-teal-light transition-colors">Privacy Policy</Link>
                            <p className="mt-4">📞 08012345678</p>
                            <p>✉️ hello@maids.ng</p>
                        </div>
                    </div>
                </div>
                <div className="max-w-5xl mx-auto mt-12 pt-8 border-t border-white/10 text-xs text-center text-ivory/30">
                    © {new Date().getFullYear()} Maids.ng. All rights reserved. · Nigeria's Most Trusted Home Help Platform
                </div>
            </footer>

            <style>{`
                @keyframes fade-up { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
                .animate-fade-up { animation: fade-up 0.7s cubic-bezier(0, 0, 0.2, 1) both; }
                details > summary::-webkit-details-marker { display: none; }
            `}</style>

            <AmbassadorChatWidget />
        </>
    );
}
