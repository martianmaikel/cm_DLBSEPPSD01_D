import { useEffect, useState, useCallback, useMemo, useRef } from 'react';
import MapGL, { Source, Layer } from 'react-map-gl/maplibre';
import 'maplibre-gl/dist/maplibre-gl.css';
import * as topojson from 'topojson-client';

import useCountryHeat from './useCountryHeat';
import { useDashboard } from '../../Contexts/DashboardContext';
import { useTheme } from '../../Contexts/ThemeContext';
import { STYLE_URL, darkenStyle, highContrastStyle } from './mapStyles';
import { TACTICAL_SEVERITY_THRESHOLD } from './constants';
import { generateTacticalGrid } from './tacticalGrid';
import TacticalReticle from './TacticalReticle';
import HotzoneCard from './HotzoneCard';
import GlobeLoadingSkeleton from './GlobeLoadingSkeleton';
import WorldThreatLevel from './WorldThreatLevel';
import LiveIndicator from './LiveIndicator';
import CriticalEventAlert from './CriticalEventAlert';
import RegionQuickJump, { REGIONS } from './RegionQuickJump';
import CursorCoordinates from './CursorCoordinates';
import TimelineScrubber from './TimelineScrubber';

// ─── CONFIG ──────────────────────────────────────────────────
const AUTO_ROTATE_MAX_ZOOM = 3;
const AUTO_ROTATE_SPEED = 0.3;
const AUTO_ROTATE_RESUME_DELAY = 5000;

const INITIAL_VIEW = {
    latitude: 30,
    longitude: 30,
    zoom: typeof window !== 'undefined' && window.innerWidth >= 1024 ? 2.8 : 2.2,
};

// ISO 3166-1 numeric → alpha-2 mapping for countries-110m.json
const NUMERIC_TO_ALPHA2 = {
    '4':'AF','8':'AL','12':'DZ','24':'AO','31':'AZ','48':'BH','50':'BD','51':'AM',
    '56':'BE','64':'BT','68':'BO','70':'BA','72':'BW','76':'BR','100':'BG','104':'MM',
    '108':'BI','112':'BY','116':'KH','120':'CM','124':'CA','140':'CF','144':'LK',
    '148':'TD','152':'CL','156':'CN','170':'CO','178':'CG','180':'CD','188':'CR',
    '191':'HR','192':'CU','196':'CY','203':'CZ','204':'BJ','208':'DK','214':'DO',
    '218':'EC','222':'SV','231':'ET','232':'ER','233':'EE','250':'FR','262':'DJ',
    '268':'GE','270':'GM','275':'PS','276':'DE','288':'GH','300':'GR','320':'GT',
    '324':'GN','332':'HT','340':'HN','348':'HU','356':'IN','360':'ID','364':'IR',
    '368':'IQ','372':'IE','376':'IL','380':'IT','384':'CI','392':'JP','398':'KZ',
    '400':'JO','404':'KE','408':'KP','410':'KR','414':'KW','417':'KG','418':'LA',
    '422':'LB','426':'LS','430':'LR','434':'LY','440':'LT','442':'LU','450':'MG',
    '454':'MW','458':'MY','466':'ML','478':'MR','484':'MX','496':'MN','498':'MD',
    '504':'MA','508':'MZ','512':'OM','516':'NA','524':'NP','528':'NL','548':'VU',
    '554':'NZ','558':'NI','562':'NE','566':'NG','578':'NO','586':'PK','591':'PA',
    '598':'PG','600':'PY','604':'PE','608':'PH','616':'PL','620':'PT','624':'GW',
    '634':'QA','642':'RO','643':'RU','646':'RW','682':'SA','686':'SN','694':'SL',
    '702':'SG','703':'SK','704':'VN','706':'SO','710':'ZA','716':'ZW','724':'ES',
    '728':'SS','729':'SD','740':'SR','752':'SE','756':'CH','760':'SY','762':'TJ',
    '764':'TH','768':'TG','780':'TT','784':'AE','788':'TN','792':'TR','795':'TM',
    '800':'UG','804':'UA','807':'MK','818':'EG','826':'GB','834':'TZ','840':'US',
    '854':'BF','858':'UY','860':'UZ','862':'VE','887':'YE','894':'ZM','926':'XK',
};

