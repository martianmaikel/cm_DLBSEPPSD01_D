import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';
import CategoryBadge from './CategoryBadge';
import StatusBadge from './StatusBadge';
import { eventUrl } from '../utils/eventUrl';

function formatDateTime(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', timeZoneName: 'short',
    });
}

function reliabilityLabel(score) {
    if (score == null) return null;
    const n = Number(score);
    if (n >= 0.8) return 'HIGH';
    if (n >= 0.5) return 'MEDIUM';
    return 'LOW';
}

function reliabilityColor(score) {
    const n = Number(score);
    if (n >= 0.8) return 'text-green-bright';
    if (n >= 0.5) return 'text-amber-bright';
    return 'text-red-bright';
}

/* ── Severity factor bar ── */
function SeverityFactorBar({ label, value }) {
    const pct = ((value || 0) / 10) * 100;

    let barColor = 'bg-green-base';
    if (value >= 7) barColor = 'bg-red-bright';
    else if (value >= 4) barColor = 'bg-amber-bright';

    return (
        <div className="flex items-center gap-3">
            <span className="font-mono text-[10px] text-text-muted uppercase tracking-wider w-24 flex-shrink-0">
                {label}
            </span>
            <div className="flex-1 h-1.5 bg-surface-3 rounded-full overflow-hidden">
                <div
                    className={`h-full rounded-full transition-all ${barColor}`}
                    style={{ width: `${pct}%` }}
                />
            </div>
            <span className="font-mono text-xs text-text-secondary w-5 text-right flex-shrink-0">
                {value ?? '—'}
            </span>
        </div>
    );
}

/* ── Structured intel row ── */
function IntelRow({ label, value, className = '' }) {
    if (!value) return null;
    return (
        <div className="flex items-baseline gap-2">
            <span className="font-mono text-[10px] text-text-dim uppercase tracking-wider w-20 flex-shrink-0">
                {label}
            </span>
            <span className={`font-mono text-xs text-text-secondary ${className}`}>
                {value}
            </span>
        </div>
    );
}

