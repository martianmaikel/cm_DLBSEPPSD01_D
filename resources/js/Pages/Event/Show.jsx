import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import SeverityBadge from '../../Components/SeverityBadge';
import StatusBadge from '../../Components/StatusBadge';
import CategoryBadge from '../../Components/CategoryBadge';
import ShareButtons from '../../Components/ShareButtons';
import MiniMap from '../../Components/MiniMap';
import { eventUrl } from '../../utils/eventUrl';

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString(undefined, {
        year: 'numeric', month: 'long', day: 'numeric',
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

/* ── Section header with green accent line ── */
function SectionHeader({ children, right }) {
    return (
        <div className="flex items-center gap-3 mb-4">
            <h2 className="font-mono text-xs text-green-base uppercase tracking-widest flex-shrink-0">
                {children}
            </h2>
            <div className="flex-1 h-px bg-border-subtle" />
            {right && <span className="font-mono text-xs text-text-muted flex-shrink-0">{right}</span>}
        </div>
    );
}

/* ── Severity factor bar ── */
function SeverityFactorBar({ label, value }) {
    const pct = ((value || 0) / 10) * 100;

    let barColor = 'bg-green-base';
    if (value >= 7) barColor = 'bg-red-bright';
    else if (value >= 4) barColor = 'bg-amber-bright';

    return (
        <div className="flex items-center gap-3">
            <span className="font-mono text-[11px] text-text-muted uppercase tracking-wider w-28 flex-shrink-0">
                {label}
            </span>
            <div className="flex-1 h-2 bg-surface-3 rounded-full overflow-hidden">
                <div
                    className={`h-full rounded-full transition-all ${barColor}`}
                    style={{ width: `${pct}%` }}
                />
            </div>
            <span className="font-mono text-sm text-text-secondary w-6 text-right flex-shrink-0">
                {value ?? '—'}
            </span>
        </div>
    );
}

/* ── Structured intel row ── */
function IntelRow({ label, value, className = '' }) {
    if (!value) return null;
    return (
        <div className="flex items-baseline gap-3 py-1.5 border-b border-border-subtle last:border-b-0">
            <span className="font-mono text-[11px] text-text-dim uppercase tracking-wider w-24 flex-shrink-0">
                {label}
            </span>
            <span className={`font-mono text-sm text-text-secondary flex-1 ${className}`}>
                {value}
            </span>
        </div>
    );
}

export default function Show({ event, corroboration_chain = [], thread = null }) {
    const { t, i18n } = useTranslation();
    const isDe = i18n.language === 'de';

    const factors = event.severity_factors;
    const raw = event.entities_json;
    const entities = Array.isArray(raw) ? raw : (typeof raw === 'string' ? JSON.parse(raw) : Object.values(raw || {}));
    const actors = entities.filter(e => e.type === 'person' || e.type === 'unit' || e.type === 'organization').map(e => e.name);
    const locations = entities.filter(e => e.type === 'location').map(e => e.name);
    const sourceReliability = event.source?.reliability_score;
    const reliability = reliabilityLabel(sourceReliability);

    const breadcrumbs = [
        ...(event.country_code ? [{ label: event.country, href: `/country/${event.country_code.toLowerCase()}` }] : []),
        { label: `#${event.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="max-w-3xl mx-auto space-y-5">
                {/* ── Header ── */}
                <div className="bg-surface-1 border border-border-mid rounded p-5 md:p-6">
                    <div className="flex items-start gap-3 md:gap-4">
                        <SeverityBadge severity={event.severity} />
                        <div className="flex-1 min-w-0">
                            <div className="flex flex-wrap gap-2 mb-2">
                                <CategoryBadge category={event.category} />
                                <StatusBadge status={event.status} />
                            </div>
                            <h1 className="font-sans font-bold text-text-primary text-xl md:text-2xl leading-snug">
                                {(isDe && event.title_de) || event.title}
                            </h1>
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2">
                                <span className="font-mono text-xs text-text-muted">
                                    {formatDate(event.occurred_at)}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Location tags */}
                    <div className="flex flex-wrap gap-1.5 mt-4">
                        {event.country_code && (
                            <Link
                                href={`/country/${event.country_code.toLowerCase()}`}
                                className="font-mono text-[11px] px-2 py-0.5 bg-surface-2 border border-border-mid text-text-secondary rounded uppercase tracking-wider hover:border-green-base hover:text-green-bright transition-colors"
                            >
                                {event.country_code}
                            </Link>
                        )}
                        {event.country && (
                            <span className="font-mono text-[11px] px-2 py-0.5 bg-surface-2 border border-border-subtle text-text-muted rounded">
                                {event.country}
                            </span>
                        )}
                        {event.region && (
                            <span className="font-mono text-[11px] px-2 py-0.5 bg-surface-2 border border-border-subtle text-text-muted rounded">
                                {event.region}
                            </span>
                        )}
                    </div>

                    <div className="mt-4">
                        <ShareButtons url={eventUrl(event)} title={event.title} />
                    </div>
                </div>

                {/* ── Severity Factors ── */}
                {factors && (
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <SectionHeader right={t('event.onNews', 'ON NEWS')}>
                            {t('event.severityFactors', 'Severity Factors')}
                        </SectionHeader>
                        <div className="space-y-3">
                            <SeverityFactorBar label={t('event.factor.impact', 'Impact')} value={factors.impact ?? event.severity} />
                            <SeverityFactorBar label={t('event.factor.casualty', 'Casualty')} value={factors.casualty} />
                            <SeverityFactorBar label={t('event.factor.escalation', 'Escalation')} value={factors.escalation} />
                            <SeverityFactorBar label={t('event.factor.international', 'International')} value={factors.international} />
                        </div>
                    </div>
                )}

                {/* ── AI Summary ── */}
                {event.summary && (
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <SectionHeader>{t('event.aiSummary', 'AI Summary')}</SectionHeader>
                        <div className="bg-surface-2 border border-border-mid rounded p-4">
                            <p className="text-text-secondary text-sm leading-relaxed">
                                {(isDe && event.summary_de) || event.summary}
                            </p>
                            <div className="flex items-center gap-2 mt-3">
                                <span className="inline-block w-1.5 h-1.5 rounded-full bg-blue-bright" />
                                <span className="font-mono text-[10px] text-blue-bright tracking-wide">
                                    {t('event.aiGenerated')}
                                </span>
                            </div>
                        </div>
                    </div>
                )}

                {/* ── Structured Intel + Mini Map ── */}
                <div className="bg-surface-1 border border-border-mid rounded p-5">
                    <SectionHeader>{t('event.structuredIntel', 'Structured Intel')}</SectionHeader>
                    <div className={`${event.coordinates ? 'grid grid-cols-1 md:grid-cols-2 gap-4' : ''}`}>
                        <div className="bg-surface-2 border border-border-mid rounded p-4">
                            <IntelRow label={t('event.intel.where', 'Where')} value={locations[0] || event.region || event.country} />
                            <IntelRow label={t('event.intel.when', 'When')} value={formatDate(event.occurred_at)} />
                            {actors.length > 0 && (
                                <IntelRow label={t('event.intel.actors', 'Actors')} value={actors.join(', ')} />
                            )}
                            <IntelRow label={t('event.intel.type', 'Type')} value={event.category} />
                            {event.coordinates && (
                                <IntelRow
                                    label={t('event.intel.coords', 'Coords')}
                                    value={`${Number(event.coordinates[1]).toFixed(4)}, ${Number(event.coordinates[0]).toFixed(4)}`}
                                    className="tabular-nums"
                                />
                            )}
                        </div>
                        {event.coordinates && (
                            <MiniMap
                                lat={Number(event.coordinates[1])}
                                lng={Number(event.coordinates[0])}
                                className="h-48 md:h-auto min-h-[12rem]"
                            />
                        )}
                    </div>
                </div>

                {/* ── Source & Confidence ── */}
                <div className="bg-surface-1 border border-border-mid rounded p-5">
                    <SectionHeader>{t('event.source', 'Source')}</SectionHeader>
                    <div className="bg-surface-2 border border-border-mid rounded p-4 space-y-2">
                        {(event.source?.name || event.source_name) && (
                            <div className="flex items-baseline gap-3">
                                <span className="font-mono text-[11px] text-text-dim uppercase tracking-wider w-24 flex-shrink-0">
                                    {t('event.intel.source', 'Source')}
                                </span>
                                {event.source_url ? (
                                    <a
                                        href={event.source_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="font-mono text-sm text-blue-bright hover:text-green-bright transition-colors underline underline-offset-2"
                                    >
                                        {event.source?.name || event.source_name}
                                    </a>
                                ) : (
                                    <span className="font-mono text-sm text-blue-bright">
                                        {event.source?.name || event.source_name}
                                    </span>
                                )}
                            </div>
                        )}
                        {event.source?.source_family && (
                            <div className="flex items-baseline gap-3">
                                <span className="font-mono text-[11px] text-text-dim uppercase tracking-wider w-24 flex-shrink-0">Family</span>
                                <span className="font-mono text-sm text-text-secondary">
                                    {event.source.source_family.name}
                                </span>
                            </div>
                        )}
                        {reliability && (
                            <div className="flex items-baseline gap-3">
                                <span className="font-mono text-[11px] text-text-dim uppercase tracking-wider w-24 flex-shrink-0">
                                    {t('event.intel.reliability', 'Reliability')}
                                </span>
                                <span className={`font-mono text-sm font-bold uppercase tracking-wider ${reliabilityColor(sourceReliability)}`}>
                                    {reliability}
                                </span>
                            </div>
                        )}
                        <div className="flex items-baseline gap-3">
                            <span className="font-mono text-[11px] text-text-dim uppercase tracking-wider w-24 flex-shrink-0">
                                {t('event.confidence', 'Confidence')}
                            </span>
                            <div className="flex items-center gap-3 flex-1">
                                <div className="flex-1 h-2 bg-surface-3 rounded-full overflow-hidden max-w-48">
                                    <div
                                        className="h-full bg-green-base rounded-full"
                                        style={{ width: `${(event.confidence / 10) * 100}%` }}
                                    />
                                </div>
                                <span className="font-mono text-sm text-text-secondary">
                                    {event.confidence * 10}%
                                </span>
                            </div>
                        </div>
                        {event.corroboration_count > 0 && (
                            <div className="flex items-baseline gap-3">
                                <span className="font-mono text-[11px] text-text-dim uppercase tracking-wider w-24 flex-shrink-0">
                                    {t('event.intel.corroboration', 'Corrobor.')}
                                </span>
                                <span className="font-mono text-sm text-amber-bright">
                                    +{event.corroboration_count} {t('event.independentSources', 'independent sources')}
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                {/* ── Conflict Thread ── */}
                {event.conflict_thread && (
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <SectionHeader>{t('event.thread', 'Conflict Thread')}</SectionHeader>
                        <Link
                            href={`/thread/${event.conflict_thread.id}`}
                            className="font-sans font-semibold text-amber hover:text-amber-bright transition-colors"
                        >
                            {event.conflict_thread.title}
                        </Link>
                        {event.conflict_thread.summary && (
                            <p className="mt-2 text-sm text-text-secondary leading-relaxed">{event.conflict_thread.summary}</p>
                        )}
                    </div>
                )}

                {/* ── Corroboration Chain ── */}
                {corroboration_chain.length > 0 && (
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <SectionHeader>
                            {t('event.corroborations', 'Corroborations')} ({corroboration_chain.length})
                        </SectionHeader>
                        <div className="space-y-2">
                            {corroboration_chain.map((link, i) => {
                                const other = link.event;
                                return (
                                    <div key={i} className="flex items-start gap-3 p-3 bg-surface-2 border border-border-subtle rounded">
                                        <div className="flex-shrink-0 w-6 h-6 flex items-center justify-center bg-surface-3 border border-border-mid rounded font-mono text-xs text-text-muted">
                                            {i + 1}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            {other ? (
                                                <Link
                                                    href={eventUrl(other)}
                                                    className="text-sm text-text-primary hover:text-green-bright transition-colors block"
                                                >
                                                    {other.title}
                                                </Link>
                                            ) : (
                                                <span className="text-sm text-text-muted">Unknown event</span>
                                            )}
                                            <div className="flex flex-wrap gap-x-3 gap-y-0.5 mt-1 font-mono text-[11px] text-text-dim">
                                                <span>Source: {other?.source?.name ?? '—'}</span>
                                                <span>Match: {Math.round((link.similarity_score ?? 0) * 100)}%</span>
                                                <span>Method: {link.match_method}</span>
                                                {link.cross_family && (
                                                    <span className="text-green-bright">Cross-family</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* ── Entities ── */}
                {entities.length > 0 && (
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <SectionHeader>Entities</SectionHeader>
                        <div className="flex flex-wrap gap-2">
                            {entities.map((entity, i) => (
                                <span
                                    key={i}
                                    className="font-mono text-xs px-2.5 py-1 bg-surface-2 border border-border-subtle text-text-secondary rounded"
                                    title={entity.type}
                                >
                                    <span className="text-text-dim uppercase text-[9px] tracking-wider mr-1.5">{entity.type}</span>
                                    {entity.name}
                                </span>
                            ))}
                        </div>
                    </div>
                )}

                {/* ── Raw Content ── */}
                {event.raw_content && (
                    <div className="bg-surface-1 border border-border-mid rounded p-5">
                        <SectionHeader>{t('event.rawContent', 'Raw Content')}</SectionHeader>
                        <pre className="text-xs text-text-secondary font-mono leading-relaxed whitespace-pre-wrap break-words bg-surface-2 border border-border-subtle rounded p-4 max-h-60 overflow-y-auto">
                            {event.raw_content}
                        </pre>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
