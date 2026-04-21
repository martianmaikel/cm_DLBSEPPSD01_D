import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';
import EventDetailPanel from '../../Components/EventDetailPanel';

const CATEGORY_COLORS = {
    war: '#E74C3C',
    terrorism: '#C0392B',
    cyber: '#3498DB',
    protest: '#F39C12',
    disaster: '#E67E22',
    diplomacy: '#2ECC71',
    economic: '#9B59B6',
    airstrike: '#E74C3C',
    artillery: '#E67E22',
    humanitarian: '#2ECC71',
    infrastructure: '#F39C12',
    troop_movement: '#C0392B',
    ground_offensive: '#C0392B',
};

const CHART_HEIGHT = 480;
const MARGIN = { top: 24, right: 24, bottom: 52, left: 56 };

function categoryColor(cat) {
    return CATEGORY_COLORS[cat] || '#8AAD83';
}

function useContainerWidth(ref, fallback = 1000) {
    const [width, setWidth] = useState(fallback);
    useEffect(() => {
        if (!ref.current) return;
        const ro = new ResizeObserver((entries) => {
            const w = entries[0]?.contentRect?.width;
            if (w && w > 0) setWidth(w);
        });
        ro.observe(ref.current);
        return () => ro.disconnect();
    }, [ref]);
    return width;
}

