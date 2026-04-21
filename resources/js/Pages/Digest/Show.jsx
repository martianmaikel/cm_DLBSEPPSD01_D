import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ComposableMap, Geographies, Geography, Marker } from 'react-simple-maps';
import AppLayout from '../../Layouts/AppLayout';

const GEO_URL = '/vendor/world-atlas/countries-110m.json';

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

function sevColor(sev) {
    if (sev >= 7) return '#E74C3C';
    if (sev >= 4) return '#F59E0B';
    return '#52A844';
}

function categoryColor(cat) {
    return CATEGORY_COLORS[cat] || '#8AAD83';
}

function formatDateRange(startIso, endIso) {
    const start = new Date(startIso);
    const end = new Date(endIso);
    const sameMonth = start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear();
    const startStr = start.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    const endStr = end.toLocaleDateString(
        undefined,
        sameMonth ? { day: 'numeric', year: 'numeric' } : { month: 'short', day: 'numeric', year: 'numeric' },
    );
    return `${startStr} – ${endStr}`;
}

function StatCard({ label, value, color = 'text-green-bright', sublabel }) {
    return (
        <div className="bg-surface-1 border border-border-mid rounded px-3 py-2">
            <div className="font-mono text-[9px] text-text-muted uppercase tracking-widest">{label}</div>
            <div className={`font-display text-2xl tabular-nums ${color}`}>{value}</div>
            {sublabel && <div className="font-mono text-[9px] text-text-dim mt-0.5">{sublabel}</div>}
        </div>
    );
}

function SectionHeader({ eyebrow, title }) {
    return (
        <div className="border-b border-border-subtle pb-1 mb-3">
            <div className="font-mono text-[10px] text-amber uppercase tracking-widest">{eyebrow}</div>
            <h2 className="font-display text-xl md:text-2xl text-green-bright tracking-wider">{title}</h2>
        </div>
    );
}

function DeltaBadge({ delta }) {
    if (delta === 0) {
        return <span className="font-mono text-[10px] text-text-dim">±0</span>;
    }
    const positive = delta > 0;
    return (
        <span
            className="font-mono text-[10px] tabular-nums"
            style={{ color: positive ? '#E74C3C' : '#52A844' }}
        >
            {positive ? '▲' : '▼'}{Math.abs(delta)}
        </span>
    );
}

