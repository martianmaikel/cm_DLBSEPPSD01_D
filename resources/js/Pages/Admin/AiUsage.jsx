import { useMemo } from 'react';
import { Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

const PROVIDER_COLORS = {
    gemini: '#4285F4',
    grok: '#E74C3C',
    claude: '#C97B1A',
};

const OPERATION_COLORS = {
    classify: '#52A844',
    embed: '#3D7A32',
    briefing: '#2980B9',
    threat_level: '#C97B1A',
};

function StatCard({ label, value, sub, accent = 'green' }) {
    const colors = {
        green: 'text-green-bright border-green-mid',
        amber: 'text-amber-bright border-amber',
        red: 'text-red-bright border-red-alert',
        blue: 'text-blue-bright border-blue-intel',
        muted: 'text-text-secondary border-border-mid',
    }[accent];
    return (
        <div className={`bg-surface-1 border-l-2 border border-border-mid rounded p-5 ${colors.split(' ')[1]}`}>
            <div className="font-mono text-xs tracking-widest uppercase text-text-muted mb-2">{label}</div>
            <div className={`font-display text-4xl tracking-wider ${colors.split(' ')[0]}`}>{value ?? '—'}</div>
            {sub && <div className="font-mono text-xs text-text-dim mt-1">{sub}</div>}
        </div>
    );
}

function BarRow({ label, value, max, color = '#52A844', suffix = '' }) {
    const pct = max > 0 ? (value / max) * 100 : 0;
    return (
        <div>
            <div className="flex justify-between font-mono text-xs mb-1">
                <span className="text-text-secondary">{label}</span>
                <span className="text-text-primary font-semibold">{typeof value === 'number' ? value.toLocaleString() : value}{suffix}</span>
            </div>
            <div className="h-1.5 bg-surface-3 rounded-full overflow-hidden">
                <div className="h-full rounded-full transition-all" style={{ width: `${pct}%`, backgroundColor: color }} />
            </div>
        </div>
    );
}

function formatCost(cost) {
    if (cost >= 1) return `$${cost.toFixed(2)}`;
    if (cost >= 0.01) return `$${cost.toFixed(3)}`;
    return `$${cost.toFixed(4)}`;
}

function formatTokens(n) {
    if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
    if (n >= 1_000) return `${(n / 1_000).toFixed(1)}K`;
    return n.toLocaleString();
}

export default function AiUsage({
    days = 30,
    summary = {},
    dailyStats = [],
    byProvider = [],
    byOperation = [],
    recentErrors = [],
    hourlyDistribution = [],
}) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { label: t('admin.dashboard'), href: '/admin' },
        { label: 'AI Usage' },
    ];

    const maxDailyRequests = Math.max(1, ...dailyStats.map(d => d.requests));
    const maxDailyCost = Math.max(0.001, ...dailyStats.map(d => d.cost));
    const maxProviderRequests = Math.max(1, ...byProvider.map(p => p.requests));
    const maxOperationCost = Math.max(0.001, ...byOperation.map(o => o.cost));
    const maxHourlyRequests = Math.max(1, ...hourlyDistribution.map(h => h.requests));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright uppercase">
                        AI Usage
                    </h1>
                    <div className="flex gap-2">
                        {[7, 14, 30, 90].map(d => (
                            <button
                                key={d}
                                onClick={() => router.get('/admin/ai-usage', { days: d }, { preserveState: true })}
                                className={`font-mono text-xs tracking-widest uppercase px-3 py-1.5 border rounded transition-colors ${
                                    days === d
                                        ? 'border-green-bright text-green-bright'
                                        : 'border-border-mid text-text-muted hover:border-border-active hover:text-text-secondary'
                                }`}
                            >
                                {d}d
                            </button>
                        ))}
                    </div>
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        label="Total Requests"
                        value={summary.total_requests?.toLocaleString()}
                        sub={`~${summary.avg_requests_per_day}/day`}
                        accent="green"
                    />
                    <StatCard
                        label="Total Cost"
                        value={formatCost(summary.total_cost || 0)}
                        sub={`~${formatCost(summary.avg_cost_per_day || 0)}/day`}
                        accent="amber"
                    />
                    <StatCard
                        label="Total Tokens"
                        value={formatTokens((summary.total_tokens_in || 0) + (summary.total_tokens_out || 0))}
                        sub={`${formatTokens(summary.total_tokens_in || 0)} in / ${formatTokens(summary.total_tokens_out || 0)} out`}
                        accent="blue"
                    />
                    <StatCard
                        label="Errors"
                        value={summary.error_count?.toLocaleString()}
                        sub={`${summary.error_rate || 0}% error rate`}
                        accent={summary.error_rate > 5 ? 'red' : 'muted'}
                    />
                </div>

                {/* Avg latency card */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        label="Avg Latency"
                        value={`${(summary.avg_latency_ms || 0).toLocaleString()}ms`}
                        sub="Across all operations"
                        accent="muted"
                    />
                </div>

                {/* Daily breakdown + Cost trend */}
                <div className="grid grid-cols-2 gap-6">
                    {/* Daily requests */}
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">
                            Daily Requests
                        </h2>
                        {dailyStats.length === 0 ? (
                            <p className="font-mono text-xs text-text-dim">No data yet</p>
                        ) : (
                            <div className="space-y-1.5">
                                {dailyStats.map(d => (
                                    <div key={d.date}>
                                        <div className="flex justify-between font-mono text-xs mb-0.5">
                                            <span className="text-text-dim">{d.date}</span>
                                            <div className="flex gap-4">
                                                <span className="text-text-secondary">{d.requests} req</span>
                                                <span className="text-amber-bright">{formatCost(d.cost)}</span>
                                            </div>
                                        </div>
                                        <div className="flex h-1.5 rounded-full overflow-hidden bg-surface-3">
                                            <div
                                                className="h-full rounded-full"
                                                style={{
                                                    width: `${(d.requests / maxDailyRequests) * 100}%`,
                                                    backgroundColor: d.errors > 0 ? '#E74C3C' : '#52A844',
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Daily cost */}
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">
                            Daily Cost
                        </h2>
                        {dailyStats.length === 0 ? (
                            <p className="font-mono text-xs text-text-dim">No data yet</p>
                        ) : (
                            <div className="space-y-1.5">
                                {dailyStats.map(d => (
                                    <div key={d.date}>
                                        <div className="flex justify-between font-mono text-xs mb-0.5">
                                            <span className="text-text-dim">{d.date}</span>
                                            <div className="flex gap-4">
                                                <span className="text-amber-bright font-semibold">{formatCost(d.cost)}</span>
                                                <span className="text-text-dim">{formatTokens(d.tokens)} tok</span>
                                            </div>
                                        </div>
                                        <div className="flex h-1.5 rounded-full overflow-hidden bg-surface-3">
                                            <div
                                                className="h-full rounded-full"
                                                style={{
                                                    width: `${(d.cost / maxDailyCost) * 100}%`,
                                                    backgroundColor: '#C97B1A',
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* By provider + By operation */}
                <div className="grid grid-cols-2 gap-6">
                    {/* By provider */}
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">
                            By Provider
                        </h2>
                        {byProvider.length === 0 ? (
                            <p className="font-mono text-xs text-text-dim">No data yet</p>
                        ) : (
                            <div className="space-y-4">
                                {byProvider.map(p => (
                                    <div key={`${p.provider}-${p.model}`} className="space-y-1">
                                        <div className="flex justify-between items-baseline">
                                            <div>
                                                <span className="font-mono text-xs tracking-widest uppercase" style={{ color: PROVIDER_COLORS[p.provider] || '#8AAD83' }}>
                                                    {p.provider}
                                                </span>
                                                <span className="font-mono text-xs text-text-dim ml-2">{p.model}</span>
                                            </div>
                                            <span className="font-mono text-xs text-amber-bright font-semibold">{formatCost(p.cost)}</span>
                                        </div>
                                        <div className="h-1.5 bg-surface-3 rounded-full overflow-hidden">
                                            <div
                                                className="h-full rounded-full"
                                                style={{
                                                    width: `${(p.requests / maxProviderRequests) * 100}%`,
                                                    backgroundColor: PROVIDER_COLORS[p.provider] || '#8AAD83',
                                                }}
                                            />
                                        </div>
                                        <div className="flex gap-4 font-mono text-xs text-text-dim">
                                            <span>{p.requests.toLocaleString()} requests</span>
                                            <span>{formatTokens(p.tokens_in)} in / {formatTokens(p.tokens_out)} out</span>
                                            <span>{p.avg_latency}ms avg</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* By operation */}
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">
                            By Operation
                        </h2>
                        {byOperation.length === 0 ? (
                            <p className="font-mono text-xs text-text-dim">No data yet</p>
                        ) : (
                            <div className="space-y-4">
                                {byOperation.map(o => (
                                    <div key={o.operation} className="space-y-1">
                                        <div className="flex justify-between items-baseline">
                                            <span className="font-mono text-xs tracking-widest uppercase" style={{ color: OPERATION_COLORS[o.operation] || '#8AAD83' }}>
                                                {o.operation}
                                            </span>
                                            <span className="font-mono text-xs text-amber-bright font-semibold">{formatCost(o.cost)}</span>
                                        </div>
                                        <div className="h-1.5 bg-surface-3 rounded-full overflow-hidden">
                                            <div
                                                className="h-full rounded-full"
                                                style={{
                                                    width: `${(o.cost / maxOperationCost) * 100}%`,
                                                    backgroundColor: OPERATION_COLORS[o.operation] || '#8AAD83',
                                                }}
                                            />
                                        </div>
                                        <div className="flex gap-4 font-mono text-xs text-text-dim">
                                            <span>{o.requests.toLocaleString()} requests</span>
                                            <span>{formatTokens(o.tokens_in)} in / {formatTokens(o.tokens_out)} out</span>
                                            <span>{o.avg_latency}ms avg</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Hourly distribution */}
                <div className="bg-surface-1 border border-border-mid rounded p-5">
                    <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">
                        Hourly Distribution (UTC)
                    </h2>
                    {hourlyDistribution.length === 0 ? (
                        <p className="font-mono text-xs text-text-dim">No data yet</p>
                    ) : (
                        <div className="flex items-end gap-1 h-24">
                            {Array.from({ length: 24 }, (_, i) => {
                                const entry = hourlyDistribution.find(h => h.hour === i);
                                const requests = entry?.requests || 0;
                                const heightPct = maxHourlyRequests > 0 ? (requests / maxHourlyRequests) * 100 : 0;
                                return (
                                    <div key={i} className="flex-1 flex flex-col items-center gap-1" title={`${i}:00 — ${requests} requests`}>
                                        <div className="w-full bg-surface-3 rounded-t relative" style={{ height: '80px' }}>
                                            <div
                                                className="absolute bottom-0 w-full rounded-t"
                                                style={{
                                                    height: `${heightPct}%`,
                                                    backgroundColor: requests > 0 ? '#52A844' : 'transparent',
                                                }}
                                            />
                                        </div>
                                        <span className="font-mono text-xs text-text-dim leading-none">
                                            {i % 3 === 0 ? `${String(i).padStart(2, '0')}` : ''}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Recent errors */}
                {recentErrors.length > 0 && (
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">
                            Recent Errors
                        </h2>
                        <div className="space-y-1 font-mono text-xs">
                            {recentErrors.map((e, i) => (
                                <div key={i} className="flex items-center gap-3 py-1.5 border-b border-border-subtle/30">
                                    <span className="text-text-dim w-36 shrink-0">
                                        {e.at?.slice(0, 19).replace('T', ' ')}
                                    </span>
                                    <span
                                        className="w-16 tracking-widest uppercase shrink-0"
                                        style={{ color: PROVIDER_COLORS[e.provider] || '#8AAD83' }}
                                    >
                                        {e.provider}
                                    </span>
                                    <span className="text-text-secondary w-24 shrink-0">{e.operation}</span>
                                    <span className="text-red-bright flex-1 truncate">{e.error}</span>
                                    <span className="text-text-dim w-16 text-right shrink-0">{e.latency_ms}ms</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Back link */}
                <div className="text-right">
                    <Link
                        href="/admin"
                        className="font-mono text-xs tracking-widest uppercase text-text-muted hover:text-green-bright transition-colors"
                    >
                        Back to dashboard
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
