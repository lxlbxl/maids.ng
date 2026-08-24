import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function About({ appSettings }) {
    const feeLabel = appSettings?.matchingFeeFormatted ?? '₦5,000';
    return (
        <PublicLayout>
            <Head title="About Us — Maids.ng" />

            <section className="min-h-screen pt-12 pb-20 px-6 bg-ivory">
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
                                { title: 'Transparent Pricing', desc: `One-time ${feeLabel} matching fee. No hidden charges, no monthly subscriptions.` },
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

        </PublicLayout>
    );
}
