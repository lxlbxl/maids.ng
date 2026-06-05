import { Head, Link } from '@inertiajs/react';

export default function About() {
    return (
        <>
            <Head title="About Us — Maids.ng" />

            <nav className="fixed top-0 w-full z-50 bg-white/90 backdrop-blur-md border-b border-gray-200/60">
                <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                    <Link href="/" className="flex items-center gap-2">
                        <img src="/maids-logo.png" alt="Maids.ng" className="h-8" />
                    </Link>
                    <div className="flex items-center gap-3">
                        <Link href="/login" className="text-sm text-teal font-medium hover:text-teal-dark transition-colors">Log In</Link>
                        <Link href="/onboarding" className="bg-teal text-white px-5 py-2.5 rounded-brand-md text-sm font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-1">
                            Find a Helper
                        </Link>
                    </div>
                </div>
            </nav>

            <section className="min-h-screen pt-24 pb-20 px-6 bg-ivory">
                <div className="max-w-3xl mx-auto">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-3">About Maids.ng</p>
                    <h1 className="font-display text-4xl md:text-5xl font-light text-espresso mb-8">
                        Building Trust for Nigerian <em className="italic text-teal">Families</em>
                    </h1>

                    <div className="prose prose-lg text-espresso/80 space-y-6">
                        <p className="text-xl leading-relaxed text-espresso/70">
                            Maids.ng was born from a simple frustration: finding reliable, verified domestic help in Nigeria is unnecessarily hard. Families rely on word-of-mouth, unverified Facebook groups, or agencies that charge exorbitant fees with no guarantees. Helpers, on the other hand, struggle to find dignified work despite having real skills and dedication.
                        </p>

                        <p>
                            We built Maids.ng to solve both sides of this problem. Our platform uses AI to match employers with domestic workers based on real compatibility — schedule, location, budget, and skill requirements — not just proximity. Every helper on our platform is verified through the National Identity Management Commission (NIMC) database, giving families peace of mind.
                        </p>

                        <h2 className="font-display text-2xl font-semibold text-teal mt-10 mb-4">Our Mission</h2>
                        <p>
                            To professionalize domestic staffing in Nigeria by creating a transparent, trust-based marketplace that protects both employers and workers. We believe that every Nigerian family deserves access to reliable help, and every domestic worker deserves to be treated with dignity and paid fairly.
                        </p>

                        <h2 className="font-display text-2xl font-semibold text-teal mt-10 mb-4">How We're Different</h2>
                        <div className="grid md:grid-cols-2 gap-6 my-8">
                            {[
                                { title: 'NIN Verification', desc: 'Every helper is verified against the National Identity Database through QoreID integration.' },
                                { title: 'AI Matching', desc: 'Our algorithm scores compatibility across 12+ factors — not just location.' },
                                { title: '10-Day Guarantee', desc: 'If the match doesn\'t work, get a replacement or full refund within 10 days.' },
                                { title: 'Transparent Pricing', desc: 'One-time ₦5,000 matching fee. No hidden charges, no monthly subscriptions.' },
                            ].map((item) => (
                                <div key={item.title} className="bg-white rounded-brand-xl p-6 border border-gray-200 shadow-brand-1">
                                    <h3 className="font-semibold text-teal mb-2">{item.title}</h3>
                                    <p className="text-muted text-sm">{item.desc}</p>
                                </div>
                            ))}
                        </div>

                        <h2 className="font-display text-2xl font-semibold text-teal mt-10 mb-4">Our Multi-Agent System</h2>
                        <p>
                            Maids.ng is powered by a sophisticated multi-agent AI system. Each agent specializes in a different aspect of the platform:
                        </p>
                        <ul className="list-disc pl-6 space-y-2">
                            <li><strong>Scout</strong> — Searches and identifies the best candidates for each employer</li>
                            <li><strong>Gatekeeper</strong> — Verifies identities and credentials through NIN checks</li>
                            <li><strong>Sentinel</strong> — Monitors bookings and alerts for any issues</li>
                            <li><strong>Concierge</strong> — Provides personalized assistance to employers and helpers</li>
                            <li><strong>Referee</strong> — Handles disputes and ensures fair resolutions</li>
                            <li><strong>Treasurer</strong> — Manages financial transactions and wallet operations</li>
                        </ul>

                        <h2 className="font-display text-2xl font-semibold text-teal mt-10 mb-4">Where We Operate</h2>
                        <p>
                            We currently serve families across Nigeria's major cities — Lagos, Abuja, and Port Harcourt — with coverage expanding to Ibadan, Kano, Enugu, and other cities. Our platform works nationwide, whether you need a full-time live-in housekeeper or a part-time cleaner.
                        </p>

                        <h2 className="font-display text-2xl font-semibold text-teal mt-10 mb-4">Join Us</h2>
                        <p>
                            Whether you're a family looking for help, or a skilled domestic worker seeking opportunities, Maids.ng is here for you. Our onboarding process takes less than 5 minutes.
                        </p>
                    </div>

                    <div className="mt-12 p-8 bg-espresso rounded-brand-2xl text-center">
                        <h3 className="font-display text-2xl text-white mb-3">Ready to Get Started?</h3>
                        <p className="text-ivory/60 mb-6">Find your perfect helper in under 5 minutes.</p>
                        <Link href="/onboarding" className="inline-block bg-teal text-white px-8 py-4 rounded-brand-md text-base font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-2">
                            Start Free →
                        </Link>
                    </div>
                </div>
            </section>

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
                            <a href="/register/maid" className="block hover:text-teal-light transition-colors">Become a Helper</a>
                            <a href="/maids" className="block hover:text-teal-light transition-colors">Browse Helpers</a>
                        </div>
                    </div>
                    <div>
                        <h4 className="font-semibold text-ivory mb-3">Company</h4>
                        <div className="space-y-2">
                            <a href="/about" className="block text-teal-light">About Us</a>
                            <a href="/contact" className="block hover:text-teal-light transition-colors">Contact</a>
                            <a href="/blog" className="block hover:text-teal-light transition-colors">Blog</a>
                        </div>
                    </div>
                    <div>
                        <h4 className="font-semibold text-ivory mb-3">Contact</h4>
                        <div className="space-y-2">
                            <p>08012345678</p>
                            <p>hello@maids.ng</p>
                            <p>Lagos, Nigeria</p>
                        </div>
                    </div>
                </div>
                <div className="max-w-5xl mx-auto mt-12 pt-8 border-t border-white/10 text-xs text-center text-ivory/30">
                    © {new Date().getFullYear()} Maids.ng. All rights reserved.
                </div>
            </footer>
        </>
    );
}
