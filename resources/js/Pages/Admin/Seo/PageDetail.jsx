import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function PageDetail({ page }) {
    return (
        <AdminLayout>
            <Head title={`SEO Page: ${page.h1}`} />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-espresso">Page Details</h1>
                    <Link href={route('admin.seo.pages')} className="text-teal text-sm hover:underline">&larr; Back to Pages</Link>
                </div>

                <div className="grid md:grid-cols-3 gap-6">
                    <div className="md:col-span-2 space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold mb-4">Page Info</h2>
                            <dl className="space-y-3 text-sm">
                                <div><dt className="text-muted">H1</dt><dd className="font-medium">{page.h1}</dd></div>
                                <div><dt className="text-muted">URL</dt><dd className="font-mono text-xs">{page.url_path}</dd></div>
                                <div><dt className="text-muted">Meta Title</dt><dd>{page.meta_title}</dd></div>
                                <div><dt className="text-muted">Meta Description</dt><dd>{page.meta_description}</dd></div>
                                <div><dt className="text-muted">Type</dt><dd>{page.page_type}</dd></div>
                                <div><dt className="text-muted">Status</dt><dd><span className={`px-2 py-1 rounded text-xs font-medium ${page.page_status === 'published' ? 'bg-green-100 text-green-800' : page.page_status === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'}`}>{page.page_status}</span></dd></div>
                                <div><dt className="text-muted">Content Score</dt><dd>{page.content_score}/100</dd></div>
                            </dl>
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold mb-4">Content Blocks</h2>
                            <pre className="bg-gray-50 p-4 rounded-lg text-xs overflow-auto max-h-96">
                                {JSON.stringify(page.content_blocks, null, 2)}
                            </pre>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold mb-4">Actions</h2>
                            <button
                                onClick={() => router.post(route('admin.seo.page.regenerate', page.id))}
                                className="w-full bg-teal text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-teal-dark mb-3"
                            >
                                Regenerate Content
                            </button>
                            <a href={page.url_path} target="_blank" className="w-full block text-center border border-teal text-teal px-4 py-2 rounded-lg text-sm font-medium hover:bg-teal-ghost">
                                View Page
                            </a>
                        </div>

                        {page.location && (
                            <div className="bg-white rounded-xl border border-gray-200 p-6">
                                <h2 className="text-lg font-semibold mb-4">Location</h2>
                                <p className="text-sm">{page.location.name} ({page.location.type})</p>
                                <p className="text-xs text-muted">Tier {page.location.tier}</p>
                            </div>
                        )}

                        {page.service && (
                            <div className="bg-white rounded-xl border border-gray-200 p-6">
                                <h2 className="text-lg font-semibold mb-4">Service</h2>
                                <p className="text-sm">{page.service.name}</p>
                                <p className="text-xs text-muted">Demand Index: {page.service.demand_index}</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
