import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

function StatCard({ label, value, sub, accent = 'green' }) {
    const accentColor = {
        green:  'text-green-bright border-green-mid',
        amber:  'text-amber-bright border-amber',
        red:    'text-red-bright border-red-alert',
        blue:   'text-blue-bright border-blue-intel',
        muted:  'text-text-secondary border-border-mid',
    }[accent] || 'text-green-bright border-green-mid';

    return (
        <div className={`bg-surface-1 border-l-2 border border-border-mid rounded p-5 ${accentColor.split(' ')[1]}`}>
            <div className="font-mono text-xs tracking-widest uppercase text-text-muted mb-2">{label}</div>
            <div className={`font-display text-4xl tracking-wider ${accentColor.split(' ')[0]}`}>{value ?? '—'}</div>
            {sub && <div className="font-mono text-xs text-text-dim mt-1">{sub}</div>}
        </div>
    );
}

function PipelineIndicator({ label, status }) {
    const isOk = status === 'running' || status === 'ok';
    const isWarn = status === 'degraded' || status === 'slow';
    const isNeutral = status === 'inactive' || status === 'unknown';
    const color = isOk ? 'bg-green-bright' : isWarn ? 'bg-amber-bright' : isNeutral ? 'bg-text-dim' : 'bg-red-bright';
    const text = isOk ? 'text-green-bright' : isWarn ? 'text-amber-bright' : isNeutral ? 'text-text-dim' : 'text-red-bright';

    return (
        <div className="flex items-center justify-between py-2 border-b border-border-subtle last:border-0">
            <span className="font-mono text-xs text-text-secondary">{label}</span>
            <div className="flex items-center gap-2">
                <span className={`w-2 h-2 rounded-full ${color}`} />
                <span className={`font-mono text-xs uppercase tracking-wide ${text}`}>{status}</span>
            </div>
        </div>
    );
}

export default function Dashboard({ stats = {}, pipeline = {} }) {
    const { t } = useTranslation();

    const breadcrumbs = [{ label: t('admin.dashboard') }];

    const byStatus = stats.by_status || {};

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright">
                        {t('admin.dashboard').toUpperCase()}
                    </h1>
                    <div className="flex gap-3">
                        <Link
                            href="/admin/sources"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            {t('admin.sources')}
                        </Link>
                        <Link
                            href="/admin/source-families"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            Families
                        </Link>
                        <Link
                            href="/admin/events"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            {t('admin.events')}
                        </Link>
                        <Link
                            href="/admin/subscribers"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            {t('admin.subscribers')}
                        </Link>
                        <Link
                            href="/admin/affiliates"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            Affiliates
                        </Link>
                        <Link
                            href="/admin/threads"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            Threads
                        </Link>
                        <Link
                            href="/admin/social-channels"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            Social
                        </Link>
                        <Link
                            href="/admin/newsletter/stats"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            Stats
                        </Link>
                        <Link
                            href="/admin/ai-usage"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            AI Usage
                        </Link>
                        <Link
                            href="/admin/logs"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            Logs
                        </Link>
                    </div>
                </div>

                {/* Stat cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        label="Total Events"
                        value={stats.total_events?.toLocaleString()}
                        sub="All time"
                        accent="green"
                    />
                    <StatCard
                        label="Active Sources"
                        value={stats.active_sources}
                        sub={`${stats.total_sources ?? 0} total`}
                        accent="blue"
                    />
                    <StatCard
                        label="Pending Classification"
                        value={stats.pending_classification}
                        sub="Awaiting AI"
                        accent={stats.pending_classification > 50 ? 'amber' : 'muted'}
                    />
                    <StatCard
                        label="Queue Depth"
                        value={stats.queue_depth}
                        sub="Jobs queued"
                        accent={stats.queue_depth > 100 ? 'red' : 'muted'}
                    />
                </div>

                {/* Events by status */}
                <div className="grid grid-cols-2 gap-6">
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">
                            Events by Status
                        </h2>
                        <div className="space-y-2">
                            {[
                                { key: 'unverified',   accent: 'text-text-muted',    label: t('status.unverified') },
                                { key: 'corroborated', accent: 'text-amber',          label: t('status.corroborated') },
                                { key: 'confirmed',    accent: 'text-green-bright',   label: t('status.confirmed') },
                                { key: 'disputed',     accent: 'text-red-bright',     label: t('status.disputed') },
                                { key: 'retracted',    accent: 'text-text-dim',       label: t('status.retracted') },
                            ].map(({ key, accent, label }) => {
                                const count = byStatus[key] || 0;
                                const total = stats.total_events || 1;
                                const pct = Math.round((count / total) * 100);
                                return (
                                    <div key={key}>
                                        <div className="flex justify-between font-mono text-xs mb-1">
                                            <span className={accent}>{label}</span>
                                            <span className="text-text-secondary">{count}</span>
                                        </div>
                                        <div className="h-1 bg-surface-3 rounded-full overflow-hidden">
                                            <div
                                                className="h-full bg-border-active rounded-full"
                                                style={{ width: `${pct}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Pipeline status */}
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">
                            {t('admin.pipeline')}
                        </h2>
                        <PipelineIndicator label="RSS Ingestion"       status={pipeline.rss ?? 'unknown'} />
                        <PipelineIndicator label="Telegram Polling"    status={pipeline.telegram ?? 'unknown'} />
                        <PipelineIndicator label="AI Classification"   status={pipeline.classification ?? 'unknown'} />
                        <PipelineIndicator label="Corroboration"       status={pipeline.corroboration ?? 'unknown'} />
                        <PipelineIndicator label="Thread Assignment"   status={pipeline.threading ?? 'unknown'} />
                        <PipelineIndicator label="Reconciliation"      status={pipeline.reconciliation ?? 'unknown'} />
                        <PipelineIndicator label="Redis"               status={pipeline.redis ?? 'unknown'} />
                    </div>
                </div>

                {/* Recent events link */}
                <div className="text-right">
                    <Link
                        href="/admin/events"
                        className="font-mono text-xs tracking-widest uppercase text-text-muted hover:text-green-bright transition-colors"
                    >
                        View all events →
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
