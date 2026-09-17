import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const naira = (n) => '₦' + Number(n || 0).toLocaleString();

/* A number that means something on its own, plus the context that makes it readable. */
function Stat({ label, value, sub, tone = 'default', href }) {
    const tones = {
        default: 'text-white',
        good: 'text-teal',
        warn: 'text-amber-400',
        danger: 'text-danger-light',
        muted: 'text-white/50',
    };
    const body = (
        <div className="bg-white/[0.03] border border-white/10 rounded-brand-md p-5 h-full hover:border-white/20 transition-colors">
            <p className="text-[10px] font-mono uppercase tracking-[0.18em] text-white/40 mb-2">{label}</p>
            <p className={`font-display text-3xl font-light tracking-tight ${tones[tone]}`}>{value}</p>
            {sub && <p className="text-xs text-white/40 mt-1.5">{sub}</p>}
        </div>
    );
    return href ? <Link href={href}>{body}</Link> : body;
}

/* Funnel: the step-to-step rate is the point, not the absolute counts. */
function Funnel({ steps }) {
    const max = Math.max(...steps.map((s) => s.value), 1);
    return (
        <div className="space-y-2.5">
            {steps.map((s, i) => {
                const width = Math.max((s.value / max) * 100, 1.5);
                const drop = s.pct_of_prev !== null && s.pct_of_prev < 50;
                return (
                    <div key={s.label}>
                        <div className="flex items-baseline justify-between mb-1">
                            <span className="text-xs text-white/70">{s.label}</span>
                            <span className="text-xs font-mono text-white/50">
                                {s.value.toLocaleString()}
                                {s.pct_of_prev !== null && (
                                    <span className={drop ? 'text-danger-light ml-2' : 'text-white/35 ml-2'}>
                                        {s.pct_of_prev}%
                                    </span>
                                )}
                            </span>
                        </div>
                        <div className="h-2 bg-white/[0.04] rounded-full overflow-hidden">
                            <div
                                className={`h-full rounded-full ${drop ? 'bg-danger/60' : 'bg-teal/70'}`}
                                style={{ width: `${width}%` }}
                            />
                        </div>
                        {i === 1 && <p className="text-[10px] text-white/25 mt-1">% is of the step above</p>}
                    </div>
                );
            })}
        </div>
    );
}

/* Sparkline-ish bars. Small multiples beat a big chart library here. */
function Bars({ data, valueKey, labelKey, format = (v) => v }) {
    const max = Math.max(...data.map((d) => d[valueKey]), 1);
    return (
        <div className="flex items-end gap-1.5 h-24">
            {data.map((d, i) => (
                <div key={i} className="flex-1 flex flex-col items-center gap-1.5 group">
                    <div className="w-full flex items-end h-20">
                        <div
                            className="w-full bg-teal/50 group-hover:bg-teal rounded-sm transition-colors relative"
                            style={{ height: `${Math.max((d[valueKey] / max) * 100, 2)}%` }}
                        >
                            <span className="absolute -top-5 left-1/2 -translate-x-1/2 text-[10px] font-mono text-white/70 opacity-0 group-hover:opacity-100 whitespace-nowrap">
                                {format(d[valueKey])}
                            </span>
                        </div>
                    </div>
                    <span className="text-[9px] font-mono text-white/30">{d[labelKey]}</span>
                </div>
            ))}
        </div>
    );
}

function Panel({ title, note, children, action }) {
    return (
        <div className="bg-white/[0.02] border border-white/10 rounded-brand-md p-6">
            <div className="flex items-start justify-between mb-5">
                <div>
                    <h2 className="font-display text-lg font-light text-white">{title}</h2>
                    {note && <p className="text-[11px] text-white/35 mt-0.5">{note}</p>}
                </div>
                {action}
            </div>
            {children}
        </div>
    );
}

