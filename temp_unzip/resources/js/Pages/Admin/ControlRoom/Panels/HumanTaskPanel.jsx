import { useState } from "react";
import axios from "axios";

const TASK_ICONS = {
    match_employer:      "\uD83D\uDD0D",
    send_message:        "\uD83D\uDCAC",
    verify_nin:          "\uD83E\uDEAA",
    process_payout:      "\uD83D\uDCB8",
    resolve_dispute:     "\u2696\uFE0F",
    review_maid_quality: "\u2B50",
    generate_content:    "\u270D\uFE0F",
    generate_seo_content:"\uD83D\uDD0E",
    send_outreach:       "\uD83D\uDCE3",
    approve_hitl:        "\u2705",
};

const PRIORITY_LABELS = { 1: "Urgent", 2: "High", 3: "Normal", 4: "Low" };
const PRIORITY_COLORS = {
    1: "text-red-400 bg-red-900/30",
    2: "text-orange-400 bg-orange-900/30",
    3: "text-blue-400 bg-blue-900/30",
    4: "text-gray-400 bg-gray-800",
};

function TaskExecutionForm({ task, inputs, onChange, onExecute, onCancel, executing }) {
    const set = (key, value) => onChange(prev => ({ ...prev, [key]: value }));

    const renderFields = () => {
        switch (task.task_type) {
            case "send_message":
            case "send_outreach":
                return (
                    <textarea
                        className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none"
                        rows={3}
                        placeholder="Message to send..."
                        value={inputs.message ?? ""}
                        onChange={e => set("message", e.target.value)}
                    />
                );
            case "verify_nin":
                return (
                    <div className="space-y-2">
                        <select
                            className="w-full bg-gray-700 text-white text-xs p-2 rounded"
                            value={inputs.decision ?? "approved"}
                            onChange={e => set("decision", e.target.value)}
                        >
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                            <option value="manual_review">Refer for Manual Review</option>
                        </select>
                        <textarea className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none"
                            rows={2} placeholder="Review notes (optional)"
                            value={inputs.notes ?? ""} onChange={e => set("notes", e.target.value)} />
                    </div>
                );
            case "process_payout":
                return (
                    <div className="bg-gray-700/50 rounded p-2 text-xs text-gray-300 space-y-1">
                        <div>Amount: <strong className="text-white">&#8358;{Number(task.task_payload?.amount || 0).toLocaleString()}</strong></div>
                        <div>Maid ID: <strong className="text-white">#{task.task_payload?.maid_id}</strong></div>
                        <textarea className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none mt-2"
                            rows={2} placeholder="Approval notes..."
                            value={inputs.notes ?? ""} onChange={e => set("notes", e.target.value)} />
                    </div>
                );
            case "resolve_dispute":
                return (
                    <div className="space-y-2">
                        <textarea className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none"
                            rows={3} placeholder="Resolution decision..."
                            value={inputs.resolution ?? ""} onChange={e => set("resolution", e.target.value)} />
                        <input type="number" placeholder="Refund amount (&#8358;), 0 if none"
                            className="w-full bg-gray-700 text-white text-xs p-2 rounded"
                            value={inputs.refund_amount ?? ""} onChange={e => set("refund_amount", e.target.value)} />
                    </div>
                );
            case "approve_hitl":
                return (
                    <div className="space-y-2">
                        <select className="w-full bg-gray-700 text-white text-xs p-2 rounded"
                            value={inputs.decision ?? "approved"} onChange={e => set("decision", e.target.value)}>
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                        </select>
                        <textarea className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none"
                            rows={2} placeholder="Decision note..."
                            value={inputs.note ?? ""} onChange={e => set("note", e.target.value)} />
                    </div>
                );
            default:
                return (
                    <div className="space-y-2">
                        <pre className="text-xs text-gray-400 bg-gray-700/50 p-2 rounded overflow-x-auto max-h-20">
                            {JSON.stringify(task.task_payload, null, 2)}
                        </pre>
                        <textarea className="w-full bg-gray-700 text-white text-xs p-2 rounded resize-none"
                            rows={2} placeholder="Completion notes..."
                            value={inputs.notes ?? ""} onChange={e => set("notes", e.target.value)} />
                    </div>
                );
        }
    };

    return (
        <div className="mt-2 bg-gray-800 rounded border border-gray-700 p-3 space-y-2">
            {renderFields()}
            <div className="flex gap-2">
                <button onClick={onExecute} disabled={executing}
                    className="px-3 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-500 text-white rounded font-medium disabled:opacity-50">
                    {executing ? "Executing..." : "Execute Task"}
                </button>
                <button onClick={onCancel} className="px-3 py-1.5 text-xs text-gray-400 hover:text-white">
                    Cancel
                </button>
            </div>
        </div>
    );
}

