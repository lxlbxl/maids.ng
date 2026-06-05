import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState, useCallback } from 'react';

const AGENT_META = {
    ambassador: { icon: '🤝', label: 'Ambassador', color: 'teal', desc: 'Front-facing SDR & support. Handles WhatsApp, Meta DM, Email, Web Chat inbound.' },
    scout:      { icon: '🔭', label: 'Scout',       color: 'blue', desc: 'Searches for matching maids against employer preferences.' },
    gatekeeper: { icon: '🛡️', label: 'Gatekeeper',  color: 'copper', desc: 'Verifies maid identities, NIN checks, and profile validation.' },
    sentinel:   { icon: '⚡', label: 'Sentinel',    color: 'teal', desc: 'Monitors platform health, flags anomalies, and triggers alerts.' },
    concierge:  { icon: '🎩', label: 'Concierge',   color: 'purple', desc: 'Assists users post-match, manages onboarding flows.' },
    referee:    { icon: '⚖️', label: 'Referee',     color: 'copper', desc: 'Mediates disputes and escalates to human admins.' },
    treasurer:  { icon: '💰', label: 'Treasurer',   color: 'green', desc: 'Manages payouts, escrow, salary schedules, and financial audits.' },
};

const CHANNEL_META = {
    whatsapp: { icon: '💬', label: 'WhatsApp',   color: 'green' },
    meta_dm:  { icon: '📘', label: 'Meta DM',    color: 'blue' },
    email:    { icon: '✉️', label: 'Email',       color: 'copper' },
    web:      { icon: '🌐', label: 'Web Chat',   color: 'teal' },
};

