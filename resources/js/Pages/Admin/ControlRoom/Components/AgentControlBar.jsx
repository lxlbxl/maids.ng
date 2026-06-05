import { useState } from "react";
import axios from "axios";

const AGENT_LABELS = {
    scout: "Scout", sentinel: "Sentinel", referee: "Referee",
    concierge: "Concierge", treasurer: "Treasurer", gatekeeper: "Gatekeeper",
    ambassador: "Ambassador", marketer: "Marketer", seo_content: "SEO Content",
    outreach: "Outreach",
};

const MODE_COLORS = {
    active:     "bg-teal-light",
    supervised: "bg-warning",
    paused:     "bg-muted",
    readonly:   "bg-info",
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
    const [testResults, setTestResults]     = useState({});

    const testAgent = async (agent) => {
        setTestResults(prev => ({ ...prev, [agent]: { loading: true } }));
        try {
            const res = await axios.post('/admin/control-room/test-agent', { agent });
            setTestResults(prev => ({ ...prev, [agent]: { loading: false, success: true, ...res.data } }));
        } catch (e) {
            setTestResults(prev => ({
                ...prev,
                [agent]: {
                    loading: false,
                    success: false,
                    error: e.response?.data?.error || e.message,
                    file: e.response?.data?.file,
                    logs: e.response?.data?.logs || [],
                }
            }));
        }
    };

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
        <div className="bg-teal-deep rounded-brand-lg p-4 flex flex-wrap gap-3 items-center border border-white/5 shadow-brand-2">
            <span className="text-white/20 text-[10px] font-mono uppercase tracking-[0.25em] mr-2 font-bold">
                Operational Status
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
                            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-brand-sm text-white text-[11px] font-mono uppercase tracking-widest cursor-pointer transition-all hover:scale-105 hover:shadow-lg ${colorClass} border border-white/10`}
                        >
                            <span className="opacity-70">{MODE_ICONS[mode] ?? "\u25C6"}</span>
                            <span>{AGENT_LABELS[agent]}</span>
                        </button>

                        <div className="hidden group-hover:block absolute top-full left-0 z-50 pt-3">
                            <div className="bg-teal-deep rounded-brand-md shadow-brand-3 border border-white/10 w-60 py-2 backdrop-blur-md">
                            {mode !== "active" && !isKilled && (
                                <button onClick={(e) => { e.stopPropagation(); openModal(agent, "resume"); }}
                                    className="w-full text-left px-4 py-2.5 text-xs font-medium text-teal-light hover:bg-white/5 transition-colors">
                                    Resume Operational Flow
                                </button>
                            )}
                            {(mode === "active" || mode === "supervised") && (
                                <button onClick={(e) => { e.stopPropagation(); openModal(agent, "pause"); }}
                                    className="w-full text-left px-4 py-2.5 text-xs font-medium text-warning hover:bg-white/5 transition-colors">
                                    Interrupt Flow (Pause)
                                </button>
                            )}
                            <button onClick={(e) => { e.stopPropagation(); openModal(agent, "supervise"); }}
                                className="w-full text-left px-4 py-2.5 text-xs font-medium text-info hover:bg-white/5 transition-colors">
                                Active Supervision Mode
                            </button>
                            <hr className="border-white/5 my-1.5" />
                            <button onClick={(e) => { e.stopPropagation(); testAgent(agent); }}
                                disabled={testResults[agent]?.loading}
                                className="w-full text-left px-4 py-2.5 text-xs font-medium text-teal-light/70 hover:bg-white/5 disabled:opacity-40 transition-colors">
                                {testResults[agent]?.loading ? 'Initializing Diagnostic...' : 'Run Diagnostics'}
                            </button>
                            {testResults[agent] && !testResults[agent].loading && (
                                <div className={`px-4 py-2 text-[10px] font-mono ${testResults[agent].success ? 'text-teal-light' : 'text-danger'} bg-black/20 mx-2 rounded mb-2`}>
                                    {testResults[agent].success
                                        ? `STATUS_OK (${testResults[agent].duration_ms}ms)`
                                        : `ERROR: ${testResults[agent].error?.substring(0, 80)}`}
                                </div>
                            )}
                            <hr className="border-white/5 my-1.5" />
                            {!isKilled ? (
                                <button onClick={(e) => { e.stopPropagation(); openModal(agent, "kill-switch"); }}
                                    className="w-full text-left px-4 py-2.5 text-xs font-medium text-danger hover:bg-white/5 transition-colors">
                                    Emergency Kill Switch
                                </button>
                            ) : (
                                <button onClick={(e) => { e.stopPropagation(); openModal(agent, "release-kill-switch"); }}
                                    className="w-full text-left px-4 py-2.5 text-xs font-medium text-teal-light hover:bg-white/5 transition-colors">
                                    Restore Systems
                                </button>
                            )}
                            </div>
                        </div>
                    </div>
                );
            })}

            {showModal && (
                <div className="fixed inset-0 bg-teal-deep/80 backdrop-blur-md z-50 flex items-center justify-center" onClick={() => setShowModal(false)}>
                    <div className="bg-teal-deep rounded-brand-lg p-8 w-[450px] shadow-brand-3 border border-white/10" onClick={e => e.stopPropagation()}>
                        <h3 className="text-white text-xl font-display font-bold mb-2 capitalize flex items-center gap-3">
                            <span className="text-teal-light">/</span> {modalAction?.replace("-", " ")} {AGENT_LABELS[selectedAgent]}
                        </h3>
                        <p className="text-white/40 text-sm mb-6 leading-relaxed">
                            Authorized personnel only. This command will be logged and dispatched to the agent matrix immediately.
                        </p>
                        <textarea
                            className="w-full bg-white/5 border border-white/10 text-white rounded-brand-md p-4 text-sm mb-6 resize-none focus:border-teal-light outline-none transition-colors"
                            rows={4}
                            placeholder="State authorization reason..."
                            value={reason}
                            onChange={e => setReason(e.target.value)}
                        />
                        <div className="flex gap-4 justify-end items-center">
                            <button onClick={() => setShowModal(false)} className="px-4 py-2 text-xs font-mono uppercase tracking-widest text-white/40 hover:text-white transition-colors">
                                Abort
                            </button>
                            <button
                                onClick={handleAction}
                                disabled={loading || !reason.trim()}
                                className="px-8 py-3 text-xs font-mono uppercase tracking-widest bg-teal-light text-teal-deep font-bold rounded-brand-md disabled:opacity-20 hover:bg-white transition-all shadow-lg active:scale-95"
                            >
                                {loading ? "Executing..." : "Dispatch"}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
