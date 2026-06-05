import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function SeoPages({ pages, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [type, setType] = useState(filters.type || '');
    const [status, setStatus] = useState(filters.status || '');

    const handleFilter = () => {
        router.get(route('admin.seo.pages'), { search, type, status }, { preserveState: true });
    };

    const statusColors = {
        published: 'bg-green-100 text-green-800',
        draft: 'bg-yellow-100 text-yellow-800',
        noindex: 'bg-gray-100 text-gray-800',
        redirected: 'bg-red-100 text-red-800',
    };

    return (
        <AdminLayout>
            <Head title="SEO Pages" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-espresso">SEO Pages</h1>
                    <Link href={route('admin.seo.dashboard')} className="text-teal text-sm hover:underline">&larr; Back to Dashboard</Link>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex gap-4 flex-wrap">
                    <input
                        type="text"
                        placeholder="Search pages..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="px-4 py-2 border border-gray-300 rounded-lg text-sm flex-1 min-w-48"
                    />
                    <select value={type} onChange={(e) => setType(e.target.value)} className="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">All Types</option>
                        <option value="service_area">Service × Area</option>
                        <option value="service_city">Service × City</option>
                        <option value="service_hub">Service Hub</option>
                        <option value="location_city">Location City</option>
                        <option value="location_area">Location Area</option>
                        <option value="price_guide">Price Guide</option>
                        <option value="hire_guide">Hire Guide</option>
                        <option value="salary_guide">Salary Guide</option>
                    </select>
                    <select value={status} onChange={(e) => setStatus(e.target.value)} className="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">All Status</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="noindex">NoIndex</option>
                    </select>
                    <button onClick={handleFilter} className="bg-teal text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-teal-dark">Filter</button>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-muted border-b bg-gray-50">
                                <th className="pb-3 px-6">Page</th>
                                <th className="pb-3">Type</th>
                                <th className="pb-3">Status</th>
                                <th className="pb-3">Score</th>
                                <th className="pb-3">Updated</th>
                                <th className="pb-3 px-6">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pages.data.map((page) => (
                                <tr key={page.id} className="border-b border-gray-100 hover:bg-gray-50">
                                    <td className="py-3 px-6">
                                        <p className="font-medium truncate max-w-xs">{page.h1}</p>
                                        <p className="text-xs text-muted">{page.url_path}</p>
                                    </td>
                                    <td className="py-3">{page.page_type}</td>
                                    <td className="py-3">
                                        <span className={`px-2 py-1 rounded text-xs font-medium ${statusColors[page.page_status] || 'bg-gray-100'}`}>{page.page_status}</span>
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
                    <div className="p-4 flex justify-between items-center text-sm text-muted">
                        <p>Showing {pages.data.length} of {pages.total} pages</p>
                        <div className="flex gap-2">
                            {pages.links.map((link, i) => (
                                link.url && (
                                    <button key={i} onClick={() => router.get(link.url)} className={`px-3 py-1 rounded ${link.active ? 'bg-teal text-white' : 'border hover:bg-gray-100'}`}>
                                        {link.label}
                                    </button>
                                )
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
