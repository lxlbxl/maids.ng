import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Contact() {
    const [formState, setFormState] = useState({ name: '', email: '', subject: '', message: '' });
    const [submitted, setSubmitted] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        setSubmitted(true);
    };

    return (
        <>
            <Head title="Contact Us — Maids.ng" />

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
                <div className="max-w-5xl mx-auto">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-3">Get in Touch</p>
                    <h1 className="font-display text-4xl md:text-5xl font-light text-espresso mb-8">
                        Contact <em className="italic text-teal">Us</em>
                    </h1>

                    <div className="grid md:grid-cols-2 gap-10">
                        <div>
                            <p className="text-espresso/70 text-lg mb-8 leading-relaxed">
                                Have a question, feedback, or need support? We'd love to hear from you. Our team typically responds within 24 hours.
                            </p>

                            <div className="space-y-6">
                                <div className="bg-white rounded-brand-xl p-6 border border-gray-200 shadow-brand-1">
                                    <h3 className="font-semibold text-teal mb-2">Email</h3>
                                    <p className="text-muted">hello@maids.ng</p>
                                    <p className="text-muted">support@maids.ng</p>
                                </div>
                                <div className="bg-white rounded-brand-xl p-6 border border-gray-200 shadow-brand-1">
                                    <h3 className="font-semibold text-teal mb-2">Phone</h3>
                                    <p className="text-muted">08012345678</p>
                                    <p className="text-muted text-sm">Mon–Sat, 8am–6pm WAT</p>
                                </div>
                                <div className="bg-white rounded-brand-xl p-6 border border-gray-200 shadow-brand-1">
                                    <h3 className="font-semibold text-teal mb-2">Office</h3>
                                    <p className="text-muted">Lagos, Nigeria</p>
                                </div>
                            </div>

                            <div className="mt-8 p-6 bg-teal-ghost rounded-brand-xl border border-teal-pale">
                                <h3 className="font-semibold text-teal mb-2">Quick Help?</h3>
                                <p className="text-muted text-sm mb-3">Chat with Ambassador, our AI assistant, for instant answers.</p>
                                <button className="text-teal font-medium text-sm hover:text-teal-dark transition-colors">
                                    Start Chat →
                                </button>
                            </div>
                        </div>

                        <div>
                            {submitted ? (
                                <div className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1 text-center">
                                    <div className="text-5xl mb-4">✉️</div>
                                    <h3 className="font-display text-2xl font-semibold text-teal mb-3">Message Sent!</h3>
                                    <p className="text-muted">Thank you for reaching out. We'll get back to you within 24 hours.</p>
                                </div>
                            ) : (
                                <form onSubmit={handleSubmit} className="bg-white rounded-brand-xl p-8 border border-gray-200 shadow-brand-1">
                                    <div className="space-y-5">
                                        <div>
                                            <label className="block text-sm font-medium text-espresso mb-1">Full Name</label>
                                            <input
                                                type="text"
                                                required
                                                value={formState.name}
                                                onChange={(e) => setFormState({ ...formState, name: e.target.value })}
                                                className="w-full px-4 py-3 rounded-brand-md border border-gray-300 focus:ring-2 focus:ring-teal focus:border-teal outline-none transition-all"
                                                placeholder="Your name"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-espresso mb-1">Email</label>
                                            <input
                                                type="email"
                                                required
                                                value={formState.email}
                                                onChange={(e) => setFormState({ ...formState, email: e.target.value })}
                                                className="w-full px-4 py-3 rounded-brand-md border border-gray-300 focus:ring-2 focus:ring-teal focus:border-teal outline-none transition-all"
                                                placeholder="you@example.com"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-espresso mb-1">Subject</label>
                                            <select
                                                value={formState.subject}
                                                onChange={(e) => setFormState({ ...formState, subject: e.target.value })}
                                                className="w-full px-4 py-3 rounded-brand-md border border-gray-300 focus:ring-2 focus:ring-teal focus:border-teal outline-none transition-all"
                                            >
                                                <option value="">Select a topic</option>
                                                <option value="support">General Support</option>
                                                <option value="billing">Billing & Payments</option>
                                                <option value="verification">Verification Issues</option>
                                                <option value="matching">Matching Questions</option>
                                                <option value="partnership">Partnership Inquiry</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-espresso mb-1">Message</label>
                                            <textarea
                                                required
                                                rows={5}
                                                value={formState.message}
                                                onChange={(e) => setFormState({ ...formState, message: e.target.value })}
                                                className="w-full px-4 py-3 rounded-brand-md border border-gray-300 focus:ring-2 focus:ring-teal focus:border-teal outline-none transition-all resize-none"
                                                placeholder="Tell us how we can help..."
                                            />
                                        </div>
                                        <button
                                            type="submit"
                                            className="w-full bg-teal text-white px-6 py-3.5 rounded-brand-md text-base font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-1"
                                        >
                                            Send Message →
                                        </button>
                                    </div>
                                </form>
                            )}
                        </div>
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
                            <a href="/about" className="block hover:text-teal-light transition-colors">About Us</a>
                            <a href="/contact" className="block text-teal-light">Contact</a>
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
