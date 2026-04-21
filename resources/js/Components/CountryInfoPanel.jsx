import { useState, useEffect, useCallback } from 'react';
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

function threatLabel(level) {
    if (level >= 8) return 'CRITICAL';
    if (level >= 6) return 'HIGH';
    if (level >= 4) return 'ELEVATED';
    if (level >= 2) return 'MODERATE';
    return 'LOW';
}

function formatTime(str) {
    if (!str) return '—';
    const d = new Date(str);
    const now = new Date();
    const diffMs = now - d;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24) return `${diffH}h ago`;
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function ThreatGauge({ level }) {
    const color = sevColor(level);
    const pct = (level / 10) * 100;
    return (
        <div>
            <div className="flex items-center justify-between mb-1">
                <span className="font-mono text-[10px] text-text-muted uppercase tracking-widest">Threat Level</span>
                <div className="flex items-center gap-2">
                    <span className="font-mono text-[10px] uppercase tracking-wider" style={{ color }}>
                        {threatLabel(level)}
                    </span>
                    <span className="font-mono text-lg font-bold" style={{ color }}>
                        {level}/10
                    </span>
                </div>
            </div>
            <div className="h-2 bg-surface-3 rounded-full overflow-hidden">
                <div
                    className="h-full rounded-full transition-all duration-500"
                    style={{ width: `${pct}%`, backgroundColor: color }}
                />
            </div>
        </div>
    );
}

