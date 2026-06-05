import { useState, useEffect, useRef } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import { usePage } from "@inertiajs/react";
import LiveFeedPanel from "./Panels/LiveFeedPanel";
import QueueHealthPanel from "./Panels/QueueHealthPanel";
import CampaignCommandPanel from "./Panels/CampaignCommandPanel";
import TokenCostPanel from "./Panels/TokenCostPanel";
import HumanTaskPanel from "./Panels/HumanTaskPanel";
import AgentControlBar from "./Components/AgentControlBar";

export default function ControlRoomIndex() {
    const { overrideStates, recentEvents, hitlQueue, todayCost,
            campaigns, agentList, lastEventId } = usePage().props;

    const [events, setEvents]           = useState(recentEvents);
    const [queueHealth, setQueueHealth] = useState({});
    const [hitlTasks, setHitlTasks]     = useState(hitlQueue);
    const [agents, setAgents]           = useState(overrideStates);
    const [pendingCount, setPending]    = useState(hitlQueue.data?.length ?? 0);
    const sseRef = useRef(null);

    useEffect(() => {
        let lastId = lastEventId;

        const connect = () => {
            const url = `/admin/control-room/stream?last_id=${lastId}`;
            const sse  = new EventSource(url);
            sseRef.current = sse;

            sse.addEventListener("agent_event", (e) => {
                const event = JSON.parse(e.data);
                lastId = event.id;
                setEvents(prev => [event, ...prev].slice(0, 200));

                if (event.requires_approval && event.approved === null) {
                    setPending(p => p + 1);
                }
            });

            sse.addEventListener("queue_health", (e) => {
                const health = JSON.parse(e.data);
                setQueueHealth(health);
                setPending(health.pendingHitl ?? 0);
            });

            sse.onerror = () => {
                sse.close();
                setTimeout(connect, 3000);
            };
        };

        connect();
        return () => sseRef.current?.close();
    }, []);

    return (
        <AdminLayout title="Agent Control Room">
            <AgentControlBar
                agents={agents}
                agentList={agentList}
                onAgentUpdate={setAgents}
            />

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-4">
                <LiveFeedPanel events={events} />
                <QueueHealthPanel health={queueHealth} agentList={agentList} />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-4">
                <CampaignCommandPanel campaigns={campaigns} />
                <TokenCostPanel todayCost={todayCost} />
                <HumanTaskPanel
                    tasks={hitlTasks}
                    pendingCount={pendingCount}
                    onTaskComplete={(taskId) => {
                        setHitlTasks(prev => ({
                            ...prev,
                            data: prev.data?.filter(t => t.id !== taskId)
                        }));
                        setPending(p => Math.max(0, p - 1));
                    }}
                />
            </div>
        </AdminLayout>
    );
}