export default function Agents({ stats, conversations, leads, agentSettings }) {
    const [activeTab, setActiveTab] = useState('overview');
    const [selectedAgent, setSelectedAgent] = useState(null);
    const [topping, setTopping] = useState(null);
    const [toast, setToast] = useState(null);

    const showToast = useCallback((msg, type = 'success') => {
        setToast({ msg, type });
        setTimeout(() => setToast(null), 4000);
    }, []);

    const toggleAgent = (agentKey, currentlyEnabled) => {
        if (!confirm(`${currentlyEnabled ? 'Disable' : 'Enable'} the ${AGENT_META[agentKey]?.label} Agent?`)) return;
        setTopping(agentKey);
        router.post('/admin/agents/toggle', { agent: agentKey, enabled: !currentlyEnabled }, {
            preserveScroll: true,
            onSuccess: () => { showToast(`${AGENT_META[agentKey]?.label} Agent ${!currentlyEnabled ? 'enabled' : 'disabled'}.`); setTopping(null); },
            onError: () => { showToast('Failed to toggle agent.', 'error'); setTopping(null); },
        });
    };

    const toggleChannel = (channel, currentlyEnabled) => {
        if (!confirm(`${currentlyEnabled ? 'Disable' : 'Enable'} the ${CHANNEL_META[channel]?.label} channel?`)) return;
        setTopping(channel);
        router.post('/admin/agents/channels/toggle', { channel, enabled: !currentlyEnabled }, {
            preserveScroll: true,
            onSuccess: () => { showToast(`${CHANNEL_META[channel]?.label} ${!currentlyEnabled ? 'enabled' : 'disabled'}.`); setTopping(null); },
            onError: () => { showToast('Failed to toggle channel.', 'error'); setTopping(null); },
        });
    };

    const closeConversation = (convId) => {
        if (!confirm('Close this conversation?')) return;
        router.post(`/admin/agents/conversations/${convId}/close`, {}, {
            preserveScroll: true,
            onSuccess: () => showToast('Conversation closed.'),
            onError: () => showToast('Failed.', 'error'),
        });
    };

    const s = stats || {};
    const convList = conversations || [];
    const leadList = leads || [];
    const settings = agentSettings || {};

    const agentKeys = Object.keys(AGENT_META);
    const channelKeys = Object.keys(CHANNEL_META);

    return (
        <AdminLayout>
            <Head title="Agent Command Center | Mission Control" />

            {/* Toast */}
            {toast && (
                <div className={`fixed top-6 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-xl shadow-2xl text-sm font-medium ${toast.type === 'error' ? 'bg-red-900/90 border border-red-500/30 text-red-100' : 'bg-teal/90 border border-teal/30 text-white'}`}>
                    {toast.type === 'error' ? '✗ ' : '✓ '}{toast.msg}
                </div>
            )}

            {/* Header */}
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Agent Command Center</h1>
                    <p className="text-white/40 text-sm">Monitor, configure and control all autonomous agents and their communication channels.</p>
                </div>
                <div className="flex gap-2">
                    {['overview', 'channels', 'conversations', 'leads'].map(tab => (
                        <button key={tab} onClick={() => setActiveTab(tab)}
                            className={`px-4 py-2 rounded-lg text-[10px] font-mono uppercase tracking-widest transition-all capitalize ${activeTab === tab ? 'bg-teal text-white' : 'bg-white/5 text-white/40 hover:text-white border border-white/10'}`}>
                            {tab}
                        </button>
                    ))}
                </div>
            </div>

            {/* Stats Row */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                {[
                    { label: 'Active Agents', value: s.active_agents ?? agentKeys.length, icon: '🤖' },
                    { label: 'Open Conversations', value: s.open_conversations ?? convList.length, icon: '💬' },
                    { label: 'New Leads', value: s.new_leads ?? leadList.length, icon: '🎯' },
                    { label: 'Msgs Today', value: s.messages_today ?? 0, icon: '📨' },
                ].map(stat => (
                    <div key={stat.label} className="bg-[#121214] border border-white/5 rounded-xl p-5">
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-lg">{stat.icon}</span>
                            <span className="font-mono text-[9px] uppercase tracking-widest text-white/30">{stat.label}</span>
                        </div>
                        <p className="text-3xl font-bold text-white">{stat.value}</p>
                    </div>
                ))}
            </div>

            {/* OVERVIEW TAB */}
            {activeTab === 'overview' && (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    {agentKeys.map(key => {
                        const meta = AGENT_META[key];
                        const enabled = settings[`agent_${key}_enabled`] !== 'false';
                        const busy = topping === key;
                        return (
                            <div key={key} className={`bg-[#121214] border rounded-xl p-6 space-y-4 transition-all ${enabled ? 'border-white/10 hover:border-teal/30' : 'border-white/5 opacity-60'}`}>
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <span className="text-3xl">{meta.icon}</span>
                                        <div>
                                            <p className="font-bold text-white">{meta.label} Agent</p>
                                            <p className={`text-[10px] font-mono uppercase tracking-widest ${enabled ? 'text-teal' : 'text-white/30'}`}>{enabled ? '● Active' : '○ Disabled'}</p>
                                        </div>
                                    </div>
                                    <button onClick={() => toggleAgent(key, enabled)} disabled={busy}
                                        className={`relative w-12 h-6 rounded-full transition-all cursor-pointer disabled:opacity-50 ${enabled ? 'bg-teal' : 'bg-white/10'}`}>
                                        <span className={`absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-all ${enabled ? 'left-7' : 'left-1'}`}></span>
                                    </button>
                                </div>
                                <p className="text-xs text-white/50 leading-relaxed">{meta.desc}</p>
                                <button onClick={() => setSelectedAgent(selectedAgent === key ? null : key)}
                                    className="text-[10px] font-mono uppercase tracking-widest text-teal/70 hover:text-teal transition-colors">
                                    {selectedAgent === key ? '▲ Hide Details' : '▼ View Details'}
                                </button>
                                {selectedAgent === key && (
                                    <div className="pt-4 border-t border-white/5 space-y-2 text-xs text-white/50">
                                        <p>🔧 Tools: resolve_identity, send_otp, create_account, find_matches, get_pricing</p>
                                        <p>🧠 Model: Configured via AI Settings</p>
                                        <p>📊 Activity logged in Agent Activity Feed</p>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            {/* CHANNELS TAB */}
            {activeTab === 'channels' && (
                <div className="space-y-6">
                    <p className="text-white/40 text-sm">The Ambassador Agent operates across these inbound channels. Toggle each to enable/disable routing.</p>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {channelKeys.map(ch => {
                            const meta = CHANNEL_META[ch];
                            const enabled = settings[`channel_${ch}_enabled`] !== 'false';
                            const busy = topping === ch;
                            return (
                                <div key={ch} className={`bg-[#121214] border rounded-xl p-6 transition-all ${enabled ? 'border-white/10 hover:border-teal/30' : 'border-white/5 opacity-60'}`}>
                                    <div className="flex items-center justify-between mb-4">
                                        <div className="flex items-center gap-3">
                                            <span className="text-2xl">{meta.icon}</span>
                                            <div>
                                                <p className="font-bold text-white">{meta.label}</p>
                                                <p className={`text-[10px] font-mono uppercase tracking-widest ${enabled ? 'text-teal' : 'text-white/30'}`}>{enabled ? '● Active' : '○ Disabled'}</p>
                                            </div>
                                        </div>
                                        <button onClick={() => toggleChannel(ch, enabled)} disabled={busy}
                                            className={`relative w-12 h-6 rounded-full transition-all cursor-pointer disabled:opacity-50 ${enabled ? 'bg-teal' : 'bg-white/10'}`}>
                                            <span className={`absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-all ${enabled ? 'left-7' : 'left-1'}`}></span>
                                        </button>
                                    </div>
                                    <div className="space-y-2 text-xs text-white/40">
                                        {ch === 'whatsapp' && <><p>Webhook: <code className="text-teal/80">/webhook/whatsapp</code></p><p>Provider: Twilio / Cloud API</p></>}
                                        {ch === 'meta_dm' && <><p>Webhook: <code className="text-teal/80">/webhook/meta</code></p><p>Provider: Meta Graph API (Instagram + Facebook)</p></>}
                                        {ch === 'email' && <><p>Inbound: Configured via SMTP/Mailgun</p><p>Outbound: Via Email Settings</p></>}
                                        {ch === 'web' && <><p>Chat widget embedded on public pages</p><p>Route: <code className="text-teal/80">/chat</code></p></>}
                                    </div>
                                    <div className="mt-4 pt-4 border-t border-white/5 flex items-center justify-between text-[10px] font-mono text-white/30">
                                        <span>Conversations today: {s[`${ch}_today`] ?? 0}</span>
                                        <span>Total: {s[`${ch}_total`] ?? 0}</span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    <div className="bg-copper/5 border border-copper/20 rounded-xl p-6">
                        <p className="text-copper text-sm font-bold mb-2">⚠ Webhook Configuration Required</p>
                        <p className="text-white/50 text-xs leading-relaxed">
                            WhatsApp and Meta DM channels require webhook URLs to be registered in your Meta Developer Console and Twilio dashboard.
                            Set your webhook base URL to: <code className="text-teal bg-white/5 px-1 rounded">{window.location.origin}/webhook/</code>
                        </p>
                    </div>
                </div>
            )}

            {/* CONVERSATIONS TAB */}
            {activeTab === 'conversations' && (
                <div className="bg-[#121214] border border-white/5 rounded-xl overflow-hidden">
                    <div className="px-8 py-5 border-b border-white/5 flex items-center justify-between">
                        <h3 className="font-mono text-[9px] uppercase tracking-widest text-white/30 font-bold">Live Conversations</h3>
                        <span className="text-[10px] font-mono text-white/30">{convList.length} open</span>
                    </div>
                    {convList.length > 0 ? (
                        <div className="divide-y divide-white/5">
                            {convList.map(conv => (
                                <div key={conv.id} className="px-8 py-5 hover:bg-white/[0.02] transition-colors">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex items-center gap-3">
                                            <span className="text-xl">{CHANNEL_META[conv.channel]?.icon || '💬'}</span>
                                            <div>
                                                <p className="text-white font-medium text-sm">{conv.identity?.display_name || conv.identity?.phone || 'Unknown'}</p>
                                                <p className="text-white/40 text-xs">{CHANNEL_META[conv.channel]?.label || conv.channel} · {new Date(conv.last_message_at).toLocaleString()}</p>
                                                <p className="text-white/30 text-xs italic mt-1 line-clamp-1">{conv.last_message || '—'}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 flex-shrink-0">
                                            <span className={`px-2 py-0.5 rounded text-[9px] font-mono uppercase ${conv.status === 'open' ? 'bg-teal/10 text-teal' : 'bg-white/5 text-white/30'}`}>{conv.status}</span>
                                            {conv.status === 'open' && (
                                                <button onClick={() => closeConversation(conv.id)}
                                                    className="px-3 py-1 bg-white/5 hover:bg-danger/10 border border-white/10 hover:border-danger/20 text-white/40 hover:text-danger rounded text-[10px] font-mono uppercase transition-all cursor-pointer">
                                                    Close
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="px-8 py-16 text-center text-white/30">
                            <div className="text-3xl mb-3">💬</div>
                            <p>No open conversations.</p>
                        </div>
                    )}
                </div>
            )}

            {/* LEADS TAB */}
            {activeTab === 'leads' && (
                <div className="bg-[#121214] border border-white/5 rounded-xl overflow-hidden">
                    <div className="px-8 py-5 border-b border-white/5">
                        <h3 className="font-mono text-[9px] uppercase tracking-widest text-white/30 font-bold">Agent-Generated Leads</h3>
                    </div>
                    {leadList.length > 0 ? (
                        <table className="w-full text-left text-sm">
                            <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-widest uppercase text-white/30">
                                <tr>
                                    <th className="px-8 py-4">Contact</th>
                                    <th className="px-8 py-4">Channel</th>
                                    <th className="px-8 py-4">Status</th>
                                    <th className="px-8 py-4">Created</th>
                                    <th className="px-8 py-4">Converted</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-white/5">
                                {leadList.map(lead => (
                                    <tr key={lead.id} className="hover:bg-white/[0.02] transition-colors">
                                        <td className="px-8 py-4">
                                            <p className="text-white font-medium">{lead.identity?.display_name || 'Unknown'}</p>
                                            <p className="text-white/40 text-xs">{lead.phone || lead.email || '—'}</p>
                                        </td>
                                        <td className="px-8 py-4">{CHANNEL_META[lead.identity?.channel]?.icon || '?'} {CHANNEL_META[lead.identity?.channel]?.label || '—'}</td>
                                        <td className="px-8 py-4">
                                            <span className={`px-2 py-0.5 rounded text-[9px] font-mono uppercase ${lead.status === 'converted' ? 'bg-teal/10 text-teal' : lead.status === 'new' ? 'bg-copper/10 text-copper' : 'bg-white/5 text-white/40'}`}>{lead.status}</span>
                                        </td>
                                        <td className="px-8 py-4 text-white/40 text-xs">{new Date(lead.created_at).toLocaleDateString()}</td>
                                        <td className="px-8 py-4 text-xs">{lead.converted_at ? <span className="text-teal">{new Date(lead.converted_at).toLocaleDateString()}</span> : <span className="text-white/20">—</span>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div className="px-8 py-16 text-center text-white/30">
                            <div className="text-3xl mb-3">🎯</div>
                            <p>No leads captured yet.</p>
                        </div>
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
