import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

const TYPE_COLORS = {
    daily_global: '#52A844',
    thread_digest: '#3D7A32',
    critical_alert: '#E74C3C',
    confirm: '#2980B9',
    test: '#C97B1A',
};

const STATUS_COLORS = {
    sent: '#52A844',
    queued: '#8AAD83',
    failed: '#E74C3C',
    bounced: '#C97B1A',
    complained: '#C0392B',
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

function BarRow({ label, value, max, color = '#52A844' }) {
    const pct = max > 0 ? (value / max) * 100 : 0;
    return (
        <div>
            <div className="flex justify-between font-mono text-xs mb-1">
                <span className="text-text-secondary">{label}</span>
                <span className="text-text-primary font-semibold">{value}</span>
            </div>
            <div className="h-1.5 bg-surface-3 rounded-full overflow-hidden">
                <div className="h-full rounded-full transition-all" style={{ width: `${pct}%`, backgroundColor: color }} />
            </div>
        </div>
    );
}

export default function NewsletterStats({
    subscribersByStatus = {},
    sendsByDay = [],
    sendsByStatus = {},
    sendsByType = {},
    topAffiliates = [],
    bounceRate = 0,
    totalSubscribers = 0,
    recentSesEvents = [],
}) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { label: t('admin.dashboard'), href: '/admin' },
        { label: 'Newsletter Stats' },
    ];

    // Aggregate sendsByDay into {day: {type: count}} for stacked rendering
    const dayAggregates = useMemo(() => {
        const map = {};
        for (const row of sendsByDay) {
            if (!map[row.day]) map[row.day] = { total: 0, byType: {} };
            map[row.day].byType[row.type] = row.count;
            map[row.day].total += row.count;
        }
        return Object.entries(map).sort(([a], [b]) => a.localeCompare(b));
    }, [sendsByDay]);

    const maxDayTotal = Math.max(1, ...dayAggregates.map(([, v]) => v.total));

    const totalSent = Object.values(sendsByStatus).reduce((s, n) => s + n, 0);
    const maxStatus = Math.max(1, ...Object.values(sendsByStatus));
    const maxType = Math.max(1, ...Object.values(sendsByType));
    const confirmedSubs = subscribersByStatus.confirmed ?? 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright uppercase">Newsletter Stats</h1>
                </div>

                {/* Stat cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard label="Subscribers" value={totalSubscribers?.toLocaleString()} sub={`${confirmedSubs} confirmed`} accent="green" />
                    <StatCard label="Total Sends" value={totalSent.toLocaleString()} sub="All types" accent="blue" />
                    <StatCard
                        label="Bounce Rate (30d)"
                        value={bounceRate + '%'}
                        sub={bounceRate > 5 ? 'Above threshold' : 'Healthy'}
                        accent={bounceRate > 5 ? 'red' : bounceRate > 2 ? 'amber' : 'muted'}
                    />
                    <StatCard
                        label="Live Affiliates"
                        value={topAffiliates.filter(a => a.active).length}
                        sub={`${topAffiliates.length} total`}
                        accent="amber"
                    />
                </div>

                <div className="grid grid-cols-2 gap-6">
                    {/* Sends by day (last 14d) */}
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">Sends · Last 14 Days</h2>
                        {dayAggregates.length === 0 ? (
                            <p className="font-mono text-xs text-text-dim">No sends yet</p>
                        ) : (
                            <div className="space-y-2">
                                {dayAggregates.map(([day, v]) => (
                                    <div key={day}>
                                        <div className="flex justify-between font-mono text-xs mb-1">
                                            <span className="text-text-dim">{day}</span>
                                            <span className="text-text-primary font-semibold">{v.total}</span>
                                        </div>
                                        <div className="flex h-2 rounded-full overflow-hidden bg-surface-3">
                                            {Object.entries(v.byType).map(([type, count]) => (
                                                <div
                                                    key={type}
                                                    style={{
                                                        width: `${(count / maxDayTotal) * 100}%`,
                                                        backgroundColor: TYPE_COLORS[type] || '#8AAD83',
                                                    }}
                                                    title={`${type}: ${count}`}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                        {/* Legend */}
                        <div className="flex flex-wrap gap-3 mt-4 pt-3 border-t border-border-subtle">
                            {Object.entries(TYPE_COLORS).map(([type, color]) => (
                                <span key={type} className="flex items-center gap-1.5 font-mono text-xs text-text-muted">
                                    <span className="w-2 h-2 rounded-sm" style={{ backgroundColor: color }} />
                                    {type}
                                </span>
                            ))}
                        </div>
                    </div>

                    {/* Sends by status */}
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">Sends by Status</h2>
                        {Object.keys(sendsByStatus).length === 0 ? (
                            <p className="font-mono text-xs text-text-dim">No sends yet</p>
                        ) : (
                            <div className="space-y-2">
                                {Object.entries(sendsByStatus).map(([status, count]) => (
                                    <BarRow
                                        key={status}
                                        label={status}
                                        value={count}
                                        max={maxStatus}
                                        color={STATUS_COLORS[status] || '#8AAD83'}
                                    />
                                ))}
                            </div>
                        )}

                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mt-6 mb-4">Sends by Type (All Time)</h2>
                        <div className="space-y-2">
                            {Object.entries(sendsByType).map(([type, count]) => (
                                <BarRow
                                    key={type}
                                    label={type}
                                    value={count}
                                    max={maxType}
                                    color={TYPE_COLORS[type] || '#8AAD83'}
                                />
                            ))}
                        </div>
                    </div>
                </div>

                {/* Top affiliates */}
                <div className="bg-surface-1 border border-border-mid rounded p-5">
                    <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">Top Affiliates</h2>
                    {topAffiliates.length === 0 ? (
                        <p className="font-mono text-xs text-text-dim">No affiliate impressions yet</p>
                    ) : (
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-border-subtle">
                                    {['Name', 'Status', 'Impressions', 'Clicks', 'CTR'].map(h => (
                                        <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted py-2">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {topAffiliates.map(a => (
                                    <tr key={a.id} className="border-b border-border-subtle/50">
                                        <td className="py-2 font-sans text-sm text-text-primary">{a.name}</td>
                                        <td className="py-2">
                                            <span className={`font-mono text-xs tracking-widest uppercase ${a.active ? 'text-green-bright' : 'text-text-muted'}`}>
                                                {a.active ? 'Active' : 'Off'}
                                            </span>
                                        </td>
                                        <td className="py-2 font-mono text-xs text-text-secondary">{a.impressions.toLocaleString()}</td>
                                        <td className="py-2 font-mono text-xs text-text-secondary">{a.clicks.toLocaleString()}</td>
                                        <td className="py-2 font-mono text-xs text-green-bright font-semibold">{a.ctr}%</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* Recent SES events */}
                {recentSesEvents.length > 0 && (
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">Recent SES Events</h2>
                        <div className="space-y-1 font-mono text-xs">
                            {recentSesEvents.map(e => (
                                <div key={e.id} className="flex items-center gap-3 py-1 border-b border-border-subtle/30">
                                    <span className="text-text-dim w-32">{e.received_at?.slice(0, 19).replace('T', ' ')}</span>
                                    <span className={`w-28 tracking-widest uppercase ${
                                        e.event_type === 'Bounce' ? 'text-red-bright' :
                                        e.event_type === 'Complaint' ? 'text-red-bright' :
                                        e.event_type === 'Delivery' ? 'text-green-bright' : 'text-text-secondary'
                                    }`}>
                                        {e.event_type}
                                    </span>
                                    <span className="text-text-secondary flex-1 truncate">{e.recipient_email || '—'}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