export default function HumanTaskPanel({ tasks, pendingCount, onTaskComplete }) {
    const [selectedTask, setSelectedTask] = useState(null);
    const [inputs, setInputs]             = useState({});
    const [executing, setExecuting]       = useState(false);

    const taskList = Array.isArray(tasks) ? tasks : (tasks?.data ?? []);

    const executeTask = async (task) => {
        setExecuting(true);
        try {
            const res = await axios.post(`/admin/control-room/hitl/${task.id}/execute`, { inputs });
            if (res.data.success) {
                onTaskComplete(task.id);
                setSelectedTask(null);
            } else {
                alert("Execution failed: " + res.data.error);
            }
        } finally {
            setExecuting(false);
        }
    };

    const skipTask = async (task) => {
        await axios.post(`/admin/control-room/hitl/${task.id}/skip`, { reason: "Skipped by operator" });
        onTaskComplete(task.id);
    };

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 flex flex-col" style={{ height: "520px" }}>
            <div className="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <h2 className="text-white font-semibold text-sm">Human Task Queue</h2>
                    {pendingCount > 0 && (
                        <span className="bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                            {pendingCount}
                        </span>
                    )}
                </div>
            </div>

            <div className="flex-1 overflow-y-auto">
                {taskList.length === 0 ? (
                    <div className="flex items-center justify-center h-full text-gray-600 text-sm">
                        No tasks pending
                    </div>
                ) : (
                    <div className="divide-y divide-gray-800">
                        {taskList.map(task => (
                            <div key={task.id} className="px-4 py-3">
                                <div className="flex items-start justify-between gap-2 mb-2">
                                    <div className="flex items-start gap-2 flex-1">
                                        <span className="text-lg">{TASK_ICONS[task.task_type] ?? "\uD83D\uDCCB"}</span>
                                        <div>
                                            <p className="text-white text-xs font-medium">{task.description}</p>
                                            <div className="flex items-center gap-2 mt-0.5">
                                                <span className={`text-xs px-1.5 py-0.5 rounded ${PRIORITY_COLORS[task.priority]}`}>
                                                    {PRIORITY_LABELS[task.priority]}
                                                </span>
                                                <span className="text-gray-500 text-xs capitalize">{task.reason}</span>
                                                <span className="text-gray-600 text-xs">
                                                    Agent: {task.agent_name}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    {task.due_by && (
                                        <span className={`text-xs flex-shrink-0 ${new Date(task.due_by) < new Date() ? 'text-red-400' : 'text-gray-500'}`}>
                                            Due: {new Date(task.due_by).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}
                                        </span>
                                    )}
                                </div>

                                <div className="flex gap-2">
                                    <button
                                        onClick={() => setSelectedTask(selectedTask?.id === task.id ? null : task)}
                                        className="px-2 py-1 text-xs bg-emerald-700 hover:bg-emerald-600 text-white rounded transition-colors"
                                    >
                                        Execute
                                    </button>
                                    <button
                                        onClick={() => skipTask(task)}
                                        className="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors"
                                    >
                                        Skip
                                    </button>
                                </div>

                                {selectedTask?.id === task.id && (
                                    <TaskExecutionForm
                                        task={task}
                                        inputs={inputs}
                                        onChange={setInputs}
                                        onExecute={() => executeTask(task)}
                                        onCancel={() => setSelectedTask(null)}
                                        executing={executing}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
