import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function Terms({ appSettings }) {
    const feeLabel = appSettings?.matchingFeeFormatted ?? '₦5,000';
    return (
        <PublicLayout>
            <Head title="Terms of Service — Maids.ng" />

            <section className="min-h-screen pt-12 pb-20 px-6 bg-ivory">
                <div className="max-w-3xl mx-auto">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-3">Legal</p>
                    <h1 className="font-display text-4xl font-light text-espresso mb-8">Terms of Service</h1>
                    <p className="text-muted text-sm mb-10">Last updated: May 2025</p>

                    <div className="prose prose-lg text-espresso/80 space-y-6">
                        <h2 className="font-display text-xl font-semibold text-teal">1. Acceptance of Terms</h2>
                        <p>By accessing or using Maids.ng ("the Platform"), you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use the Platform.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">2. Description of Service</h2>
                        <p>Maids.ng is an AI-powered platform that connects employers with verified domestic workers in Nigeria. The Platform provides: (a) a matching service that scores compatibility between employers and domestic workers; (b) identity verification services through NIN checks; (c) payment processing for matching fees; and (d) communication tools for arranging employment.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">3. User Accounts</h2>
                        <p>You must be at least 18 years old to create an account. You are responsible for maintaining the confidentiality of your account credentials. You must provide accurate and complete information during registration.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">4. Matching Fee & Guarantee</h2>
                        <p>The Platform charges a one-time matching fee of {feeLabel} (subject to change) to access a matched helper's contact details. This fee is covered by our 10-day money-back guarantee: if the match is unsuccessful within 10 days, you may request a replacement match or full refund.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">5. Employer Responsibilities</h2>
                        <p>Employers agree to: (a) treat matched workers with dignity and respect; (b) comply with all applicable Nigerian labour laws; (c) provide safe working conditions; (d) pay agreed-upon salaries on time; and (e) not use the Platform for any illegal or exploitative purposes.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">6. Worker Responsibilities</h2>
                        <p>Domestic workers on the Platform agree to: (a) provide accurate information in their profiles; (b) complete all required verification processes; (c) perform duties as agreed with employers; and (d) comply with all applicable laws.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">7. Verification & Privacy</h2>
                        <p>The Platform conducts NIN verification through licensed third-party providers. Personal data is handled in accordance with Nigeria's Data Protection Act. Verification reports are shared only with authorized requesters.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">8. Limitation of Liability</h2>
                        <p>Maids.ng is a matching platform, not an employment agency. We do not employ domestic workers and are not a party to the employment relationship between employers and workers. We do not guarantee the quality, reliability, or conduct of any user on the Platform.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">9. Dispute Resolution</h2>
                        <p>Disputes between employers and workers should first be reported through the Platform's dispute system. Our Referee AI agent will review and recommend resolutions. Unresolved disputes may be escalated to our support team.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">10. Termination</h2>
                        <p>We reserve the right to suspend or terminate accounts that violate these terms, engage in fraudulent activity, or pose a safety risk to other users.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">11. Changes to Terms</h2>
                        <p>We may update these terms at any time. Users will be notified of material changes via email or Platform notification.</p>

                        <h2 className="font-display text-xl font-semibold text-teal">12. Contact</h2>
                        <p>For questions about these terms, contact us at hello@maids.ng.</p>
                    </div>
                </div>
            </section>

        </PublicLayout>
    );
}
