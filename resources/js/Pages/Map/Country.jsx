import { useState, useEffect, useCallback } from 'react';
import { Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ComposableMap, Geographies, Geography, Marker } from 'react-simple-maps';
import AppLayout from '../../Layouts/AppLayout';
import EventCard from '../../Components/EventCard';
import EventDetailPanel from '../../Components/EventDetailPanel';
import FilterBar from '../../Components/FilterBar';
import DiamondMarker from '../../Components/DiamondMarker';
import RelationshipGraph from '../../Components/Graph/RelationshipGraph';

const GEO_URL = '/vendor/world-atlas/countries-110m.json';

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

function ThreatLevelGauge({ level }) {
    const color = sevColor(level);
    const percentage = (level / 10) * 100;

    return (
        <div className="flex items-center gap-3">
            <div className="flex-1">
                <div className="flex items-center justify-between mb-1">
                    <span className="font-mono text-[10px] text-text-muted uppercase tracking-widest">Threat Level</span>
                    <span className="font-mono text-lg font-bold" style={{ color }}>
                        {level}/10
                    </span>
                </div>
                <div className="h-2 bg-surface-3 rounded-full overflow-hidden">
                    <div
                        className="h-full rounded-full transition-all duration-500"
                        style={{ width: `${percentage}%`, backgroundColor: color }}
                    />
                </div>
            </div>
        </div>
    );
}

