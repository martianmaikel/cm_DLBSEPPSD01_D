import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import ForceGraph2D from 'react-force-graph-2d';

const NODE_COLORS = {
    actor_person: '#5BBF4A',
    actor_organization: '#06B6D4',
    country: '#F59E0B',
    conflict: '#EF4444',
    event: '#A855F7',
};
const EDGE_COLORS = {
    derived: 'rgba(139,146,152,0.5)',
    manual: 'rgba(91,191,74,0.85)',
    ai: 'rgba(6,182,212,0.75)',
    live: 'rgba(139,146,152,0.35)',
};

function flagEmoji(iso) {
    if (!iso || iso.length !== 2) return '';
    const base = 0x1F1E6;
    return String.fromCodePoint(base + iso.charCodeAt(0) - 65) + String.fromCodePoint(base + iso.charCodeAt(1) - 65);
}

function colorForNode(n) {
    if (n.type === 'actor') {
        return NODE_COLORS[`actor_${n.subtype || 'person'}`] ?? NODE_COLORS.actor_person;
    }
    return NODE_COLORS[n.type] ?? '#FFFFFF';
}

function navigateFor(node) {
    if (node.type === 'actor' && node.slug) return `/actor/${node.slug}`;
    if (node.type === 'conflict' && node.slug) return `/conflict/${node.slug}`;
    if (node.type === 'country' && node.iso) return `/country/${node.iso}`;
    if (node.type === 'event' && node.slug) return `/event/${node.id.split(':')[1]}-${node.slug}`;
    return null;
}

const imageCache = new Map();
function loadImage(url) {
    if (!url) return null;
    if (imageCache.has(url)) return imageCache.get(url);
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = url;
    imageCache.set(url, img);
    return img;
}

/**
 * RelationshipGraph
 *  type: "actor"|"country"|"conflict"|"event"
 *  id:   node id
 *  height: px
 *  depth: 1 (default)
 *  mode: "local" (expandFrom) | "global" (globalSnapshot)
 */
