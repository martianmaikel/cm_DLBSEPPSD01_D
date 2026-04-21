import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';
import i18n from '../../i18n';
import AppLayout from '../../Layouts/AppLayout';
import SeverityBadge from '../../Components/SeverityBadge';
import StatusBadge from '../../Components/StatusBadge';
import CategoryBadge from '../../Components/CategoryBadge';
import { eventUrl } from '../../utils/eventUrl';

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function TimelineNode({ event, isLast }) {
    const severityLevel = event.severity >= 7 ? 'high' : event.severity >= 4 ? 'medium' : 'low';
    const dotColor = {
        high:   'bg-red-alert border-red-bright',
        medium: 'bg-amber border-amber-bright',
        low:    'bg-green-mid border-green-base',
    }[severityLevel];

    return (
        <div className="flex gap-4">
            {/* Timeline spine */}
            <div className="flex flex-col items-center">
                <div className={`w-3 h-3 rounded-full border-2 flex-shrink-0 mt-1 ${dotColor}`} />
                {!isLast && <div className="w-px flex-1 mt-1 bg-border-mid" />}
            </div>

            {/* Event card */}
            <div className={`pb-6 flex-1 min-w-0 ${isLast ? '' : ''}`}>
                <div className="bg-surface-1 border border-border-mid rounded p-4 hover:border-border-active transition-colors">
                    <div className="flex items-start gap-3 mb-2">
                        <SeverityBadge severity={event.severity} />
                        <div className="flex-1">
                            <div className="flex flex-wrap gap-2 mb-1.5">
                                <CategoryBadge category={event.category} />
                                <StatusBadge status={event.status} />
                            </div>
                            <Link
                                href={eventUrl(event)}
                                className="font-sans font-semibold text-text-primary text-sm leading-snug hover:text-green-bright transition-colors block"
                            >
                                {(i18n.language === 'de' && event.title_de) || event.title}
                            </Link>
                        </div>
                    </div>

                    {(event.summary || event.summary_de) && (
                        <p className="text-text-secondary text-xs leading-relaxed line-clamp-2 mb-2">
                            {(i18n.language === 'de' && event.summary_de) || event.summary}
                        </p>
                    )}

                    <div className="flex items-center justify-between font-mono text-xs text-text-muted">
                        <span>{event.source_name && <span className="text-blue-bright">{event.source_name} · </span>}{event.country}</span>
                        <span>{formatDate(event.occurred_at)}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function Show({ thread, events = [] }) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { label: `Thread #${thread.id}` },
    ];

    // Sort events by occurred_at ascending for timeline
    const sorted = [...events].sort((a, b) =>
        new Date(a.occurred_at) - new Date(b.occurred_at)
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="max-w-2xl mx-auto space-y-6">
                {/* Thread header */}
                <div className="bg-surface-1 border border-border-mid rounded p-6">
                    <div className="flex items-start gap-2 mb-1">
                        <span className="font-mono text-xs text-text-dim tracking-widest uppercase">
                            Conflict Thread #{thread.id}
                        </span>
                    </div>
                    <h1 className="font-display text-3xl tracking-wider text-amber mb-4">
                        {thread.title?.toUpperCase()}
                    </h1>

                    {thread.summary && (
                        <div className="bg-surface-2 border border-border-mid rounded p-4 mb-4">
                            <p className="text-text-secondary text-sm leading-relaxed">{thread.summary}</p>
                            <div className="flex items-center gap-2 mt-3">
                                <span className="inline-block w-1.5 h-1.5 rounded-full bg-blue-bright" />
                                <span className="font-mono text-xs text-blue-bright tracking-wide">
                                    {t('event.aiGenerated')}
                                </span>
                            </div>
                        </div>
                    )}

                    <div className="flex flex-wrap gap-4 font-mono text-xs text-text-muted">
                        <span>{events.length} events</span>
                        {thread.started_at && <span>Started: {formatDate(thread.started_at)}</span>}
                        {thread.updated_at && <span>Last update: {formatDate(thread.updated_at)}</span>}
                    </div>
                </div>

                {/* Timeline */}
                <div>
                    <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">
                        Event Timeline
                    </h2>

                    {sorted.length === 0 ? (
                        <div className="bg-surface-1 border border-border-subtle rounded p-8 text-center font-mono text-text-muted">
                            {t('common.noData')}
                        </div>
                    ) : (
                        <div>
                            {sorted.map((event, i) => (
                                <TimelineNode
                                    key={event.id}
                                    event={event}
                                    isLast={i === sorted.length - 1}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
