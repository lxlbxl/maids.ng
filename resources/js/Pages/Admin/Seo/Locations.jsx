import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function SeoLocations({ locations }) {
    const tierColors = { 1: 'bg-green-100 text-green-800', 2: 'bg-blue-100 text-blue-800', 3: 'bg-gray-100 text-gray-800' };

    return (
        <AdminLayout>
            <Head title="SEO Locations" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-espresso">SEO Locations</h1>
                    <Link href={route('admin.seo.dashboard')} className="text-teal text-sm hover:underline">&larr; Back to Dashboard</Link>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-muted border-b bg-gray-50">
                                <th className="pb-3 px-6">Name</th>
                                <th className="pb-3">Type</th>
                                <th className="pb-3">Tier</th>
                                <th className="pb-3">Parent</th>
                                <th className="pb-3">State</th>
                                <th className="pb-3 px-6">Slug</th>
                            </tr>
                        </thead>
                        <tbody>
                            {locations.map((loc) => (
                                <tr key={loc.id} className="border-b border-gray-100 hover:bg-gray-50">
                                    <td className="py-3 px-6 font-medium">{loc.name}</td>
                                    <td className="py-3">{loc.type}</td>
                                    <td className="py-3"><span className={`px-2 py-1 rounded text-xs font-medium ${tierColors[loc.tier] || 'bg-gray-100'}`}>Tier {loc.tier}</span></td>
                                    <td className="py-3">{loc.parent?.name || '—'}</td>
                                    <td className="py-3">{loc.state || '—'}</td>
                                    <td className="py-3 px-6 font-mono text-xs">{loc.slug}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
