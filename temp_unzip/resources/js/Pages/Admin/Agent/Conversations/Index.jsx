import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function ConversationsIndex({ conversations, stats, filters, channels, statuses }) {
    return (
        <AdminLayout>
            <Head title="Agent Conversations" />

            <div className="p-6">
                {/* Header */}
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-2xl font-bold">Agent Conversations</h1>
                        <p className="text-gray-500 mt-1">
                            Monitor and manage conversations across all channels.
                        </p>
                    </div>
                    <Link
                        href={route('admin.agent.conversations.analytics')}
                        className="btn btn-secondary"
                    >
                        View Analytics
                    </Link>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-5 gap-4 mb-6">
                    <div className="bg-white rounded-lg shadow p-4">
                        <p className="text-sm text-gray-500">Total</p>
                        <p className="text-2xl font-bold">{stats.total}</p>
                    </div>
                    <div className="bg-white rounded-lg shadow p-4">
                        <p className="text-sm text-gray-500">Open</p>
                        <p className="text-2xl font-bold text-blue-600">{stats.open}</p>
                    </div>
                    <div className="bg-white rounded-lg shadow p-4">
                        <p className="text-sm text-gray-500">Escalated</p>
                        <p className="text-2xl font-bold text-orange-600">{stats.escalated}</p>
                    </div>
                    <div className="bg-white rounded-lg shadow p-4">
                        <p className="text-sm text-gray-500">Closed</p>
                        <p className="text-2xl font-bold text-green-600">{stats.closed}</p>
                    </div>
                    <div className="bg-white rounded-lg shadow p-4">
                        <p className="text-sm text-gray-500">Today</p>
                        <p className="text-2xl font-bold">{stats.today}</p>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-lg shadow p-4 mb-6">
                    <form className="flex gap-4 items-end">
                        <div className="flex-1">
                            <label className="block text-sm font-medium mb-1">Search</label>
                            <input
                                type="text"
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Email, phone, or name..."
                                className="w-full border rounded p-2"
                                onChange={(e) => {
                                    if (e.target.value.length > 2 || e.target.value.length === 0) {
                                        router.get(route('admin.agent.conversations.index'), {
                                            ...filters,
                                            search: e.target.value,
                                        }, { preserveState: true });
                                    }
                                }}
                            />
                        </div>
                        <div className="w-48">
                            <label className="block text-sm font-medium mb-1">Channel</label>
                            <select
                                name="channel"
                                defaultValue={filters.channel}
                                className="w-full border rounded p-2"
                                onChange={(e) => {
                                    router.get(route('admin.agent.conversations.index'), {
                                        ...filters,
                                        channel: e.target.value,
                                    }, { preserveState: true });
                                }}
                            >
                                <option value="">All Channels</option>
                                {channels.map((ch) => (
                                    <option key={ch} value={ch}>{ch}</option>
                                ))}
                            </select>
                        </div>
                        <div className="w-48">
                            <label className="block text-sm font-medium mb-1">Status</label>
                            <select
                                name="status"
                                defaultValue={filters.status}
                                className="w-full border rounded p-2"
                                onChange={(e) => {
                                    router.get(route('admin.agent.conversations.index'), {
                                        ...filters,
                                        status: e.target.value,
                                    }, { preserveState: true });
                                }}
                            >
                                <option value="">All Statuses</option>
                                {statuses.map((st) => (
                                    <option key={st} value={st}>{st}</option>
                                ))}
                            </select>
                        </div>
                    </form>
                </div>

                {/* Conversations Table */}
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <table className="w-full">
                        <thead className="bg-gray-50 border-b">
                            <tr>
                                <th className="text-left p-3 text-sm font-medium">User</th>
                                <th className="text-left p-3 text-sm font-medium">Channel</th>
                                <th className="text-left p-3 text-sm font-medium">Intent</th>
                                <th className="text-left p-3 text-sm font-medium">Status</th>
                                <th className="text-left p-3 text-sm font-medium">Last Message</th>
                                <th className="text-left p-3 text-sm font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {conversations.data.length === 0 ? (
                                <tr>
                                    <td colSpan="6" className="p-6 text-center text-gray-500">
                                        No conversations found.
                                    </td>
                                </tr>
                            ) : (
                                conversations.data.map((conv) => (
                                    <tr key={conv.id} className="border-t hover:bg-gray-50">
                                        <td className="p-3">
                                            <div className="font-medium">
                                                {conv.identity?.display_name || 'Anonymous'}
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                {conv.identity?.email || conv.identity?.phone || 'N/A'}
                                            </div>
                                        </td>
                                        <td className="p-3">
                                            <span className={`px-2 py-1 rounded text-xs font-mono ${conv.channel === 'web' ? 'bg-blue-100 text-blue-800' :
                                                    conv.channel === 'email' ? 'bg-purple-100 text-purple-800' :
                                                        conv.channel === 'whatsapp' ? 'bg-green-100 text-green-800' :
                                                            'bg-gray-100 text-gray-800'
                                                }`}>
                                                {conv.channel}
                                            </span>
                                        </td>
                                        <td className="p-3 text-sm max-w-xs truncate">
                                            {conv.intent_summary || '—'}
                                        </td>
                                        <td className="p-3">
                                            <span className={`px-2 py-1 rounded text-xs ${conv.status === 'open' ? 'bg-blue-100 text-blue-800' :
                                                    conv.status === 'escalated' ? 'bg-orange-100 text-orange-800' :
                                                        'bg-gray-100 text-gray-800'
                                                }`}>
                                                {conv.status}
                                            </span>
                                        </td>
                                        <td className="p-3 text-sm text-gray-500">
                                            {conv.last_message_at || conv.created_at}
                                        </td>
                                        <td className="p-3">
                                            <Link
                                                href={route('admin.agent.conversations.show', conv.id)}
                                                className="text-blue-600 hover:underline text-sm"
                                            >
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    {conversations.links && conversations.links.length > 3 && (
                        <div className="p-4 border-t flex justify-center gap-2">
                            {conversations.links.map((link, i) => (
                                <button
                                    key={i}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    onClick={() => router.get(link.url)}
                                    className={`px-3 py-1 rounded text-sm ${link.active
                                            ? 'bg-blue-600 text-white'
                                            : link.url
                                                ? 'bg-gray-100 hover:bg-gray-200'
                                                : 'bg-gray-50 text-gray-400 cursor-not-allowed'
                                        }`}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}