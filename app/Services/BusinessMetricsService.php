<?php

namespace App\Services;

use App\Models\HireRequest;
use App\Models\MatchingFeePayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The numbers the business actually runs on.
 *
 * The old dashboard showed seven flat counters — total users, total bookings,
 * total revenue — which tell you the size of the database, not how the business
 * is doing. None of them answered the questions that matter day to day: are
 * requests getting filled, where do people fall out of the funnel, is revenue
 * growing, and is there enough supply to serve demand.
 *
 * Everything here is derived from live tables rather than stored aggregates, so
 * nothing can drift out of date; the periods are small enough that this is
 * cheaper than keeping a summary table honest.
 */
class BusinessMetricsService
{
    /** Fee is charged per helper, so revenue counts paid requests, not customers. */
    public function revenue(): array
    {
        $paid = MatchingFeePayment::whereIn('status', ['paid', 'completed']);

        $thisMonth = (clone $paid)->whereBetween('paid_at', [now()->startOfMonth(), now()])->sum('amount');
        $lastMonth = MatchingFeePayment::whereIn('status', ['paid', 'completed'])
            ->whereBetween('paid_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->sum('amount');

        // What has been requested but not yet collected — the number worth chasing.
        $outstanding = HireRequest::whereIn('status', HireRequest::OPEN_STATUSES)
            ->get()->filter(fn ($r) => !$r->isPaid())->sum('fee_amount');

        return [
            'all_time'      => (int) (clone $paid)->sum('amount'),
            'this_month'    => (int) $thisMonth,
            'last_month'    => (int) $lastMonth,
            'change_pct'    => $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100) : null,
            'outstanding'   => (int) $outstanding,
            'paid_count'    => (clone $paid)->count(),
            'avg_fee'       => (int) round((clone $paid)->avg('amount') ?: 0),
            'by_month'      => $this->revenueByMonth(6),
        ];
    }

