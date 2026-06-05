import { Head, Link } from '@inertiajs/react';

export default function Privacy() {
    return (
        <>
            <Head title="Privacy Policy — Maids.ng" />

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
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-3">Legal</p>
                    <h1 className="font-display text-4xl font-light text-espresso mb-8">Privacy Policy</h1>
                    <p className="text-muted text-sm mb-10">Last updated: May 2025</p>

                    <div className="prose prose-lg text-espresso/80 space-y-6">
                        <h2 className="font-display text-xl font-semibold text-teal">1. Information We Collect</h2>
                        <p>We collect information you provide directly, including: (a) account registration details (name, email, phone number); (b) profile information (bio, skills, location, bank details for workers; household needs for employers); (c) National Identification Number (NIN) for verification purposes; (d) payment information processed through our payment providers (Paystack, Flutterwave); and (e) communications with our AI agents and support team.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">2. How We Use Your Information</h2>
                        <p>Your information is used to: (a) create and manage your account; (b) match employers with compatible domestic workers; (c) verify identities through NIN checks; (d) process payments and manage wallets; (e) send notifications and updates; (f) improve our AI matching algorithms; and (g) comply with legal obligations.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">3. NIN Verification</h2>
                        <p>NIN verification is conducted through licensed third-party providers (QoreID). The NIN itself is hashed and stored securely. Verification results are shared only with authorized requesters who have paid for verification services. We do not sell or share NIN data with any third party for marketing purposes.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">4. Data Sharing</h2>
                        <p>We may share your information with: (a) matched users (employer and worker contact details are shared after a successful match and payment); (b) payment processors (Paystack, Flutterwave) to process transactions; (c) verification providers (QoreID) to verify identities; (d) law enforcement if required by law or to protect safety.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">5. Data Security</h2>
                        <p>We implement industry-standard security measures including encryption, secure servers, and access controls. However, no internet transmission is 100% secure, and we cannot guarantee absolute security.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">6. Data Retention</h2>
                        <p>We retain your data for as long as your account is active or as needed to provide services. You may request deletion of your account and associated data at any time. Certain data (transaction records, verification logs) may be retained longer for legal and compliance purposes.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">7. Your Rights</h2>
                        <p>Under Nigeria's Data Protection Act, you have the right to: (a) access your personal data; (b) correct inaccurate data; (c) request deletion of your data; (d) object to processing; and (e) withdraw consent. Contact us at hello@maids.ng to exercise these rights.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">8. Cookies & Analytics</h2>
                        <p>We use cookies for session management, authentication, and analytics. You can control cookie preferences through your browser settings.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">9. AI-Powered Features</h2>
                        <p>Our Platform uses AI for matching, notifications, and customer support. AI agents process your data to provide these services. You may opt out of AI-assisted matching and request manual matching instead.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">10. Children's Privacy</h2>
                        <p>Our services are not directed to individuals under 18. We do not knowingly collect personal information from children.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">11. Changes to This Policy</h2>
                        <p>We may update this privacy policy at any time. Material changes will be communicated via email or Platform notification.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">12. Contact</h2>
                        <p>For privacy questions or data requests, contact us at hello@maids.ng or write to us at Lagos, Nigeria.</p>
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
                        <h4 className="font-semibold text-ivory mb-3">Company</h4>
                        <div className="space-y-2">
                            <a href="/about" className="block hover:text-teal-light transition-colors">About Us</a>
                            <a href="/contact" className="block hover:text-teal-light transition-colors">Contact</a>
                            <a href="/blog" className="block hover:text-teal-light transition-colors">Blog</a>
                        </div>
                    </div>
                    <div>
                        <h4 className="font-semibold text-ivory mb-3">Legal</h4>
                        <div className="space-y-2">
                            <a href="/terms" className="block hover:text-teal-light transition-colors">Terms of Service</a>
                            <a href="/privacy" className="block text-teal-light">Privacy Policy</a>
                        </div>
                    </div>
                    <div>
                        <h4 className="font-semibold text-ivory mb-3">Contact</h4>
                        <div className="space-y-2">
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
