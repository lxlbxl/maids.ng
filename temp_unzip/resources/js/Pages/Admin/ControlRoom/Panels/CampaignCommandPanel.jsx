import { useState } from "react";
import axios from "axios";

export default function CampaignCommandPanel({ campaigns }) {
    const [toggling, setToggling] = useState(null);

    const toggleCampaign = async (campaign) => {
        setToggling(campaign.id);
        try {
            await axios.patch(`/admin/agent/campaigns/${campaign.id}/toggle`);
            window.location.reload();
        } finally {
            setToggling(null);
        }
    };

    const runNow = async (campaign) => {
        try {
            await axios.post(`/admin/agent/campaigns/${campaign.id}/run-now`);
            alert(`Campaign "${campaign.name}" queued`);
        } catch (e) {
            alert("Failed: " + (e.response?.data?.message || e.message));
        }
    };

    const campaignList = Array.isArray(campaigns) ? campaigns : (campaigns?.data ?? []);

    return (
        <div className="bg-gray-900 rounded-xl border border-gray-800 flex flex-col" style={{ height: "240px" }}>
            <div className="px-4 py-3 border-b border-gray-800">
                <h2 className="text-white font-semibold text-sm">Campaign Command</h2>
            </div>

            <div className="flex-1 overflow-y-auto divide-y divide-gray-800">
                {campaignList.length === 0 ? (
                    <div className="flex items-center justify-center h-full text-gray-600 text-sm">
                        No campaigns yet
                    </div>
                ) : (
                    campaignList.map(c => (
                        <div key={c.id} className="px-3 py-2 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${c.is_active ? 'bg-green-500' : 'bg-gray-600'}`} />
                                <span className="text-gray-300 text-xs truncate max-w-36">{c.name}</span>
                                <span className="text-gray-600 text-xs">{c.trigger_type}</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <button onClick={() => runNow(c)}
                                    className="text-xs text-blue-400 hover:text-blue-300 px-1">
                                    Run
                                </button>
                                <button onClick={() => toggleCampaign(c)} disabled={toggling === c.id}
                                    className={`text-xs px-1 ${c.is_active ? 'text-yellow-400 hover:text-yellow-300' : 'text-green-400 hover:text-green-300'}`}>
                                    {c.is_active ? 'Pause' : 'Start'}
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
