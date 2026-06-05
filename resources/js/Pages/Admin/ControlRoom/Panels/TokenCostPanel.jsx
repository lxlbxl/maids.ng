import { useState, useEffect } from "react";
import axios from "axios";

export default function TokenCostPanel({ todayCost }) {
    const [analytics, setAnalytics] = useState(null);
    const [range, setRange]         = useState("7d");

    useEffect(() => {
        axios.get(`/admin/control-room/cost-analytics?range=${range}`)
            .then(res => setAnalytics(res.data));
    }, [range]);

    const costData = typeof todayCost === 'object' && todayCost !== null ? todayCost : {};
    const todayTotal = Object.values(costData).reduce((sum, d) => sum + (d.total_cost_usd ?? 0), 0);
    const todayTokens = Object.values(costData).reduce((sum, d) => sum + (d.total_tokens ?? 0), 0);

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 flex flex-col" style={{ height: "240px" }}>
            <div className="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <h2 className="text-white font-semibold text-sm">Token Cost Tracker</h2>
                <select value={range} onChange={e => setRange(e.target.value)}
                    className="bg-gray-800 text-gray-300 text-xs rounded px-2 py-1 border border-gray-700">
                    <option value="1d">Today</option>
                    <option value="7d">7 Days</option>
                    <option value="30d">30 Days</option>
                </select>
            </div>

            <div className="flex-1 overflow-y-auto p-3">
                <div className="grid grid-cols-2 gap-2 mb-3">
                    <div className="bg-gray-800 rounded p-2 text-center">
                        <div className="text-white font-bold text-sm">${Number(todayTotal || 0).toFixed(3)}</div>
                        <div className="text-gray-500 text-xs">Today's Cost</div>
                    </div>
                    <div className="bg-gray-800 rounded p-2 text-center">
                        <div className="text-white font-bold text-sm">{(Number(todayTokens || 0) / 1000).toFixed(1)}k</div>
                        <div className="text-gray-500 text-xs">Tokens Used</div>
                    </div>
                </div>

                {analytics && (
                    <div className="space-y-1">
                        {analytics.byAgent?.map(a => (
                            <div key={a.agent_name} className="flex items-center gap-2">
                                <span className="text-gray-400 text-xs w-24 truncate capitalize">{a.agent_name}</span>
                                <div className="flex-1 bg-gray-700 rounded-full h-1.5">
                                    <div
                                        className="bg-emerald-500 h-1.5 rounded-full"
                                        style={{ width: `${Math.min(100, (a.total_cost / (analytics.byAgent[0]?.total_cost || 1)) * 100)}%` }}
                                    />
                                </div>
                                <span className="text-gray-400 text-xs w-14 text-right">${(parseFloat(a.total_cost) || 0).toFixed(3)}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
