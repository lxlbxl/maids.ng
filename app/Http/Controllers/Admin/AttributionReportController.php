<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

/**
 * /admin/attribution — leads, signups, paid conversions and revenue grouped by
 * traffic channel / campaign / source, from our own tables (the source of truth
 * for revenue). The page also embeds the shared PostHog dashboard for funnels
 * and trends.
 */
class AttributionReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);

        $rows = $this->aggregate($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->csv($rows['by_channel'], $from, $to);
        }

        return Inertia::render('Admin/Attribution', [
            'filters'          => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'by_channel'       => $rows['by_channel'],
            'by_campaign'      => $rows['by_campaign'],
            'by_source'        => $rows['by_source'],
            'totals'           => $rows['totals'],
            'unattributed'     => $rows['unattributed'],
            'posthog_embed_url'=> config('services.posthog.dashboard_embed_url'),
        ]);
    }

    private function aggregate(Carbon $from, Carbon $to): array
    {
        // leads = user_attributions created in range (a user was created from a lead)
        // paid  = matching_fee_payments.status=paid in range, joined to the payer's attribution snapshot
        $leadBase = DB::table('user_attributions')
            ->join('users', 'users.id', '=', 'user_attributions.user_id')
            ->whereBetween('user_attributions.created_at', [$from, $to]);

        $paidBase = DB::table('matching_fee_payments')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to]);

        $group = function (string $col) use ($leadBase, $paidBase) {
            $leads = (clone $leadBase)
                ->selectRaw("coalesce(nullif(user_attributions.$col, ''), 'unknown') as k, count(*) as c")
                ->groupBy('k')->pluck('c', 'k');

            $revExpr = "coalesce(nullif(attribution->>'$col', ''), 'unknown')";
            $paid = (clone $paidBase)
                ->selectRaw("$revExpr as k, count(*) as c, coalesce(sum(amount),0) as revenue")
                ->groupBy('k')->get()->keyBy('k');

            $keys = collect($leads->keys())->merge($paid->keys())->unique()->values();
            return $keys->map(fn ($k) => [
                'key'     => $k,
                'leads'   => (int) ($leads[$k] ?? 0),
                'paid'    => (int) ($paid[$k]->c ?? 0),
                'revenue' => (int) ($paid[$k]->revenue ?? 0),
            ])->sortByDesc('revenue')->sortByDesc('leads')->values()->all();
        };

        $totalLeads = (clone $leadBase)->count();
        $totalPaid  = (clone $paidBase)->count();
        $totalRev   = (int) (clone $paidBase)->sum('amount');
        $noAttrib   = DB::table('users')
            ->leftJoin('user_attributions', 'user_attributions.user_id', '=', 'users.id')
            ->where('users.role', 'employer')
            ->whereBetween('users.created_at', [$from, $to])
            ->whereNull('user_attributions.id')
            ->count();

        return [
            'by_channel'  => $group('channel'),
            'by_campaign' => $group('campaign'),
            'by_source'   => $group('source'),
            'totals'      => ['leads' => $totalLeads, 'paid' => $totalPaid, 'revenue' => $totalRev],
            'unattributed'=> $noAttrib,
        ];
    }

    private function range(Request $request): array
    {
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        return [$from, $to];
    }

    private function csv(array $byChannel, Carbon $from, Carbon $to): StreamedResponse
    {
        $name = 'attribution_' . $from->toDateString() . '_' . $to->toDateString() . '.csv';
        return response()->streamDownload(function () use ($byChannel) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['channel', 'leads', 'paid', 'revenue_ngn']);
            foreach ($byChannel as $r) {
                fputcsv($out, [$r['key'], $r['leads'], $r['paid'], $r['revenue']]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
