import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import MapGL, { Source, Layer } from 'react-map-gl/maplibre';
import 'maplibre-gl/dist/maplibre-gl.css';
import AppLayout from '../../Layouts/AppLayout';
import { STYLE_URL, darkenStyle, highContrastStyle } from '../../Components/Globe/mapStyles';
import { useTheme } from '../../Contexts/ThemeContext';

const PERIODS = [
    { value: '7d', label: '7 Days', full: 'Last 7 Days' },
    { value: '30d', label: '30 Days', full: 'Last 30 Days' },
    { value: '90d', label: '90 Days', full: 'Last 90 Days' },
];

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
};

function formatCategoryLabel(raw) {
    if (!raw) return 'All';
    return raw.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

const INITIAL_VIEW = {
    latitude: 20,
    longitude: 20,
    zoom: typeof window !== 'undefined' && window.innerWidth >= 1024 ? 1.9 : 1.3,
};

const HEATMAP_LAYER = {
    id: 'hotzone-heat',
    type: 'heatmap',
    source: 'hotzone-points',
    maxzoom: 9,
    paint: {
        'heatmap-weight': [
            'interpolate', ['linear'], ['get', 'severity'],
            0, 0.05,
            4, 0.35,
            7, 0.75,
            10, 1,
        ],
        'heatmap-intensity': [
            'interpolate', ['linear'], ['zoom'],
            0, 1,
            4, 1.6,
            9, 3,
        ],
        'heatmap-color': [
            'interpolate', ['linear'], ['heatmap-density'],
            0, 'rgba(0, 0, 0, 0)',
            0.08, 'rgba(82, 168, 68, 0.35)',
            0.25, 'rgba(82, 168, 68, 0.7)',
            0.45, 'rgba(245, 158, 11, 0.8)',
            0.7, 'rgba(231, 76, 60, 0.9)',
            1, 'rgba(255, 80, 40, 1)',
        ],
        'heatmap-radius': [
            'interpolate', ['linear'], ['zoom'],
            0, 6,
            3, 18,
            6, 36,
            9, 70,
        ],
        'heatmap-opacity': [
            'interpolate', ['linear'], ['zoom'],
            6, 0.95,
            9, 0.55,
        ],
    },
};

const CIRCLE_LAYER = {
    id: 'hotzone-points-circles',
    type: 'circle',
    source: 'hotzone-points',
    minzoom: 6,
    paint: {
        'circle-radius': [
            'interpolate', ['linear'], ['get', 'severity'],
            0, 2,
            4, 4,
            10, 9,
        ],
        'circle-color': [
            'case',
            ['>=', ['get', 'severity'], 7], '#E74C3C',
            ['>=', ['get', 'severity'], 4], '#F59E0B',
            '#52A844',
        ],
        'circle-stroke-color': '#080A07',
        'circle-stroke-width': 1,
        'circle-opacity': [
            'interpolate', ['linear'], ['zoom'],
            6, 0,
            7, 0.95,
        ],
    },
};

function periodFull(period) {
    return PERIODS.find((p) => p.value === period)?.full ?? 'Last 7 Days';
}

export default function Hotzones({ period: initialPeriod = '7d', category: initialCategory = null }) {
    const { t } = useTranslation();
    const { highContrast } = useTheme();

    const [period, setPeriod] = useState(initialPeriod);
    const [category, setCategory] = useState(initialCategory || '');
    const [events, setEvents] = useState([]);
    const [availableCategories, setAvailableCategories] = useState([]);
    const [loading, setLoading] = useState(true);
    const [mapStyle, setMapStyle] = useState(null);
    const [mode, setMode] = useState('2d');
    const mapRef = useRef(null);

    useEffect(() => {
        let cancelled = false;
        fetch(STYLE_URL)
            .then((r) => r.json())
            .then((style) => {
                if (cancelled) return;
                const styled = highContrast ? highContrastStyle(style) : darkenStyle(style);
                // Initial projection honours current mode; runtime switches use setProjection below
                styled.projection = { type: mode === '3d' ? 'globe' : 'mercator' };
                setMapStyle(styled);
            })
            .catch((err) => console.warn('Failed to load map style:', err));
        return () => { cancelled = true; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [highContrast]);

    // Runtime projection switch without reloading the style
    useEffect(() => {
        const map = mapRef.current?.getMap?.();
        if (!map?.setProjection) return;
        try {
            map.setProjection({ type: mode === '3d' ? 'globe' : 'mercator' });
        } catch (err) {
            console.warn('setProjection failed:', err);
        }
    }, [mode, mapStyle]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        const params = new URLSearchParams({ period });
        if (category) params.set('category', category);

        fetch(`/api/map/hotzones?${params.toString()}`)
            .then((r) => r.json())
            .then((data) => {
                if (cancelled) return;
                setEvents(Array.isArray(data.events) ? data.events : []);
                if (Array.isArray(data.available_categories)) {
                    setAvailableCategories(data.available_categories);
                }
                setLoading(false);
            })
            .catch((err) => {
                if (cancelled) return;
                console.warn('Hotzone fetch failed:', err);
                setLoading(false);
            });

        return () => { cancelled = true; };
    }, [period, category]);

    useEffect(() => {
        const params = new URLSearchParams();
        if (period !== '7d') params.set('period', period);
        if (category) params.set('category', category);
        const qs = params.toString();
        const url = qs ? `/map/hotzones?${qs}` : '/map/hotzones';
        if (window.location.pathname + window.location.search !== url) {
            window.history.replaceState({}, '', url);
        }
    }, [period, category]);

    const geojson = useMemo(() => ({
        type: 'FeatureCollection',
        features: events
            .filter((e) => Array.isArray(e.coordinates) && e.coordinates.length === 2)
            .map((e) => ({
                type: 'Feature',
                geometry: { type: 'Point', coordinates: e.coordinates },
                properties: {
                    severity: e.severity || 0,
                    category: e.category || '',
                },
            })),
    }), [events]);

    const handleExportPng = useCallback(() => {
        const map = mapRef.current?.getMap?.();
        if (!map) return;
        // Force a repaint so the WebGL buffer is fresh before reading pixels
        map.triggerRepaint();
        map.once('render', () => {
            const src = map.getCanvas();
            const w = src.width;
            const h = src.height;
            const out = document.createElement('canvas');
            out.width = w;
            out.height = h;
            const ctx = out.getContext('2d');
            if (!ctx) return;

            // Map bitmap
            ctx.drawImage(src, 0, 0, w, h);

            // Scale so overlays are crisp regardless of devicePixelRatio
            const scale = w / (src.clientWidth || w);
            const pad = 24 * scale;

            // ── Title (top-left) ──
            ctx.font = `600 ${30 * scale}px "Rajdhani", "Segoe UI", sans-serif`;
            ctx.fillStyle = 'rgba(0,0,0,0.6)';
            const titleText = 'CONFLICT HOTZONES';
            const titleMetrics = ctx.measureText(titleText);
            ctx.fillRect(pad - 12 * scale, pad - 10 * scale, titleMetrics.width + 24 * scale, 48 * scale);

            ctx.fillStyle = '#52A844';
            ctx.textBaseline = 'top';
            ctx.fillText(titleText, pad, pad);

            const subtitle = `${periodFull(period).toUpperCase()}${category ? ` · ${formatCategoryLabel(category).toUpperCase()}` : ''}`;
            ctx.font = `${14 * scale}px "Roboto Mono", monospace`;
            ctx.fillStyle = '#8AAD83';
            ctx.fillText(subtitle, pad, pad + 36 * scale);

            // ── Event count (top-right) ──
            const countLabel = events.length.toLocaleString();
            ctx.font = `600 ${28 * scale}px "Rajdhani", "Segoe UI", sans-serif`;
            const countMetrics = ctx.measureText(countLabel);
            const countBoxW = Math.max(countMetrics.width + 32 * scale, 120 * scale);
            const countBoxH = 62 * scale;
            const countX = w - pad - countBoxW;
            const countY = pad - 6 * scale;
            ctx.fillStyle = 'rgba(0,0,0,0.7)';
            ctx.fillRect(countX, countY, countBoxW, countBoxH);
            ctx.strokeStyle = '#2D5426';
            ctx.lineWidth = 1 * scale;
            ctx.strokeRect(countX, countY, countBoxW, countBoxH);

            ctx.font = `${11 * scale}px "Roboto Mono", monospace`;
            ctx.fillStyle = '#8AAD83';
            ctx.fillText('EVENTS', countX + 12 * scale, countY + 8 * scale);

            ctx.font = `600 ${28 * scale}px "Rajdhani", "Segoe UI", sans-serif`;
            ctx.fillStyle = '#52A844';
            ctx.fillText(countLabel, countX + 12 * scale, countY + 24 * scale);

            // ── Legend (bottom-right) ──
            const legW = 170 * scale;
            const legH = 10 * scale;
            const legX = w - pad - legW;
            const legY = h - pad - 30 * scale;

            ctx.fillStyle = 'rgba(0,0,0,0.7)';
            ctx.fillRect(legX - 12 * scale, legY - 22 * scale, legW + 24 * scale, 58 * scale);
            ctx.strokeStyle = '#2D5426';
            ctx.strokeRect(legX - 12 * scale, legY - 22 * scale, legW + 24 * scale, 58 * scale);

            ctx.font = `${11 * scale}px "Roboto Mono", monospace`;
            ctx.fillStyle = '#8AAD83';
            ctx.fillText('INTENSITY', legX, legY - 16 * scale);

            const grad = ctx.createLinearGradient(legX, 0, legX + legW, 0);
            grad.addColorStop(0, 'rgba(82,168,68,0.7)');
            grad.addColorStop(0.33, 'rgba(245,158,11,0.85)');
            grad.addColorStop(0.66, 'rgba(231,76,60,0.95)');
            grad.addColorStop(1, 'rgba(255,80,40,1)');
            ctx.fillStyle = grad;
            ctx.fillRect(legX, legY, legW, legH);

            ctx.font = `${10 * scale}px "Roboto Mono", monospace`;
            ctx.fillStyle = '#5A6B53';
            ctx.fillText('LOW', legX, legY + legH + 4 * scale);
            const highMetrics = ctx.measureText('HIGH');
            ctx.fillText('HIGH', legX + legW - highMetrics.width, legY + legH + 4 * scale);

            // ── Watermark (bottom-left) ──
            ctx.font = `600 ${22 * scale}px "Rajdhani", "Segoe UI", sans-serif`;
            const wmText = 'CLASHMONITOR.COM';
            const wmMetrics = ctx.measureText(wmText);
            ctx.fillStyle = 'rgba(0,0,0,0.6)';
            ctx.fillRect(pad - 12 * scale, h - pad - 44 * scale, wmMetrics.width + 24 * scale, 50 * scale);

            ctx.fillStyle = 'rgba(82,168,68,0.95)';
            ctx.fillText(wmText, pad, h - pad - 36 * scale);

            ctx.font = `${11 * scale}px "Roboto Mono", monospace`;
            ctx.fillStyle = '#5A6B53';
            ctx.fillText('REAL-TIME OSINT · CLASHMONITOR', pad, h - pad - 12 * scale);

            // ── Trigger download ──
            out.toBlob((blob) => {
                if (!blob) return;
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                const date = new Date().toISOString().slice(0, 10);
                const catSuffix = category ? `-${category}` : '';
                a.download = `clashmonitor-hotzones-${period}${catSuffix}-${date}.png`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }, 'image/png');
        });
    }, [period, category, events.length]);

    const categoryChips = useMemo(
        () => [{ value: '', label: 'All', color: '#8AAD83' }].concat(
            availableCategories.map((c) => ({
                value: c,
                label: formatCategoryLabel(c),
                color: CATEGORY_COLORS[c] || '#8AAD83',
            })),
        ),
        [availableCategories],
    );

    const activeCategoryLabel = category ? formatCategoryLabel(category) : 'All';

    return (
        <AppLayout breadcrumbs={[{ label: 'Hotzones' }]}>
            <div className="space-y-3">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
                    <div>
                        <h1 className="font-display text-3xl md:text-4xl tracking-wider text-green-bright">
                            {t('hotzones.title', 'GLOBAL CONFLICT HOTZONES')}
                        </h1>
                        <p className="font-mono text-xs text-text-muted tracking-widest uppercase mt-1">
                            {periodFull(period)}
                            {category ? <> · {category.toUpperCase()}</> : null}
                            <> · {loading ? t('common.loading', 'Loading…') : `${events.length.toLocaleString()} events`}</>
                        </p>
                    </div>
                    <div className="flex items-center gap-2 self-start sm:self-end">
                        <button
                            onClick={handleExportPng}
                            disabled={!mapStyle || loading}
                            className="font-mono text-xs text-green-bright hover:text-green-neon transition-colors border border-green-base hover:border-green-bright px-3 py-1.5 rounded disabled:opacity-40 disabled:cursor-not-allowed"
                            title={t('hotzones.exportTitle', 'Download current view as PNG')}
                        >
                            ↓ {t('hotzones.exportPng', 'Export PNG')}
                        </button>
                        <button
                            onClick={() => router.reload({ preserveScroll: true })}
                            className="font-mono text-xs text-text-muted hover:text-green-bright transition-colors border border-border-mid hover:border-border-active px-3 py-1.5 rounded"
                        >
                            {t('common.refresh', 'Refresh')} ↺
                        </button>
                    </div>
                </div>

                {/* Period chips + projection toggle */}
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-mono text-[10px] text-text-muted uppercase tracking-widest">
                        {t('hotzones.period', 'Period')}
                    </span>
                    {PERIODS.map((p) => (
                        <button
                            key={p.value}
                            onClick={() => setPeriod(p.value)}
                            className={`font-mono text-xs px-3 py-1 border rounded transition-colors ${
                                period === p.value
                                    ? 'border-green-base text-green-bright bg-surface-2'
                                    : 'border-border-mid text-text-secondary hover:border-border-active'
                            }`}
                        >
                            {p.label}
                        </button>
                    ))}

                    <span className="font-mono text-[10px] text-text-muted uppercase tracking-widest ml-2">
                        {t('hotzones.view', 'View')}
                    </span>
                    <div className="inline-flex border border-border-mid rounded overflow-hidden">
                        {['2d', '3d'].map((m) => (
                            <button
                                key={m}
                                onClick={() => setMode(m)}
                                className={`font-mono text-xs px-3 py-1 transition-colors ${
                                    mode === m
                                        ? 'bg-surface-2 text-green-bright'
                                        : 'text-text-secondary hover:text-green-bright'
                                }`}
                                title={m === '2d' ? t('hotzones.view2dTitle', 'Flat map — best for screenshots') : t('hotzones.view3dTitle', 'Interactive globe')}
                            >
                                {m.toUpperCase()}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Category chips — dynamically populated from available data */}
                {categoryChips.length > 1 && (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-[10px] text-text-muted uppercase tracking-widest">
                            {t('hotzones.category', 'Category')}
                        </span>
                        {categoryChips.map((c) => (
                            <button
                                key={c.value || 'all'}
                                onClick={() => setCategory(c.value)}
                                className={`font-mono text-xs px-3 py-1 border rounded transition-colors ${
                                    category === c.value
                                        ? 'border-green-base text-green-bright bg-surface-2'
                                        : 'border-border-mid text-text-secondary hover:border-border-active'
                                }`}
                                style={category === c.value ? { borderColor: c.color, color: c.color } : undefined}
                            >
                                {c.label}
                            </button>
                        ))}
                    </div>
                )}

                {/* Map canvas — poster layout */}
                <div
                    className="relative rounded overflow-hidden border border-border-mid bg-black"
                    style={{ height: 'min(80vh, 820px)' }}
                >
                    {mapStyle ? (
                        <MapGL
                            ref={mapRef}
                            mapStyle={mapStyle}
                            initialViewState={INITIAL_VIEW}
                            minZoom={0.8}
                            maxZoom={9}
                            attributionControl={false}
                            cursor="grab"
                            preserveDrawingBuffer={true}
                            renderWorldCopies={false}
                        >
                            <Source id="hotzone-points" type="geojson" data={geojson}>
                                <Layer {...HEATMAP_LAYER} />
                                <Layer {...CIRCLE_LAYER} />
                            </Source>
                        </MapGL>
                    ) : (
                        <div className="absolute inset-0 flex items-center justify-center font-mono text-xs text-text-muted">
                            {t('common.loading', 'Loading…')}
                        </div>
                    )}

                    {/* Title overlay — top-left */}
                    <div className="absolute top-4 left-4 pointer-events-none max-w-[70%]">
                        <div className="font-display text-xl md:text-2xl tracking-wider text-green-bright drop-shadow-[0_2px_8px_rgba(0,0,0,0.95)]">
                            CONFLICT HOTZONES
                        </div>
                        <div className="font-mono text-[10px] md:text-xs text-text-secondary uppercase tracking-widest mt-0.5 drop-shadow-[0_2px_8px_rgba(0,0,0,0.95)]">
                            {periodFull(period)}{category ? ` · ${activeCategoryLabel}` : ''}
                        </div>
                    </div>

                    {/* Event count badge — top-right */}
                    <div className="absolute top-4 right-4 bg-black/70 border border-border-mid rounded px-3 py-2 pointer-events-none">
                        <div className="font-mono text-[9px] text-text-muted uppercase tracking-widest">
                            {t('hotzones.events', 'Events')}
                        </div>
                        <div className="font-display text-xl text-green-bright tabular-nums">
                            {loading ? '—' : events.length.toLocaleString()}
                        </div>
                    </div>

                    {/* Legend — bottom-right */}
                    <div className="absolute bottom-4 right-4 bg-black/70 border border-border-mid rounded px-3 py-2 pointer-events-none">
                        <div className="font-mono text-[9px] text-text-muted uppercase tracking-widest mb-1">
                            {t('hotzones.intensity', 'Intensity')}
                        </div>
                        <div
                            className="h-2 w-32 rounded-sm"
                            style={{
                                background:
                                    'linear-gradient(to right, rgba(82,168,68,0.7), rgba(245,158,11,0.85), rgba(231,76,60,0.95), rgba(255,80,40,1))',
                            }}
                        />
                        <div className="flex justify-between font-mono text-[9px] text-text-dim mt-0.5">
                            <span>{t('hotzones.low', 'Low')}</span>
                            <span>{t('hotzones.high', 'High')}</span>
                        </div>
                    </div>

                    {/* Watermark — bottom-left */}
                    <div className="absolute bottom-4 left-4 pointer-events-none">
                        <div className="font-display text-sm tracking-wider text-green-bright/90 drop-shadow-[0_2px_8px_rgba(0,0,0,0.95)]">
                            CLASHMONITOR.COM
                        </div>
                        <div className="font-mono text-[9px] text-text-dim uppercase tracking-widest drop-shadow-[0_2px_8px_rgba(0,0,0,0.95)]">
                            Real-time OSINT
                        </div>
                    </div>
                </div>

                {/* Footer note */}
                <div className="font-mono text-[10px] text-text-dim text-center">
                    {t('hotzones.footer', 'Severity-weighted density of geolocated events. Heat = concentration × severity.')}
                </div>
            </div>
        </AppLayout>
    );
}