export default function Show({
    week,
    statistics,
    topEvents,
    threadScoreboard,
    categoryBreakdown,
    countryRanking,
    newThreads,
    hotzoneEvents,
}) {
    const { t, i18n } = useTranslation();

    const dateRange = useMemo(() => formatDateRange(week.start, week.end), [week.start, week.end]);
    const maxCategoryCount = useMemo(
        () => Math.max(1, ...categoryBreakdown.map((c) => c.count)),
        [categoryBreakdown],
    );
    const maxCountryCount = useMemo(
        () => Math.max(1, ...countryRanking.map((c) => c.count)),
        [countryRanking],
    );

    const breadcrumbs = [
        { label: 'Digest', href: '/digest' },
        { label: week.label },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                {/* ── HERO ── */}
                <header className="border border-green-base/40 rounded-lg p-5 md:p-8 bg-gradient-to-br from-surface-1 to-black">
                    <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                        <div>
                            <div className="font-mono text-xs text-amber uppercase tracking-widest mb-2">
                                {t('digest.eyebrow', 'Weekly Conflict Digest')}
                            </div>
                            <h1 className="font-display text-4xl md:text-6xl tracking-wider text-green-bright">
                                {week.label}
                            </h1>
                            <p className="font-mono text-sm text-text-secondary tracking-wider mt-2">
                                {dateRange}
                            </p>
                        </div>
                        <div className="text-right">
                            <div className="font-display text-5xl md:text-7xl tabular-nums text-green-bright leading-none">
                                {statistics.total_events.toLocaleString()}
                            </div>
                            <div className="font-mono text-xs text-text-muted uppercase tracking-widest mt-1">
                                {t('digest.eventsTracked', 'Events Tracked')}
                            </div>
                        </div>
                    </div>

                    {/* Week nav */}
                    <div className="flex items-center justify-between mt-5 pt-4 border-t border-border-subtle font-mono text-xs">
                        <Link
                            href={`/digest/${week.prev}`}
                            className="text-text-secondary hover:text-green-bright transition-colors"
                        >
                            ← {week.prev}
                        </Link>
                        <Link
                            href="/digest"
                            className="text-text-dim hover:text-green-bright transition-colors uppercase tracking-widest"
                        >
                            {t('digest.latest', 'Latest')}
                        </Link>
                        {week.next ? (
                            <Link
                                href={`/digest/${week.next}`}
                                className="text-text-secondary hover:text-green-bright transition-colors"
                            >
                                {week.next} →
                            </Link>
                        ) : (
                            <span className="text-text-dim">—</span>
                        )}
                    </div>
                </header>

                {/* ── STATS GRID ── */}
                <section>
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-2">
                        <StatCard
                            label={t('digest.statHigh', 'High Sev (7+)')}
                            value={statistics.severity_breakdown.high.toLocaleString()}
                            color="text-red-400"
                        />
                        <StatCard
                            label={t('digest.statConfirmed', 'Confirmed')}
                            value={statistics.confirmed_count.toLocaleString()}
                        />
                        <StatCard
                            label={t('digest.statCorroborated', 'Corroborated')}
                            value={statistics.corroborated_count.toLocaleString()}
                        />
                        <StatCard
                            label={t('digest.statCountries', 'Countries')}
                            value={statistics.countries_affected.toLocaleString()}
                        />
                        <StatCard
                            label={t('digest.statNewThreads', 'New Threads')}
                            value={statistics.new_threads_count.toLocaleString()}
                            color="text-amber"
                        />
                    </div>
                </section>

                {/* ── HOTZONE MINI-MAP ── */}
                {hotzoneEvents.length > 0 && (
                    <section>
                        <SectionHeader
                            eyebrow={t('digest.hotzoneEyebrow', 'Geographic Distribution')}
                            title={t('digest.hotzoneTitle', 'Where It Happened')}
                        />
                        <div className="bg-surface-0 border border-border-mid rounded overflow-hidden">
                            <ComposableMap
                                projection="geoEqualEarth"
                                projectionConfig={{ scale: 170 }}
                                style={{ width: '100%', height: 'auto' }}
                            >
                                <Geographies geography={GEO_URL}>
                                    {({ geographies }) =>
                                        geographies.map((geo) => (
                                            <Geography
                                                key={geo.rsmKey}
                                                geography={geo}
                                                style={{
                                                    default: { fill: '#1A2618', stroke: '#243320', strokeWidth: 0.4, outline: 'none' },
                                                    hover: { fill: '#1A2618', stroke: '#243320', strokeWidth: 0.4, outline: 'none' },
                                                    pressed: { fill: '#1A2618', outline: 'none' },
                                                }}
                                            />
                                        ))
                                    }
                                </Geographies>
                                {hotzoneEvents.map((e, i) => (
                                    <Marker key={i} coordinates={e.coordinates}>
                                        <circle
                                            r={Math.max(1.5, (e.severity || 1) * 0.45)}
                                            fill={sevColor(e.severity)}
                                            fillOpacity={0.75}
                                            stroke={sevColor(e.severity)}
                                            strokeWidth={0.4}
                                        />
                                    </Marker>
                                ))}
                            </ComposableMap>
                            <div className="px-3 py-2 border-t border-border-subtle font-mono text-[10px] text-text-dim flex justify-between">
                                <span>{hotzoneEvents.length} {t('digest.geoEvents', 'geolocated events')}</span>
                                <span>{t('digest.sevLegend', 'Size = severity')}</span>
                            </div>
                        </div>
                    </section>
                )}

                {/* ── TOP EVENTS ── */}
                {topEvents.length > 0 && (
                    <section>
                        <SectionHeader
                            eyebrow={t('digest.topEventsEyebrow', 'Most Severe')}
                            title={t('digest.topEventsTitle', 'Top Events')}
                        />
                        <div className="space-y-2">
                            {topEvents.map((e, i) => {
                                const title = i18n.language === 'de' ? e.title_de || e.title : e.title;
                                const summary = i18n.language === 'de' ? e.summary_de || e.summary : e.summary;
                                return (
                                    <Link
                                        key={e.id}
                                        href={`/event/${e.id}`}
                                        className="block bg-surface-1 border border-border-mid rounded px-4 py-3 hover:border-green-base transition-colors"
                                    >
                                        <div className="flex items-start gap-4">
                                            <div className="font-display text-2xl text-text-dim tabular-nums w-8 flex-shrink-0">
                                                {String(i + 1).padStart(2, '0')}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 font-mono text-[10px] uppercase tracking-wider mb-1">
                                                    <span style={{ color: categoryColor(e.category) }}>{e.category || '—'}</span>
                                                    <span className="text-text-dim">·</span>
                                                    <span className="text-text-muted">{e.country}</span>
                                                    <span className="text-text-dim">·</span>
                                                    <span className="text-text-muted uppercase">{e.status}</span>
                                                    {e.thread && (
                                                        <>
                                                            <span className="text-text-dim">·</span>
                                                            <span className="text-green-base truncate">{e.thread.name}</span>
                                                        </>
                                                    )}
                                                </div>
                                                <div className="font-sans text-sm text-text-primary font-semibold line-clamp-2">
                                                    {title}
                                                </div>
                                                {summary && (
                                                    <div className="font-sans text-xs text-text-muted mt-1 line-clamp-2">
                                                        {summary}
                                                    </div>
                                                )}
                                            </div>
                                            <div
                                                className="font-display text-2xl tabular-nums flex-shrink-0 w-10 text-right"
                                                style={{ color: sevColor(e.severity) }}
                                            >
                                                {e.severity}
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </section>
                )}

                {/* ── THREAD SCOREBOARD + CATEGORIES ── */}
                <section className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {threadScoreboard.length > 0 && (
                        <div>
                            <SectionHeader
                                eyebrow={t('digest.scoreboardEyebrow', 'Most Active')}
                                title={t('digest.scoreboardTitle', 'Conflict Scoreboard')}
                            />
                            <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                                <table className="w-full font-mono text-xs">
                                    <thead className="bg-surface-2 text-text-muted">
                                        <tr>
                                            <th className="text-left px-3 py-2 w-8">#</th>
                                            <th className="text-left px-3 py-2">{t('digest.thread', 'Thread')}</th>
                                            <th className="text-right px-3 py-2">{t('digest.events', 'Events')}</th>
                                            <th className="text-right px-3 py-2">Δ</th>
                                            <th className="text-right px-3 py-2">{t('digest.maxSev', 'Max')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {threadScoreboard.map((t, i) => (
                                            <tr key={t.id ?? i} className="border-t border-border-subtle">
                                                <td className="px-3 py-2 text-text-dim tabular-nums">{i + 1}</td>
                                                <td className="px-3 py-2 text-text-primary truncate">
                                                    {t.slug ? (
                                                        <Link href={`/conflict/${t.slug}`} className="hover:text-green-bright transition-colors">
                                                            {t.name}
                                                        </Link>
                                                    ) : t.name}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums text-green-bright">{t.event_count}</td>
                                                <td className="px-3 py-2 text-right"><DeltaBadge delta={t.delta} /></td>
                                                <td className="px-3 py-2 text-right tabular-nums" style={{ color: sevColor(t.max_severity) }}>
                                                    {t.max_severity}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {categoryBreakdown.length > 0 && (
                        <div>
                            <SectionHeader
                                eyebrow={t('digest.categoryEyebrow', 'Distribution')}
                                title={t('digest.categoryTitle', 'Category Breakdown')}
                            />
                            <div className="bg-surface-1 border border-border-mid rounded p-4 space-y-2">
                                {categoryBreakdown.map((c) => {
                                    const pct = (c.count / maxCategoryCount) * 100;
                                    const color = categoryColor(c.category);
                                    return (
                                        <div key={c.category} className="flex items-center gap-3">
                                            <span
                                                className="font-mono text-[10px] uppercase tracking-wider w-24 text-right truncate"
                                                style={{ color }}
                                            >
                                                {c.category.replace(/_/g, ' ')}
                                            </span>
                                            <div className="flex-1 h-3 bg-surface-3 rounded-sm overflow-hidden">
                                                <div
                                                    className="h-full transition-all"
                                                    style={{ width: `${pct}%`, backgroundColor: color }}
                                                />
                                            </div>
                                            <span className="font-mono text-xs tabular-nums text-text-primary w-10 text-right">
                                                {c.count}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </section>

                {/* ── COUNTRY RANKING ── */}
                {countryRanking.length > 0 && (
                    <section>
                        <SectionHeader
                            eyebrow={t('digest.countriesEyebrow', 'Most Affected')}
                            title={t('digest.countriesTitle', 'Country Ranking')}
                        />
                        <div className="bg-surface-1 border border-border-mid rounded p-4 space-y-2">
                            {countryRanking.map((c, i) => {
                                const pct = (c.count / maxCountryCount) * 100;
                                return (
                                    <Link
                                        key={c.code}
                                        href={`/country/${c.code}`}
                                        className="flex items-center gap-3 hover:bg-surface-2 rounded px-2 py-1 -mx-2 -my-1 transition-colors"
                                    >
                                        <span className="font-display text-lg text-text-dim tabular-nums w-6 text-right">
                                            {i + 1}
                                        </span>
                                        <span className="font-mono text-xs uppercase tracking-wider text-text-secondary w-8">
                                            {c.code}
                                        </span>
                                        <span className="font-sans text-sm text-text-primary flex-1 truncate">{c.name}</span>
                                        <div className="hidden sm:block flex-1 max-w-xs h-2 bg-surface-3 rounded-sm overflow-hidden">
                                            <div
                                                className="h-full"
                                                style={{ width: `${pct}%`, backgroundColor: sevColor(c.max_severity) }}
                                            />
                                        </div>
                                        <span className="font-mono text-xs tabular-nums text-green-bright w-10 text-right">
                                            {c.count}
                                        </span>
                                        <span
                                            className="font-mono text-xs tabular-nums w-6 text-right"
                                            style={{ color: sevColor(c.max_severity) }}
                                        >
                                            {c.max_severity}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    </section>
                )}

                {/* ── NEW THREADS ── */}
                {newThreads.length > 0 && (
                    <section>
                        <SectionHeader
                            eyebrow={t('digest.newThreadsEyebrow', 'Emerging')}
                            title={t('digest.newThreadsTitle', 'New Conflict Threads')}
                        />
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                            {newThreads.map((t) => (
                                <Link
                                    key={t.id}
                                    href={`/conflict/${t.slug}`}
                                    className="block bg-surface-1 border border-border-mid rounded p-3 hover:border-amber transition-colors"
                                    style={{ borderLeft: `3px solid ${sevColor(t.max_severity)}` }}
                                >
                                    <div className="font-sans text-sm font-semibold text-text-primary truncate">{t.name}</div>
                                    {t.summary && (
                                        <div className="font-sans text-xs text-text-muted mt-1 line-clamp-2">{t.summary}</div>
                                    )}
                                    <div className="font-mono text-[10px] text-text-dim mt-2 flex gap-3">
                                        <span>{t.event_count_total} events</span>
                                        <span style={{ color: sevColor(t.max_severity) }}>sev {t.max_severity}</span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {/* ── FOOTER WATERMARK ── */}
                <footer className="border-t border-border-subtle pt-4 text-center">
                    <div className="font-display text-sm text-green-bright/80 tracking-wider">
                        CLASHMONITOR.COM · {week.label}
                    </div>
                    <div className="font-mono text-[10px] text-text-dim uppercase tracking-widest mt-1">
                        Real-time OSINT · Conflict monitoring
                    </div>
                </footer>
            </div>
        </AppLayout>
    );
}
