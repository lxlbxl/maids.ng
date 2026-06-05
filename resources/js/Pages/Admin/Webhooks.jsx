import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function Webhooks({ auth, initialWebhooks, statistics }) {
    const [webhooks, setWebhooks] = useState(initialWebhooks?.data || []);
    const [loading, setLoading] = useState(false);
    const [selectedWebhook, setSelectedWebhook] = useState(null);
    const [showForm, setShowForm] = useState(false);
    const [availableEvents, setAvailableEvents] = useState({});
    const [stats, setStats] = useState(statistics || {});
    const [filter, setFilter] = useState('all');
    const [search, setSearch] = useState('');

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        url: '',
        secret: '',
        events: [],
        active: true,
        verify_ssl: true,
        timeout_seconds: 30,
        max_retries: 3,
    });

    // Fetch webhooks
    const fetchWebhooks = async () => {
        setLoading(true);
        try {
            const res = await axios.get('/api/v1/admin/webhooks');
            setWebhooks(res.data.data?.data || []);
            if (res.data.data?.statistics) {
                setStats(res.data.data.statistics);
            }
        } catch (error) {
            console.error('Failed to fetch webhooks:', error);
        } finally {
            setLoading(false);
        }
    };

    // Fetch available events
    const fetchAvailableEvents = async () => {
        try {
            const res = await axios.get('/api/v1/admin/webhooks/events');
            setAvailableEvents(res.data.data || {});
        } catch (error) {
            console.error('Failed to fetch events:', error);
        }
    };

    useEffect(() => {
        fetchWebhooks();
        fetchAvailableEvents();
    }, []);

    // Open form for new webhook
    const openNewForm = () => {
        reset();
        setSelectedWebhook(null);
        setShowForm(true);
    };

    // Open form for editing
    const openEditForm = (webhook) => {
        setData({
            name: webhook.name || '',
            url: webhook.url || '',
            secret: '',
            events: webhook.events || [],
            active: webhook.active ?? true,
            verify_ssl: webhook.verify_ssl ?? true,
            timeout_seconds: webhook.timeout_seconds || 30,
            max_retries: webhook.max_retries || 3,
        });
        setSelectedWebhook(webhook);
        setShowForm(true);
    };

    // Submit form
    const handleSubmit = async (e) => {
        e.preventDefault();

        try {
            if (selectedWebhook) {
                const res = await axios.put(`/api/v1/admin/webhooks/${selectedWebhook.id}`, data);
                if (res.data.success) {
                    setShowForm(false);
                    fetchWebhooks();
                }
            } else {
                const res = await axios.post('/api/v1/admin/webhooks', data);
                if (res.data.success) {
                    setShowForm(false);
                    fetchWebhooks();
                }
            }
        } catch (error) {
            console.error('Failed to save webhook:', error);
            alert(error.response?.data?.message || 'Failed to save webhook');
        }
    };

    // Delete webhook
    const deleteWebhook = async (id) => {
        if (!confirm('Are you sure you want to delete this webhook?')) return;

        try {
            const res = await axios.delete(`/api/v1/admin/webhooks/${id}`);
            if (res.data.success) {
                fetchWebhooks();
            }
        } catch (error) {
            console.error('Failed to delete webhook:', error);
        }
    };

    // Test webhook
    const testWebhook = async (id) => {
        try {
            const res = await axios.post(`/api/v1/admin/webhooks/${id}/test`);
            if (res.data.success) {
                alert('Webhook test successful!');
            } else {
                alert('Webhook test failed: ' + (res.data.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Webhook test failed: ' + (error.response?.data?.message || error.message));
        }
    };

    // Toggle event selection
    const toggleEvent = (event) => {
        if (data.events.includes(event)) {
            setData('events', data.events.filter(e => e !== event));
        } else {
            setData('events', [...data.events, event]);
        }
    };

    // Filter webhooks
    const filteredWebhooks = webhooks.filter(webhook => {
        if (filter === 'active' && !webhook.active) return false;
        if (filter === 'inactive' && webhook.active) return false;
        if (search && !webhook.name.toLowerCase().includes(search.toLowerCase()) &&
            !webhook.url.toLowerCase().includes(search.toLowerCase())) return false;
        return true;
    });

    return (
        <AdminLayout>
            <Head title="Webhooks | Mission Control" />

            <div className="mb-10">
                <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Webhooks</h1>
                <p className="text-white/40 text-sm">Manage outgoing webhook integrations for real-time event notifications.</p>
            </div>

            {/* Statistics Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div className="bg-[#1a1a1f] border border-white/5 rounded-brand-xl p-6">
                    <p className="text-white/40 text-xs font-mono uppercase tracking-widest mb-2">Total Deliveries</p>
                    <p className="text-3xl font-display text-white">{stats.total_deliveries || 0}</p>
                </div>
                <div className="bg-[#1a1a1f] border border-white/5 rounded-brand-xl p-6">
                    <p className="text-white/40 text-xs font-mono uppercase tracking-widest mb-2">Successful</p>
                    <p className="text-3xl font-display text-teal">{stats.successful || 0}</p>
                </div>
                <div className="bg-[#1a1a1f] border border-white/5 rounded-brand-xl p-6">
                    <p className="text-white/40 text-xs font-mono uppercase tracking-widest mb-2">Failed</p>
                    <p className="text-3xl font-display text-red-400">{stats.failed || 0}</p>
                </div>
                <div className="bg-[#1a1a1f] border border-white/5 rounded-brand-xl p-6">
                    <p className="text-white/40 text-xs font-mono uppercase tracking-widest mb-2">Success Rate</p>
                    <p className="text-3xl font-display text-amber-400">{stats.success_rate || 0}%</p>
                </div>
            </div>

            {/* Filters and Actions */}
            <div className="flex flex-col md:flex-row gap-4 mb-6">
                <div className="flex-1 flex gap-2">
                    <button
                        onClick={() => setFilter('all')}
                        className={`px-4 py-2 rounded-brand-lg text-sm font-mono uppercase tracking-wider transition-all ${filter === 'all'
                                ? 'bg-teal text-black'
                                : 'bg-white/5 text-white/60 hover:bg-white/10'
                            }`}
                    >
                        All
                    </button>
                    <button
                        onClick={() => setFilter('active')}
                        className={`px-4 py-2 rounded-brand-lg text-sm font-mono uppercase tracking-wider transition-all ${filter === 'active'
                                ? 'bg-teal text-black'
                                : 'bg-white/5 text-white/60 hover:bg-white/10'
                            }`}
                    >
                        Active
                    </button>
                    <button
                        onClick={() => setFilter('inactive')}
                        className={`px-4 py-2 rounded-brand-lg text-sm font-mono uppercase tracking-wider transition-all ${filter === 'inactive'
                                ? 'bg-teal text-black'
                                : 'bg-white/5 text-white/60 hover:bg-white/10'
                            }`}
                    >
                        Inactive
                    </button>
                </div>

                <div className="flex gap-2">
                    <input
                        type="text"
                        placeholder="Search webhooks..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-2 text-white text-sm focus:border-teal/50 outline-none"
                    />
                    <button
                        onClick={openNewForm}
                        className="px-6 py-2 bg-teal text-black font-mono text-xs uppercase tracking-widest font-bold rounded-brand-lg hover:bg-teal-light transition-all"
                    >
                        + New Webhook
                    </button>
                </div>
            </div>

            {/* Webhooks List */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden">
                {loading ? (
                    <div className="p-8 text-center text-white/40">Loading webhooks...</div>
                ) : filteredWebhooks.length === 0 ? (
                    <div className="p-8 text-center text-white/40">
                        No webhooks found. Create one to get started.
                    </div>
                ) : (
                    <table className="w-full">
                        <thead className="bg-white/5 border-b border-white/5">
                            <tr>
                                <th className="text-left p-4 text-white/40 font-mono text-xs uppercase tracking-widest">Name</th>
                                <th className="text-left p-4 text-white/40 font-mono text-xs uppercase tracking-widest">URL</th>
                                <th className="text-left p-4 text-white/40 font-mono text-xs uppercase tracking-widest">Events</th>
                                <th className="text-left p-4 text-white/40 font-mono text-xs uppercase tracking-widest">Status</th>
                                <th className="text-left p-4 text-white/40 font-mono text-xs uppercase tracking-widest">Last Triggered</th>
                                <th className="text-right p-4 text-white/40 font-mono text-xs uppercase tracking-widest">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredWebhooks.map((webhook) => (
                                <tr key={webhook.id} className="border-b border-white/5 hover:bg-white/5 transition-colors">
                                    <td className="p-4">
                                        <div className="flex items-center gap-3">
                                            <div className={`w-2 h-2 rounded-full ${webhook.active ? 'bg-teal' : 'bg-white/20'}`} />
                                            <span className="text-white font-medium">{webhook.name}</span>
                                        </div>
                                    </td>
                                    <td className="p-4">
                                        <span className="text-white/60 text-sm truncate max-w-xs block">{webhook.url}</span>
                                    </td>
                                    <td className="p-4">
                                        <div className="flex flex-wrap gap-1">
                                            {webhook.events?.slice(0, 3).map(event => (
                                                <span key={event} className="text-[10px] font-mono uppercase tracking-wider bg-white/10 text-white/60 px-2 py-1 rounded">
                                                    {event.split('.').pop()}
                                                </span>
                                            ))}
                                            {webhook.events?.length > 3 && (
                                                <span className="text-[10px] font-mono uppercase tracking-wider bg-white/10 text-white/60 px-2 py-1 rounded">
                                                    +{webhook.events.length - 3}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="p-4">
                                        <span className={`text-xs font-mono uppercase tracking-wider px-2 py-1 rounded ${webhook.active
                                                ? 'bg-teal/10 text-teal'
                                                : 'bg-white/10 text-white/40'
                                            }`}>
                                            {webhook.active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="p-4 text-white/40 text-sm">
                                        {webhook.last_triggered_at
                                            ? new Date(webhook.last_triggered_at).toLocaleString()
                                            : 'Never'}
                                    </td>
                                    <td className="p-4 text-right">
                                        <div className="flex justify-end gap-2">
                                            <button
                                                onClick={() => testWebhook(webhook.id)}
                                                className="text-[10px] font-mono uppercase tracking-wider text-amber-400 hover:text-amber-300 transition-colors"
                                            >
                                                Test
                                            </button>
                                            <button
                                                onClick={() => openEditForm(webhook)}
                                                className="text-[10px] font-mono uppercase tracking-wider text-teal hover:text-teal-light transition-colors"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() => deleteWebhook(webhook.id)}
                                                className="text-[10px] font-mono uppercase tracking-wider text-red-400 hover:text-red-300 transition-colors"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {/* Form Modal */}
            {showForm && (
                <div className="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4">
                    <div className="bg-[#121214] border border-white/10 rounded-brand-xl p-8 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="text-2xl font-display text-white">
                                {selectedWebhook ? 'Edit Webhook' : 'Create Webhook'}
                            </h2>
                            <button
                                onClick={() => setShowForm(false)}
                                className="text-white/40 hover:text-white transition-colors"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <label className="block text-[10px] font-mono uppercase tracking-widest text-white/40">Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-white focus:border-teal/50 outline-none"
                                    placeholder="My Webhook"
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <label className="block text-[10px] font-mono uppercase tracking-widest text-white/40">URL</label>
                                <input
                                    type="url"
                                    value={data.url}
                                    onChange={(e) => setData('url', e.target.value)}
                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-white focus:border-teal/50 outline-none"
                                    placeholder="https://example.com/webhook"
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <label className="block text-[10px] font-mono uppercase tracking-widest text-white/40">Secret (for signature verification)</label>
                                <input
                                    type="text"
                                    value={data.secret}
                                    onChange={(e) => setData('secret', e.target.value)}
                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-white focus:border-teal/50 outline-none"
                                    placeholder={selectedWebhook ? 'Leave blank to keep current' : 'Generate random if empty'}
                                />
                            </div>

                            <div className="space-y-2">
                                <label className="block text-[10px] font-mono uppercase tracking-widest text-white/40 mb-2">Events</label>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-48 overflow-y-auto p-2 bg-[#0a0a0b] border border-white/10 rounded-brand-lg">
                                    {Object.entries(availableEvents).map(([key, label]) => (
                                        <label key={key} className="flex items-center gap-2 cursor-pointer hover:bg-white/5 p-2 rounded">
                                            <input
                                                type="checkbox"
                                                checked={data.events.includes(key)}
                                                onChange={() => toggleEvent(key)}
                                                className="w-4 h-4 rounded border-white/20 text-teal focus:ring-teal/20 bg-[#1a1a1f]"
                                            />
                                            <span className="text-white/80 text-sm">{label}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label className="block text-[10px] font-mono uppercase tracking-widest text-white/40">Timeout (seconds)</label>
                                    <input
                                        type="number"
                                        value={data.timeout_seconds}
                                        onChange={(e) => setData('timeout_seconds', parseInt(e.target.value))}
                                        min="5"
                                        max="120"
                                        className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-white focus:border-teal/50 outline-none"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <label className="block text-[10px] font-mono uppercase tracking-widest text-white/40">Max Retries</label>
                                    <input
                                        type="number"
                                        value={data.max_retries}
                                        onChange={(e) => setData('max_retries', parseInt(e.target.value))}
                                        min="1"
                                        max="10"
                                        className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-white focus:border-teal/50 outline-none"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.active}
                                        onChange={(e) => setData('active', e.target.checked)}
                                        className="w-4 h-4 rounded border-white/20 text-teal focus:ring-teal/20 bg-[#1a1a1f]"
                                    />
                                    <span className="text-white/80 text-sm">Active</span>
                                </label>

                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.verify_ssl}
                                        onChange={(e) => setData('verify_ssl', e.target.checked)}
                                        className="w-4 h-4 rounded border-white/20 text-teal focus:ring-teal/20 bg-[#1a1a1f]"
                                    />
                                    <span className="text-white/80 text-sm">Verify SSL</span>
                                </label>
                            </div>

                            <div className="flex justify-end gap-3 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setShowForm(false)}
                                    className="px-6 py-3 bg-white/5 text-white/60 font-mono text-xs uppercase tracking-widest rounded-brand-lg hover:bg-white/10 transition-all"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-6 py-3 bg-teal text-black font-mono text-xs uppercase tracking-widest font-bold rounded-brand-lg hover:bg-teal-light transition-all disabled:opacity-50"
                                >
                                    {processing ? 'Saving...' : (selectedWebhook ? 'Update' : 'Create')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}