// Alpha-2 → display name for hotzone cards
const COUNTRY_NAMES = {
    AF:'Afghanistan',AL:'Albania',DZ:'Algeria',AO:'Angola',AZ:'Azerbaijan',
    BH:'Bahrain',BD:'Bangladesh',AM:'Armenia',BE:'Belgium',BT:'Bhutan',
    BO:'Bolivia',BA:'Bosnia',BW:'Botswana',BR:'Brazil',BG:'Bulgaria',
    MM:'Myanmar',BI:'Burundi',BY:'Belarus',KH:'Cambodia',CM:'Cameroon',
    CA:'Canada',CF:'C.A.R.',LK:'Sri Lanka',TD:'Chad',CL:'Chile',
    CN:'China',CO:'Colombia',CG:'Congo',CD:'D.R. Congo',CR:'Costa Rica',
    HR:'Croatia',CU:'Cuba',CY:'Cyprus',CZ:'Czechia',BJ:'Benin',
    DK:'Denmark',DO:'Dom. Republic',EC:'Ecuador',SV:'El Salvador',
    ET:'Ethiopia',ER:'Eritrea',EE:'Estonia',FR:'France',DJ:'Djibouti',
    GE:'Georgia',GM:'Gambia',PS:'Palestine',DE:'Germany',GH:'Ghana',
    GR:'Greece',GT:'Guatemala',GN:'Guinea',HT:'Haiti',HN:'Honduras',
    HU:'Hungary',IN:'India',ID:'Indonesia',IR:'Iran',IQ:'Iraq',
    IE:'Ireland',IL:'Israel',IT:'Italy',CI:"Côte d'Ivoire",JP:'Japan',
    KZ:'Kazakhstan',JO:'Jordan',KE:'Kenya',KP:'North Korea',KR:'South Korea',
    KW:'Kuwait',KG:'Kyrgyzstan',LA:'Laos',LB:'Lebanon',LS:'Lesotho',
    LR:'Liberia',LY:'Libya',LT:'Lithuania',LU:'Luxembourg',MG:'Madagascar',
    MW:'Malawi',MY:'Malaysia',ML:'Mali',MR:'Mauritania',MX:'Mexico',
    MN:'Mongolia',MD:'Moldova',MA:'Morocco',MZ:'Mozambique',OM:'Oman',
    NA:'Namibia',NP:'Nepal',NL:'Netherlands',VU:'Vanuatu',NZ:'New Zealand',
    NI:'Nicaragua',NE:'Niger',NG:'Nigeria',NO:'Norway',PK:'Pakistan',
    PA:'Panama',PG:'Papua N.G.',PY:'Paraguay',PE:'Peru',PH:'Philippines',
    PL:'Poland',PT:'Portugal',GW:'Guinea-Bissau',QA:'Qatar',RO:'Romania',
    RU:'Russia',RW:'Rwanda',SA:'Saudi Arabia',SN:'Senegal',SL:'Sierra Leone',
    SG:'Singapore',SK:'Slovakia',VN:'Vietnam',SO:'Somalia',ZA:'South Africa',
    ZW:'Zimbabwe',ES:'Spain',SS:'South Sudan',SD:'Sudan',SR:'Suriname',
    SE:'Sweden',CH:'Switzerland',SY:'Syria',TJ:'Tajikistan',TH:'Thailand',
    TG:'Togo',TT:'Trinidad',AE:'U.A.E.',TN:'Tunisia',TR:'Turkey',
    TM:'Turkmenistan',UG:'Uganda',UA:'Ukraine',MK:'N. Macedonia',EG:'Egypt',
    GB:'United Kingdom',TZ:'Tanzania',US:'United States',BF:'Burkina Faso',
    UY:'Uruguay',UZ:'Uzbekistan',VE:'Venezuela',YE:'Yemen',ZM:'Zambia',
    XK:'Kosovo',
};

// ─── SEVERITY HELPERS ────────────────────────────────────────
function getSeverityHex(sev) {
    if (sev >= 7) return '#E74C3C';
    if (sev >= 4) return '#F59E0B';
    return '#52A844';
}

const SEVERITY_COLOR_EXPR = [
    'case',
    ['>=', ['get', 'severity'], 7], '#E74C3C',
    ['>=', ['get', 'severity'], 4], '#F59E0B',
    '#52A844',
];

// ─── CORROBORATION-DRIVEN STYLING ────────────────────────────
// Stroke width varies by verification status
const STATUS_STROKE_WIDTH = [
    'case',
    ['==', ['get', 'status'], 'confirmed'], 2.5,
    ['==', ['get', 'status'], 'corroborated'], 1.5,
    ['==', ['get', 'status'], 'disputed'], 1,
    0.6, // unverified / pending
];

// Core opacity varies by verification status
const STATUS_CORE_OPACITY = [
    'case',
    ['==', ['get', 'status'], 'confirmed'], 1,
    ['==', ['get', 'status'], 'corroborated'], 0.85,
    ['==', ['get', 'status'], 'disputed'], 0.5,
    0.55, // unverified
];

// ─── DATA CONVERSION ────────────────────────────────────────
function eventsToGeoJSON(events) {
    const now = Date.now();
    return {
        type: 'FeatureCollection',
        features: events
            .filter((e) => e.coordinates?.length === 2)
            .map((e) => {
                const hoursAgo = (now - new Date(e.occurred_at).getTime()) / 3600000;
                return {
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: [e.coordinates[0], e.coordinates[1]] },
                    properties: {
                        id: e.id,
                        title: e.title,
                        severity: e.severity,
                        country: e.country || '',
                        category: e.category || '',
                        status: e.status || '',
                        occurred_at: e.occurred_at || '',
                        hour_index: Math.max(0, Math.min(23, 23 - Math.floor(hoursAgo))),
                    },
                };
            }),
    };
}