export default function EventDetailPanel({ event, onClose }) {
    const { t, i18n } = useTranslation();
    const isDe = i18n.language === 'de';

    if (!event) return null;

    const factors = event.severity_factors;
    const raw = event.entities_json;
    const entities = Array.isArray(raw) ? raw : (typeof raw === 'string' ? JSON.parse(raw) : Object.values(raw || {}));
    const actors = entities.filter(e => e.type === 'person' || e.type === 'unit' || e.type === 'organization').map(e => e.name);
    const locations = entities.filter(e => e.type === 'location').map(e => e.name);
    const reliability = reliabilityLabel(event.source_reliability);

    return (
        <div className="fixed inset-0 md:inset-y-0 md:left-auto md:right-0 w-full md:max-w-md bg-surface-0 md:border-l border-border-mid shadow-2xl z-50 flex flex-col overflow-hidden">
            {/* ── Header ── */}
            <div className="flex items-start justify-between p-4 border-b border-border-mid bg-surface-1">
                <div className="flex flex-wrap gap-2">
                    <CategoryBadge category={event.category} />
                    <StatusBadge status={event.status} />
                </div>
                <button
                    onClick={onClose}
                    className="font-mono text-text-muted hover:text-red-bright transition-colors text-lg leading-none ml-2"
                    aria-label="Close panel"
                >
                    ✕
                </button>
            </div>

            {/* ── Scrollable content ── */}
            <div className="flex-1 overflow-y-auto">
                {/* Title + timestamp */}
                <div className="px-4 pt-4 pb-3">
                    <h2 className="font-sans font-bold text-text-primary text-lg leading-snug">
                        {(isDe && event.title_de) || event.title}
                    </h2>
                    {event.occurred_at && (
                        <span className="block mt-1.5 font-mono text-[11px] text-text-muted">
                            {formatDateTime(event.occurred_at)}
                        </span>
                    )}
                </div>

                {/* Location tags */}
                <div className="px-4 pb-3 flex flex-wrap gap-1.5">
                    {event.country && (
                        <span className="font-mono text-[10px] px-2 py-0.5 bg-surface-2 border border-border-mid text-text-secondary rounded uppercase tracking-wider">
                            {event.country}
                        </span>
                    )}
                    {event.region && (
                        <span className="font-mono text-[10px] px-2 py-0.5 bg-surface-2 border border-border-subtle text-text-muted rounded">
                            {event.region}
                        </span>
                    )}
                </div>

                {/* ── Severity Factors ── */}
                <div className="px-4 pb-4">
                    <div className="flex items-center gap-2 mb-3">
                        <h3 className="font-mono text-[10px] text-green-base uppercase tracking-widest">
                            {t('event.severityFactors', 'Severity Factors')}
                        </h3>
                        <div className="flex-1 h-px bg-border-subtle" />
                        <span className="font-mono text-xs text-text-muted">
                            {t('event.onNews', 'ON NEWS')}
                        </span>
                    </div>
                    <div className="space-y-2">
                        <SeverityFactorBar label={t('event.factor.impact', 'Impact')} value={factors?.impact ?? event.severity} />
                        <SeverityFactorBar label={t('event.factor.casualty', 'Casualty')} value={factors?.casualty} />
                        <SeverityFactorBar label={t('event.factor.escalation', 'Escalation')} value={factors?.escalation} />
                        <SeverityFactorBar label={t('event.factor.international', 'International')} value={factors?.international} />
                    </div>
                </div>

                {/* ── AI Summary ── */}
                {event.summary && (
                    <div className="px-4 pb-4">
                        <div className="flex items-center gap-2 mb-2">
                            <h3 className="font-mono text-[10px] text-green-base uppercase tracking-widest">
                                {t('event.aiSummary', 'AI Summary')}
                            </h3>
                            <div className="flex-1 h-px bg-border-subtle" />
                        </div>
                        <div className="bg-surface-1 border border-border-mid rounded p-3">
                            <p className="text-text-secondary text-sm leading-relaxed">
                                {(isDe && event.summary_de) || event.summary}
                            </p>
                            <span className="block mt-2 font-mono text-[10px] text-text-dim">
                                [{t('event.aiGenerated')}]
                            </span>
                        </div>
                    </div>
                )}

                {/* ── Structured Intel ── */}
                <div className="px-4 pb-4">
                    <div className="flex items-center gap-2 mb-3">
                        <h3 className="font-mono text-[10px] text-green-base uppercase tracking-widest">
                            {t('event.structuredIntel', 'Structured Intel')}
                        </h3>
                        <div className="flex-1 h-px bg-border-subtle" />
                    </div>
                    <div className="bg-surface-1 border border-border-mid rounded p-3 space-y-1.5">
                        <IntelRow label={t('event.intel.where', 'Where')} value={locations[0] || event.region || event.country} />
                        <IntelRow label={t('event.intel.when', 'When')} value={formatDateTime(event.occurred_at)} />
                        {actors.length > 0 && (
                            <IntelRow label={t('event.intel.actors', 'Actors')} value={actors.join(', ')} />
                        )}
                        {event.category && (
                            <IntelRow label={t('event.intel.type', 'Type')} value={event.category} />
                        )}
                        {event.coordinates && (
                            <IntelRow
                                label={t('event.intel.coords', 'Coords')}
                                value={`${event.coordinates[1]?.toFixed(4)}, ${event.coordinates[0]?.toFixed(4)}`}
                            />
                        )}
                    </div>
                </div>

                {/* ── Source ── */}
                <div className="px-4 pb-4">
                    <div className="flex items-center gap-2 mb-3">
                        <h3 className="font-mono text-[10px] text-green-base uppercase tracking-widest">
                            {t('event.source', 'Source')}
                        </h3>
                        <div className="flex-1 h-px bg-border-subtle" />
                    </div>
                    <div className="bg-surface-1 border border-border-mid rounded p-3 space-y-1.5">
                        {event.source_name && (
                            <div className="flex items-baseline gap-2">
                                <span className="font-mono text-[10px] text-text-dim uppercase tracking-wider w-20 flex-shrink-0">
                                    {t('event.intel.source', 'Source')}
                                </span>
                                {event.source_url ? (
                                    <a
                                        href={event.source_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="font-mono text-xs text-blue-bright hover:text-green-bright transition-colors underline underline-offset-2"
                                    >
                                        {event.source_name}
                                    </a>
                                ) : (
                                    <span className="font-mono text-xs text-blue-bright">{event.source_name}</span>
                                )}
                            </div>
                        )}
                        {reliability && (
                            <div className="flex items-baseline gap-2">
                                <span className="font-mono text-[10px] text-text-dim uppercase tracking-wider w-20 flex-shrink-0">
                                    {t('event.intel.reliability', 'Reliability')}
                                </span>
                                <span className={`font-mono text-xs font-bold uppercase tracking-wider ${reliabilityColor(event.source_reliability)}`}>
                                    {reliability}
                                </span>
                            </div>
                        )}
                        <div className="flex items-baseline gap-2">
                            <span className="font-mono text-[10px] text-text-dim uppercase tracking-wider w-20 flex-shrink-0">
                                {t('event.confidence', 'Confidence')}
                            </span>
                            <span className="font-mono text-xs text-text-secondary">
                                {event.confidence * 10}%
                            </span>
                        </div>
                        {event.corroboration_count > 0 && (
                            <div className="flex items-baseline gap-2">
                                <span className="font-mono text-[10px] text-text-dim uppercase tracking-wider w-20 flex-shrink-0">
                                    {t('event.intel.corroboration', 'Corrobor.')}
                                </span>
                                <span className="font-mono text-xs text-amber-bright">
                                    +{event.corroboration_count} {t('event.independentSources', 'independent sources')}
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* ── Footer ── */}
            <div className="p-4 border-t border-border-mid bg-surface-1 space-y-2">
                {event.country_code && (
                    <Link
                        href={`/country/${event.country_code.toLowerCase()}`}
                        className="block w-full text-center font-mono text-xs tracking-widest uppercase py-2 border border-border-mid text-text-secondary hover:border-green-base hover:text-green-bright transition-colors rounded"
                    >
                        {t('event.moreInCountry', 'More in')} {event.country}
                    </Link>
                )}
                <Link
                    href={eventUrl(event)}
                    className="block w-full text-center font-mono text-xs tracking-widest uppercase py-2 border border-border-active text-green-bright hover:bg-green-dim transition-colors rounded"
                >
                    {t('event.viewFull', 'View Full Event')}
                </Link>
                <Link
                    href="/newsletter"
                    className="flex items-center justify-center gap-2 w-full font-mono text-[10px] tracking-wider py-1.5 text-text-dim hover:text-green-bright transition-colors"
                >
                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    {t('cta.eventAlertCta')}
                </Link>
            </div>
        </div>
    );
}
