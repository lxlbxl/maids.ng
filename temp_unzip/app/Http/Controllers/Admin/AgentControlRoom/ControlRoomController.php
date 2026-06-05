<?php

namespace App\Http\Controllers\Admin\AgentControlRoom;

use App\Http\Controllers\Controller;
use App\Models\{AgentEvent, AgentOverride, HumanTask};
use App\Services\{AgentOverrideService, HumanExecutionService, AgentEventLogger};
use Illuminate\Http\Request;
use Inertia\Inertia;

class ControlRoomController extends Controller
{
    public function __construct(
        private AgentOverrideService $overrides,
        private HumanExecutionService $executor,
    ) {}

    public function index(): \Inertia\Response
    {
        $agentList = ['scout', 'sentinel', 'referee', 'concierge', 'treasurer',
                      'gatekeeper', 'ambassador', 'marketer', 'seo_content', 'outreach'];

        $overrideStates = AgentOverride::whereIn('agent_name', $agentList)
            ->get(['agent_name', 'mode', 'kill_switch', 'override_reason',
                   'daily_spend_cap_usd', 'current_daily_spend_usd'])
            ->keyBy('agent_name');

        $recentEvents = AgentEvent::with('relatedUser:id,name')
            ->orderByDesc('id')
            ->take(50)
            ->get([
                'id', 'agent_name', 'event_type', 'severity', 'summary',
                'triggered_by_human', 'related_user_id', 'related_model',
                'related_id', 'requires_approval', 'total_tokens',
                'estimated_cost_usd', 'channel', 'created_at',
            ])
            ->map(fn($e) => array_merge($e->toArray(), [
                'created_at' => $e->created_at->diffForHumans(),
            ]));

        $hitlQueue = HumanTask::pending()
            ->with('relatedUser:id,name', 'triggerEvent:id,summary')
            ->orderBy('priority')
            ->orderBy('created_at')
            ->take(20)
            ->get();

        $todayCost = AgentEvent::where('created_at', '>=', now()->startOfDay())
            ->selectRaw("
                SUM(estimated_cost_usd) as total_cost_usd,
                SUM(total_tokens) as total_tokens,
                agent_name,
                COUNT(*) as event_count
            ")
            ->groupBy('agent_name')
            ->get()
            ->keyBy('agent_name');

        $campaigns = \App\Models\AgentCampaign::with([
            'logs' => fn($q) => $q->where('sent_at', '>=', now()->subDays(7))
        ])
        ->orderBy('trigger_type')
        ->get(['id', 'name', 'slug', 'trigger_type', 'preferred_channel', 'is_active']);

        return Inertia::render('Admin/ControlRoom/Index', [
            'overrideStates' => $overrideStates,
            'recentEvents'   => $recentEvents,
            'hitlQueue'      => $hitlQueue,
            'todayCost'      => $todayCost,
            'campaigns'      => $campaigns,
            'agentList'      => $agentList,
            'lastEventId'    => $recentEvents->first()['id'] ?? 0,
        ]);
    }

    public function pauseAgent(Request $request, string $agentName): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'reason'               => 'required|string|max:500',
            'auto_resume_minutes'  => 'nullable|integer|min:5|max:1440',
        ]);

        $override = $this->overrides->pause(
            $agentName,
            auth()->user(),
            $request->reason,
            $request->auto_resume_minutes
        );

        return response()->json(['success' => true, 'mode' => $override->mode]);
    }

    public function resumeAgent(string $agentName): \Illuminate\Http\JsonResponse
    {
        $override = $this->overrides->resume($agentName, auth()->user());
        return response()->json(['success' => true, 'mode' => $override->mode]);
    }

    public function superviseAgent(Request $request, string $agentName): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'reason'       => 'required|string|max:500',
            'action_types' => 'nullable|array',
        ]);

        $override = $this->overrides->supervise(
            $agentName,
            auth()->user(),
            $request->reason,
            $request->action_types
        );

        return response()->json(['success' => true, 'mode' => $override->mode]);
    }

    public function killSwitch(Request $request, string $agentName): \Illuminate\Http\JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $this->overrides->killSwitch($agentName, auth()->user(), $request->reason);
        return response()->json(['success' => true, 'killed' => true]);
    }

    public function releaseKillSwitch(string $agentName): \Illuminate\Http\JsonResponse
    {
        $override = $this->overrides->releaseKillSwitch($agentName, auth()->user());
        return response()->json(['success' => true, 'mode' => $override->mode]);
    }

    public function updateSpendCap(Request $request, string $agentName): \Illuminate\Http\JsonResponse
    {
        $request->validate(['cap_usd' => 'required|numeric|min:0']);
        $this->overrides->updateSpendCap($agentName, auth()->user(), $request->cap_usd);
        return response()->json(['success' => true]);
    }

    public function hitlQueue(Request $request): \Illuminate\Http\JsonResponse
    {
        $tasks = HumanTask::pending()
            ->with('relatedUser:id,name', 'triggerEvent:id,summary,agent_name')
            ->when($request->agent, fn($q, $a) => $q->where('agent_name', $a))
            ->orderBy('priority')
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json($tasks);
    }

    public function executeHitlTask(Request $request, HumanTask $task): \Illuminate\Http\JsonResponse
    {
        $request->validate(['inputs' => 'nullable|array']);
        $result = $this->executor->execute($task, auth()->user(), $request->inputs ?? []);
        return response()->json($result);
    }

    public function skipHitlTask(Request $request, HumanTask $task): \Illuminate\Http\JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        $task->update([
            'status'           => 'skipped',
            'completed_by'     => auth()->id(),
            'completed_at'     => now(),
            'completion_notes' => 'Skipped: ' . ($request->reason ?? 'No reason given'),
        ]);
        return response()->json(['success' => true]);
    }

    public function reassignHitlTask(Request $request, HumanTask $task): \Illuminate\Http\JsonResponse
    {
        $request->validate(['assign_to_user_id' => 'required|exists:users,id']);
        $task->update([
            'assigned_to' => $request->assign_to_user_id,
            'status'      => 'assigned',
            'assigned_at' => now(),
        ]);
        return response()->json(['success' => true]);
    }

    public function eventDetail(AgentEvent $event): \Illuminate\Http\JsonResponse
    {
        $event->load('triggeredBy:id,name', 'relatedUser:id,name', 'approvedByUser:id,name');
        return response()->json($event);
    }

    public function triggerAgentJob(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'job_type'   => 'required|string',
            'parameters' => 'nullable|array',
        ]);

        $jobClass = match ($request->job_type) {
            'scan_campaign_metrics'   => null,
            'generate_social_content' => null,
            'generate_seo_content'    => null,
            'process_salary_reminders' => null,
            'refresh_seo_content'     => \App\Jobs\RefreshSeoContent::class,
            'generate_seo_registry'   => \App\Jobs\GenerateSeoPageRegistry::class,
            default => null,
        };

        if ($jobClass) {
            dispatch(new $jobClass())->onQueue('default');
        } else {
            dispatch(function () use ($request) {
                $logger = app(\App\Services\AgentEventLogger::class);
                $logger->log('system', 'job.manually_triggered', 'info',
                    "Manual trigger: {$request->job_type}",
                    ['job_type' => $request->job_type, 'parameters' => $request->parameters ?? []],
                    ['triggered_by_human' => true, 'triggered_by_user_id' => auth()->id()]
                );
            })->onQueue('default');
        }

        app(AgentEventLogger::class)->log(
            'system',
            'job.manually_triggered',
            'info',
            "Operator " . auth()->user()->name . " manually triggered: {$request->job_type}",
            ['job_type' => $request->job_type, 'parameters' => $request->parameters ?? []],
            ['triggered_by_human' => true, 'triggered_by_user_id' => auth()->id()]
        );

        return response()->json(['success' => true, 'job' => $request->job_type]);
    }

    public function costAnalytics(Request $request): \Illuminate\Http\JsonResponse
    {
        $range = $request->range ?? '7d';
        $since = match ($range) {
            '1d'  => now()->subDay(),
            '7d'  => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };

        $byAgent = AgentEvent::where('created_at', '>=', $since)
            ->whereNotNull('estimated_cost_usd')
            ->groupBy('agent_name')
            ->selectRaw("
                agent_name,
                COUNT(*) as call_count,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost_usd) as total_cost,
                AVG(estimated_cost_usd) as avg_cost_per_call,
                MAX(estimated_cost_usd) as max_single_call_cost
            ")
            ->orderByDesc('total_cost')
            ->get();

        $byDay = AgentEvent::where('created_at', '>=', $since)
            ->whereNotNull('estimated_cost_usd')
            ->groupBy(\DB::raw('DATE(created_at)'))
            ->selectRaw("DATE(created_at) as date, SUM(estimated_cost_usd) as cost, SUM(total_tokens) as tokens")
            ->orderBy('date')
            ->get();

        $byModel = AgentEvent::where('created_at', '>=', $since)
            ->whereNotNull('llm_model')
            ->groupBy('llm_model')
            ->selectRaw("llm_model, SUM(total_tokens) as tokens, SUM(estimated_cost_usd) as cost")
            ->orderByDesc('cost')
            ->get();

        return response()->json(compact('byAgent', 'byDay', 'byModel'));
    }

    public function showHitlTask(HumanTask $task): \Inertia\Response
    {
        return Inertia::render('Admin/ControlRoom/HumanTask/Show', [
            'task'         => $task->load('relatedUser', 'triggerEvent', 'assignedOperator'),
            'similarTasks' => HumanTask::where('task_type', $task->task_type)
                ->where('status', 'completed')
                ->latest('completed_at')
                ->take(3)
                ->with('completedByOperator:id,name')
                ->get(['id', 'description', 'completion_notes', 'completed_at', 'completed_by']),
        ]);
    }
}