// ─── GREAT-CIRCLE ARC INTERPOLATION ─────────────────────────
function interpolateGreatCircle(from, to, segments = 30) {
    const toRad = (d) => (d * Math.PI) / 180;
    const toDeg = (r) => (r * 180) / Math.PI;
    const [lng1, lat1] = [toRad(from[0]), toRad(from[1])];
    const [lng2, lat2] = [toRad(to[0]), toRad(to[1])];

    const d =
        2 *
        Math.asin(
            Math.sqrt(
                Math.pow(Math.sin((lat1 - lat2) / 2), 2) +
                    Math.cos(lat1) * Math.cos(lat2) * Math.pow(Math.sin((lng1 - lng2) / 2), 2),
            ),
        );

    if (d < 0.0001) return [from, to];

    const points = [];
    for (let i = 0; i <= segments; i++) {
        const f = i / segments;
        const A = Math.sin((1 - f) * d) / Math.sin(d);
        const B = Math.sin(f * d) / Math.sin(d);
        const x = A * Math.cos(lat1) * Math.cos(lng1) + B * Math.cos(lat2) * Math.cos(lng2);
        const y = A * Math.cos(lat1) * Math.sin(lng1) + B * Math.cos(lat2) * Math.sin(lng2);
        const z = A * Math.sin(lat1) + B * Math.sin(lat2);
        points.push([toDeg(Math.atan2(y, x)), toDeg(Math.atan2(z, Math.sqrt(x * x + y * y)))]);
    }
    return points;
}

function eventsToThreadArcs(events) {
    const threads = {};
    for (const e of events) {
        if (!e.conflict_thread_id || !e.coordinates?.length) continue;
        if (!threads[e.conflict_thread_id]) threads[e.conflict_thread_id] = [];
        threads[e.conflict_thread_id].push(e);
    }

    const features = [];
    for (const threadEvents of Object.values(threads)) {
        if (threadEvents.length < 2) continue;
        threadEvents.sort((a, b) => new Date(a.occurred_at) - new Date(b.occurred_at));
        for (let i = 0; i < threadEvents.length - 1; i++) {
            const from = threadEvents[i].coordinates;
            const to = threadEvents[i + 1].coordinates;
            // Skip arcs between identical or very close points
            if (Math.abs(from[0] - to[0]) < 0.01 && Math.abs(from[1] - to[1]) < 0.01) continue;
            features.push({
                type: 'Feature',
                geometry: { type: 'LineString', coordinates: interpolateGreatCircle(from, to) },
                properties: {
                    severity: Math.max(threadEvents[i].severity, threadEvents[i + 1].severity),
                },
            });
        }
    }

    return { type: 'FeatureCollection', features };
}

function getFeatureCentroid(feature) {
    const coords = feature.geometry?.coordinates;
    if (!coords) return null;
    const flat = [];
    const walk = (a) => { if (typeof a[0] === 'number') flat.push(a); else a.forEach(walk); };
    walk(coords);
    if (!flat.length) return null;
    return {
        lng: flat.reduce((s, c) => s + c[0], 0) / flat.length,
        lat: flat.reduce((s, c) => s + c[1], 0) / flat.length,
    };
}

