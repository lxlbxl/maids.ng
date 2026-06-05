import { useState } from "react";
import axios from "axios";

const AGENT_LABELS = {
    scout: "Scout", sentinel: "Sentinel", referee: "Referee",
    concierge: "Concierge", treasurer: "Treasurer", gatekeeper: "Gatekeeper",
    ambassador: "Ambassador", marketer: "Marketer", seo_content: "SEO Content",
    outreach: "Outreach",
};

const MODE_COLORS = {
    active:     "bg-green-500",
    supervised: "bg-yellow-400",
    paused:     "bg-gray-400",
    readonly:   "bg-blue-400",
};

const MODE_ICONS = {
    active: "\u25CF", supervised: "\u25D1", paused: "\u25A0", readonly: "\u25CB",
};

export default function AgentControlBar({ agents, agentList, onAgentUpdate }) {
    const [selectedAgent, setSelectedAgent] = useState(null);
    const [showModal, setShowModal]         = useState(false);
    const [modalAction, setModalAction]     = useState(null);
    const [reason, setReason]               = useState("");
    const [loading, setLoading]             = useState(false);

    const openModal = (agent, action) => {
        setSelectedAgent(agent);
        setModalAction(action);
        setReason("");
        setShowModal(true);
    };

    const handleAction = async () => {
        setLoading(true);
        try {
            const endpoint = `/admin/control-room/agents/${selectedAgent}/${modalAction}`;
            const res = await axios.post(endpoint, { reason, auto_resume_minutes: null });

            onAgentUpdate(prev => ({
                ...prev,
                [selectedAgent]: {
                    ...prev[selectedAgent],
                    mode: res.data.mode,
                    kill_switch: res.data.killed ?? false,
                }
            }));
            setShowModal(false);
        } catch (e) {
            alert("Action failed: " + (e.response?.data?.message ?? e.message));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="bg-gray-900 rounded-xl p-3 flex flex-wrap gap-2 items-center">
            <span className="text-gray-400 text-xs font-semibold uppercase tracking-wider mr-2">
                Agents
            </span>

            {agentList.map(agent => {
                const state      = agents[agent] ?? {};
                const isKilled   = state.kill_switch;
                const mode       = isKilled ? "killed" : (state.mode ?? "active");
                const colorClass = isKilled ? "bg-red-600" : (MODE_COLORS[mode] ?? "bg-gray-400");

                return (
                    <div key={agent} className="relative group">
                        <button
                            onClick={() => openModal(agent, null)}
                            className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-white text-xs font-medium cursor-pointer transition-all hover:scale-105 ${colorClass}`}
                        >
                            <span>{MODE_ICONS[mode] ?? "\u25C6"}</span>
                            <span>{AGENT_LABELS[agent]}</span>
                        </button>

                        <div className="hidden group-hover:block absolute top-8 left-0 z-50 bg-gray-800 rounded-lg shadow-xl border border-gray-700 w-44 py-1">
                            {mode !== "active" && !isKilled && (
                                <button onClick={(e) => { e.stopPropagation(); openModal(agent, "resume"); }}
                                    className="w-full text-left px-3 py-2 text-sm text-green-400 hover:bg-gray-700">
                                    Resume
                                </button>
                            )}
                            {(mode === "active" || mode === "supervised") && (
                                <button onClick={(e) => { e.stopPropagation(); openModal(agent, "pause"); }}
                                    className="w-full text-left px-3 py-2 text-sm text-yellow-400 hover:bg-gray-700">
                                    Pause
                                </button>
                            )}
                            <button onClick={(e) => { e.stopPropagation(); openModal(agent, "supervise"); }}
                                className="w-full text-left px-3 py-2 text-sm text-blue-400 hover:bg-gray-700">
                                Supervise
                            </button>
                            <hr className="border-gray-700 my-1" />
                            {!isKilled ? (
                                <button onClick={(e) => { e.stopPropagation(); openModal(agent, "kill-switch"); }}
                                    className="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-gray-700">
                                    Kill Switch
                                </button>
                            ) : (
                                <button onClick={(e) => { e.stopPropagation(); openModal(agent, "release-kill-switch"); }}
                                    className="w-full text-left px-3 py-2 text-sm text-green-400 hover:bg-gray-700">
                                    Release Kill Switch
                                </button>
                            )}
                        </div>
                    </div>
                );
            })}

            {showModal && (
                <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center" onClick={() => setShowModal(false)}>
                    <div className="bg-gray-800 rounded-xl p-6 w-96 shadow-2xl border border-gray-700" onClick={e => e.stopPropagation()}>
                        <h3 className="text-white font-semibold mb-1 capitalize">
                            {modalAction?.replace("-", " ")} {AGENT_LABELS[selectedAgent]}
                        </h3>
                        <p className="text-gray-400 text-sm mb-4">
                            This action takes effect immediately and is logged in the event feed.
                        </p>
                        <textarea
                            className="w-full bg-gray-700 text-white rounded-lg p-3 text-sm mb-4 resize-none"
                            rows={3}
                            placeholder="Reason (required)..."
                            value={reason}
                            onChange={e => setReason(e.target.value)}
                        />
                        <div className="flex gap-3 justify-end">
                            <button onClick={() => setShowModal(false)} className="px-4 py-2 text-sm text-gray-400 hover:text-white">
                                Cancel
                            </button>
                            <button
                                onClick={handleAction}
                                disabled={loading || !reason.trim()}
                                className="px-4 py-2 text-sm bg-emerald-600 text-white rounded-lg disabled:opacity-40 hover:bg-emerald-500"
                            >
                                {loading ? "Applying..." : "Confirm"}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
