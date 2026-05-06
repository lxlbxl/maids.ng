import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function SeoServices({ services }) {
    return (
        <AdminLayout>
            <Head title="SEO Services" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-espresso">SEO Services</h1>
                    <Link href={route('admin.seo.dashboard')} className="text-teal text-sm hover:underline">&larr; Back to Dashboard</Link>
                </div>

                <div className="grid md:grid-cols-2 gap-6">
                    {services.map((svc) => (
                        <div key={svc.id} className="bg-white rounded-xl border border-gray-200 p-6">
                            <div className="flex items-start justify-between mb-3">
                                <h3 className="text-lg font-semibold text-espresso">{svc.name}</h3>
                                <span className="px-2 py-1 bg-teal-ghost text-teal text-xs rounded-full font-medium">Demand: {svc.demand_index}%</span>
                            </div>
                            <p className="text-sm text-muted mb-4">{svc.short_description}</p>
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between"><dt className="text-muted">Salary Range</dt><dd>&#8358;{Number(svc.salary_min).toLocaleString()} - &#8358;{Number(svc.salary_max).toLocaleString()}/mo</dd></div>
                                <div className="flex justify-between"><dt className="text-muted">Live-in</dt><dd>{svc.live_in_available ? 'Yes' : 'No'}</dd></div>
                                <div className="flex justify-between"><dt className="text-muted">Part-time</dt><dd>{svc.part_time_available ? 'Yes' : 'No'}</dd></div>
                                <div className="flex justify-between"><dt className="text-muted">NIN Required</dt><dd>{svc.nin_required ? 'Yes' : 'No'}</dd></div>
                                <div className="flex justify-between"><dt className="text-muted">Also Known As</dt><dd className="text-xs">{(svc.also_known_as || []).join(', ')}</dd></div>
                            </dl>
                        </div>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
