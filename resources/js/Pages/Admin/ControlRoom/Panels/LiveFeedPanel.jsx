import { useState } from "react";
import axios from "axios";

const SEVERITY_COLORS = {
    success: "border-l-green-500 bg-green-500/5",
    warning: "border-l-yellow-400 bg-yellow-400/5",
    error:   "border-l-red-500 bg-red-500/5",
    pending: "border-l-purple-500 bg-purple-500/5",
    info:    "border-l-blue-400 bg-blue-400/5",
};

const AGENT_COLORS = {
    scout: "text-emerald-400", sentinel: "text-yellow-400", referee: "text-orange-400",
    concierge: "text-blue-400", treasurer: "text-green-400", gatekeeper: "text-purple-400",
    ambassador: "text-pink-400", marketer: "text-cyan-400",
    seo_content: "text-lime-400", outreach: "text-indigo-400", system: "text-gray-400",
};

export default function LiveFeedPanel({ events }) {
    const [filter, setFilter]         = useState("all");
    const [expandedId, setExpandedId] = useState(null);
    const [detail, setDetail]         = useState({});

    const filtered = filter === "all" ? events : events.filter(e => e.agent_name === filter);

    const loadDetail = async (eventId) => {
        if (detail[eventId]) {
            setExpandedId(expandedId === eventId ? null : eventId);
            return;
        }
        const res = await axios.get(`/admin/control-room/events/${eventId}`);
        setDetail(prev => ({ ...prev, [eventId]: res.data }));
        setExpandedId(eventId);
    };

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 flex flex-col" style={{ height: "520px" }}>
            <div className="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                <div className="flex items-center gap-2">
                    <span className="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
                    <h2 className="text-white font-semibold text-sm">Live Agent Feed</h2>
                    <span className="text-gray-500 text-xs">{filtered.length} events</span>
                </div>
                <select
                    value={filter}
                    onChange={e => setFilter(e.target.value)}
                    className="bg-gray-800 text-gray-300 text-xs rounded px-2 py-1 border border-gray-700"
                >
                    <option value="all">All Agents</option>
                    {["scout","sentinel","referee","concierge","treasurer",
                      "gatekeeper","ambassador","marketer","seo_content","outreach"].map(a => (
                        <option key={a} value={a}>{a}</option>
                    ))}
                </select>
            </div>

            <div className="flex-1 overflow-y-auto px-2 py-2 space-y-1">
                {filtered.map(event => (
                    <div key={event.id}>
                        <div
                            onClick={() => loadDetail(event.id)}
                            className={`border-l-2 px-3 py-2 rounded-r cursor-pointer hover:opacity-90 transition-opacity ${SEVERITY_COLORS[event.severity] ?? SEVERITY_COLORS.info}`}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-0.5">
                                        <span className={`text-xs font-semibold uppercase ${AGENT_COLORS[event.agent_name] ?? 'text-gray-400'}`}>
                                            {event.agent_name}
                                        </span>
                                        <span className="text-gray-600 text-xs">{event.event_type}</span>
                                        {event.triggered_by_human && (
                                            <span className="text-xs bg-orange-500/20 text-orange-400 px-1 rounded">HUMAN</span>
                                        )}
                                        {event.requires_approval && event.approved === null && (
                                            <span className="text-xs bg-purple-500/20 text-purple-400 px-1 rounded animate-pulse">PENDING APPROVAL</span>
                                        )}
                                    </div>
                                    <p className="text-gray-300 text-xs leading-tight truncate">{event.summary}</p>
                                </div>
                                <div className="flex-shrink-0 text-right">
                                    <div className="text-gray-500 text-xs">{event.created_at}</div>
                                    {event.estimated_cost_usd && (
                                        <div className="text-gray-600 text-xs">${parseFloat(event.estimated_cost_usd).toFixed(4)}</div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {expandedId === event.id && detail[event.id] && (
                            <div className="ml-3 bg-gray-800 rounded-b border border-gray-700 border-t-0 p-3">
                                <pre className="text-xs text-gray-300 overflow-x-auto whitespace-pre-wrap max-h-48">
                                    {JSON.stringify(detail[event.id].detail, null, 2)}
                                </pre>
                                {detail[event.id].related_model && (
                                    <a
                                        href={`/admin/${detail[event.id].related_model?.toLowerCase()}/${detail[event.id].related_id}`}
                                        className="mt-2 inline-block text-xs text-blue-400 hover:underline"
                                    >
                                        View {detail[event.id].related_model} #{detail[event.id].related_id} &rarr;
                                    </a>
                                )}
                            </div>
                        )}
                    </div>
                ))}

                {filtered.length === 0 && (
                    <div className="flex items-center justify-center h-full text-gray-600 text-sm">
                        No events yet
                    </div>
                )}
            </div>
        </div>
    );
}
