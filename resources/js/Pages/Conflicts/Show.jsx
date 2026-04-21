import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import i18n from '../../i18n';
import AppLayout from '../../Layouts/AppLayout';
import SeverityBadge from '../../Components/SeverityBadge';
import RelationshipGraph from '../../Components/Graph/RelationshipGraph';
import { eventUrl } from '../../utils/eventUrl';

const CATEGORY_COLORS = {
    war: '#E74C3C',
    terrorism: '#E74C3C',
    cyber: '#3498DB',
    protest: '#F39C12',
    disaster: '#E67E22',
    diplomacy: '#2ECC71',
    economic: '#9B59B6',
};

function sevColor(severity) {
    if (severity >= 7) return '#E74C3C';
    if (severity >= 4) return '#F59E0B';
    return '#52A844';
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

function SubThreadCard({ subThread }) {
    return (
        <div
            className="border border-border-mid rounded bg-surface-1 p-3"
            style={{ borderLeft: `3px solid ${sevColor(subThread.max_severity)}` }}
        >
            <div className="flex items-start justify-between gap-2 mb-1">
                <h4 className="font-sans text-xs font-bold text-text-primary uppercase tracking-wide">
                    {subThread.name}
                </h4>
                <span
                    className="flex-shrink-0 font-mono text-[10px] font-bold px-1.5 py-0.5 rounded"
                    style={{
                        backgroundColor: sevColor(subThread.max_severity) + '33',
                        color: sevColor(subThread.max_severity),
                    }}
                >
                    SEV {subThread.max_severity}
                </span>
            </div>
            {subThread.summary && (
                <p className="text-xs text-text-secondary line-clamp-2 mb-2">{subThread.summary}</p>
            )}
            <div className="flex items-center gap-3 font-mono text-[10px] text-text-dim">
                <span>{subThread.event_count_24h || 0} in 24h</span>
                <span>{subThread.event_count_total || 0} total</span>
            </div>
        </div>
    );
}

function EventTimelineRow({ event }) {
    return (
        <Link
            href={eventUrl(event)}
            className="flex items-start gap-3 py-3 px-4 hover:bg-surface-2 transition-colors border-b border-border-subtle last:border-b-0 group"
        >
            {/* Timeline dot */}
            <div className="flex flex-col items-center pt-1">
                <span
                    className="w-2.5 h-2.5 rounded-full flex-shrink-0"
                    style={{ backgroundColor: sevColor(event.severity) }}
                />
            </div>

            {/* Content */}
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-0.5">
                    <span
                        className="font-mono text-[10px] uppercase tracking-wider"
                        style={{ color: CATEGORY_COLORS[event.category] || '#888' }}
                    >
                        {event.subcategory || event.category}
                    </span>
                    {event.status !== 'unverified' && (
                        <span className="font-mono text-[10px] text-amber uppercase">{event.status}</span>
                    )}
                </div>
                <h4 className="text-sm text-text-primary font-semibold leading-snug group-hover:text-green-bright transition-colors">
                    {(i18n.language === 'de' && event.title_de) || event.title}
                </h4>
                {(event.summary || event.summary_de) && (
                    <p className="text-xs text-text-secondary line-clamp-2 mt-1">{(i18n.language === 'de' && event.summary_de) || event.summary}</p>
                )}
                <div className="flex items-center gap-2 mt-1.5">
                    {event.country && (
                        <span className="font-mono text-[10px] text-text-muted uppercase">{event.country}</span>
                    )}
                    {event.source?.name && (
                        <span className="font-mono text-[10px] text-blue-bright">{event.source.name}</span>
                    )}
                    <span className="font-mono text-[10px] text-text-dim">{timeAgo(event.occurred_at)}</span>
                </div>
            </div>

            <SeverityBadge severity={event.severity} />
        </Link>
    );
}

export default function ConflictShow({ conflict, events }) {
    const { t } = useTranslation();

    return (
        <AppLayout breadcrumbs={[
            { label: 'Conflicts', href: '/conflicts' },
            { label: conflict.name },
        ]}>
            <div className="max-w-6xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 sm:gap-4 mb-3">
                        <h1 className="font-display text-2xl md:text-3xl tracking-wider text-green-bright">
                            {conflict.name}
                        </h1>
                        <div className="flex items-center gap-2 flex-shrink-0">
                            <Link
                                href={`/conflict/${conflict.slug}/timeline`}
                                className="font-mono text-xs tracking-widest uppercase px-3 py-1 border border-green-base text-green-bright hover:bg-surface-2 hover:text-green-neon transition-colors rounded"
                                title={t('conflict.viewTimelineTitle', 'Chronological severity chart')}
                            >
                                ◉ {t('conflict.viewTimeline', 'Timeline')}
                            </Link>
                            <span
                                className="font-mono text-sm font-bold px-3 py-1 rounded"
                                style={{
                                    backgroundColor: sevColor(conflict.max_severity) + '33',
                                    color: sevColor(conflict.max_severity),
                                    border: `1px solid ${sevColor(conflict.max_severity)}`,
                                }}
                            >
                                SEV {conflict.max_severity}
                            </span>
                        </div>
                    </div>

                    {conflict.summary && (
                        <p className="text-sm text-text-secondary leading-relaxed mb-4 max-w-3xl">
                            {conflict.summary}
                        </p>
                    )}

                    {/* Tags */}
                    <div className="flex flex-wrap gap-1.5 mb-4">
                        {(conflict.countries || []).map(c => (
                            <Link
                                key={c}
                                href={`/country/${c}`}
                                className="font-mono text-[10px] px-2 py-0.5 bg-surface-3 border border-border-mid text-text-muted rounded uppercase hover:border-green-base hover:text-green-bright transition-colors"
                            >
                                {c}
                            </Link>
                        ))}
                        {(conflict.categories || []).map(c => (
                            <span
                                key={c}
                                className="font-mono text-[10px] px-2 py-0.5 rounded uppercase"
                                style={{
                                    backgroundColor: `${CATEGORY_COLORS[c] || '#555'}22`,
                                    color: CATEGORY_COLORS[c] || '#888',
                                    border: `1px solid ${CATEGORY_COLORS[c] || '#555'}44`,
                                }}
                            >
                                {c}
                            </span>
                        ))}
                    </div>

                    {/* Stats bar */}
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-xs text-text-dim border-t border-border-mid pt-3">
                        <span><strong className="text-text-primary">{conflict.event_count_24h}</strong> in 24h</span>
                        <span><strong className="text-text-primary">{conflict.event_count_total}</strong> all-time</span>
                        {conflict.sub_thread_count >= 2 && (
                            <span><strong className="text-text-primary">{conflict.sub_thread_count}</strong> sub-threads</span>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main: Event timeline */}
                    <div className="lg:col-span-2">
                        <h2 className="font-sans text-sm font-bold text-text-primary uppercase tracking-widest mb-3">
                            {t('conflict.timeline', 'Event Timeline')}
                        </h2>
                        <div className="border border-border-mid rounded bg-surface-1">
                            {events.data && events.data.length > 0 ? (
                                events.data.map(event => (
                                    <EventTimelineRow key={event.id} event={event} />
                                ))
                            ) : (
                                <div className="p-6 text-center font-mono text-xs text-text-dim">
                                    No events in this conflict thread
                                </div>
                            )}
                        </div>

                        {/* Pagination */}
                        {events.links && events.links.length > 3 && (
                            <div className="flex items-center justify-center gap-2 mt-4">
                                {events.links.map((link, i) => (
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            className={`font-mono text-xs px-3 py-1 rounded border transition-colors ${
                                                link.active
                                                    ? 'border-green-base text-green-bright bg-surface-2'
                                                    : 'border-border-mid text-text-muted hover:border-green-base'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span
                                            key={i}
                                            className="font-mono text-xs px-3 py-1 text-text-dim"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Sidebar: Sub-threads */}
                    <div>
                        {conflict.children && conflict.children.length > 0 && (
                            <>
                                <h2 className="font-sans text-sm font-bold text-text-primary uppercase tracking-widest mb-3">
                                    {t('conflict.subThreads', 'Sub-Threads')}
                                </h2>
                                <div className="space-y-2">
                                    {conflict.children.map(child => (
                                        <SubThreadCard key={child.id} subThread={child} />
                                    ))}
                                </div>
                            </>
                        )}
                    </div>
                </div>

                <div className="mt-6">
                    <RelationshipGraph type="conflict" id={String(conflict.id)} depth={1} height={480} />
                </div>
            </div>
        </AppLayout>
    );
}
