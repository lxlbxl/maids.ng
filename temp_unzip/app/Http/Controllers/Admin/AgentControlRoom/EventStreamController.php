<?php

namespace App\Http\Controllers\Admin\AgentControlRoom;

use App\Http\Controllers\Controller;
use App\Models\AgentEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventStreamController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        $lastEventId = (int) ($request->header('Last-Event-ID') ?? $request->query('last_id', 0));

        return new StreamedResponse(function () use ($lastEventId) {
            if (ob_get_level()) {
                ob_end_flush();
            }

            echo "retry: 3000\n\n";
            flush();

            $lastSentId  = $lastEventId;
            $iterations  = 0;
            $maxIterations = 150;

            while ($iterations < $maxIterations) {
                $newEvents = AgentEvent::where('id', '>', $lastSentId)
                    ->orderBy('id')
                    ->take(20)
                    ->get([
                        'id', 'agent_name', 'event_type', 'severity', 'summary',
                        'triggered_by_human', 'related_user_id', 'related_model',
                        'related_id', 'requires_approval', 'total_tokens',
                        'estimated_cost_usd', 'channel', 'created_at',
                    ]);

                foreach ($newEvents as $event) {
                    $data = $event->toArray();
                    $data['created_at'] = $event->created_at->diffForHumans();

                    echo "id: {$event->id}\n";
                    echo "event: agent_event\n";
                    echo "data: " . json_encode($data) . "\n\n";

                    $lastSentId = $event->id;
                    flush();
                }

                if ($iterations % 5 === 0) {
                    $health = $this->getQueueHealth();
                    echo "event: queue_health\n";
                    echo "data: " . json_encode($health) . "\n\n";
                    flush();
                }

                if ($iterations % 3 === 0) {
                    echo ": heartbeat\n\n";
                    flush();
                }

                if (connection_aborted()) {
                    break;
                }

                sleep(2);
                $iterations++;
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    private function getQueueHealth(): array
    {
        $agents = ['scout', 'sentinel', 'referee', 'concierge', 'treasurer',
                   'gatekeeper', 'ambassador', 'marketer', 'seo_content', 'outreach'];

        $health = [];
        $since  = now()->subHours(24);

        foreach ($agents as $agent) {
            $events = AgentEvent::where('agent_name', $agent)
                ->where('created_at', '>=', $since)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN severity = 'error' THEN 1 ELSE 0 END) as errors,
                    SUM(CASE WHEN severity = 'success' THEN 1 ELSE 0 END) as successes,
                    AVG(duration_ms) as avg_duration_ms,
                    SUM(total_tokens) as total_tokens,
                    SUM(estimated_cost_usd) as total_cost
                ")
                ->first();

            $health[$agent] = [
                'total'          => $events->total ?? 0,
                'errors'         => $events->errors ?? 0,
                'successes'      => $events->successes ?? 0,
                'avg_duration'   => round($events->avg_duration_ms ?? 0),
                'total_tokens'   => $events->total_tokens ?? 0,
                'total_cost'     => round($events->total_cost ?? 0, 4),
                'error_rate'     => $events->total > 0
                    ? round(($events->errors / $events->total) * 100, 1)
                    : 0,
            ];
        }

        $queuedJobs = \DB::table('jobs')
            ->selectRaw("queue, COUNT(*) as count")
            ->groupBy('queue')
            ->pluck('count', 'queue')
            ->toArray();

        $pendingHitl = \App\Models\HumanTask::pending()->count();

        return compact('health', 'queuedJobs', 'pendingHitl');
    }
}