function IntelligenceBriefing({ briefingEn, briefingDe, generatedAt }) {
    const { i18n } = useTranslation();
    const [expanded, setExpanded] = useState(false);
    const briefing = i18n.language === 'de' ? (briefingDe || briefingEn) : briefingEn;

    if (!briefing) return null;

    return (
        <div className="bg-surface-1 border border-border-mid rounded">
            <button
                onClick={() => setExpanded(!expanded)}
                className="w-full text-left px-4 py-3 flex items-center justify-between hover:bg-surface-2 transition-colors"
            >
                <div className="flex items-center gap-2">
                    <span className="font-mono text-[10px] text-amber uppercase tracking-widest">Intel</span>
                    <span className="font-sans text-sm font-bold text-text-primary uppercase tracking-wide">
                        Intelligence Briefing
                    </span>
                </div>
                <span className="font-mono text-[10px] text-green-base">
                    {expanded ? '−' : '+'}
                </span>
            </button>
            {expanded && (
                <div className="px-4 pb-4 border-t border-border-subtle">
                    <p className="text-sm text-text-secondary leading-relaxed mt-3 whitespace-pre-line">
                        {briefing}
                    </p>
                    {generatedAt && (
                        <p className="font-mono text-[10px] text-text-dim mt-3">
                            Generated: {new Date(generatedAt).toLocaleString()}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

function CategoryBreakdown({ breakdown }) {
    if (!breakdown || Object.keys(breakdown).length === 0) return null;

    const sorted = Object.entries(breakdown).sort((a, b) => b[1] - a[1]);
    const max = sorted[0]?.[1] || 1;

    return (
        <div className="bg-surface-1 border border-border-mid rounded p-4">
            <h3 className="font-mono text-[10px] text-text-muted uppercase tracking-widest mb-3">
                Event Categories (7d)
            </h3>
            <div className="space-y-2">
                {sorted.map(([cat, count]) => (
                    <div key={cat} className="flex items-center gap-2">
                        <span
                            className="font-mono text-[10px] uppercase tracking-wider w-20 text-right"
                            style={{ color: CATEGORY_COLORS[cat] || '#888' }}
                        >
                            {cat}
                        </span>
                        <div className="flex-1 h-2 bg-surface-3 rounded-full overflow-hidden">
                            <div
                                className="h-full rounded-full"
                                style={{
                                    width: `${(count / max) * 100}%`,
                                    backgroundColor: CATEGORY_COLORS[cat] || '#555',
                                }}
                            />
                        </div>
                        <span className="font-mono text-[10px] text-text-dim w-8 text-right">{count}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function Pagination({ links, meta }) {
    if (!meta || meta.last_page <= 1) return null;
    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-2 pt-4 font-mono text-xs text-text-muted">
            <span>Page {meta.current_page} of {meta.last_page}</span>
            <div className="flex flex-wrap gap-2 justify-center">
                {links.map((link, i) => (
                    <button
                        key={i}
                        disabled={!link.url}
                        onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                        className={`px-3 py-1 border rounded transition-colors ${
                            link.active
                                ? 'border-green-base text-green-bright bg-green-dim'
                                : link.url
                                    ? 'border-border-mid text-text-secondary hover:border-border-active'
                                    : 'border-border-subtle text-text-dim cursor-default'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
}

export default function Country({ country, events, coordinates = [], intelligence, activeThreads = [], filters = {} }) {
    const { t } = useTranslation();
    const [selectedEvent, setSelectedEvent] = useState(null);

    const breadcrumbs = [
        { label: 'Conflicts', href: '/conflicts' },
        { label: country.name },
    ];

    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ preserveScroll: true, preserveState: true });
        }, 60000);
        return () => clearInterval(interval);
    }, []);

    const handleEventClick = useCallback((event) => {
        setSelectedEvent(event);
    }, []);

    const eventList = events?.data ?? [];
    const paginationLinks = events?.links ?? [];
    const paginationMeta = events?.meta ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-4">
                {/* Page header with country info */}
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                    <div>
                        <h1 className="font-display text-3xl md:text-4xl tracking-wider text-green-bright">
                            {country.name.toUpperCase()}
                        </h1>
                        <p className="font-mono text-xs text-text-muted tracking-widest uppercase mt-1">
                            {country.continent_name}
                            {intelligence && (
                                <> · {intelligence.event_count_total} Events · Max Sev: {intelligence.max_severity}</>
                            )}
                            {!intelligence && (
                                <> · {paginationMeta?.total ?? eventList.length} Events</>
                            )}
                        </p>
                    </div>
                    <button
                        onClick={() => router.reload({ preserveScroll: true })}
                        className="self-start font-mono text-xs text-text-muted hover:text-green-bright transition-colors border border-border-mid hover:border-border-active px-3 py-1.5 rounded"
                    >
                        {t('common.refresh', 'Refresh')} ↺
                    </button>
                </div>

                {/* Intelligence overview row */}
                {intelligence && (
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                        {/* Threat Level */}
                        <div className="bg-surface-1 border border-border-mid rounded p-4">
                            <ThreatLevelGauge level={intelligence.threat_level} />
                            <div className="flex items-center gap-4 mt-3 font-mono text-[10px] text-text-dim">
                                <span><strong className="text-text-primary">{intelligence.event_count_24h}</strong> in 24h</span>
                                <span>Avg Sev: <strong className="text-text-primary">{intelligence.avg_severity}</strong></span>
                            </div>
                        </div>

                        {/* Category breakdown */}
                        <CategoryBreakdown breakdown={intelligence.category_breakdown} />

                        {/* Active conflicts */}
                        {activeThreads.length > 0 && (
                            <div className="bg-surface-1 border border-border-mid rounded p-4">
                                <h3 className="font-mono text-[10px] text-text-muted uppercase tracking-widest mb-3">
                                    Active Conflicts
                                </h3>
                                <div className="space-y-2">
                                    {activeThreads.map(thread => (
                                        <Link
                                            key={thread.id}
                                            href={`/conflict/${thread.slug}`}
                                            className="block text-xs text-text-secondary hover:text-green-bright transition-colors"
                                            style={{ borderLeft: `2px solid ${sevColor(thread.max_severity)}`, paddingLeft: '8px' }}
                                        >
                                            <span className="font-semibold">{thread.name}</span>
                                            <span className="font-mono text-[10px] text-text-dim ml-2">
                                                {thread.event_count} events
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Intelligence Briefing */}
                {intelligence && (
                    <IntelligenceBriefing
                        briefingEn={intelligence.intelligence_briefing_en}
                        briefingDe={intelligence.intelligence_briefing_de}
                        generatedAt={intelligence.generated_at}
                    />
                )}

                {/* Filter bar */}
                <FilterBar filters={filters} baseUrl={`/country/${country.code}`} />

                {/* Split layout — stacks on mobile, side-by-side on lg */}
                <div className="flex flex-col lg:flex-row gap-4 items-start">
                    {/* Mini-Map — shown first on mobile, sidebar on desktop */}
                    <div className="w-full lg:w-80 lg:flex-shrink-0 lg:sticky lg:top-4 lg:order-2">
                        <div className="bg-surface-0 border border-border-mid rounded overflow-hidden">
                            <div className="px-3 py-2 border-b border-border-subtle font-mono text-xs text-text-muted tracking-widest uppercase">
                                {country.name} · Event Locations
                            </div>
                            <div className="relative">
                                <ComposableMap
                                    projection="geoNaturalEarth1"
                                    projectionConfig={{ scale: 160 }}
                                    style={{ width: '100%', height: 'auto' }}
                                >
                                    <Geographies geography={GEO_URL}>
                                        {({ geographies }) =>
                                            geographies.map(geo => (
                                                <Geography
                                                    key={geo.rsmKey}
                                                    geography={geo}
                                                    style={{
                                                        default: {
                                                            fill: '#1F261C',
                                                            stroke: '#243320',
                                                            strokeWidth: 0.5,
                                                            outline: 'none',
                                                        },
                                                        hover:   { fill: '#1F261C', stroke: '#243320', strokeWidth: 0.5, outline: 'none' },
                                                        pressed: { fill: '#1F261C', outline: 'none' },
                                                    }}
                                                />
                                            ))
                                        }
                                    </Geographies>

                                    {coordinates.map((coord, i) => (
                                        <Marker key={i} coordinates={coord.coordinates || [0, 0]}>
                                            <DiamondMarker
                                                x={0}
                                                y={0}
                                                severity={coord.severity}
                                                status={coord.status}
                                                size={8}
                                            />
                                        </Marker>
                                    ))}
                                </ComposableMap>
                            </div>
                            <div className="px-3 py-2 border-t border-border-subtle font-mono text-xs text-text-dim">
                                {coordinates.length} event{coordinates.length !== 1 ? 's' : ''} plotted
                            </div>
                        </div>

                        <div className="mt-2 text-center font-mono text-xs text-text-dim">
                            Auto-refreshes every 60s
                        </div>
                    </div>

                    {/* Event Feed */}
                    <div className="flex-1 min-w-0 space-y-3 lg:order-1">
                        {eventList.length === 0 ? (
                            <div className="bg-surface-1 border border-border-subtle rounded p-8 text-center font-mono text-text-muted">
                                {t('map.noEvents', 'No events in the last 24 hours')}
                            </div>
                        ) : (
                            eventList.map(event => (
                                <div key={event.id} onClick={() => handleEventClick(event)}>
                                    <EventCard event={event} />
                                </div>
                            ))
                        )}
                        <Pagination links={paginationLinks} meta={paginationMeta} />
                    </div>
                </div>

                <div className="mt-6">
                    <RelationshipGraph type="country" id={country.code} depth={1} height={440} />
                </div>
            </div>

            {/* Slide-in detail panel */}
            {selectedEvent && (
                <>
                    <div
                        className="fixed inset-0 bg-black/50 z-40"
                        onClick={() => setSelectedEvent(null)}
                    />
                    <EventDetailPanel
                        event={selectedEvent}
                        onClose={() => setSelectedEvent(null)}
                    />
                </>
            )}
        </AppLayout>
    );
}