export default function CountryInfoPanel({ countryCode, onClose }) {
    const { t, i18n } = useTranslation();
    const isDe = i18n.language === 'de';
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);

    const fetchData = useCallback(async () => {
        if (!countryCode) return;
        setLoading(true);
        setError(false);
        try {
            const res = await fetch(`/api/map/country-brief/${countryCode}`);
            if (!res.ok) throw new Error('fetch failed');
            setData(await res.json());
        } catch {
            setError(true);
        } finally {
            setLoading(false);
        }
    }, [countryCode]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    useEffect(() => {
        const handleKey = (e) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', handleKey);
        return () => window.removeEventListener('keydown', handleKey);
    }, [onClose]);

    if (!countryCode) return null;

    const intel = data?.intelligence;
    const topEvents = data?.topEvents ?? [];
    const threads = data?.activeThreads ?? [];
    const countryName = data?.country?.name ?? countryCode;

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
                            {t('country.intelligence', 'Country Intelligence')}
                        </span>
                        <h2 className="font-display text-xl tracking-wider text-text-primary mt-0.5">
                            {loading ? '...' : countryName.toUpperCase()}
                        </h2>
                    </div>
                    <button
                        onClick={onClose}
                        className="font-mono text-text-muted hover:text-red-bright transition-colors text-lg leading-none ml-2"
                        aria-label="Close panel"
                    >
                        ✕
                    </button>
                </div>

                {/* Scrollable content */}
                <div className="flex-1 overflow-y-auto">
                    {loading && (
                        <div className="p-6 space-y-3">
                            {[...Array(4)].map((_, i) => (
                                <div key={i} className="h-4 bg-surface-2 rounded animate-pulse" style={{ width: `${70 + i * 8}%` }} />
                            ))}
                        </div>
                    )}

                    {error && (
                        <div className="p-6 text-center">
                            <p className="font-mono text-xs text-red-bright mb-3">
                                {t('common.loadError', 'Failed to load data')}
                            </p>
                            <button
                                onClick={fetchData}
                                className="font-mono text-xs text-text-muted hover:text-green-bright border border-border-mid px-3 py-1.5 rounded transition-colors"
                            >
                                {t('common.retry', 'Retry')}
                            </button>
                        </div>
                    )}

                    {!loading && !error && data && (
                        <>
                            {/* Threat Level */}
                            {intel && (
                                <div className="px-4 pt-4 pb-3">
                                    <ThreatGauge level={intel.threat_level} />
                                    <div className="flex items-center gap-4 mt-2 font-mono text-[10px] text-text-dim">
                                        <span>
                                            <strong className="text-text-primary">{intel.event_count_24h}</strong> events in 24h
                                        </span>
                                        <span>
                                            Avg Sev: <strong className="text-text-primary">{intel.avg_severity}</strong>
                                        </span>
                                    </div>
                                </div>
                            )}

                            {/* Intelligence Briefing */}
                            {intel && (intel.intelligence_briefing_en || intel.intelligence_briefing_de) && (
                                <div className="px-4 pb-3">
                                    <div className="flex items-center gap-2 mb-2">
                                        <h3 className="font-mono text-[10px] text-amber uppercase tracking-widest">Intel</h3>
                                        <span className="font-sans text-xs font-bold text-text-primary uppercase tracking-wide">
                                            {t('country.briefing', 'Intelligence Briefing')}
                                        </span>
                                        <div className="flex-1 h-px bg-border-subtle" />
                                    </div>
                                    <div className="bg-surface-1 border border-border-mid rounded p-3">
                                        <p className="text-xs text-text-secondary leading-relaxed whitespace-pre-line line-clamp-6">
                                            {isDe
                                                ? (intel.intelligence_briefing_de || intel.intelligence_briefing_en)
                                                : intel.intelligence_briefing_en}
                                        </p>
                                        {intel.generated_at && (
                                            <p className="font-mono text-[10px] text-text-dim mt-2">
                                                Generated: {new Date(intel.generated_at).toLocaleString()}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Top Events (highest severity, 24h) */}
                            <div className="px-4 pb-3">
                                <div className="flex items-center gap-2 mb-2">
                                    <h3 className="font-mono text-[10px] text-green-base uppercase tracking-widest">
                                        {t('country.topEvents', 'Top Events')}
                                    </h3>
                                    <div className="flex-1 h-px bg-border-subtle" />
                                    <span className="font-mono text-[10px] text-text-dim">24H</span>
                                </div>

                                {topEvents.length === 0 ? (
                                    <div className="bg-surface-1 border border-border-subtle rounded p-4 text-center font-mono text-xs text-text-muted">
                                        {t('country.noEvents', 'No events in the last 24 hours')}
                                    </div>
                                ) : (
                                    <div className="space-y-1.5">
                                        {topEvents.map((ev) => (
                                            <Link
                                                key={ev.id}
                                                href={eventUrl(ev)}
                                                className="block bg-surface-1 border border-border-mid rounded p-2.5 hover:border-border-active transition-colors group"
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
                                                        <div className="flex items-center gap-2 mt-1">
                                                            <CategoryBadge category={ev.category} />
                                                            <StatusBadge status={ev.status} />
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
                                )}
                            </div>

                            {/* Active Conflicts */}
                            {threads.length > 0 && (
                                <div className="px-4 pb-4">
                                    <div className="flex items-center gap-2 mb-2">
                                        <h3 className="font-mono text-[10px] text-green-base uppercase tracking-widest">
                                            {t('country.activeConflicts', 'Active Conflicts')}
                                        </h3>
                                        <div className="flex-1 h-px bg-border-subtle" />
                                    </div>
                                    <div className="space-y-1.5">
                                        {threads.map((thread) => (
                                            <Link
                                                key={thread.id}
                                                href={`/conflict/${thread.slug}`}
                                                className="block bg-surface-1 border border-border-mid rounded p-3 hover:border-border-active transition-colors group"
                                                style={{ borderLeft: `3px solid ${sevColor(thread.max_severity)}` }}
                                            >
                                                <p className="text-xs font-semibold text-text-primary group-hover:text-green-bright transition-colors">
                                                    {thread.name}
                                                </p>
                                                <div className="flex items-center gap-3 mt-1 font-mono text-[10px] text-text-dim">
                                                    <span>{thread.event_count} events</span>
                                                    <span style={{ color: sevColor(thread.max_severity) }}>
                                                        SEV {thread.max_severity}
                                                    </span>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>

                {/* Footer */}
                <div className="p-4 border-t border-border-mid bg-surface-1 space-y-2">
                    <Link
                        href={`/country/${countryCode.toLowerCase()}`}
                        className="block w-full text-center font-mono text-xs tracking-widest uppercase py-2.5 border border-border-active text-green-bright hover:bg-green-dim transition-colors rounded"
                    >
                        {t('country.viewFull', 'View Full Country Page')}
                    </Link>
                </div>
            </div>
        </>
    );
}
