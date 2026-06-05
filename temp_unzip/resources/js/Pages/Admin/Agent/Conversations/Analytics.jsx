import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function ConversationsAnalytics({ analytics }) {
    const { by_channel, by_status, messages_per_day, avg_response_time_seconds, period_days } = analytics;

    // Convert objects to arrays for rendering
    const channelData = Object.entries(by_channel).map(([channel, count]) => ({
        channel,
        count,
        percentage: Object.values(by_channel).reduce((a, b) => a + b, 0) > 0
            ? Math.round((count / Object.values(by_channel).reduce((a, b) => a + b, 0)) * 100)
            : 0,
    }));

    const statusData = Object.entries(by_status).map(([status, count]) => ({
        status,
        count,
        percentage: Object.values(by_status).reduce((a, b) => a + b, 0) > 0
            ? Math.round((count / Object.values(by_status).reduce((a, b) => a + b, 0)) * 100)
            : 0,
    }));

    const dayData = Object.entries(messages_per_day).map(([date, count]) => ({
        date,
        count,
    }));

    const maxDayCount = Math.max(...dayData.map((d) => d.count), 1);

    return (
        <AdminLayout>
            <Head title="Conversation Analytics" />

            <div className="p-6 max-w-6xl mx-auto">
                {/* Header */}
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <Link
                            href={route('admin.agent.conversations.index')}
                            className="text-sm text-gray-500 hover:underline mb-2 block"
                        >
                            ← Back to Conversations
                        </Link>
                        <h1 className="text-2xl font-bold">Conversation Analytics</h1>
                        <p className="text-gray-500 mt-1">
                            Last {period_days} days
                        </p>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-4 gap-4 mb-8">
                    <div className="bg-white rounded-lg shadow p-4">
                        <p className="text-sm text-gray-500">Avg Response Time</p>
                        <p className="text-2xl font-bold">
                            {avg_response_time_seconds < 60
                                ? `${avg_response_time_seconds}s`
                                : `${Math.round(avg_response_time_seconds / 60)}m`}
                        </p>
                    </div>
                    <div className="bg-white rounded-lg shadow p-4">
                        <p className="text-sm text-gray-500">Total Conversations</p>
                        <p className="text-2xl font-bold">
                            {Object.values(by_status).reduce((a, b) => a + b, 0)}
                        </p>
                    </div>
                    <div className="bg-white rounded-lg shadow p-4">
                        <p className="text-sm text-gray-500">Active Channels</p>
                        <p className="text-2xl font-bold">{channelData.length}</p>
                    </div>
                    <div className="bg-white rounded-lg shadow p-4">
                        <p className="text-sm text-gray-500">Messages/Day (Avg)</p>
                        <p className="text-2xl font-bold">
                            {dayData.length > 0
                                ? Math.round(dayData.reduce((a, b) => a + b.count, 0) / dayData.length)
                                : 0}
                        </p>
                    </div>
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-2 gap-6 mb-8">
                    {/* Conversations by Channel */}
                    <div className="bg-white rounded-lg shadow p-6">
                        <h3 className="text-lg font-bold mb-4">Conversations by Channel</h3>
                        {channelData.length === 0 ? (
                            <p className="text-gray-400 text-center py-8">No data yet</p>
                        ) : (
                            <div className="space-y-3">
                                {channelData.map(({ channel, count, percentage }) => (
                                    <div key={channel}>
                                        <div className="flex justify-between text-sm mb-1">
                                            <span className="capitalize font-medium">{channel}</span>
                                            <span className="text-gray-500">{count} ({percentage}%)</span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2">
                                            <div
                                                className="bg-blue-600 h-2 rounded-full"
                                                style={{ width: `${percentage}%` }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Conversations by Status */}
                    <div className="bg-white rounded-lg shadow p-6">
                        <h3 className="text-lg font-bold mb-4">Conversations by Status</h3>
                        {statusData.length === 0 ? (
                            <p className="text-gray-400 text-center py-8">No data yet</p>
                        ) : (
                            <div className="space-y-3">
                                {statusData.map(({ status, count, percentage }) => (
                                    <div key={status}>
                                        <div className="flex justify-between text-sm mb-1">
                                            <span className="capitalize font-medium">{status}</span>
                                            <span className="text-gray-500">{count} ({percentage}%)</span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2">
                                            <div
                                                className={`h-2 rounded-full ${status === 'open' ? 'bg-blue-600' :
                                                        status === 'escalated' ? 'bg-orange-500' :
                                                            'bg-green-600'
                                                    }`}
                                                style={{ width: `${percentage}%` }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Messages Over Time */}
                <div className="bg-white rounded-lg shadow p-6">
                    <h3 className="text-lg font-bold mb-4">Messages per Day (Last 7 Days)</h3>
                    {dayData.length === 0 ? (
                        <p className="text-gray-400 text-center py-8">No data yet</p>
                    ) : (
                        <div className="flex items-end gap-2 h-48">
                            {dayData.map(({ date, count }) => (
                                <div key={date} className="flex-1 flex flex-col items-center">
                                    <span className="text-xs text-gray-500 mb-1">{count}</span>
                                    <div
                                        className="bg-blue-600 w-full rounded-t"
                                        style={{
                                            height: `${(count / maxDayCount) * 160}px`,
                                            minHeight: '4px',
                                        }}
                                    />
                                    <span className="text-xs text-gray-400 mt-2">
                                        {new Date(date).toLocaleDateString('en-GB', {
                                            day: 'numeric',
                                            month: 'short',
                                        })}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}