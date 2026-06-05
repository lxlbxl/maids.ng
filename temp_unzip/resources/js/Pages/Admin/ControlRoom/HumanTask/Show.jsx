import { useState } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import { usePage } from "@inertiajs/react";
import axios from "axios";

const TASK_ICONS = {
    match_employer: "Search", send_message: "Message", verify_nin: "NIN",
    process_payout: "Payout", resolve_dispute: "Dispute",
    review_maid_quality: "Quality", generate_content: "Content",
    generate_seo_content: "SEO", send_outreach: "Outreach",
    approve_hitl: "Approval",
};

const PRIORITY_LABELS = { 1: "Urgent", 2: "High", 3: "Normal", 4: "Low" };
const PRIORITY_COLORS = {
    1: "text-red-400 bg-red-900/30",
    2: "text-orange-400 bg-orange-900/30",
    3: "text-blue-400 bg-blue-900/30",
    4: "text-gray-400 bg-gray-800",
};

export default function HumanTaskShow() {
    const { task, similarTasks } = usePage().props;
    const [inputs, setInputs] = useState({});
    const [executing, setExecuting] = useState(false);
    const [result, setResult] = useState(null);
    const [error, setError] = useState(null);

    const set = (key, value) => setInputs(prev => ({ ...prev, [key]: value }));

    const execute = async () => {
        setExecuting(true);
        setError(null);
        try {
            const res = await axios.post(`/admin/control-room/hitl/${task.id}/execute`, { inputs });
            setResult(res.data);
        } catch (e) {
            setError(e.response?.data?.message || e.message);
        } finally {
            setExecuting(false);
        }
    };

    const skip = async () => {
        await axios.post(`/admin/control-room/hitl/${task.id}/skip`, { reason: "Skipped from detail view" });
        window.location.href = "/admin/control-room";
    };

    const renderForm = () => {
        switch (task.task_type) {
            case "send_message":
                return (
                    <textarea className="w-full bg-gray-700 text-white p-3 rounded-lg text-sm resize-none"
                        rows={4} placeholder="Message to send..."
                        value={inputs.message ?? ""} onChange={e => set("message", e.target.value)} />
                );
            case "verify_nin":
                return (
                    <div className="space-y-3">
                        <select className="w-full bg-gray-700 text-white p-2 rounded-lg text-sm"
                            value={inputs.decision ?? "approved"} onChange={e => set("decision", e.target.value)}>
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                            <option value="manual_review">Refer for Manual Review</option>
                        </select>
                        <textarea className="w-full bg-gray-700 text-white p-2 rounded-lg text-sm resize-none"
                            rows={2} placeholder="Review notes..." value={inputs.notes ?? ""}
                            onChange={e => set("notes", e.target.value)} />
                    </div>
                );
            case "process_payout":
                return (
                    <div className="bg-gray-700/50 rounded-lg p-3 text-sm text-gray-300 space-y-2">
                        <div>Amount: <strong className="text-white">&#8358;{Number(task.task_payload?.amount || 0).toLocaleString()}</strong></div>
                        <div>Maid ID: <strong className="text-white">#{task.task_payload?.maid_id}</strong></div>
                        <textarea className="w-full bg-gray-700 text-white p-2 rounded-lg text-sm resize-none mt-2"
                            rows={2} placeholder="Approval notes..." value={inputs.notes ?? ""}
                            onChange={e => set("notes", e.target.value)} />
                    </div>
                );
            case "resolve_dispute":
                return (
                    <div className="space-y-3">
                        <textarea className="w-full bg-gray-700 text-white p-3 rounded-lg text-sm resize-none"
                            rows={3} placeholder="Resolution decision..."
                            value={inputs.resolution ?? ""} onChange={e => set("resolution", e.target.value)} />
                        <input type="number" placeholder="Refund amount (&#8358;)" defaultValue={inputs.refund_amount ?? ""}
                            className="w-full bg-gray-700 text-white p-2 rounded-lg text-sm"
                            onChange={e => set("refund_amount", e.target.value)} />
                    </div>
                );
            case "approve_hitl":
                return (
                    <div className="space-y-3">
                        <select className="w-full bg-gray-700 text-white p-2 rounded-lg text-sm"
                            value={inputs.decision ?? "approved"} onChange={e => set("decision", e.target.value)}>
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                        </select>
                        <textarea className="w-full bg-gray-700 text-white p-2 rounded-lg text-sm resize-none"
                            rows={2} placeholder="Decision note..." value={inputs.note ?? ""}
                            onChange={e => set("note", e.target.value)} />
                    </div>
                );
            default:
                return (
                    <div className="space-y-3">
                        <pre className="text-xs text-gray-400 bg-gray-700/50 p-3 rounded-lg overflow-x-auto max-h-40">
                            {JSON.stringify(task.task_payload, null, 2)}
                        </pre>
                        <textarea className="w-full bg-gray-700 text-white p-2 rounded-lg text-sm resize-none"
                            rows={2} placeholder="Completion notes..." value={inputs.notes ?? ""}
                            onChange={e => set("notes", e.target.value)} />
                    </div>
                );
        }
    };

    return (
        <AdminLayout title={`Task: ${task.description}`}>
            <div className="max-w-4xl mx-auto">
                <div className="mb-4">
                    <a href="/admin/control-room" className="text-blue-400 text-sm hover:underline">
                        &larr; Back to Control Room
                    </a>
                </div>

                <div className="bg-gray-900 rounded-xl border border-gray-800 p-6 mb-6">
                    <div className="flex items-start gap-3 mb-4">
                        <span className="text-2xl">{TASK_ICONS[task.task_type] ?? "Task"}</span>
                        <div>
                            <h1 className="text-white text-lg font-semibold">{task.description}</h1>
                            <div className="flex items-center gap-3 mt-1">
                                <span className={`text-xs px-2 py-0.5 rounded ${PRIORITY_COLORS[task.priority]}`}>
                                    {PRIORITY_LABELS[task.priority]}
                                </span>
                                <span className="text-gray-400 text-xs capitalize">Reason: {task.reason}</span>
                                <span className="text-gray-500 text-xs">Agent: {task.agent_name}</span>
                            </div>
                        </div>
                    </div>

                    {task.related_user && (
                        <div className="bg-gray-800 rounded-lg p-3 mb-4">
                            <p className="text-gray-400 text-xs mb-1">Related User</p>
                            <p className="text-white text-sm">{task.related_user.name} ({task.related_user.email || task.related_user.phone})</p>
                        </div>
                    )}

                    {task.trigger_event && (
                        <div className="bg-gray-800 rounded-lg p-3 mb-4">
                            <p className="text-gray-400 text-xs mb-1">Triggered By</p>
                            <p className="text-gray-300 text-sm">{task.trigger_event.summary}</p>
                        </div>
                    )}

                    <div className="mb-6">
                        <label className="block text-gray-400 text-xs font-semibold mb-2 uppercase tracking-wider">
                            Action
                        </label>
                        {renderForm()}
                    </div>

                    <div className="flex gap-3">
                        <button onClick={execute} disabled={executing}
                            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-medium text-sm disabled:opacity-50">
                            {executing ? "Executing..." : "Execute Task"}
                        </button>
                        <button onClick={skip}
                            className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg text-sm">
                            Skip Task
                        </button>
                    </div>

                    {error && (
                        <div className="mt-4 bg-red-900/30 border border-red-700 rounded-lg p-3 text-red-400 text-sm">
                            {error}
                        </div>
                    )}

                    {result && (
                        <div className="mt-4 bg-emerald-900/30 border border-emerald-700 rounded-lg p-3">
                            <p className="text-emerald-400 text-sm font-medium mb-1">
                                {result.success ? "Task Completed" : "Task Failed"}
                            </p>
                            <pre className="text-xs text-gray-300 overflow-x-auto">
                                {JSON.stringify(result, null, 2)}
                            </pre>
                        </div>
                    )}
                </div>

                {similarTasks && similarTasks.length > 0 && (
                    <div className="bg-gray-900 rounded-xl border border-gray-800 p-6">
                        <h2 className="text-white font-semibold text-sm mb-3">Similar Completed Tasks</h2>
                        <div className="divide-y divide-gray-800">
                            {similarTasks.map(t => (
                                <div key={t.id} className="py-2">
                                    <p className="text-gray-300 text-xs">{t.description}</p>
                                    <div className="flex items-center gap-3 mt-1">
                                        <span className="text-gray-500 text-xs">
                                            By: {t.completed_by_operator?.name ?? 'Unknown'}
                                        </span>
                                        <span className="text-gray-600 text-xs">
                                            {new Date(t.completed_at).toLocaleDateString()}
                                        </span>
                                    </div>
                                    {t.completion_notes && (
                                        <p className="text-gray-500 text-xs mt-0.5 truncate">{t.completion_notes}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