    /** @return array<int, array{month:string, amount:int, count:int}> */
    private function revenueByMonth(int $months): array
    {
        $rows = MatchingFeePayment::whereIn('status', ['paid', 'completed'])
            ->where('paid_at', '>=', now()->subMonths($months)->startOfMonth())
            ->selectRaw("to_char(paid_at,'YYYY-MM') m, sum(amount) total, count(*) n")
            ->groupBy('m')->orderBy('m')->get()->keyBy('m');

        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $out[] = [
                'month'  => now()->subMonths($i)->format('M'),
                'amount' => (int) ($rows[$key]->total ?? 0),
                'count'  => (int) ($rows[$key]->n ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Requests by lifecycle stage — the operational heartbeat.
     *
     * Ageing matters more than the counts: a request open for nine days is a
     * customer losing patience, and a flat total never shows that.
     */
    public function requests(): array
    {
        $all = HireRequest::all();

        $byStatus = [];
        foreach (['open', 'paid', 'matching', 'matched', 'fulfilled', 'cancelled'] as $s) {
            $byStatus[$s] = $all->where('status', $s)->count();
        }

        $live = $all->whereIn('status', HireRequest::OPEN_STATUSES);

        // How long the oldest unfilled requests have been waiting.
        $ageing = $live->map(fn ($r) => [
            'reference' => $r->reference,
            'status'    => $r->status,
            'area'      => $r->area,
            'days_open' => (int) floor(abs(now()->diffInHours($r->created_at) / 24)),
            'paid'      => $r->isPaid(),
            'cover'     => DB::table('placement_candidates')
                ->where('hire_request_id', $r->id)
                ->whereIn('status', ['queued', 'offered'])->count(),
        ])->sortByDesc('days_open')->take(8)->values()->all();

        $fulfilled = $all->where('status', 'fulfilled');
        $timeToFill = $fulfilled->filter(fn ($r) => $r->resumed_at && $r->created_at)
            ->map(fn ($r) => abs($r->created_at->diffInDays($r->resumed_at)));

        return [
            'by_status'       => $byStatus,
            'live'            => $live->count(),
            'unpaid_live'     => $live->filter(fn ($r) => !$r->isPaid())->count(),
            'without_cover'   => $live->filter(fn ($r) => DB::table('placement_candidates')
                                    ->where('hire_request_id', $r->id)
                                    ->whereIn('status', ['queued', 'offered'])->count() === 0)->count(),
            'ageing'          => $ageing,
            'avg_days_to_fill'=> $timeToFill->count() ? round($timeToFill->avg(), 1) : null,
            'fill_rate_pct'   => $all->count() ? round(($fulfilled->count() / max($all->count(), 1)) * 100) : 0,
        ];
    }

    /**
     * Two funnels, not one chain.
     *
     * Splicing web sessions onto request records makes a chart that lies. The
     * web steps are counted per session and the request steps per request, over
     * different periods — requests were only tracked from September, so the join
     * between them shows a 96% "drop" that is really a gap in what was recorded.
     * Worse, a single chain let matched (6) exceed paid (4), which is impossible
     * in a real funnel and quietly destroys trust in every other number here.
     *
     * So: each funnel is internally consistent, every step a strict subset of
     * the one above it, and the two are shown side by side with the join stated
     * rather than implied.
     */
    public function funnel(): array
    {
        // --- Web: counted per session, so the steps are comparable.
        $distinct = fn (string $type) => (int) DB::table('user_events')
            ->where('event_type', $type)->distinct()->count('session_id');

        $visits    = $distinct('page_view');
        $quizStart = $distinct('quiz_start');
        $quizDone  = $distinct('quiz_complete');
        $viewed    = $distinct('matches_viewed');

        // page_view and quiz_start only began recording on 2026-09-17; before
        // that the front end reported nothing, so a visitor was invisible unless
        // they finished a quiz. Until enough history accumulates, fall back to
        // all sessions and say so, rather than showing a funnel that implies
        // almost nobody visits.
        $backfilled = $visits < $quizDone;
        if ($backfilled) {
            $visits    = (int) DB::table('user_events')->distinct()->count('session_id');
            $quizStart = max($quizStart, $quizDone);
        }

        $web = [
            ['label' => 'Visits',         'value' => $visits],
            ['label' => 'Quiz started',   'value' => $quizStart],
            ['label' => 'Quiz completed', 'value' => $quizDone],
            ['label' => 'Matches viewed', 'value' => $viewed],
        ];

        // --- Requests: each step a strict subset of the previous one.
        $all       = HireRequest::all();
        $opened    = $all->count();
        $paid      = $all->filter(fn ($r) => $r->isPaid());
        $matched   = $paid->whereIn('status', ['matched', 'fulfilled']);
        $fulfilled = $paid->where('status', 'fulfilled');

        $request = [
            ['label' => 'Requests opened', 'value' => $opened],
            ['label' => 'Fee paid',        'value' => $paid->count()],
            ['label' => 'Helper matched',  'value' => $matched->count()],
            ['label' => 'Helper resumed',  'value' => $fulfilled->count()],
        ];

        foreach ([&$web, &$request] as &$set) {
            foreach ($set as $i => &$s) {
                $prev = $i === 0 ? null : $set[$i - 1]['value'];
                $s['pct_of_prev'] = $prev ? round(($s['value'] / max($prev, 1)) * 100) : null;
            }
            unset($s);
        }
        unset($set);

        // Matched-but-unpaid is a real condition worth surfacing rather than
        // hiding: it means a helper was committed before the fee arrived.
        $matchedUnpaid = $all->whereIn('status', ['matched', 'fulfilled'])
            ->filter(fn ($r) => !$r->isPaid())->count();

        return [
            'web'             => $web,
            'request'         => $request,
            'matched_unpaid'  => $matchedUnpaid,
            'note'            => 'Web steps count sessions; request steps count requests. '
                               . 'They cannot be read as one chain.',
            'web_backfilled'  => $backfilled,
            'web_note'        => $backfilled
                ? 'Visit and quiz-start tracking began 17 Sep. Until history builds, '
                  . '"Visits" falls back to any session that produced an event, which '
                  . 'undercounts real traffic and hides quiz abandonment.'
                : null,
        ];
    }

    /** Where demand comes from. */
    public function traffic(): array
    {
        $channels = DB::table('user_attributions')
            ->selectRaw('coalesce(channel,\'unknown\') channel, count(*) n')
            ->groupBy('channel')->orderByDesc('n')->get()
            ->map(fn ($r) => ['channel' => $r->channel, 'count' => (int) $r->n])->all();

        $leadChannels = DB::table('lead_attributions')->get()
            ->map(fn ($r) => json_decode($r->attribution, true)['channel'] ?? 'unknown')
            ->countBy()->sortDesc()
            ->map(fn ($n, $c) => ['channel' => $c, 'count' => $n])->values()->all();

        $daily = DB::table('user_events')
            ->where('created_at', '>=', now()->subDays(14))
            ->selectRaw("to_char(created_at,'YYYY-MM-DD') d, count(distinct session_id) sessions, count(*) events")
            ->groupBy('d')->orderBy('d')->get()
            ->map(fn ($r) => ['date' => substr($r->d, 5), 'sessions' => (int) $r->sessions, 'events' => (int) $r->events])
            ->all();

        return [
            'channels'      => $channels,
            'lead_channels' => $leadChannels,
            'daily'         => $daily,
            'attributed'    => count($channels) ? array_sum(array_column($channels, 'count')) : 0,
        ];
    }

    /**
     * Supply. Demand is worthless without helpers who can actually take the work,
     * so this counts the ones who can — verified, available and not already placed.
     */
    public function supply(): array
    {
        $profiles  = DB::table('maid_profiles');
        $total     = (clone $profiles)->count();
        $available = (clone $profiles)->where('availability_status', 'available')->count();
        $verified  = (clone $profiles)->where('nin_verified', true)->count();

        $committed = count(app(PlacementQueueService::class)->unavailableMaids());

        return [
            'maids_total'       => $total,
            'available'         => $available,
            'nin_verified'      => $verified,
            'verified_pct'      => $total ? round(($verified / $total) * 100) : 0,
            'committed'         => $committed,
            'offerable'         => max($available - $committed, 0),
            'employers'         => User::where('role', 'employer')->count(),
            'group_claims'      => DB::table('group_job_claims')->count(),
            'group_claims_live' => DB::table('group_job_claims')->where('status', 'claimed')->count(),
            'shortlisted'       => DB::table('placement_candidates')->whereIn('status', ['queued', 'offered'])->count(),
        ];
    }

    /**
     * Where the pipeline is bleeding. Every entry here is something a human
     * should act on today, which is the only reason to put it on a dashboard.
     */
    public function health(): array
    {
        $alerts = [];

        $thin = HireRequest::whereIn('status', ['paid', 'matching', 'matched'])->get()
            ->filter(fn ($r) => $r->isPaid() && DB::table('placement_candidates')
                ->where('hire_request_id', $r->id)->whereIn('status', ['queued', 'offered'])->count() < 2);

        foreach ($thin as $r) {
            $alerts[] = ['level' => 'warn', 'text' => "{$r->reference} has thin cover — top up the shortlist"];
        }

        $stale = HireRequest::whereIn('status', HireRequest::OPEN_STATUSES)
            ->where('created_at', '<', now()->subDays(7))->get()
            ->filter(fn ($r) => $r->isPaid());

        foreach ($stale as $r) {
            $days = (int) floor(abs(now()->diffInHours($r->created_at) / 24));
            $alerts[] = ['level' => 'danger', 'text' => "{$r->reference} paid and unfilled for {$days} days"];
        }

        $doubleBooked = DB::table('maid_assignments')
            ->whereIn('status', ['pending_acceptance', 'accepted'])
            ->selectRaw('maid_id, count(distinct employer_id) n')
            ->groupBy('maid_id')->havingRaw('count(distinct employer_id) > 1')->get();

        foreach ($doubleBooked as $d) {
            $name = User::find($d->maid_id)?->name ?? "maid {$d->maid_id}";
            $alerts[] = ['level' => 'danger', 'text' => "{$name} is committed to {$d->n} households"];
        }

        $unreach = DB::table('placement_candidates')->where('status', 'unreachable')->count();
        $offered = DB::table('placement_candidates')->whereIn('status', ['offered', 'accepted', 'declined', 'unreachable'])->count();

        return [
            'alerts'          => array_slice($alerts, 0, 8),
            'alert_count'     => count($alerts),
            'unreachable_pct' => $offered ? round(($unreach / $offered) * 100) : 0,
        ];
    }

    /** Everything, for the dashboard. */
    public function all(): array
    {
        return [
            'revenue'  => $this->revenue(),
            'requests' => $this->requests(),
            'funnel'   => $this->funnel(),
            'traffic'  => $this->traffic(),
            'supply'   => $this->supply(),
            'health'   => $this->health(),
            'asOf'     => now()->toDayDateTimeString(),
        ];
    }
}
