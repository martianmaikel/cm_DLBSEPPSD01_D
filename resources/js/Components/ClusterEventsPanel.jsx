import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';
import CategoryBadge from './CategoryBadge';
import StatusBadge from './StatusBadge';
import { eventUrl } from '../utils/eventUrl';

function sevColor(severity) {
    if (severity >= 7) return '#E74C3C';
    if (severity >= 4) return '#F59E0B';
    return '#52A844';
}

function formatTime(str) {
    if (!str) return '—';
    const d = new Date(str);
    const diffMs = Date.now() - d;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24) return `${diffH}h ago`;
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

export default function ClusterEventsPanel({ events, onClose, onEventSelect }) {
    const { t, i18n } = useTranslation();
    const isDe = i18n.language === 'de';

    useEffect(() => {
        const handleKey = (e) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', handleKey);
        return () => window.removeEventListener('keydown', handleKey);
    }, [onClose]);

    const sorted = [...events].sort((a, b) => b.severity - a.severity);

    return (
        <>
            {/* Backdrop */}
            <div className="fixed inset-0 bg-black/50 z-40" onClick={onClose} />

            {/* Panel */}
            <div className="fixed inset-0 md:inset-y-0 md:left-auto md:right-0 w-full md:max-w-sm bg-surface-0 md:border-l border-border-mid shadow-2xl z-50 flex flex-col overflow-hidden">
                {/* Header */}
                <div className="flex items-start justify-between p-4 border-b border-border-mid bg-surface-1">
                    <div>
                        <span className="font-mono text-[10px] text-green-base uppercase tracking-widest">
                            {t('cluster.region', 'Region Events')}
                        </span>
                        <h2 className="font-display text-xl tracking-wider text-text-primary mt-0.5">
                            {sorted.length} EVENT{sorted.length !== 1 ? 'S' : ''}
                        </h2>
                    </div>
                    <button
                        onClick={onClose}
                        className="font-mono text-text-muted hover:text-red-bright transition-colors text-lg leading-none ml-2"
                        aria-label="Close panel"
                    >
                        &#x2715;
                    </button>
                </div>

                {/* Event list */}
                <div className="flex-1 overflow-y-auto p-4 space-y-1.5">
                    {sorted.map((ev) => (
                        <Link
                            key={ev.id}
                            href={eventUrl(ev)}
                            className="block bg-surface-1 border border-border-mid rounded p-2.5 hover:border-border-active transition-colors group"
                            onClick={(e) => {
                                if (onEventSelect) {
                                    e.preventDefault();
                                    onEventSelect(ev);
                                    onClose();
                                }
                            }}
                        >
                            <div className="flex items-start gap-2">
                                {/* Severity indicator */}
                                <div
                                    className="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0"
                                    style={{ backgroundColor: sevColor(ev.severity) }}
                                />
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs text-text-primary leading-snug line-clamp-2 group-hover:text-green-bright transition-colors">
                                        {(isDe && ev.title_de) || ev.title}
                                    </p>
                                    {ev.summary && (
                                        <p className="text-[11px] text-text-muted leading-snug line-clamp-1 mt-0.5">
                                            {(isDe && ev.summary_de) || ev.summary}
                                        </p>
                                    )}
                                    <div className="flex items-center gap-2 mt-1">
                                        <CategoryBadge category={ev.category} />
                                        <StatusBadge status={ev.status} />
                                        {ev.corroboration_count > 0 && (
                                            <span className="font-mono text-[10px] text-amber">
                                                +{ev.corroboration_count}
                                            </span>
                                        )}
                                        <span className="font-mono text-[10px] text-text-dim ml-auto">
                                            {formatTime(ev.occurred_at)}
                                        </span>
                                    </div>
                                </div>
                                {/* Severity number */}
                                <span
                                    className="font-mono text-xs font-bold flex-shrink-0"
                                    style={{ color: sevColor(ev.severity) }}
                                >
                                    {ev.severity}
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}
