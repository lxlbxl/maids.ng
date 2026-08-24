import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function Privacy() {
    return (
        <PublicLayout>
            <Head title="Privacy Policy — Maids.ng" />

            <section className="min-h-screen pt-12 pb-20 px-6 bg-ivory">
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

        </PublicLayout>
    );
}
