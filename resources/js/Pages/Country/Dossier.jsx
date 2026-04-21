import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

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

const ENTITY_TYPE_COLORS = {
    person: '#F39C12',
    unit: '#E74C3C',
    organization: '#3498DB',
    location: '#2ECC71',
};

const ASPECT_RATIOS = [
    { value: '4/5', label: '4:5', hint: 'Feed / Twitter' },
    { value: '9/16', label: '9:16', hint: 'Story / Reels' },
    { value: '1/1', label: '1:1', hint: 'Square' },
];

function sevColor(sev) {
    if (sev >= 7) return '#E74C3C';
    if (sev >= 4) return '#F59E0B';
    if (sev > 0) return '#52A844';
    return '#2D5426';
}

function categoryColor(cat) {
    return CATEGORY_COLORS[cat] || '#8AAD83';
}

function formatDateShort(iso) {
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function SeverityTrendSparkline({ trend }) {
    if (!trend || trend.length === 0) return null;

    const w = 520;
    const h = 60;
    const padX = 4;
    const padY = 4;

    const counts = trend.map((d) => d.count);
    const maxCount = Math.max(1, ...counts);

    const barW = (w - padX * 2) / trend.length;

    return (
        <svg width="100%" viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" className="block">
            {/* Baseline */}
            <line
                x1={padX}
                y1={h - padY}
                x2={w - padX}
                y2={h - padY}
                stroke="#243320"
                strokeWidth="1"
            />
            {trend.map((d, i) => {
                const barH = ((d.count / maxCount) * (h - padY * 2)) || 0;
                const x = padX + i * barW;
                const y = h - padY - barH;
                const color = d.count === 0 ? '#1A2618' : sevColor(d.max_severity);
                return (
                    <rect
                        key={d.date}
                        x={x + 0.5}
                        y={y}
                        width={Math.max(1, barW - 1)}
                        height={Math.max(1, barH)}
                        fill={color}
                        fillOpacity={d.count === 0 ? 0.35 : 0.85}
                    >
                        <title>{`${d.date}: ${d.count} events, max sev ${d.max_severity}`}</title>
                    </rect>
                );
            })}
        </svg>
    );
}

function ThreatLevelBar({ level }) {
    const pct = ((level || 0) / 10) * 100;
    const color = sevColor(level);
    return (
        <div>
            <div className="flex items-end justify-between mb-1">
                <span className="font-mono text-[10px] text-text-muted uppercase tracking-widest">
                    Threat Level
                </span>
                <span className="font-display text-4xl tabular-nums leading-none" style={{ color }}>
                    {level ?? '—'}
                    <span className="font-mono text-sm text-text-dim ml-1">/10</span>
                </span>
            </div>
            <div className="h-3 bg-surface-3 rounded-sm overflow-hidden">
                <div
                    className="h-full transition-all duration-500"
                    style={{ width: `${pct}%`, backgroundColor: color, boxShadow: `0 0 12px ${color}` }}
                />
            </div>
        </div>
    );
}

export default function Dossier({
    country,
    intelligence,
    stats,
    severityTrend,
    topCategories,
    topEntities,
    activeThreads,
}) {
    const { t, i18n } = useTranslation();
    const [aspectRatio, setAspectRatio] = useState('4/5');

    const maxCatCount = useMemo(
        () => Math.max(1, ...topCategories.map((c) => c.count)),
        [topCategories],
    );

    const briefing = useMemo(() => {
        if (!intelligence) return null;
        const text = i18n.language === 'de'
            ? intelligence.briefing_de || intelligence.briefing_en
            : intelligence.briefing_en;
        if (!text) return null;
        // Take first ~280 chars to keep the poster tight
        if (text.length <= 280) return text;
        return text.slice(0, 275).replace(/\s+\S*$/, '') + '…';
    }, [intelligence, i18n.language]);

    const totalEventsAllTime = intelligence?.event_count_total ?? 0;
    const threatLevel = intelligence?.threat_level ?? 0;

    const breadcrumbs = [
        { label: country.continent_name || 'World', href: country.continent_slug ? `/region/${country.continent_slug}` : '/' },
        { label: country.name, href: `/country/${country.code}` },
        { label: 'Dossier' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-4">
                {/* Header with aspect-ratio controls */}
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
                    <div>
                        <h1 className="font-display text-3xl md:text-4xl tracking-wider text-green-bright">
                            {country.name.toUpperCase()} · DOSSIER
                        </h1>
                        <p className="font-mono text-xs text-text-muted tracking-widest uppercase mt-1">
                            {t('dossier.subtitle', 'Intelligence Card · Screenshot-ready')}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="font-mono text-[10px] text-text-muted uppercase tracking-widest">
                            {t('dossier.format', 'Format')}
                        </span>
                        <div className="inline-flex border border-border-mid rounded overflow-hidden">
                            {ASPECT_RATIOS.map((r) => (
                                <button
                                    key={r.value}
                                    onClick={() => setAspectRatio(r.value)}
                                    className={`font-mono text-xs px-3 py-1 transition-colors ${
                                        aspectRatio === r.value
                                            ? 'bg-surface-2 text-green-bright'
                                            : 'text-text-secondary hover:text-green-bright'
                                    }`}
                                    title={r.hint}
                                >
                                    {r.label}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                {/* The dossier card itself — centered, aspect-ratio controlled */}
                <div className="flex justify-center">
                    <article
                        className="w-full max-w-[560px] bg-gradient-to-b from-[#0B100A] to-black border border-green-base/50 rounded-lg relative overflow-hidden shadow-[0_0_40px_rgba(82,168,68,0.15)]"
                        style={{ aspectRatio }}
                    >
                        {/* Subtle tactical grid background */}
                        <div
                            className="absolute inset-0 pointer-events-none opacity-[0.08]"
                            style={{
                                backgroundImage:
                                    'linear-gradient(rgba(82,168,68,1) 1px, transparent 1px), linear-gradient(90deg, rgba(82,168,68,1) 1px, transparent 1px)',
                                backgroundSize: '24px 24px',
                            }}
                        />

                        {/* Content — absolute positioned so it fills the card */}
                        <div className="relative z-10 h-full flex flex-col p-5 md:p-6">
                            {/* ── TOP: Country header ── */}
                            <header className="border-b border-green-base/30 pb-3 mb-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="font-mono text-[10px] text-amber uppercase tracking-widest">
                                            {t('dossier.classified', 'Intelligence Dossier')}
                                        </div>
                                        <h2 className="font-display text-2xl md:text-3xl tracking-wider text-green-bright leading-tight truncate">
                                            {country.name.toUpperCase()}
                                        </h2>
                                        {country.continent_name && (
                                            <div className="font-mono text-[10px] text-text-dim uppercase tracking-widest mt-0.5">
                                                {country.continent_name} · {country.code}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex-shrink-0 w-12 h-12 border border-green-base/60 rounded flex items-center justify-center">
                                        <span className="font-display text-xl text-green-bright tracking-wider">
                                            {country.code}
                                        </span>
                                    </div>
                                </div>
                            </header>

                            {/* ── THREAT LEVEL ── */}
                            <div className="mb-3">
                                <ThreatLevelBar level={threatLevel} />
                            </div>

                            {/* ── STATS GRID ── */}
                            <div className="grid grid-cols-4 gap-2 mb-3 font-mono text-xs">
                                <div className="bg-black/40 border border-border-mid rounded px-2 py-1.5">
                                    <div className="text-[8px] text-text-muted uppercase tracking-widest">24h</div>
                                    <div className="font-display text-base text-green-bright tabular-nums">
                                        {(intelligence?.event_count_24h ?? 0).toLocaleString()}
                                    </div>
                                </div>
                                <div className="bg-black/40 border border-border-mid rounded px-2 py-1.5">
                                    <div className="text-[8px] text-text-muted uppercase tracking-widest">7d</div>
                                    <div className="font-display text-base text-green-bright tabular-nums">
                                        {stats.event_count_7d.toLocaleString()}
                                    </div>
                                </div>
                                <div className="bg-black/40 border border-border-mid rounded px-2 py-1.5">
                                    <div className="text-[8px] text-text-muted uppercase tracking-widest">30d</div>
                                    <div className="font-display text-base text-green-bright tabular-nums">
                                        {stats.event_count_30d.toLocaleString()}
                                    </div>
                                </div>
                                <div className="bg-black/40 border border-border-mid rounded px-2 py-1.5">
                                    <div className="text-[8px] text-text-muted uppercase tracking-widest">Total</div>
                                    <div className="font-display text-base text-green-bright tabular-nums">
                                        {totalEventsAllTime.toLocaleString()}
                                    </div>
                                </div>
                            </div>

                            {/* ── SEVERITY TREND SPARKLINE ── */}
                            <div className="mb-3">
                                <div className="flex items-center justify-between mb-1">
                                    <span className="font-mono text-[9px] text-text-muted uppercase tracking-widest">
                                        {t('dossier.trend30d', 'Activity · Last 30 Days')}
                                    </span>
                                    <span className="font-mono text-[9px] text-text-dim">
                                        {severityTrend[0] && formatDateShort(severityTrend[0].date)} – {severityTrend[severityTrend.length - 1] && formatDateShort(severityTrend[severityTrend.length - 1].date)}
                                    </span>
                                </div>
                                <div className="border border-border-mid rounded p-1 bg-black/40">
                                    <SeverityTrendSparkline trend={severityTrend} />
                                </div>
                            </div>

                            {/* ── TOP CATEGORIES ── */}
                            {topCategories.length > 0 && (
                                <div className="mb-3">
                                    <div className="font-mono text-[9px] text-text-muted uppercase tracking-widest mb-1">
                                        {t('dossier.topCategories', 'Top Categories')}
                                    </div>
                                    <div className="space-y-1">
                                        {topCategories.slice(0, 3).map((c) => {
                                            const pct = (c.count / maxCatCount) * 100;
                                            const color = categoryColor(c.category);
                                            return (
                                                <div key={c.category} className="flex items-center gap-2">
                                                    <span
                                                        className="font-mono text-[9px] uppercase tracking-wider w-20 text-right truncate"
                                                        style={{ color }}
                                                    >
                                                        {c.category.replace(/_/g, ' ')}
                                                    </span>
                                                    <div className="flex-1 h-1.5 bg-surface-3 rounded-sm overflow-hidden">
                                                        <div
                                                            className="h-full"
                                                            style={{ width: `${pct}%`, backgroundColor: color }}
                                                        />
                                                    </div>
                                                    <span className="font-mono text-[9px] tabular-nums text-text-primary w-6 text-right">
                                                        {c.count}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* ── ACTIVE THREADS ── */}
                            {activeThreads.length > 0 && (
                                <div className="mb-3">
                                    <div className="font-mono text-[9px] text-text-muted uppercase tracking-widest mb-1">
                                        {t('dossier.activeThreads', 'Active Conflicts')}
                                    </div>
                                    <div className="space-y-1">
                                        {activeThreads.map((thr) => (
                                            <div
                                                key={thr.id}
                                                className="flex items-center gap-2 px-2 py-1 bg-black/40 border border-border-mid rounded"
                                                style={{ borderLeft: `3px solid ${sevColor(thr.max_severity)}` }}
                                            >
                                                <span className="font-sans text-xs text-text-primary font-semibold truncate flex-1 min-w-0">
                                                    {thr.name}
                                                </span>
                                                <span className="font-mono text-[9px] tabular-nums text-text-dim flex-shrink-0">
                                                    {thr.event_count}
                                                </span>
                                                <span
                                                    className="font-mono text-[9px] tabular-nums font-bold flex-shrink-0 w-5 text-right"
                                                    style={{ color: sevColor(thr.max_severity) }}
                                                >
                                                    {thr.max_severity}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* ── DOMINANT ENTITIES ── */}
                            {topEntities.length > 0 && (
                                <div className="mb-3">
                                    <div className="font-mono text-[9px] text-text-muted uppercase tracking-widest mb-1">
                                        {t('dossier.dominantEntities', 'Most Mentioned')}
                                    </div>
                                    <div className="flex flex-wrap gap-1">
                                        {topEntities.slice(0, 5).map((e, i) => (
                                            <span
                                                key={`${e.name}-${i}`}
                                                className="font-mono text-[9px] px-1.5 py-0.5 border rounded uppercase tracking-wider"
                                                style={{
                                                    borderColor: ENTITY_TYPE_COLORS[e.type] || '#2D5426',
                                                    color: ENTITY_TYPE_COLORS[e.type] || '#8AAD83',
                                                    backgroundColor: 'rgba(0,0,0,0.4)',
                                                }}
                                                title={`${e.type} · ${e.mentions} mentions`}
                                            >
                                                {e.name} <span className="text-text-dim normal-case">×{e.mentions}</span>
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* ── BRIEFING EXCERPT ── */}
                            {briefing && (
                                <div className="mb-3 flex-1 min-h-0">
                                    <div className="font-mono text-[9px] text-amber uppercase tracking-widest mb-1">
                                        {t('dossier.briefingExcerpt', 'Intelligence Summary')}
                                    </div>
                                    <p className="font-sans text-[11px] md:text-xs text-text-secondary leading-relaxed line-clamp-6">
                                        {briefing}
                                    </p>
                                </div>
                            )}

                            {/* ── FOOTER WATERMARK ── */}
                            <footer className="mt-auto pt-2 border-t border-green-base/30 flex items-center justify-between">
                                <div>
                                    <div className="font-display text-sm text-green-bright tracking-wider leading-none">
                                        CLASHMONITOR.COM
                                    </div>
                                    <div className="font-mono text-[8px] text-text-dim uppercase tracking-widest mt-0.5">
                                        Real-time OSINT
                                    </div>
                                </div>
                                {intelligence?.generated_at && (
                                    <div className="font-mono text-[8px] text-text-dim text-right">
                                        {t('dossier.updated', 'Updated')}
                                        <br />
                                        {new Date(intelligence.generated_at).toLocaleDateString()}
                                    </div>
                                )}
                            </footer>
                        </div>
                    </article>
                </div>

                {/* Footer links */}
                <div className="flex items-center justify-center gap-4 font-mono text-[10px] text-text-dim">
                    <Link href={`/country/${country.code}`} className="hover:text-green-bright transition-colors">
                        ← {t('dossier.backToCountry', 'Full country view')}
                    </Link>
                    <span>·</span>
                    <span>{t('dossier.screenshotHint', 'Use OS screenshot to share')}</span>
                </div>
            </div>
        </AppLayout>
    );
}
