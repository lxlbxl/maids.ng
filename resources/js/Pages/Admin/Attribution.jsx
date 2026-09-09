import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

const naira = (n) => '₦' + Number(n || 0).toLocaleString('en-NG');
const CHANNEL_LABEL = {
    google_ads: 'Google Ads',
    organic_search: 'Organic search',
    meta_paid: 'Meta ads',
    meta_organic: 'FB / IG (organic)',
    meta_ctwa: 'Meta CTWA ad',
    paid_other: 'Paid (other)',
    email: 'Email',
    referral: 'Referral',
    whatsapp_direct: 'WhatsApp direct',
    other: 'Other',
    direct: 'Direct',
    unknown: 'Unknown',
};

function Table({ title, rows, keyLabel }) {
    return (
        <div className="rounded-xl border border-white/10 bg-white/[0.02] overflow-hidden">
            <div className="px-4 py-3 border-b border-white/10 text-sm font-semibold text-white/90">{title}</div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="text-white/50 text-xs uppercase tracking-wide">
                        <tr>
                            <th className="text-left font-medium px-4 py-2">{keyLabel}</th>
                            <th className="text-right font-medium px-4 py-2">Leads</th>
                            <th className="text-right font-medium px-4 py-2">Paid</th>
                            <th className="text-right font-medium px-4 py-2">Revenue</th>
                            <th className="text-right font-medium px-4 py-2">Conv.</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td colSpan={5} className="px-4 py-6 text-center text-white/40">No data in this range</td></tr>
                        )}
                        {rows.map((r) => (
                            <tr key={r.key} className="border-t border-white/5">
                                <td className="px-4 py-2 text-white/90">{keyLabel === 'Channel' ? (CHANNEL_LABEL[r.key] || r.key) : (r.key || '—')}</td>
                                <td className="px-4 py-2 text-right text-white/70">{r.leads.toLocaleString()}</td>
                                <td className="px-4 py-2 text-right text-white/70">{r.paid.toLocaleString()}</td>
                                <td className="px-4 py-2 text-right text-teal font-medium">{naira(r.revenue)}</td>
                                <td className="px-4 py-2 text-right text-white/50">{r.leads ? Math.round((r.paid / r.leads) * 100) + '%' : '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function Attribution({ by_channel = [], by_campaign = [], by_source = [], totals = {}, unattributed = 0, filters = {}, posthog_embed_url }) {
    const [range, setRange] = useState({ from: filters.from || '', to: filters.to || '' });
    const apply = () => router.get('/admin/attribution', range, { preserveState: true, replace: true });

    return (
        <AdminLayout>
            <Head title="Attribution | Mission Control" />
            <div className="p-6 space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-white">Traffic attribution</h1>
                        <p className="text-sm text-white/50">Where leads and matching-fee revenue come from.</p>
                    </div>
                    <div className="flex items-end gap-2">
                        <label className="text-xs text-white/50">From
                            <input type="date" value={range.from} onChange={(e) => setRange((s) => ({ ...s, from: e.target.value }))}
                                className="block mt-1 bg-white/5 border border-white/10 rounded px-2 py-1 text-sm text-white" />
                        </label>
                        <label className="text-xs text-white/50">To
                            <input type="date" value={range.to} onChange={(e) => setRange((s) => ({ ...s, to: e.target.value }))}
                                className="block mt-1 bg-white/5 border border-white/10 rounded px-2 py-1 text-sm text-white" />
                        </label>
                        <button onClick={apply} className="px-3 py-1.5 rounded bg-teal text-white text-sm font-medium">Apply</button>
                        <a href={`/admin/attribution?export=csv&from=${range.from}&to=${range.to}`}
                            className="px-3 py-1.5 rounded border border-white/15 text-white/80 text-sm">CSV</a>
                    </div>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <Stat label="Leads" value={(totals.leads || 0).toLocaleString()} />
                    <Stat label="Paid" value={(totals.paid || 0).toLocaleString()} />
                    <Stat label="Revenue" value={naira(totals.revenue)} accent />
                    <Stat label="Unattributed leads" value={unattributed.toLocaleString()} />
                </div>

                <div className="grid lg:grid-cols-2 gap-4">
                    <Table title="By channel" rows={by_channel} keyLabel="Channel" />
                    <Table title="By campaign" rows={by_campaign} keyLabel="Campaign" />
                </div>
                <Table title="By site source (button)" rows={by_source} keyLabel="Source" />

                {posthog_embed_url && (
                    <div className="rounded-xl border border-white/10 overflow-hidden">
                        <div className="px-4 py-3 border-b border-white/10 text-sm font-semibold text-white/90">
                            PostHog — funnels &amp; trends
                        </div>
                        <iframe src={posthog_embed_url} title="PostHog attribution dashboard"
                            className="w-full" style={{ height: 900, border: 0, background: '#fff' }} />
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

function Stat({ label, value, accent }) {
    return (
        <div className="rounded-xl border border-white/10 bg-white/[0.02] px-4 py-3">
            <div className="text-xs text-white/50">{label}</div>
            <div className={`text-lg font-semibold ${accent ? 'text-teal' : 'text-white'}`}>{value}</div>
        </div>
    );
}