function formatDateShort(d) {
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function formatMonth(d) {
    return d.toLocaleDateString(undefined, { month: 'short', year: '2-digit' });
}

function pickTimeTicks(minMs, maxMs, targetCount = 6) {
    const span = maxMs - minMs;
    if (span <= 0) return [minMs];
    const step = span / targetCount;
    const ticks = [];
    for (let i = 0; i <= targetCount; i++) {
        ticks.push(minMs + step * i);
    }
    return ticks;
}

export default function Timeline({ conflict, events }) {
    const { t } = useTranslation();
    const containerRef = useRef(null);
    const containerWidth = useContainerWidth(containerRef, 1000);
    const [selectedEvent, setSelectedEvent] = useState(null);
    const [activeCategories, setActiveCategories] = useState(null);

    const allCategories = useMemo(() => {
        const set = new Set();
        for (const e of events) if (e.category) set.add(e.category);
        return Array.from(set).sort();
    }, [events]);

    const filteredEvents = useMemo(() => {
        if (!activeCategories) return events;
        return events.filter((e) => activeCategories.has(e.category));
    }, [events, activeCategories]);

    const timeExtent = useMemo(() => {
        if (!events.length) return null;
        const times = events
            .map((e) => (e.occurred_at ? new Date(e.occurred_at).getTime() : null))
            .filter((t) => t !== null);
        if (!times.length) return null;
        return { min: Math.min(...times), max: Math.max(...times) };
    }, [events]);

    const plotWidth = Math.max(containerWidth - MARGIN.left - MARGIN.right, 100);
    const plotHeight = CHART_HEIGHT - MARGIN.top - MARGIN.bottom;

    const xScale = (ms) => {
        if (!timeExtent) return 0;
        const span = timeExtent.max - timeExtent.min || 1;
        return ((ms - timeExtent.min) / span) * plotWidth;
    };

    const yScale = (severity) => {
        const clamped = Math.max(1, Math.min(10, severity || 1));
        return ((10 - clamped) / 9) * plotHeight;
    };

    const confidenceRadius = (confidence) => {
        const c = Math.max(1, Math.min(10, confidence || 5));
        return 3 + (c / 10) * 6;
    };

    const timeTicks = useMemo(() => {
        if (!timeExtent) return [];
        return pickTimeTicks(timeExtent.min, timeExtent.max);
    }, [timeExtent]);

    const severityTicks = [1, 3, 5, 7, 10];

    const stats = useMemo(() => {
        if (!events.length) return null;
        const sevs = events.map((e) => e.severity || 0);
        const avg = sevs.reduce((a, b) => a + b, 0) / sevs.length;
        const max = Math.max(...sevs);
        const minDate = timeExtent ? new Date(timeExtent.min) : null;
        const maxDate = timeExtent ? new Date(timeExtent.max) : null;
        const countries = new Set(events.map((e) => e.country).filter(Boolean));
        return {
            total: events.length,
            avgSev: avg.toFixed(1),
            maxSev: max,
            rangeDays: minDate && maxDate ? Math.max(1, Math.round((maxDate - minDate) / 86400000)) : 0,
            minDate,
            maxDate,
            countries: countries.size,
        };
    }, [events, timeExtent]);

    const toggleCategory = (cat) => {
        setActiveCategories((prev) => {
            const base = prev || new Set(allCategories);
            const next = new Set(base);
            if (next.has(cat)) next.delete(cat);
            else next.add(cat);
            if (next.size === allCategories.length) return null;
            if (next.size === 0) return new Set(allCategories);
            return next;
        });
    };

    const resetCategories = () => setActiveCategories(null);

    const breadcrumbs = [
        { label: 'Conflicts', href: '/conflicts' },
        { label: conflict.name, href: `/conflict/${conflict.slug}` },
        { label: 'Timeline' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-4">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
                    <div>
                        <h1 className="font-display text-3xl md:text-4xl tracking-wider text-green-bright">
                            {conflict.name.toUpperCase()}
                        </h1>
                        <p className="font-mono text-xs text-text-muted tracking-widest uppercase mt-1">
                            {t('timeline.subtitle', 'Event Timeline')}
                            {stats && (
                                <>
                                    <> · {stats.total.toLocaleString()} events</>
                                    <> · {stats.rangeDays} {t('timeline.days', 'days')}</>
                                </>
                            )}
                        </p>
                    </div>
                    <Link
                        href={`/conflict/${conflict.slug}`}
                        className="self-start sm:self-end font-mono text-xs text-text-muted hover:text-green-bright transition-colors border border-border-mid hover:border-border-active px-3 py-1.5 rounded"
                    >
                        ← {t('timeline.backToOverview', 'Back to overview')}
                    </Link>
                </div>

                {/* Stats row */}
                {stats && (
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-2 font-mono text-xs">
                        <div className="bg-surface-1 border border-border-mid rounded px-3 py-2">
                            <div className="text-[9px] text-text-muted uppercase tracking-widest">{t('timeline.events', 'Events')}</div>
                            <div className="font-display text-xl text-green-bright tabular-nums">{stats.total.toLocaleString()}</div>
                        </div>
                        <div className="bg-surface-1 border border-border-mid rounded px-3 py-2">
                            <div className="text-[9px] text-text-muted uppercase tracking-widest">{t('timeline.avgSeverity', 'Avg Sev')}</div>
                            <div className="font-display text-xl text-amber tabular-nums">{stats.avgSev}</div>
                        </div>
                        <div className="bg-surface-1 border border-border-mid rounded px-3 py-2">
                            <div className="text-[9px] text-text-muted uppercase tracking-widest">{t('timeline.maxSeverity', 'Max Sev')}</div>
                            <div className="font-display text-xl tabular-nums" style={{ color: stats.maxSev >= 7 ? '#E74C3C' : stats.maxSev >= 4 ? '#F59E0B' : '#52A844' }}>
                                {stats.maxSev}
                            </div>
                        </div>
                        <div className="bg-surface-1 border border-border-mid rounded px-3 py-2">
                            <div className="text-[9px] text-text-muted uppercase tracking-widest">{t('timeline.countries', 'Countries')}</div>
                            <div className="font-display text-xl text-green-bright tabular-nums">{stats.countries}</div>
                        </div>
                        <div className="bg-surface-1 border border-border-mid rounded px-3 py-2 col-span-2 md:col-span-1">
                            <div className="text-[9px] text-text-muted uppercase tracking-widest">{t('timeline.timespan', 'Timespan')}</div>
                            <div className="font-mono text-xs text-text-primary">
                                {stats.minDate && `${formatDateShort(stats.minDate)} – ${formatDateShort(stats.maxDate)}`}
                            </div>
                        </div>
                    </div>
                )}

                {/* Category filter chips */}
                {allCategories.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-[10px] text-text-muted uppercase tracking-widest">
                            {t('timeline.filter', 'Filter')}
                        </span>
                        <button
                            onClick={resetCategories}
                            className={`font-mono text-xs px-3 py-1 border rounded transition-colors ${
                                activeCategories === null
                                    ? 'border-green-base text-green-bright bg-surface-2'
                                    : 'border-border-mid text-text-secondary hover:border-border-active'
                            }`}
                        >
                            {t('timeline.all', 'All')}
                        </button>
                        {allCategories.map((cat) => {
                            const active = activeCategories === null || activeCategories.has(cat);
                            const color = categoryColor(cat);
                            return (
                                <button
                                    key={cat}
                                    onClick={() => toggleCategory(cat)}
                                    className="font-mono text-xs px-3 py-1 border rounded transition-colors"
                                    style={{
                                        borderColor: active ? color : '#243320',
                                        color: active ? color : '#5A6B53',
                                        backgroundColor: active ? 'rgba(82,168,68,0.08)' : 'transparent',
                                    }}
                                >
                                    {cat.replace(/_/g, ' ')}
                                </button>
                            );
                        })}
                    </div>
                )}

                {/* Timeline SVG */}
                <div
                    ref={containerRef}
                    className="relative bg-surface-0 border border-border-mid rounded p-2 overflow-hidden"
                    style={{ minHeight: CHART_HEIGHT }}
                >
                    {filteredEvents.length === 0 ? (
                        <div className="flex items-center justify-center h-96 font-mono text-xs text-text-muted">
                            {t('timeline.noEvents', 'No events match the current filter.')}
                        </div>
                    ) : (
                        <svg width={containerWidth} height={CHART_HEIGHT} className="block">
                            <defs>
                                <linearGradient id="severity-bg" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="rgba(231,76,60,0.08)" />
                                    <stop offset="33%" stopColor="rgba(231,76,60,0.04)" />
                                    <stop offset="66%" stopColor="rgba(245,158,11,0.04)" />
                                    <stop offset="100%" stopColor="rgba(82,168,68,0.04)" />
                                </linearGradient>
                            </defs>

                            {/* Plot background */}
                            <rect
                                x={MARGIN.left}
                                y={MARGIN.top}
                                width={plotWidth}
                                height={plotHeight}
                                fill="url(#severity-bg)"
                            />

                            {/* Severity gridlines + labels */}
                            {severityTicks.map((sev) => {
                                const y = MARGIN.top + yScale(sev);
                                return (
                                    <g key={`sev-${sev}`}>
                                        <line
                                            x1={MARGIN.left}
                                            y1={y}
                                            x2={MARGIN.left + plotWidth}
                                            y2={y}
                                            stroke="#243320"
                                            strokeWidth="1"
                                            strokeDasharray="2 4"
                                        />
                                        <text
                                            x={MARGIN.left - 8}
                                            y={y + 4}
                                            textAnchor="end"
                                            fill="#5A6B53"
                                            fontFamily="'Roboto Mono', monospace"
                                            fontSize="10"
                                        >
                                            {sev}
                                        </text>
                                    </g>
                                );
                            })}

                            {/* Y axis label */}
                            <text
                                x={-MARGIN.top - plotHeight / 2}
                                y={16}
                                transform="rotate(-90)"
                                textAnchor="middle"
                                fill="#8AAD83"
                                fontFamily="'Roboto Mono', monospace"
                                fontSize="10"
                                letterSpacing="2"
                            >
                                SEVERITY
                            </text>

                            {/* Time ticks */}
                            {timeTicks.map((ms, i) => {
                                const x = MARGIN.left + xScale(ms);
                                const date = new Date(ms);
                                const span = (timeExtent?.max ?? 0) - (timeExtent?.min ?? 0);
                                const label = span > 365 * 86400000 ? formatMonth(date) : formatDateShort(date);
                                return (
                                    <g key={`t-${i}`}>
                                        <line
                                            x1={x}
                                            y1={MARGIN.top}
                                            x2={x}
                                            y2={MARGIN.top + plotHeight}
                                            stroke="#1A2618"
                                            strokeWidth="1"
                                        />
                                        <text
                                            x={x}
                                            y={MARGIN.top + plotHeight + 16}
                                            textAnchor="middle"
                                            fill="#8AAD83"
                                            fontFamily="'Roboto Mono', monospace"
                                            fontSize="10"
                                        >
                                            {label}
                                        </text>
                                    </g>
                                );
                            })}

                            {/* Events (dots) */}
                            {filteredEvents.map((e) => {
                                if (!e.occurred_at) return null;
                                const ms = new Date(e.occurred_at).getTime();
                                const cx = MARGIN.left + xScale(ms);
                                const cy = MARGIN.top + yScale(e.severity);
                                const r = confidenceRadius(e.confidence);
                                const color = categoryColor(e.category);
                                const isSelected = selectedEvent?.id === e.id;
                                return (
                                    <g key={e.id}>
                                        {isSelected && (
                                            <circle
                                                cx={cx}
                                                cy={cy}
                                                r={r + 6}
                                                fill="none"
                                                stroke={color}
                                                strokeWidth="1.5"
                                                opacity="0.7"
                                            >
                                                <animate attributeName="r" values={`${r + 4};${r + 10};${r + 4}`} dur="1.5s" repeatCount="indefinite" />
                                                <animate attributeName="opacity" values="0.8;0.2;0.8" dur="1.5s" repeatCount="indefinite" />
                                            </circle>
                                        )}
                                        <circle
                                            cx={cx}
                                            cy={cy}
                                            r={r}
                                            fill={color}
                                            fillOpacity="0.65"
                                            stroke={color}
                                            strokeWidth="1.5"
                                            style={{ cursor: 'pointer' }}
                                            onClick={() => setSelectedEvent(e)}
                                        >
                                            <title>
                                                {`${e.title} — sev ${e.severity}/10 · ${e.category || 'unknown'} · ${new Date(e.occurred_at).toLocaleDateString()}`}
                                            </title>
                                        </circle>
                                    </g>
                                );
                            })}

                            {/* Axis baseline */}
                            <line
                                x1={MARGIN.left}
                                y1={MARGIN.top + plotHeight}
                                x2={MARGIN.left + plotWidth}
                                y2={MARGIN.top + plotHeight}
                                stroke="#2D5426"
                                strokeWidth="1.5"
                            />
                            <line
                                x1={MARGIN.left}
                                y1={MARGIN.top}
                                x2={MARGIN.left}
                                y2={MARGIN.top + plotHeight}
                                stroke="#2D5426"
                                strokeWidth="1.5"
                            />

                            {/* Watermark */}
                            <text
                                x={MARGIN.left + plotWidth - 8}
                                y={MARGIN.top + 16}
                                textAnchor="end"
                                fill="rgba(82,168,68,0.35)"
                                fontFamily="'Rajdhani', sans-serif"
                                fontSize="14"
                                letterSpacing="2"
                                fontWeight="600"
                            >
                                CLASHMONITOR.COM
                            </text>
                        </svg>
                    )}
                </div>

                {/* Legend */}
                <div className="flex items-center justify-between font-mono text-[10px] text-text-dim">
                    <span>{t('timeline.legendSize', '● dot size = confidence · y = severity · x = time')}</span>
                    <span>{filteredEvents.length.toLocaleString()} / {events.length.toLocaleString()} {t('timeline.eventsShown', 'shown')}</span>
                </div>
            </div>

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
