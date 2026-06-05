import { useState } from "react";
import axios from "axios";

const JOB_TRIGGERS = [
    { label: "Refresh SEO Content",   job: "refresh_seo_content",   icon: "\uD83D\uDD0D" },
    { label: "Generate SEO Registry", job: "generate_seo_registry", icon: "\uD83D\uDCCB" },
    { label: "Scan Campaigns",        job: "scan_campaign_metrics", icon: "\uD83D\uDCCA" },
    { label: "Generate Social Post",  job: "generate_social_content", icon: "\u270D\uFE0F" },
    { label: "Salary Reminders",      job: "process_salary_reminders", icon: "\uD83D\uDCB0" },
    { label: "Sync Analytics",        job: "sync_post_analytics", icon: "\uD83D\uDCC8" },
];

export default function QueueHealthPanel({ health, agentList }) {
    const [triggering, setTriggering] = useState(null);

    const trigger = async (jobType, params = {}) => {
        setTriggering(jobType);
        try {
            await axios.post("/admin/control-room/trigger", { job_type: jobType, parameters: params });
            alert("\u2713 " + jobType + " queued");
        } catch (e) {
            alert("Failed: " + (e.response?.data?.message || e.message));
        } finally {
            setTriggering(null);
        }
    };

    const agents = health.health ?? {};
    const queues = health.queuedJobs ?? {};

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 flex flex-col" style={{ height: "520px" }}>
            <div className="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <h2 className="text-white font-semibold text-sm">Queue Health (24h)</h2>
                <span className="text-gray-500 text-xs">
                    {Object.values(queues).reduce((a, b) => a + b, 0)} jobs queued
                </span>
            </div>

            <div className="flex-1 overflow-y-auto">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="text-gray-500 border-b border-gray-800">
                            <th className="px-3 py-2 text-left">Agent</th>
                            <th className="px-3 py-2 text-right">Events</th>
                            <th className="px-3 py-2 text-right">Errors</th>
                            <th className="px-3 py-2 text-right">Avg ms</th>
                            <th className="px-3 py-2 text-right">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        {agentList.map(agent => {
                            const d = agents[agent] ?? {};
                            const errorRate = d.error_rate ?? 0;
                            const rowColor = errorRate > 10 ? "bg-red-900/20" : errorRate > 3 ? "bg-yellow-900/20" : "";

                            return (
                                <tr key={agent} className={`border-b border-gray-800/50 hover:bg-gray-800/50 ${rowColor}`}>
                                    <td className="px-3 py-2 text-gray-300 font-medium capitalize">{agent}</td>
                                    <td className="px-3 py-2 text-right text-gray-400">{d.total ?? 0}</td>
                                    <td className={`px-3 py-2 text-right ${d.errors > 0 ? 'text-red-400 font-semibold' : 'text-gray-500'}`}>
                                        {d.errors ?? 0}
                                    </td>
                                    <td className="px-3 py-2 text-right text-gray-400">{d.avg_duration ?? '\u2014'}</td>
                                    <td className="px-3 py-2 text-right text-gray-400">
                                        {d.total_cost ? `$${d.total_cost.toFixed(3)}` : '\u2014'}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>

                <div className="px-4 py-3 border-t border-gray-800">
                    <p className="text-gray-500 text-xs mb-2 font-semibold uppercase tracking-wider">Manual Triggers</p>
                    <div className="grid grid-cols-2 gap-1.5">
                        {JOB_TRIGGERS.map(({ label, job, icon }) => (
                            <button
                                key={job}
                                onClick={() => trigger(job)}
                                disabled={triggering === job}
                                className="flex items-center gap-1.5 px-2 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs rounded border border-gray-700 disabled:opacity-50 transition-colors"
                            >
                                <span>{icon}</span>
                                <span className="truncate">{label}</span>
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
