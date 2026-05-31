import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';
import axios from 'axios';

const STATUS_COLORS = {
    2: 'text-teal bg-teal/10 border-teal/20',
    3: 'text-amber-400 bg-amber-400/10 border-amber-400/20',
    4: 'text-orange-400 bg-orange-400/10 border-orange-400/20',
    5: 'text-red-400 bg-red-400/10 border-red-400/20',
};

function statusColor(code) {
    const bucket = Math.floor(code / 100);
    return STATUS_COLORS[bucket] || 'text-white/40 bg-white/5 border-white/10';
}

export default function AuditLog({ auth, logs, filters = {}, retentionDays = 90 }) {
    const [form, setForm] = useState({ ...filters });
    const [purging, setPurging] = useState(false);
    const [retention, setRetention] = useState(retentionDays);
    const [retentionSaving, setRetentionSaving] = useState(false);
    const [expandedId, setExpandedId] = useState(null);

    const applyFilters = (e) => {
        e.preventDefault();
        router.get(route('admin.audit'), form, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setForm({});
        router.get(route('admin.audit'), {}, { replace: true });
    };

    const handlePurge = async () => {
        if (!confirm('Permanently delete ALL audit log entries? This cannot be undone.')) return;
        setPurging(true);
        try {
            await axios.delete(route('admin.audit.purge'));
            router.reload({ only: ['logs'] });
        } finally {
            setPurging(false);
        }
    };

    const saveRetention = async () => {
        setRetentionSaving(true);
        try {
            await axios.post(route('admin.audit.retention'), { days: retention });
        } finally {
            setRetentionSaving(false);
        }
    };

    return (
        <AdminLayout>
            <Head title="API Audit Trail | Mission Control" />

            {/* Header */}
            <div className="mb-10 flex flex-col md:flex-row md:items-start justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">API Audit Trail</h1>
                    <p className="text-white/40 text-sm font-light">Full incident-traceable log of all API requests and responses.</p>
                </div>
                <div className="flex items-center gap-3 flex-shrink-0">
                    {/* Retention control */}
                    <div className="flex items-center gap-2 bg-white/5 border border-white/10 rounded-brand-lg px-4 py-2">
                        <span className="text-[10px] font-mono uppercase tracking-widest text-white/30">Retention</span>
                        <input
                            type="number"
                            min="1"
                            max="3650"
                            value={retention}
                            onChange={e => setRetention(Number(e.target.value))}
                            className="w-16 bg-transparent border-none outline-none text-white text-sm font-mono text-center"
                        />
                        <span className="text-[10px] font-mono uppercase tracking-widest text-white/30">days</span>
                        <button
                            onClick={saveRetention}
                            disabled={retentionSaving}
                            className="text-[10px] font-mono uppercase tracking-widest text-teal hover:brightness-110 disabled:opacity-40 ml-1"
                        >
                            {retentionSaving ? 'Saving…' : 'Save'}
                        </button>
                    </div>
                    <button
                        onClick={handlePurge}
                        disabled={purging}
                        className="text-[10px] font-mono uppercase tracking-widest px-5 py-3 bg-red-400/10 border border-red-400/20 text-red-400 rounded-brand-lg hover:bg-red-400/20 disabled:opacity-40 transition-all"
                    >
                        {purging ? 'Purging…' : 'Purge All'}
                    </button>
                </div>
            </div>

            {/* Filters */}
            <form onSubmit={applyFilters} className="bg-[#121214] border border-white/5 rounded-brand-xl p-6 mb-8">
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    {[
                        { key: 'method', placeholder: 'Method (GET, POST…)' },
                        { key: 'endpoint', placeholder: 'Endpoint contains…' },
                        { key: 'status', placeholder: 'Status code (200…)' },
                        { key: 'user_id', placeholder: 'User ID' },
                        { key: 'from', placeholder: 'From date (YYYY-MM-DD)', type: 'date' },
                        { key: 'to', placeholder: 'To date (YYYY-MM-DD)', type: 'date' },
                    ].map(({ key, placeholder, type = 'text' }) => (
                        <input
                            key={key}
                            type={type}
                            value={form[key] || ''}
                            onChange={e => setForm(f => ({ ...f, [key]: e.target.value }))}
                            placeholder={placeholder}
                            className="bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-white text-xs focus:border-teal/50 outline-none placeholder-white/20 font-mono"
                        />
                    ))}
                </div>
                <div className="flex gap-3 mt-4">
                    <button type="submit" className="px-6 py-2 bg-teal text-black font-mono text-[10px] uppercase tracking-widest font-bold rounded-brand-lg hover:brightness-110 transition-all">
                        Apply Filters
                    </button>
                    <button type="button" onClick={clearFilters} className="px-6 py-2 bg-white/5 border border-white/10 text-white/50 font-mono text-[10px] uppercase tracking-widest rounded-brand-lg hover:bg-white/10 transition-all">
                        Clear
                    </button>
                </div>
            </form>

            {/* Log Table */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse">
                        <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                            <tr>
                                <th className="px-6 py-4">Status</th>
                                <th className="px-6 py-4">Method</th>
                                <th className="px-6 py-4">Endpoint</th>
                                <th className="px-6 py-4">User</th>
                                <th className="px-6 py-4 text-right">Timestamp</th>
                                <th className="px-6 py-4 text-center">Detail</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {logs?.data?.length > 0 ? logs.data.map(log => (
                                <>
                                    <tr key={log.id} className="hover:bg-white/[0.02] transition-colors">
                                        <td className="px-6 py-4">
                                            <span className={`text-[10px] font-mono font-bold uppercase px-2 py-0.5 rounded border ${statusColor(log.response_status)}`}>
                                                {log.response_status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="text-[10px] font-mono uppercase tracking-widest text-white/70 bg-white/5 px-2 py-1 rounded">
                                                {log.method}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 font-mono text-xs text-white/60 max-w-xs truncate">{log.endpoint}</td>
                                        <td className="px-6 py-4 font-mono text-xs text-white/40">{log.user_id ? `#${log.user_id}` : '—'}</td>
                                        <td className="px-6 py-4 text-right font-mono text-[10px] text-white/20 leading-tight">
                                            {new Date(log.created_at).toLocaleDateString()}<br />
                                            {new Date(log.created_at).toLocaleTimeString()}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <button
                                                onClick={() => setExpandedId(expandedId === log.id ? null : log.id)}
                                                className="text-[10px] font-mono uppercase tracking-widest text-teal/60 hover:text-teal transition-colors"
                                            >
                                                {expandedId === log.id ? 'Collapse' : 'Expand'}
                                            </button>
                                        </td>
                                    </tr>
                                    {expandedId === log.id && (
                                        <tr key={`${log.id}-detail`} className="bg-[#0a0a0b]">
                                            <td colSpan="6" className="px-6 py-4">
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 mb-2">Request Body</p>
                                                        <pre className="bg-black/40 border border-white/5 rounded p-3 text-xs text-teal/70 font-mono overflow-x-auto max-h-48 whitespace-pre-wrap break-all">
                                                            {JSON.stringify(log.request_body, null, 2) || '—'}
                                                        </pre>
                                                    </div>
                                                    <div>
                                                        <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 mb-2">Response Body</p>
                                                        <pre className="bg-black/40 border border-white/5 rounded p-3 text-xs text-amber-400/70 font-mono overflow-x-auto max-h-48 whitespace-pre-wrap break-all">
                                                            {JSON.stringify(log.response_body, null, 2) || '—'}
                                                        </pre>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </>
                            )) : (
                                <tr>
                                    <td colSpan="6" className="px-8 py-16 text-center text-white/30">
                                        <div className="text-3xl mb-3">🔍</div>
                                        <p className="text-sm">No audit log entries found.</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {logs?.links?.length > 3 && (
                <div className="mt-8 flex justify-center gap-1">
                    {logs.links.map((link, k) => (
                        <Link
                            key={k}
                            href={link.url || '#'}
                            className={`px-4 py-2 font-mono text-[10px] uppercase tracking-widest rounded-brand-md border transition-all ${link.active ? 'bg-teal text-white border-teal shadow-[0_0_15px_rgba(45,164,142,0.3)]' : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10'} ${!link.url ? 'opacity-30 cursor-not-allowed hidden' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
