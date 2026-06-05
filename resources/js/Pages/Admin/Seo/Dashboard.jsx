import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function SeoDashboard({ stats, pagesByType, recentPages }) {
    const statusColors = {
        published: 'bg-green-100 text-green-800',
        draft: 'bg-yellow-100 text-yellow-800',
        noindex: 'bg-gray-100 text-gray-800',
        redirected: 'bg-red-100 text-red-800',
    };

    return (
        <AdminLayout>
            <Head title="SEO Dashboard" />

            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-espresso">SEO Engine Dashboard</h1>
                        <p className="text-muted mt-1">Manage programmatic SEO pages, locations, and content.</p>
                    </div>
                    <div className="flex gap-3">
                        <button
                            onClick={() => router.post(route('admin.seo.bulk.generate'))}
                            className="bg-teal text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-teal-dark transition-colors"
                        >
                            Generate Page Registry
                        </button>
                        <button
                            onClick={() => router.post(route('admin.seo.bulk.refresh'))}
                            className="bg-copper text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-copper-light transition-colors"
                        >
                            Refresh Content
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div className="bg-white rounded-xl p-6 border border-gray-200">
                        <p className="text-sm text-muted">Total Pages</p>
                        <p className="text-3xl font-bold text-espresso">{stats.total_pages}</p>
                    </div>
                    <div className="bg-white rounded-xl p-6 border border-gray-200">
                        <p className="text-sm text-green-600">Published</p>
                        <p className="text-3xl font-bold text-green-600">{stats.published}</p>
                    </div>
                    <div className="bg-white rounded-xl p-6 border border-gray-200">
                        <p className="text-sm text-yellow-600">Draft</p>
                        <p className="text-3xl font-bold text-yellow-600">{stats.draft}</p>
                    </div>
                    <div className="bg-white rounded-xl p-6 border border-gray-200">
                        <p className="text-sm text-gray-600">NoIndex</p>
                        <p className="text-3xl font-bold text-gray-600">{stats.noindex}</p>
                    </div>
                </div>

                <div className="grid md:grid-cols-3 gap-4 mb-8">
                    <div className="bg-white rounded-xl p-6 border border-gray-200">
                        <p className="text-sm text-muted">Locations</p>
                        <p className="text-2xl font-bold text-espresso">{stats.total_locations}</p>
                        <Link href={route('admin.seo.locations')} className="text-teal text-sm mt-2 hover:underline">Manage locations &rarr;</Link>
                    </div>
                    <div className="bg-white rounded-xl p-6 border border-gray-200">
                        <p className="text-sm text-muted">Services</p>
                        <p className="text-2xl font-bold text-espresso">{stats.total_services}</p>
                        <Link href={route('admin.seo.services')} className="text-teal text-sm mt-2 hover:underline">Manage services &rarr;</Link>
                    </div>
                    <div className="bg-white rounded-xl p-6 border border-gray-200">
                        <p className="text-sm text-muted">FAQs</p>
                        <p className="text-2xl font-bold text-espresso">{stats.total_faqs}</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 mb-8">
                    <div className="p-6 border-b border-gray-200">
                        <h2 className="text-lg font-semibold text-espresso">Pages by Type</h2>
                    </div>
                    <div className="p-6">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-muted border-b">
                                    <th className="pb-3">Type</th>
                                    <th className="pb-3">Published</th>
                                    <th className="pb-3">Draft</th>
                                    <th className="pb-3">NoIndex</th>
                                    <th className="pb-3">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {Object.entries(pagesByType || {}).map(([type, counts]) => (
                                    <tr key={type} className="border-b border-gray-100">
                                        <td className="py-3 font-medium">{type}</td>
                                        <td className="py-3"><span className="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">{counts.published || 0}</span></td>
                                        <td className="py-3"><span className="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">{counts.draft || 0}</span></td>
                                        <td className="py-3"><span className="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">{counts.noindex || 0}</span></td>
                                        <td className="py-3 font-semibold">{(counts.published || 0) + (counts.draft || 0) + (counts.noindex || 0)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-espresso">Recent Pages</h2>
                        <Link href={route('admin.seo.pages')} className="text-teal text-sm hover:underline">View all &rarr;</Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-muted border-b">
                                    <th className="pb-3 px-6">Page</th>
                                    <th className="pb-3">Type</th>
                                    <th className="pb-3">Status</th>
                                    <th className="pb-3">Score</th>
                                    <th className="pb-3">Updated</th>
                                    <th className="pb-3 px-6">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentPages.map((page) => (
                                    <tr key={page.id} className="border-b border-gray-100 hover:bg-gray-50">
                                        <td className="py-3 px-6">
                                            <p className="font-medium truncate max-w-xs">{page.h1}</p>
                                            <p className="text-xs text-muted">{page.url_path}</p>
                                        </td>
                                        <td className="py-3">{page.page_type}</td>
                                        <td className="py-3">
                                            <span className={`px-2 py-1 rounded text-xs font-medium ${statusColors[page.page_status] || 'bg-gray-100'}`}>
                                                {page.page_status}
                                            </span>
                                        </td>
                                        <td className="py-3">{page.content_score}/100</td>
                                        <td className="py-3 text-muted">{new Date(page.updated_at).toLocaleDateString()}</td>
                                        <td className="py-3 px-6">
                                            <Link href={route('admin.seo.page.show', page.id)} className="text-teal text-xs hover:underline">View</Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