// ─── COMPONENT ───────────────────────────────────────────────
export default function ConflictGlobeInner({ events = [], onEventSelect, onCountryCardClick, onClusterClick }) {
    const { filters, setSelectedCountry, setSelectedEvent, selectedEvent, newEvents } = useDashboard();
    const { highContrast } = useTheme();
    const heatData = useCountryHeat(filters.timeRange);
    const [hovered, setHovered] = useState(null);
    const [countries, setCountries] = useState(null);
    const [heatmapMode, setHeatmapMode] = useState(false);
    const [cursorCoords, setCursorCoords] = useState(null);
    const [timelineCursor, setTimelineCursor] = useState(23);
    const [dataGlitch, setDataGlitch] = useState(false);

    const mapRef = useRef(null);
    const lastMouseMoveRef = useRef(0);
    const [viewState, setViewState] = useState(INITIAL_VIEW);

    const isRotatingRef = useRef(true);
    const idleTimerRef = useRef(null);
    const rafRef = useRef(null);
    const lastFrameRef = useRef(null);

    const [mapStyle, setMapStyle] = useState(null);

    // ─── READY STATE (boot sequence until core assets loaded) ─
    const [minTimeReached, setMinTimeReached] = useState(false);
    const globeReady = mapStyle && countries && minTimeReached;

    useEffect(() => {
        const timer = setTimeout(() => setMinTimeReached(true), 2400);
        return () => clearTimeout(timer);
    }, []);
    useEffect(() => {
        fetch(STYLE_URL)
            .then((r) => r.json())
            .then((style) => setMapStyle(highContrast ? highContrastStyle(style) : darkenStyle(style)))
            .catch((err) => console.warn('Failed to load map style:', err));
    }, [highContrast]);

    // ─── DATA GLITCH on new events ────────────────────────
    useEffect(() => {
        if (!newEvents?.length || !globeReady) return;
        setDataGlitch(true);
        const timer = setTimeout(() => setDataGlitch(false), 400);
        return () => clearTimeout(timer);
    }, [newEvents, globeReady]);
    // ─── MEMOIZED DATA ──────────────────────────────────────
    const eventsGeoJSON = useMemo(() => {
        const full = eventsToGeoJSON(events);
        if (timelineCursor >= 23) return full;
        return {
            ...full,
            features: full.features.filter((f) => f.properties.hour_index <= timelineCursor),
        };
    }, [events, timelineCursor]);
    const threadArcsGeoJSON = useMemo(() => eventsToThreadArcs(events), [events]);

    const criticalEvents = useMemo(
        () => events.filter((e) => e.severity >= TACTICAL_SEVERITY_THRESHOLD && e.coordinates?.length === 2),
        [events],
    );

    const tacticalGridGeoJSON = useMemo(() => {
        if (!criticalEvents.length) return { type: 'FeatureCollection', features: [] };
        const allFeatures = criticalEvents.slice(0, 5).flatMap((e) =>
            generateTacticalGrid(e.coordinates[0], e.coordinates[1]).features,
        );
        return { type: 'FeatureCollection', features: allFeatures };
    }, [criticalEvents]);

    const firstSymbolId = useMemo(() => {
        if (!mapStyle?.layers) return undefined;
        const sym = mapStyle.layers.find((l) => l.type === 'symbol');
        return sym?.id;
    }, [mapStyle]);

    const countriesWithHeat = useMemo(() => {
        if (!countries) return null;
        return {
            ...countries,
            features: countries.features.map((f) => {
                const alpha2 = NUMERIC_TO_ALPHA2[String(f.id)];
                const heat = alpha2 ? heatData.get(alpha2) : null;
                return {
                    ...f,
                    properties: {
                        ...f.properties,
                        max_severity: heat?.max_severity ?? 0,
                        event_count: heat?.event_count ?? 0,
                    },
                };
            }),
        };
    }, [countries, heatData]);

    // Pre-build lookup from alpha-2 code → feature for O(1) access
    const countryLookup = useMemo(() => {
        if (!countries) return new Map();
        const lookup = new Map();
        for (const f of countries.features) {
            const alpha2 = NUMERIC_TO_ALPHA2[String(f.id)];
            if (alpha2) lookup.set(alpha2, f);
        }
        return lookup;
    }, [countries]);

    const hotzones = useMemo(() => {
        if (!countryLookup.size) return [];
        const zones = [];
        for (const [code, entry] of heatData) {
            if (entry.max_severity < 1) continue;
            const feature = countryLookup.get(code);
            if (!feature) continue;
            const c = getFeatureCentroid(feature);
            if (!c) continue;
            zones.push({
                code,
                name: COUNTRY_NAMES[code] || code,
                lat: c.lat,
                lng: c.lng,
                eventCount: entry.event_count,
                maxSeverity: entry.max_severity,
            });
        }
        zones.sort((a, b) => b.maxSeverity - a.maxSeverity || b.eventCount - a.eventCount);
        return zones.slice(0, 8);
    }, [countryLookup, heatData]);

    useEffect(() => {
        fetch('/vendor/world-atlas/countries-50m.json')
            .then((r) => r.json())
            .then((topo) => setCountries(topojson.feature(topo, topo.objects.countries)))
            .catch(() => {});
    }, []);

    // ─── AUTO-ROTATION ───────────────────────────────────────
    useEffect(() => {
        let lastRotateSync = 0;

        const animate = (timestamp) => {
            if (lastFrameRef.current === null) {
                lastFrameRef.current = timestamp;
                lastRotateSync = timestamp;
            }
            lastFrameRef.current = timestamp;

            if (isRotatingRef.current) {
                const elapsed = timestamp - lastRotateSync;
                // Throttle React state updates to ~30fps; rotation is slow enough
                if (elapsed >= 33) {
                    const delta = elapsed / 1000;
                    lastRotateSync = timestamp;
                    setViewState((prev) => {
                        if (prev.zoom > AUTO_ROTATE_MAX_ZOOM) return prev;
                        return { ...prev, longitude: prev.longitude + AUTO_ROTATE_SPEED * delta };
                    });
                }
            }

            rafRef.current = requestAnimationFrame(animate);
        };

        rafRef.current = requestAnimationFrame(animate);
        return () => { if (rafRef.current) cancelAnimationFrame(rafRef.current); };
    }, []);

    const onMove = useCallback((evt) => setViewState(evt.viewState), []);

    const pauseRotation = useCallback(() => {
        isRotatingRef.current = false;
        lastFrameRef.current = null;
        clearTimeout(idleTimerRef.current);
        idleTimerRef.current = setTimeout(() => { isRotatingRef.current = true; }, AUTO_ROTATE_RESUME_DELAY);
    }, []);

    useEffect(() => () => clearTimeout(idleTimerRef.current), []);

    // ─── FLY-TO HELPERS ─────────────────────────────────────
    const flyTo = useCallback((lat, lng, zoom = 6) => {
        const map = mapRef.current?.getMap();
        if (map) {
            pauseRotation();
            map.flyTo({ center: [lng, lat], zoom, duration: 1500 });
        }
    }, [pauseRotation]);

    const flyToCountry = useCallback((code, lat, lng) => {
        flyTo(lat, lng, 6);
        setSelectedCountry({ code, name: COUNTRY_NAMES[code] || code, lat, lng });
    }, [flyTo, setSelectedCountry]);

    const resetView = useCallback(() => {
        const map = mapRef.current?.getMap();
        if (map) {
            pauseRotation();
            map.flyTo({ center: [INITIAL_VIEW.longitude, INITIAL_VIEW.latitude], zoom: INITIAL_VIEW.zoom, duration: 1200 });
        }
    }, [pauseRotation]);

    // Fly to event when selected externally (e.g. from feed)
    const prevSelectedRef = useRef(null);
    useEffect(() => {
        if (selectedEvent && selectedEvent !== prevSelectedRef.current && selectedEvent.coordinates?.length === 2) {
            flyTo(selectedEvent.coordinates[1], selectedEvent.coordinates[0], 8);
        }
        prevSelectedRef.current = selectedEvent;
    }, [selectedEvent, flyTo]);

    // ─── KEYBOARD SHORTCUTS ─────────────────────────────────
    useEffect(() => {
        const handleKey = (e) => {
            // Don't capture if user is typing in an input
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;

            const map = mapRef.current?.getMap();

            switch (e.key) {
                case 'r':
                case 'R':
                    resetView();
                    break;
                case '+':
                case '=':
                    if (map) { pauseRotation(); map.zoomIn({ duration: 300 }); }
                    break;
                case '-':
                    if (map) { pauseRotation(); map.zoomOut({ duration: 300 }); }
                    break;
                case 'Escape':
                    setSelectedEvent(null);
                    break;
                case 'h':
                case 'H':
                    setHeatmapMode((v) => !v);
                    break;
                default: {
                    // 1-6: region quick-jump
                    const region = REGIONS.find((r) => r.key === e.key);
                    if (region) flyTo(region.lat, region.lng, region.zoom);
                    break;
                }
            }
        };

        window.addEventListener('keydown', handleKey);
        return () => window.removeEventListener('keydown', handleKey);
    }, [pauseRotation, flyTo, resetView, setSelectedEvent]);

    // ─── PULSE ANIMATION (event markers only) ───────────────
    useEffect(() => {
        let running = true;
        let lastPulseFrame = 0;

        const animatePulse = (timestamp) => {
            if (!running) return;

            // Throttle paint property updates to ~20fps
            if (timestamp - lastPulseFrame < 50) {
                requestAnimationFrame(animatePulse);
                return;
            }
            lastPulseFrame = timestamp;

            const map = mapRef.current?.getMap();
            if (!map) { requestAnimationFrame(animatePulse); return; }

            const zoom = map.getZoom();

            if (map.getLayer('events-pulse')) {
                const markerFade = zoom < 4 ? 0 : zoom > 6 ? 1 : (zoom - 4) / 2;
                const phase = (Date.now() % 2500) / 2500;

                map.setPaintProperty('events-pulse', 'circle-radius',
                    ['interpolate', ['linear'], ['get', 'severity'],
                        1, 3 + phase * 6,
                        4, 4 + phase * 8,
                        7, 5 + phase * 10,
                    ]);
                map.setPaintProperty('events-pulse', 'circle-opacity', 0.2 * (1 - phase) * markerFade);

                const breathe = Math.sin(Date.now() / 1200) * 1.5;
                map.setPaintProperty('events-glow', 'circle-radius',
                    ['interpolate', ['linear'], ['get', 'severity'],
                        1, 6 + breathe,
                        4, 8 + breathe,
                        7, 10 + breathe,
                    ]);
                map.setPaintProperty('events-glow', 'circle-opacity', 0.08 * markerFade);
            }

            requestAnimationFrame(animatePulse);
        };

        requestAnimationFrame(animatePulse);
        return () => { running = false; };
    }, []);

    // ─── EVENT HANDLERS ──────────────────────────────────────
    const handleClick = useCallback(async (e) => {
        const map = mapRef.current?.getMap();
        if (!map) return;

        // Check interactiveLayerIds features first (populated by react-map-gl)
        const feature = e.features?.[0];

        // Click on cluster → open event list
        if (feature?.properties?.cluster_id != null) {
            const source = map.getSource('events');
            if (source && onClusterClick) {
                try {
                    const clusterId = feature.properties.cluster_id;
                    const pointCount = feature.properties.point_count;
                    const leaves = await source.getClusterLeaves(clusterId, pointCount, 0);
                    const matched = leaves
                        .map((leaf) => events.find((ev) => ev.id === leaf.properties.id))
                        .filter(Boolean);
                    if (matched.length > 0) onClusterClick(matched);
                } catch { /* cluster read failed, ignore */ }
            }
            return;
        }

        // Click on individual event marker
        if (feature?.properties?.id && !feature?.properties?.cluster_id) {
            const event = events.find((ev) => ev.id === feature.properties.id);
            if (event && onEventSelect) {
                onEventSelect(event);
                if (event.coordinates?.length === 2) {
                    flyTo(event.coordinates[1], event.coordinates[0], 8);
                }
                return;
            }
        }

        // Fallback: country fill click
        if (map.getZoom() >= 4) {
            const eventFeatures = map.queryRenderedFeatures(e.point, { layers: ['events-core'] });
            if (eventFeatures.length > 0 && onEventSelect) {
                const props = eventFeatures[0].properties;
                const event = events.find((ev) => ev.id === props.id);
                if (event) {
                    onEventSelect(event);
                    if (event.coordinates?.length === 2) {
                        flyTo(event.coordinates[1], event.coordinates[0], 8);
                    }
                    return;
                }
            }
        }

        const countryFeatures = map.queryRenderedFeatures(e.point, { layers: ['country-fills'] });
        if (countryFeatures.length > 0) {
            const f = countryFeatures[0];
            const alpha2 = NUMERIC_TO_ALPHA2[String(f.id)];
            if (!alpha2) return;
            const c = getFeatureCentroid(f);
            if (!c) return;
            flyToCountry(alpha2, c.lat, c.lng);
        }
    }, [events, onEventSelect, onClusterClick, flyToCountry, pauseRotation]);

    const handleMouseMove = useCallback((e) => {
        const now = performance.now();
        if (now - lastMouseMoveRef.current < 50) return;
        lastMouseMoveRef.current = now;

        const map = mapRef.current?.getMap();
        if (!map) return;

        // Track cursor coordinates
        setCursorCoords(e.lngLat);

        // Hover info for individual events (interactiveLayerIds handles cursor)
        const feature = e.features?.[0];
        if (feature?.properties?.id && !feature?.properties?.cluster_id) {
            const props = feature.properties;
            setHovered({ severity: props.severity, title: props.title, country: props.country });
            return;
        }

        setHovered(null);
    }, []);

    const starOffset = -(((viewState.longitude % 360) + 360) % 360);

    // ─── RENDER ──────────────────────────────────────────────
    if (!mapStyle) {
        return <GlobeLoadingSkeleton />;
    }

    return (
        <div
            className="globe-container relative w-full h-full min-h-[400px] overflow-hidden"
            style={{ background: highContrast ? '#020302' : '#080A07', '--star-offset': starOffset }}
            onPointerDown={pauseRotation}
            onWheel={pauseRotation}
        >
            {/* Boot sequence overlay */}
            {!globeReady && (
                <div className="absolute inset-0 z-30">
                    <GlobeLoadingSkeleton />
                </div>
            )}

            {/* Data-update glitch flash */}
            {dataGlitch && (
                <div className="globe-data-glitch" aria-hidden="true">
                    <div className="globe-data-glitch-scanline" />
                </div>
            )}

            {/* Globe */}
            <div className={globeReady ? 'globe-reveal w-full h-full' : 'w-full h-full opacity-0'}>
            <MapGL
                ref={mapRef}
                {...viewState}
                onMove={onMove}
                style={{ width: '100%', height: '100%' }}
                mapStyle={mapStyle}
                minZoom={0}
                maxZoom={15}
                clickTolerance={5}
                interactiveLayerIds={['events-cluster-circle', 'events-cluster-count', 'events-core']}
                onClick={handleClick}
                onMouseMove={handleMouseMove}
                onDragStart={pauseRotation}
                onZoomStart={pauseRotation}
                attributionControl={false}
            >
                {/* Country polygons */}
                {countriesWithHeat && (
                    <Source id="countries" type="geojson" data={countriesWithHeat} promoteId="id">
                        <Layer
                            id="country-heat"
                            type="fill"
                            beforeId={firstSymbolId}
                            paint={{
                                'fill-color': [
                                    'case',
                                    ['>=', ['get', 'max_severity'], 7], '#E74C3C',
                                    ['>=', ['get', 'max_severity'], 4], '#F59E0B',
                                    ['>', ['get', 'max_severity'], 0], '#52A844',
                                    'rgba(0,0,0,0)',
                                ],
                                'fill-opacity': ['interpolate', ['linear'], ['zoom'],
                                    0, ['case', ['>', ['get', 'max_severity'], 0], 0.08, 0],
                                    5, 0,
                                ],
                            }}
                        />
                        <Layer
                            id="country-fills"
                            type="fill"
                            beforeId={firstSymbolId}
                            paint={{ 'fill-color': '#000000', 'fill-opacity': 0.01 }}
                        />
                    </Source>
                )}

                {/* Conflict thread arcs */}
                <Source id="thread-arcs" type="geojson" data={threadArcsGeoJSON}>
                    <Layer
                        id="thread-arcs-line"
                        type="line"
                        beforeId={firstSymbolId}
                        paint={{
                            'line-color': [
                                'case',
                                ['>=', ['get', 'severity'], 7], 'rgba(231, 76, 60, 0.3)',
                                ['>=', ['get', 'severity'], 4], 'rgba(245, 158, 11, 0.25)',
                                'rgba(82, 168, 68, 0.2)',
                            ],
                            'line-width': ['interpolate', ['linear'], ['zoom'], 0, 0.8, 6, 1.5],
                            'line-opacity': ['interpolate', ['linear'], ['zoom'], 0, 0.6, 5, 0.4, 8, 0],
                            'line-dasharray': [6, 4],
                        }}
                    />
                </Source>

                {/* Tactical grid around critical events */}
                <Source id="tactical-grid" type="geojson" data={tacticalGridGeoJSON}>
                    <Layer
                        id="tactical-grid-lines"
                        type="line"
                        beforeId={firstSymbolId}
                        paint={{
                            'line-color': '#2D5426',
                            'line-width': 0.5,
                            'line-opacity': ['interpolate', ['linear'], ['zoom'], 4, 0, 6, 0.3],
                            'line-dasharray': [4, 4],
                        }}
                    />
                </Source>

                {/* Event markers with clustering */}
                <Source
                    id="events"
                    type="geojson"
                    data={eventsGeoJSON}
                    cluster={true}
                    clusterMaxZoom={6}
                    clusterRadius={50}
                    clusterProperties={{
                        max_severity: ['max', ['get', 'severity']],
                        sum_severity: ['+', ['get', 'severity']],
                    }}
                >
                    {/* Cluster circles — visible at low zoom */}
                    <Layer
                        id="events-cluster-circle"
                        type="circle"
                        filter={['has', 'point_count']}
                        paint={{
                            'circle-radius': ['interpolate', ['linear'], ['get', 'point_count'],
                                2, 12, 10, 18, 50, 26, 200, 34],
                            'circle-color': [
                                'case',
                                ['>=', ['get', 'max_severity'], 7], 'rgba(231, 76, 60, 0.6)',
                                ['>=', ['get', 'max_severity'], 4], 'rgba(245, 158, 11, 0.5)',
                                'rgba(82, 168, 68, 0.4)',
                            ],
                            'circle-stroke-width': 1,
                            'circle-stroke-color': [
                                'case',
                                ['>=', ['get', 'max_severity'], 7], 'rgba(231, 76, 60, 0.8)',
                                ['>=', ['get', 'max_severity'], 4], 'rgba(245, 158, 11, 0.7)',
                                'rgba(82, 168, 68, 0.6)',
                            ],
                            'circle-opacity': heatmapMode ? 0 : ['interpolate', ['linear'], ['zoom'], 0, 0.8, 5, 0.6, 7, 0],
                        }}
                    />
                    {/* Cluster count labels */}
                    <Layer
                        id="events-cluster-count"
                        type="symbol"
                        filter={['has', 'point_count']}
                        layout={{
                            'text-field': '{point_count_abbreviated}',
                            'text-size': 11,
                            'text-font': ['Open Sans Bold'],
                            'text-allow-overlap': true,
                        }}
                        paint={{
                            'text-color': highContrast ? '#F2F8F0' : '#D4E8CF',
                            'text-opacity': heatmapMode ? 0 : ['interpolate', ['linear'], ['zoom'], 0, 0.9, 7, 0],
                        }}
                    />

                    {/* Heatmap layer (togglable) */}
                    <Layer
                        id="events-heatmap"
                        type="heatmap"
                        filter={['!', ['has', 'point_count']]}
                        paint={{
                            'heatmap-weight': ['interpolate', ['linear'], ['get', 'severity'], 1, 0.1, 5, 0.5, 10, 1],
                            'heatmap-intensity': ['interpolate', ['linear'], ['zoom'], 0, 0.6, 5, 1.5, 9, 3],
                            'heatmap-radius': ['interpolate', ['linear'], ['zoom'], 0, 12, 5, 25, 9, 35],
                            'heatmap-opacity': heatmapMode
                                ? ['interpolate', ['linear'], ['zoom'], 0, 0.7, 7, 0.4, 10, 0]
                                : 0,
                            'heatmap-color': [
                                'interpolate', ['linear'], ['heatmap-density'],
                                0, 'rgba(0,0,0,0)',
                                0.15, '#1A3018',
                                0.3, '#2D5426',
                                0.5, '#3D7A32',
                                0.7, '#F59E0B',
                                0.85, '#E74C3C',
                                1, '#ff5555',
                            ],
                        }}
                    />

                    {/* Individual event layers (unclustered points only) */}
                    <Layer
                        id="events-glow"
                        type="circle"
                        filter={['!', ['has', 'point_count']]}
                        paint={{
                            'circle-radius': ['interpolate', ['linear'], ['get', 'severity'],
                                1, 6, 4, 8, 7, 10],
                            'circle-color': SEVERITY_COLOR_EXPR,
                            'circle-opacity': 0,
                            'circle-blur': 1,
                        }}
                    />
                    <Layer
                        id="events-pulse"
                        type="circle"
                        filter={['!', ['has', 'point_count']]}
                        paint={{
                            'circle-radius': 6,
                            'circle-color': SEVERITY_COLOR_EXPR,
                            'circle-opacity': 0,
                            'circle-blur': 0.6,
                        }}
                    />
                    <Layer
                        id="events-core"
                        type="circle"
                        filter={['!', ['has', 'point_count']]}
                        paint={{
                            'circle-radius': ['interpolate', ['linear'], ['get', 'severity'],
                                1, 2, 4, 3, 7, 4],
                            'circle-color': SEVERITY_COLOR_EXPR,
                            'circle-opacity': ['interpolate', ['linear'], ['zoom'],
                                4, 0,
                                6, STATUS_CORE_OPACITY,
                            ],
                            'circle-stroke-color': SEVERITY_COLOR_EXPR,
                            'circle-stroke-width': STATUS_STROKE_WIDTH,
                            'circle-stroke-opacity': ['interpolate', ['linear'], ['zoom'],
                                4, 0,
                                6, ['*', 0.5, STATUS_CORE_OPACITY],
                            ],
                        }}
                    />

                    {/* Confirmed events: extra glow ring */}
                    <Layer
                        id="events-confirmed-ring"
                        type="circle"
                        filter={['all',
                            ['!', ['has', 'point_count']],
                            ['==', ['get', 'status'], 'confirmed'],
                        ]}
                        paint={{
                            'circle-radius': ['interpolate', ['linear'], ['get', 'severity'],
                                1, 5, 4, 6, 7, 8],
                            'circle-color': 'transparent',
                            'circle-stroke-color': SEVERITY_COLOR_EXPR,
                            'circle-stroke-width': 0.5,
                            'circle-stroke-opacity': ['interpolate', ['linear'], ['zoom'],
                                4, 0, 6, 0.4],
                        }}
                    />

                </Source>

                {/* Hotzone tactical cards — visible when zoomed out */}
                {hotzones.map((hz) => (
                    <HotzoneCard
                        key={hz.code}
                        country={hz.name}
                        lat={hz.lat}
                        lng={hz.lng}
                        eventCount={hz.eventCount}
                        maxSeverity={hz.maxSeverity}
                        zoom={viewState.zoom}
                        onClick={() => { flyToCountry(hz.code, hz.lat, hz.lng); onCountryCardClick?.(hz.code); }}
                    />
                ))}

                {/* Tactical reticles for critical events */}
                {criticalEvents.slice(0, 5).map((e) => (
                    <TacticalReticle key={e.id} event={e} zoom={viewState.zoom} />
                ))}
            </MapGL>
            </div>

            {/* ── OVERLAYS ─────────────────────────────────── */}

            {/* Live status indicator — top right */}
            <LiveIndicator />

            {/* Critical event alerts — top center */}
            <CriticalEventAlert onEventSelect={onEventSelect} />

            {/* Reset view button — top left, visible when zoomed in */}
            {viewState.zoom > INITIAL_VIEW.zoom + 0.5 && (
                <button
                    className="reset-view-btn"
                    onClick={(e) => { e.stopPropagation(); resetView(); }}
                    title="Reset view [R]"
                >
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="1.2">
                        <circle cx="6" cy="6" r="5" />
                        <path d="M6 3v3l2 1" />
                    </svg>
                    <span>RESET</span>
                </button>
            )}

            {/* Region quick-jump — left edge */}
            <RegionQuickJump onFlyTo={flyTo} />

            {/* Cursor coordinates — bottom left */}
            <CursorCoordinates lat={cursorCoords?.lat} lng={cursorCoords?.lng} />

            {/* Heatmap toggle + Zoom indicator — bottom right */}
            <div className="globe-bottom-right">
                <button
                    className={`heatmap-toggle${heatmapMode ? ' heatmap-toggle--active' : ''}`}
                    onClick={(e) => { e.stopPropagation(); setHeatmapMode((v) => !v); }}
                    title="Toggle heatmap [H]"
                >
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <circle cx="7" cy="7" r="6" stroke="currentColor" strokeWidth="1" opacity="0.5" />
                        <circle cx="7" cy="7" r="4" stroke="currentColor" strokeWidth="0.8" opacity="0.35" />
                        <circle cx="7" cy="7" r="2" fill="currentColor" opacity="0.6" />
                    </svg>
                    <span>HEAT</span>
                </button>
                <div className="zoom-indicator">
                    <span className="zoom-indicator-label">ZOOM</span>
                    <span className="zoom-indicator-value">{viewState.zoom.toFixed(1)}</span>
                    <div className="zoom-indicator-bar">
                        <div
                            className="zoom-indicator-fill"
                            style={{ width: `${Math.min(100, (viewState.zoom / 15) * 100)}%` }}
                        />
                    </div>
                </div>
            </div>

            {/* Timeline scrubber — above threat level */}
            <TimelineScrubber events={events} value={timelineCursor} onChange={setTimelineCursor} />

            {/* World Threat Level — bottom center */}
            <WorldThreatLevel />

            {/* Hover tooltip */}
            {hovered && (
                <div className="absolute top-12 left-4 bg-surface-1 border border-border-mid rounded px-3 py-2 z-10 max-w-xs pointer-events-none">
                    <div className="flex items-center gap-2 mb-1">
                        <span
                            className="w-2 h-2 rounded-full flex-shrink-0"
                            style={{ backgroundColor: getSeverityHex(hovered.severity) }}
                        />
                        <span className="font-mono text-xs text-text-muted">SEV {hovered.severity}</span>
                    </div>
                    <div className="text-sm text-text-primary font-sans leading-snug">{hovered.title}</div>
                    {hovered.country && (
                        <div className="font-mono text-xs text-text-muted mt-1">{hovered.country}</div>
                    )}
                </div>
            )}
        </div>
    );
}
