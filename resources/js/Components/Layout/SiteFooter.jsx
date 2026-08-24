import { Link } from '@inertiajs/react';
import WhatsAppCTA from '@/Components/Hero/WhatsAppCTA';

/**
 * Shared site footer — one source of truth for every public page.
 * Carries the footer WhatsApp CTA (Directive 09 attribution position 5/5).
 */
export default function SiteFooter({ intent = 'hire' }) {
    return (
        <footer className="bg-espresso text-ivory/60 py-16 px-6 print:hidden">
            <div className="max-w-5xl mx-auto grid md:grid-cols-5 gap-10 text-sm">
                <div className="md:col-span-2">
                    <img src="/maids-logo.png" alt="Maids.ng" className="h-8 mb-4 brightness-0 invert" />
                    <p className="text-xs leading-relaxed max-w-xs mb-5">
                        Nigeria's most trusted platform connecting families with NIN-verified domestic helpers.
                    </p>
                    <WhatsAppCTA source="footer" intent={intent} size="sm" label="WhatsApp Us" glyphClass="w-4 h-4" />
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
                        <p className="mt-4">📞 0201 330 9202</p>
                        <p>✉️ hello@maids.ng</p>
                    </div>
                </div>
            </div>
            <div className="max-w-5xl mx-auto mt-12 pt-8 border-t border-white/10 text-xs text-center text-ivory/30">
                © {new Date().getFullYear()} Maids.ng. All rights reserved. · Nigeria's Most Trusted Home Help Platform
            </div>
        </footer>
    );
}
