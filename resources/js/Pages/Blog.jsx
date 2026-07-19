import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function Blog() {
    const posts = [
        {
            id: 1,
            title: 'How Much Does a Housekeeper Cost in Lagos in 2025?',
            excerpt: 'A comprehensive breakdown of housekeeper salaries across Lagos neighbourhoods — from Lekki to Ikeja. What factors determine pay and how to set fair compensation.',
            category: 'Pricing Guide',
            date: 'May 2025',
            readTime: '6 min read',
            slug: 'housekeeper-cost-lagos-2025',
        },
        {
            id: 2,
            title: 'The Complete Guide to NIN Verification for Domestic Workers',
            excerpt: 'Why NIN verification matters, how it works, and what every employer should check before hiring. Plus, how Maids.ng automates this process.',
            category: 'Safety',
            date: 'April 2025',
            readTime: '8 min read',
            slug: 'nin-verification-guide',
        },
        {
            id: 3,
            title: 'Live-In vs Live-Out: Which Is Right for Your Family?',
            excerpt: 'Pros and cons of both arrangements, salary differences, legal considerations, and how to decide based on your household needs.',
            category: 'Hiring Guide',
            date: 'April 2025',
            readTime: '5 min read',
            slug: 'live-in-vs-live-out',
        },
        {
            id: 4,
            title: '5 Red Flags When Hiring a Nanny in Nigeria',
            excerpt: 'Warning signs to watch out for during interviews and reference checks. Protect your family with these essential screening tips.',
            category: 'Safety',
            date: 'March 2025',
            readTime: '4 min read',
            slug: 'nanny-hiring-red-flags',
        },
        {
            id: 5,
            title: 'How AI Is Changing Domestic Staff Hiring in Nigeria',
            excerpt: 'Behind the scenes at Maids.ng — how our multi-agent system matches employers with compatible helpers using 12+ scoring factors.',
            category: 'Technology',
            date: 'March 2025',
            readTime: '7 min read',
            slug: 'ai-domestic-staff-nigeria',
        },
        {
            id: 6,
            title: 'Understanding Domestic Worker Rights in Nigeria',
            excerpt: 'A plain-language guide to the Labour Act as it relates to domestic workers. Minimum standards, rest days, and fair treatment.',
            category: 'Legal',
            date: 'February 2025',
            readTime: '10 min read',
            slug: 'domestic-worker-rights-nigeria',
        },
    ];

    return (
        <PublicLayout>
            <Head title="Blog — Maids.ng" />

            <section className="min-h-screen pt-12 pb-20 px-6 bg-ivory">
                <div className="max-w-5xl mx-auto">
                    <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-teal mb-3">Blog</p>
                    <h1 className="font-display text-4xl md:text-5xl font-light text-espresso mb-4">
                        Insights & <em className="italic text-teal">Guides</em>
                    </h1>
                    <p className="text-muted text-lg mb-12 max-w-xl">
                        Expert advice on hiring, managing, and understanding domestic staff in Nigeria.
                    </p>

                    <div className="grid md:grid-cols-2 gap-6">
                        {posts.map((post) => (
                            <article key={post.id} className="bg-white rounded-brand-xl border border-gray-200 shadow-brand-1 hover:shadow-brand-2 hover:-translate-y-1 transition-all duration-300 overflow-hidden group">
                                <div className="p-6">
                                    <div className="flex items-center gap-3 mb-3">
                                        <span className="px-2.5 py-0.5 bg-teal-ghost text-teal text-[10px] font-mono uppercase tracking-wider rounded-full">{post.category}</span>
                                        <span className="text-xs text-muted">{post.readTime}</span>
                                    </div>
                                    <h2 className="font-display text-xl font-semibold text-espresso mb-3 group-hover:text-teal transition-colors">
                                        {post.title}
                                    </h2>
                                    <p className="text-muted text-sm leading-relaxed mb-4">{post.excerpt}</p>
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs text-muted">{post.date}</span>
                                        <a href={`/blog/${post.slug}`} className="text-teal text-sm font-medium hover:text-teal-dark transition-colors">
                                            Read more →
                                        </a>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>

                    <div className="mt-12 p-8 bg-espresso rounded-brand-2xl text-center">
                        <h3 className="font-display text-2xl text-white mb-3">Need Help Right Now?</h3>
                        <p className="text-ivory/60 mb-6">Find verified domestic staff in under 5 minutes.</p>
                        <Link href="/onboarding" className="inline-block bg-teal text-white px-8 py-4 rounded-brand-md text-base font-medium hover:bg-teal-dark transition-all hover:scale-[1.02] shadow-brand-2">
                            Start Free →
                        </Link>
                    </div>
                </div>
            </section>

        </PublicLayout>
    );
}