export default function AdminDashboard({ revenue, requests, funnel, traffic, supply, health, asOf }) {
    const statusTone = { open: 'muted', paid: 'default', matching: 'default', matched: 'good', fulfilled: 'good' };

    return (
        <AdminLayout>
            <Head title="Business Dashboard | Admin" />

            <div className="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-1.5">Business Dashboard</h1>
                    <p className="text-[11px] font-mono uppercase tracking-[0.18em] text-white/35">As of {asOf}</p>
                </div>
                {health.alert_count > 0 && (
                    <div className="bg-danger/10 border border-danger/25 px-5 py-3 rounded-brand-md">
                        <p className="text-[10px] font-mono uppercase tracking-widest text-danger-light font-bold mb-0.5">
                            {health.alert_count} thing{health.alert_count === 1 ? '' : 's'} need attention
                        </p>
                        <p className="text-xs text-white/60">{health.alerts[0]?.text}</p>
                    </div>
                )}
            </div>

            {/* The five numbers worth knowing before anything else */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                <Stat
                    label="Revenue this month"
                    value={naira(revenue.this_month)}
                    tone="good"
                    sub={
                        revenue.change_pct === null
                            ? `${revenue.paid_count} fees all time`
                            : `${revenue.change_pct >= 0 ? '+' : ''}${revenue.change_pct}% vs last month`
                    }
                />
                <Stat
                    label="Uncollected"
                    value={naira(revenue.outstanding)}
                    tone={revenue.outstanding > 0 ? 'warn' : 'muted'}
                    sub={`${requests.unpaid_live} live request${requests.unpaid_live === 1 ? '' : 's'} unpaid`}
                />
                <Stat
                    label="Live requests"
                    value={requests.live}
                    sub={`${requests.without_cover} with no shortlist`}
                    tone={requests.without_cover > 0 ? 'warn' : 'default'}
                />
                <Stat
                    label="Fill rate"
                    value={`${requests.fill_rate_pct}%`}
                    tone={requests.fill_rate_pct >= 50 ? 'good' : 'warn'}
                    sub={requests.avg_days_to_fill ? `${requests.avg_days_to_fill} days average` : 'none filled yet'}
                />
                <Stat
                    label="Offerable helpers"
                    value={supply.offerable}
                    sub={`${supply.committed} placed or held`}
                    tone={supply.offerable < 10 ? 'danger' : 'good'}
                />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div className="xl:col-span-2">
                    <Panel title="Conversion" note={funnel.note}>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <p className="text-[10px] font-mono uppercase tracking-widest text-white/35 mb-3">
                                    On the site — per session
                                </p>
                                <Funnel steps={funnel.web} />
                                {funnel.web_note && (
                                    <p className="text-[11px] text-amber-300/70 mt-3 leading-snug">{funnel.web_note}</p>
                                )}
                            </div>
                            <div>
                                <p className="text-[10px] font-mono uppercase tracking-widest text-white/35 mb-3">
                                    After a request — per request
                                </p>
                                <Funnel steps={funnel.request} />
                                {funnel.matched_unpaid > 0 && (
                                    <p className="text-[11px] text-amber-300/80 mt-3 leading-snug">
                                        {funnel.matched_unpaid} matched request
                                        {funnel.matched_unpaid === 1 ? ' is' : 's are'} not marked paid — a helper
                                        was committed before the fee arrived.
                                    </p>
                                )}
                            </div>
                        </div>
                    </Panel>
                </div>

                <Panel title="Requests" note="The operational heartbeat">
                    <div className="space-y-2.5">
                        {Object.entries(requests.by_status).map(([status, n]) => (
                            <div key={status} className="flex items-center justify-between py-1.5 border-b border-white/5 last:border-0">
                                <span className="text-xs text-white/60 capitalize">{status}</span>
                                <span
                                    className={`font-mono text-sm ${
                                        statusTone[status] === 'good'
                                            ? 'text-teal'
                                            : statusTone[status] === 'muted'
                                            ? 'text-white/35'
                                            : 'text-white'
                                    }`}
                                >
                                    {n}
                                </span>
                            </div>
                        ))}
                    </div>
                </Panel>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
                <Panel title="Revenue" note="Matching fees, charged per helper">
                    <Bars data={revenue.by_month} valueKey="amount" labelKey="month" format={naira} />
                    <div className="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-white/5">
                        <div>
                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/35">All time</p>
                            <p className="text-lg font-display font-light text-white mt-1">{naira(revenue.all_time)}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/35">Last month</p>
                            <p className="text-lg font-display font-light text-white mt-1">{naira(revenue.last_month)}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/35">Average fee</p>
                            <p className="text-lg font-display font-light text-white mt-1">{naira(revenue.avg_fee)}</p>
                        </div>
                    </div>
                </Panel>

                <Panel title="Traffic" note="Sessions over the last 14 days">
                    {traffic.daily.length > 0 ? (
                        <Bars data={traffic.daily} valueKey="sessions" labelKey="date" />
                    ) : (
                        <p className="text-xs text-white/35 py-8 text-center">No session events recorded yet.</p>
                    )}
                    <div className="mt-5 pt-5 border-t border-white/5">
                        <p className="text-[10px] font-mono uppercase tracking-widest text-white/35 mb-3">
                            Acquisition channel
                        </p>
                        {traffic.channels.length ? (
                            <div className="space-y-1.5">
                                {traffic.channels.map((c) => (
                                    <div key={c.channel} className="flex items-center justify-between">
                                        <span className="text-xs text-white/60 capitalize">
                                            {c.channel.replace(/_/g, ' ')}
                                        </span>
                                        <span className="font-mono text-xs text-white/70">{c.count}</span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-xs text-white/35">Nothing attributed yet.</p>
                        )}
                    </div>
                </Panel>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div className="xl:col-span-2">
                    <Panel title="Oldest open requests" note="Longest wait first — these are the customers losing patience">
                        {requests.ageing.length ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-[10px] font-mono uppercase tracking-widest text-white/35 border-b border-white/10">
                                            <th className="text-left pb-2 font-normal">Request</th>
                                            <th className="text-left pb-2 font-normal">Status</th>
                                            <th className="text-left pb-2 font-normal">Area</th>
                                            <th className="text-right pb-2 font-normal">Open</th>
                                            <th className="text-right pb-2 font-normal">Cover</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {requests.ageing.map((r) => (
                                            <tr key={r.reference} className="border-b border-white/5 last:border-0">
                                                <td className="py-2.5 font-mono text-xs text-white/80">{r.reference}</td>
                                                <td className="py-2.5 text-xs text-white/55 capitalize">{r.status}</td>
                                                <td className="py-2.5 text-xs text-white/55">{r.area || '—'}</td>
                                                <td
                                                    className={`py-2.5 text-right font-mono text-xs ${
                                                        r.days_open > 7 ? 'text-danger-light' : 'text-white/60'
                                                    }`}
                                                >
                                                    {r.days_open}d
                                                </td>
                                                <td
                                                    className={`py-2.5 text-right font-mono text-xs ${
                                                        r.cover === 0 ? 'text-danger-light' : 'text-white/60'
                                                    }`}
                                                >
                                                    {r.cover}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-xs text-white/35 py-6 text-center">Nothing open.</p>
                        )}
                    </Panel>
                </div>

                <div className="space-y-6">
                    <Panel title="Supply">
                        <div className="space-y-2.5">
                            {[
                                ['Helpers on the platform', supply.maids_total],
                                ['Available', supply.available],
                                ['NIN verified', `${supply.nin_verified} (${supply.verified_pct}%)`],
                                ['Placed or held', supply.committed],
                                ['Shortlisted now', supply.shortlisted],
                                ['Group claims', `${supply.group_claims_live} live / ${supply.group_claims}`],
                                ['Employers', supply.employers],
                            ].map(([label, value]) => (
                                <div key={label} className="flex items-center justify-between py-1 border-b border-white/5 last:border-0">
                                    <span className="text-xs text-white/60">{label}</span>
                                    <span className="font-mono text-xs text-white/80">{value}</span>
                                </div>
                            ))}
                        </div>
                    </Panel>

                    {health.alerts.length > 0 && (
                        <Panel title="Needs attention" note={`${health.unreachable_pct}% of approaches go unanswered`}>
                            <div className="space-y-2">
                                {health.alerts.map((a, i) => (
                                    <div
                                        key={i}
                                        className={`text-xs px-3 py-2 rounded border ${
                                            a.level === 'danger'
                                                ? 'bg-danger/10 border-danger/25 text-danger-light'
                                                : 'bg-amber-500/10 border-amber-500/25 text-amber-300'
                                        }`}
                                    >
                                        {a.text}
                                    </div>
                                ))}
                            </div>
                        </Panel>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
