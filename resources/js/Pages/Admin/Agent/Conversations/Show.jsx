import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function ConversationsShow({ conversation, messages }) {
    const { post, processing, data, setData, errors } = useForm({
        reason: '',
        admin_note: conversation.admin_note || '',
        note: '',
    });

    const [showEscalate, setShowEscalate] = useState(false);
    const [showNote, setShowNote] = useState(false);

    const handleEscalate = (e) => {
        e.preventDefault();
        post(route('admin.agent.conversations.escalate', conversation.id), {
            onSuccess: () => {
                setShowEscalate(false);
                setData('reason', '');
            },
        });
    };

    const handleNote = (e) => {
        e.preventDefault();
        post(route('admin.agent.conversations.note', conversation.id), {
            onSuccess: () => {
                setShowNote(false);
                setData('note', '');
            },
        });
    };

    const handleAssign = () => {
        post(route('admin.agent.conversations.assign', conversation.id), {
            admin_id: auth()?.id,
        });
    };

    const handleClose = () => {
        if (confirm('Close this conversation?')) {
            post(route('admin.agent.conversations.close', conversation.id));
        }
    };

    return (
        <AdminLayout>
            <Head title={`Conversation ${conversation.id}`} />

            <div className="p-6 max-w-6xl mx-auto">
                {/* Header */}
                <div className="flex justify-between items-start mb-6">
                    <div>
                        <Link
                            href={route('admin.agent.conversations.index')}
                            className="text-sm text-gray-500 hover:underline mb-2 block"
                        >
                            ← Back to Conversations
                        </Link>
                        <h1 className="text-2xl font-bold">
                            Conversation #{conversation.id}
                        </h1>
                        <div className="flex gap-3 mt-2">
                            <span className={`px-2 py-1 rounded text-xs font-mono ${conversation.channel === 'web' ? 'bg-blue-100 text-blue-800' :
                                    conversation.channel === 'email' ? 'bg-purple-100 text-purple-800' :
                                        conversation.channel === 'whatsapp' ? 'bg-green-100 text-green-800' :
                                            'bg-gray-100 text-gray-800'
                                }`}>
                                {conversation.channel}
                            </span>
                            <span className={`px-2 py-1 rounded text-xs ${conversation.status === 'open' ? 'bg-blue-100 text-blue-800' :
                                    conversation.status === 'escalated' ? 'bg-orange-100 text-orange-800' :
                                        'bg-gray-100 text-gray-800'
                                }`}>
                                {conversation.status}
                            </span>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-2">
                        {conversation.status !== 'escalated' && (
                            <button
                                onClick={() => setShowEscalate(true)}
                                className="btn btn-warning text-sm"
                            >
                                Escalate
                            </button>
                        )}
                        {conversation.status !== 'closed' && (
                            <button
                                onClick={handleClose}
                                className="btn btn-secondary text-sm"
                            >
                                Close
                            </button>
                        )}
                    </div>
                </div>

                {/* User Info & Intent */}
                <div className="grid grid-cols-3 gap-4 mb-6">
                    <div className="bg-white rounded-lg shadow p-4">
                        <h3 className="text-sm font-medium text-gray-500 mb-2">User Identity</h3>
                        {conversation.identity ? (
                            <div>
                                <p className="font-medium">{conversation.identity.display_name || 'Anonymous'}</p>
                                {conversation.identity.email && (
                                    <p className="text-sm text-gray-600">{conversation.identity.email}</p>
                                )}
                                {conversation.identity.phone && (
                                    <p className="text-sm text-gray-600">{conversation.identity.phone}</p>
                                )}
                                <p className="text-xs text-gray-400 mt-1">
                                    Tier: {conversation.identity.tier}
                                    {conversation.identity.is_verified && ' • Verified'}
                                </p>
                            </div>
                        ) : (
                            <p className="text-gray-400">No identity resolved</p>
                        )}
                    </div>

                    <div className="bg-white rounded-lg shadow p-4">
                        <h3 className="text-sm font-medium text-gray-500 mb-2">Account</h3>
                        {conversation.user ? (
                            <div>
                                <p className="font-medium">{conversation.user.name}</p>
                                <p className="text-sm text-gray-600">{conversation.user.email}</p>
                                <p className="text-xs text-gray-400 mt-1">
                                    Role: {conversation.user.role}
                                </p>
                            </div>
                        ) : (
                            <p className="text-gray-400">No linked account</p>
                        )}
                    </div>

                    <div className="bg-white rounded-lg shadow p-4">
                        <h3 className="text-sm font-medium text-gray-500 mb-2">Intent Summary</h3>
                        <p className="text-sm">{conversation.intent_summary || 'Not yet determined'}</p>
                        <p className="text-xs text-gray-400 mt-2">
                            Created: {conversation.created_at}
                        </p>
                        <p className="text-xs text-gray-400">
                            Last message: {conversation.last_message_at}
                        </p>
                    </div>
                </div>

                {/* Admin Note */}
                <div className="bg-white rounded-lg shadow p-4 mb-6">
                    <div className="flex justify-between items-center mb-2">
                        <h3 className="text-sm font-medium">Admin Note</h3>
                        <button
                            onClick={() => setShowNote(!showNote)}
                            className="text-sm text-blue-600 hover:underline"
                        >
                            {showNote ? 'Cancel' : (conversation.admin_note ? 'Edit' : 'Add')}
                        </button>
                    </div>
                    {showNote ? (
                        <form onSubmit={handleNote}>
                            <textarea
                                value={data.note || data.admin_note}
                                onChange={(e) => setData('note', e.target.value)}
                                className="w-full border rounded p-2 text-sm"
                                rows="3"
                                placeholder="Add an admin note..."
                            />
                            {errors.note && (
                                <p className="text-red-600 text-xs mt-1">{errors.note}</p>
                            )}
                            <div className="flex gap-2 mt-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="btn btn-primary text-sm"
                                >
                                    Save Note
                                </button>
                            </div>
                        </form>
                    ) : (
                        <p className="text-sm text-gray-600">
                            {conversation.admin_note || 'No admin note yet.'}
                        </p>
                    )}
                </div>

                {/* Escalation Modal */}
                {showEscalate && (
                    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                        <div className="bg-white rounded-lg p-6 w-full max-w-md">
                            <h3 className="text-lg font-bold mb-4">Escalate Conversation</h3>
                            <form onSubmit={handleEscalate}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium mb-1">Reason</label>
                                    <textarea
                                        value={data.reason}
                                        onChange={(e) => setData('reason', e.target.value)}
                                        className="w-full border rounded p-2"
                                        rows="3"
                                        placeholder="Why is this being escalated?"
                                    />
                                </div>
                                <div className="flex gap-2 justify-end">
                                    <button
                                        type="button"
                                        onClick={() => setShowEscalate(false)}
                                        className="btn btn-ghost"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="btn btn-warning"
                                    >
                                        Escalate
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Message History */}
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="p-4 border-b">
                        <h3 className="text-lg font-bold">Message History</h3>
                        <p className="text-sm text-gray-500">
                            {messages.length} messages
                        </p>
                    </div>

                    <div className="divide-y">
                        {messages.map((msg) => (
                            <div
                                key={msg.id}
                                className={`p-4 ${msg.role === 'user' ? 'bg-blue-50' : 'bg-gray-50'
                                    }`}
                            >
                                <div className="flex justify-between items-start mb-2">
                                    <span className={`px-2 py-1 rounded text-xs font-medium ${msg.role === 'user'
                                            ? 'bg-blue-200 text-blue-800'
                                            : 'bg-gray-200 text-gray-800'
                                        }`}>
                                        {msg.role === 'user' ? 'User' : 'Assistant'}
                                    </span>
                                    <span className="text-xs text-gray-400">{msg.created_at}</span>
                                </div>
                                <p className="text-sm whitespace-pre-wrap">{msg.content}</p>
                                {msg.tool_calls && msg.tool_calls.length > 0 && (
                                    <div className="mt-2">
                                        <span className="text-xs text-gray-500">Tool calls:</span>
                                        <div className="flex gap-1 mt-1 flex-wrap">
                                            {msg.tool_calls.map((tc, i) => (
                                                <span
                                                    key={i}
                                                    className="px-2 py-1 rounded text-xs bg-purple-100 text-purple-800"
                                                >
                                                    {tc.function?.name || 'tool'}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}