export default function RelationshipGraph({
    type,
    id,
    depth = 1,
    height = 480,
    mode = 'local',
    initialIncludeEvents = false,
    filters = null,
}) {
    const [data, setData] = useState({ nodes: [], links: [] });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [selected, setSelected] = useState(null);
    const [includeEvents, setIncludeEvents] = useState(initialIncludeEvents);
    const containerRef = useRef(null);
    const graphRef = useRef(null);
    const [size, setSize] = useState({ w: 0, h: height });

    const endpoint = useMemo(() => {
        if (mode === 'global') {
            const params = new URLSearchParams({ include_events: includeEvents ? '1' : '0', ...(filters || {}) });
            return `/api/graph/global?${params.toString()}`;
        }
        const params = new URLSearchParams({ depth: String(depth), include_events: includeEvents ? '1' : '0', ...(filters || {}) });
        return `/api/graph/node/${type}/${id}?${params.toString()}`;
    }, [mode, type, id, depth, includeEvents, filters]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError(null);
        fetch(endpoint, { headers: { Accept: 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`)))
            .then(payload => {
                if (cancelled) return;
                const nodes = payload.nodes.map(n => ({ ...n }));
                const links = payload.edges.map(e => ({
                    source: e.from,
                    target: e.to,
                    type: e.type,
                    source_kind: e.source,
                    directed: e.directed,
                    weight: e.weight,
                }));
                setData({ nodes, links });
                setLoading(false);
            })
            .catch(e => { if (!cancelled) { setError(e.message); setLoading(false); } });
        return () => { cancelled = true; };
    }, [endpoint]);

    useEffect(() => {
        if (!containerRef.current) return;
        const ro = new ResizeObserver(entries => {
            for (const entry of entries) {
                setSize({ w: entry.contentRect.width, h: height });
            }
        });
        ro.observe(containerRef.current);
        return () => ro.disconnect();
    }, [height]);

    const nodePaint = useCallback((node, ctx, globalScale) => {
        const r = node.type === 'event' ? 4 : node.type === 'country' ? 10 : 8;
        ctx.save();

        // Node shape
        if (node.type === 'conflict') {
            ctx.translate(node.x, node.y);
            ctx.rotate(Math.PI / 4);
            ctx.fillStyle = colorForNode(node);
            ctx.fillRect(-r, -r, r * 2, r * 2);
            ctx.rotate(-Math.PI / 4);
            ctx.translate(-node.x, -node.y);
        } else if (node.type === 'event') {
            ctx.beginPath();
            ctx.moveTo(node.x, node.y - r);
            ctx.lineTo(node.x + r, node.y + r);
            ctx.lineTo(node.x - r, node.y + r);
            ctx.closePath();
            ctx.fillStyle = colorForNode(node);
            ctx.fill();
        } else if (node.type === 'actor' && node.image_url) {
            const img = loadImage(node.image_url);
            if (img && img.complete && img.naturalWidth > 0) {
                ctx.beginPath();
                ctx.arc(node.x, node.y, r + 2, 0, 2 * Math.PI, false);
                ctx.closePath();
                ctx.save();
                ctx.clip();
                ctx.drawImage(img, node.x - (r + 2), node.y - (r + 2), (r + 2) * 2, (r + 2) * 2);
                ctx.restore();
                ctx.lineWidth = 1.5;
                ctx.strokeStyle = colorForNode(node);
                ctx.stroke();
            } else {
                ctx.beginPath();
                ctx.arc(node.x, node.y, r, 0, 2 * Math.PI, false);
                ctx.fillStyle = colorForNode(node);
                ctx.fill();
            }
        } else {
            ctx.beginPath();
            ctx.arc(node.x, node.y, r, 0, 2 * Math.PI, false);
            ctx.fillStyle = colorForNode(node);
            ctx.fill();
        }

        // Country flag text
        if (node.type === 'country' && node.flag) {
            ctx.font = `${Math.max(10, r)}px sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#000';
            ctx.fillText(node.flag, node.x, node.y);
        }

        // Label (only when zoomed in enough)
        const label = node.label ?? '';
        if (globalScale >= 1.2 && label) {
            const fontSize = 11 / globalScale;
            ctx.font = `${fontSize}px Rajdhani, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            ctx.fillStyle = '#F1F0EC';
            ctx.fillText(label.length > 28 ? label.slice(0, 27) + '…' : label, node.x, node.y + r + 2);
        }

        ctx.restore();
    }, []);

    const linkColor = useCallback(link => EDGE_COLORS[link.source_kind] ?? 'rgba(139,146,152,0.4)', []);
    const linkWidth = useCallback(link => {
        if (link.source_kind === 'manual') return 2;
        if (link.source_kind === 'ai') return 1.5;
        return 1;
    }, []);

    const handleNodeClick = useCallback(node => {
        setSelected(node);
        const href = navigateFor(node);
        if (href) router.visit(href);
    }, []);

    const handleNodeRightClick = useCallback(node => {
        // expand one more hop — fetch neighbors and merge
        if (!node) return;
        const [nt, nid] = node.id.split(':');
        const params = new URLSearchParams({ depth: '1', include_events: includeEvents ? '1' : '0' });
        fetch(`/api/graph/node/${nt}/${nid}?${params.toString()}`, { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(payload => {
                setData(prev => {
                    const nodeMap = new Map(prev.nodes.map(n => [n.id, n]));
                    for (const n of payload.nodes) {
                        if (!nodeMap.has(n.id)) {
                            nodeMap.set(n.id, { ...n });
                        }
                    }
                    const keyOf = e => `${typeof e.source === 'object' ? e.source.id : e.source}__${typeof e.target === 'object' ? e.target.id : e.target}__${e.type}__${e.source_kind}`;
                    const linkKeys = new Set(prev.links.map(keyOf));
                    const newLinks = [...prev.links];
                    for (const e of payload.edges) {
                        const link = { source: e.from, target: e.to, type: e.type, source_kind: e.source, directed: e.directed, weight: e.weight };
                        if (!linkKeys.has(keyOf(link))) {
                            newLinks.push(link);
                            linkKeys.add(keyOf(link));
                        }
                    }
                    return { nodes: Array.from(nodeMap.values()), links: newLinks };
                });
            })
            .catch(err => console.warn('expand failed', err));
    }, [includeEvents]);

    return (
        <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
            <div className="flex items-center justify-between px-4 py-2 border-b border-border-mid bg-surface-2">
                <div className="font-display text-sm tracking-widest uppercase text-green-bright">RELATIONSHIPS</div>
                <div className="flex items-center gap-3 font-mono text-[10px] tracking-widest uppercase text-text-muted">
                    <label className="flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" checked={includeEvents} onChange={e => setIncludeEvents(e.target.checked)} className="accent-green-base" />
                        <span>Show events</span>
                    </label>
                    <button onClick={() => graphRef.current?.zoomToFit?.(400, 40)} className="hover:text-green-bright">
                        Fit
                    </button>
                </div>
            </div>
            <div ref={containerRef} className="relative" style={{ height }}>
                {loading && (
                    <div className="absolute inset-0 flex items-center justify-center font-mono text-xs text-text-muted">
                        Loading graph…
                    </div>
                )}
                {error && (
                    <div className="absolute inset-0 flex items-center justify-center font-mono text-xs text-red-bright">
                        {error}
                    </div>
                )}
                {!loading && !error && data.nodes.length === 0 && (
                    <div className="absolute inset-0 flex items-center justify-center font-mono text-xs text-text-muted">
                        No relationships for this entity yet.
                    </div>
                )}
                {size.w > 0 && (
                    <ForceGraph2D
                        ref={graphRef}
                        graphData={data}
                        width={size.w}
                        height={size.h}
                        backgroundColor="transparent"
                        nodeCanvasObject={nodePaint}
                        nodeRelSize={6}
                        linkColor={linkColor}
                        linkWidth={linkWidth}
                        linkDirectionalArrowLength={l => l.directed ? 4 : 0}
                        linkDirectionalArrowRelPos={1}
                        linkLabel={l => `${l.type} (${l.source_kind})`}
                        onNodeClick={handleNodeClick}
                        onNodeRightClick={handleNodeRightClick}
                        cooldownTicks={80}
                        d3AlphaDecay={0.03}
                    />
                )}
            </div>
            <div className="px-4 py-2 border-t border-border-mid bg-surface-2 font-mono text-[10px] tracking-widest uppercase text-text-dim flex items-center gap-4 flex-wrap">
                <span><span className="inline-block w-2 h-2 rounded-full align-middle mr-1" style={{ background: NODE_COLORS.actor_person }}></span> Person</span>
                <span><span className="inline-block w-2 h-2 rounded-sm align-middle mr-1" style={{ background: NODE_COLORS.actor_organization }}></span> Org</span>
                <span><span className="inline-block w-2 h-2 rounded-full align-middle mr-1" style={{ background: NODE_COLORS.country }}></span> Country</span>
                <span><span className="inline-block w-2 h-2 align-middle mr-1 rotate-45" style={{ background: NODE_COLORS.conflict }}></span> Conflict</span>
                <span className="ml-auto">Right-click node = expand · Click = open</span>
            </div>
        </div>
    );
}
