import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import SeverityBadge from './SeverityBadge';
import StatusBadge from './StatusBadge';
import CategoryBadge from './CategoryBadge';
import { eventUrl } from '../utils/eventUrl';

function timeAgo(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

export default function EventCard({ event }) {
    const { t, i18n } = useTranslation();
    const isDe = i18n.language === 'de';

    const isUnverified = event.status === 'unverified';
    const cardClass = isUnverified
        ? 'opacity-60 border-dashed border-border-mid'
        : 'border-border-mid hover:border-border-active';

    return (
        <Link
            href={eventUrl(event)}
            className={`block bg-surface-1 border rounded p-4 transition-all hover:bg-surface-2 group ${cardClass}`}
        >
            {/* Top row: badges + severity */}
            <div className="flex items-start justify-between gap-3 mb-2">
                <div className="flex flex-wrap items-center gap-2">
                    <CategoryBadge category={event.category} />
                    <StatusBadge status={event.status} />
                </div>
                <SeverityBadge severity={event.severity} />
            </div>

            {/* Title */}
            <h3 className="font-sans font-semibold text-text-primary text-base leading-snug mb-2 group-hover:text-green-bright transition-colors">
                {(isDe && event.title_de) || event.title}
            </h3>

            {/* AI Summary */}
            {event.summary && (
                <div className="mb-3">
                    <p className="text-text-secondary text-sm leading-relaxed line-clamp-2">
                        {(isDe && event.summary_de) || event.summary}
                    </p>
                    <span className="inline-block mt-1 font-mono text-xs text-text-dim tracking-wide">
                        [{t('event.aiGenerated')}]
                    </span>
                </div>
            )}

            {/* Confidence bar */}
            <div className="flex items-center gap-2 mb-3">
                <span className="font-mono text-xs text-text-muted tracking-wide w-20">
                    {t('event.confidence')}
                </span>
                <div className="flex-1 h-1 bg-surface-3 rounded-full overflow-hidden">
                    <div
                        className="h-full bg-green-base rounded-full transition-all"
                        style={{ width: `${(event.confidence / 10) * 100}%` }}
                    />
                </div>
                <span className="font-mono text-xs text-text-secondary w-6 text-right">
                    {event.confidence}
                </span>
            </div>

            {/* Footer: source, time, corroborations */}
            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs font-mono text-text-muted">
                <div className="flex items-center gap-3">
                    {event.source_name && (
                        <span className="text-blue-bright truncate max-w-[120px] sm:max-w-none">{event.source_name}</span>
                    )}
                    {event.country && (
                        <span>{event.country}</span>
                    )}
                </div>
                <div className="flex items-center gap-3">
                    {event.corroboration_count > 0 && (
                        <span className="text-amber-bright">
                            +{event.corroboration_count} {t('event.corroborations')}
                        </span>
                    )}
                    <span>{timeAgo(event.occurred_at)}</span>
                </div>
            </div>
        </Link>
    );
}
