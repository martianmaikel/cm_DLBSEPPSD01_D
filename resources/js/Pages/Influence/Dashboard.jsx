import { useEffect, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

const METRICS = [
    {
        key: 'degree',
        label: 'Degree',
        blurb: 'Direct connections — how many other actors this one is directly linked to.',
    },
    {
        key: 'betweenness',
        label: 'Betweenness',
        blurb: 'Bridge role — how often this actor lies on the shortest path between two others.',
    },
    {
        key: 'pagerank',
        label: 'PageRank',
        blurb: 'Influence by association — importance weighted by the importance of connected actors.',
    },
];

const LIMIT = 50;

export default function Dashboard() {
    const [metric, setMetric] = useState('degree');
    const [nodes, setNodes] = useState([]);
    const [status, setStatus] = useState('loading'); // loading | ready | error

    useEffect(() => {
        const controller = new AbortController();
        setStatus('loading');

        fetch(`/api/graph/centrality?metric=${metric}&limit=${LIMIT}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                setNodes(Array.isArray(data.nodes) ? data.nodes : []);
                setStatus('ready');
            })
            .catch(err => {
                if (err.name !== 'AbortError') setStatus('error');
            });

        return () => controller.abort();
    }, [metric]);

    const active = METRICS.find(m => m.key === metric);

    return (
        <AppLayout breadcrumbs={[{ label: 'Influence' }]}>
            <div className="px-4 md:px-6 py-6 max-w-[1100px] mx-auto">
                <div className="mb-5">
                    <h1 className="font-display text-3xl md:text-4xl tracking-wider text-green-bright">
                        ACTOR INFLUENCE
                    </h1>
                    <p className="font-mono text-xs tracking-widest uppercase text-text-muted mt-1">
                        Network analytics · centrality ranking
                    </p>
                </div>

                {/* Metric switcher */}
                <div className="flex flex-wrap gap-2 mb-3">
                    {METRICS.map(m => (
                        <button
                            key={m.key}
                            type="button"
                            onClick={() => setMetric(m.key)}
                            className={`font-mono text-xs tracking-widest uppercase px-3 py-1.5 rounded border transition-colors ${
                                m.key === metric
                                    ? 'bg-green-base/20 border-green-base text-green-bright'
                                    : 'bg-surface-1 border-border-mid text-text-secondary hover:border-green-base/50'
                            }`}
                        >
                            {m.label}
                        </button>
                    ))}
                </div>

                <p className="font-mono text-xs text-text-muted mb-5">
                    <span className="text-green-bright">›</span> {active?.blurb}
                </p>

                <div className="bg-surface-1 border border-border-mid rounded">
                    {/* Header row */}
                    <div className="grid grid-cols-[2.5rem_1fr_6rem_4rem_8rem] gap-3 px-4 py-2 border-b border-border-subtle font-mono text-[10px] tracking-widest uppercase text-text-dim">
                        <div>#</div>
                        <div>Actor</div>
                        <div>Type</div>
                        <div className="text-right">Score</div>
                        <div>Rank</div>
                    </div>

                    {status === 'loading' && <SkeletonRows />}

                    {status === 'error' && (
                        <div className="px-4 py-8 font-mono text-xs text-amber-bright">
                            Could not load centrality data. Switch metric to retry.
                        </div>
                    )}

                    {status === 'ready' && nodes.length === 0 && (
                        <div className="px-4 py-8 font-mono text-xs text-text-muted">
                            No actor relationships available yet.
                        </div>
                    )}

                    {status === 'ready' &&
                        nodes.map((node, i) => (
                            <Row key={node.id} rank={i + 1} node={node} metric={metric} />
                        ))}
                </div>
            </div>
        </AppLayout>
    );
}

function Row({ rank, node, metric }) {
    const score = Number(node[metric] ?? 0);
    const pct = Math.max(0, Math.min(100, score * 100));

    return (
        <div className="grid grid-cols-[2.5rem_1fr_6rem_4rem_8rem] gap-3 px-4 py-2 border-b border-border-subtle/50 items-center hover:bg-surface-2/40">
            <div className="font-mono text-xs text-text-dim">{rank}</div>
            <div className="text-sm text-text-primary truncate" title={node.label}>
                {node.label}
            </div>
            <div className="font-mono text-[10px] tracking-wider uppercase text-text-muted truncate">
                {node.type ?? '—'}
            </div>
            <div className="font-mono text-xs text-text-secondary text-right tabular-nums">
                {score.toFixed(3)}
            </div>
            <div className="h-1.5 bg-surface-2 rounded overflow-hidden">
                <div className="h-full bg-green-base" style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

function SkeletonRows() {
    return (
        <>
            {Array.from({ length: 8 }).map((_, i) => (
                <div
                    key={i}
                    className="grid grid-cols-[2.5rem_1fr_6rem_4rem_8rem] gap-3 px-4 py-2 border-b border-border-subtle/50 items-center"
                >
                    <div className="h-3 bg-surface-2 rounded animate-pulse" />
                    <div className="h-3 bg-surface-2 rounded animate-pulse" />
                    <div className="h-3 bg-surface-2 rounded animate-pulse" />
                    <div className="h-3 bg-surface-2 rounded animate-pulse" />
                    <div className="h-1.5 bg-surface-2 rounded animate-pulse" />
                </div>
            ))}
        </>
    );
}
