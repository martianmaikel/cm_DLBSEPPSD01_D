import { useEffect, useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { METRICS } from './metrics';

export default function Compare() {
    const [nodes, setNodes] = useState([]);
    const [status, setStatus] = useState('loading'); // loading | ready | error
    const [aId, setAId] = useState('');
    const [bId, setBId] = useState('');

    useEffect(() => {
        const controller = new AbortController();
        setStatus('loading');

        fetch('/api/graph/centrality?metric=all&limit=200', {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                const list = Array.isArray(data.nodes) ? data.nodes : [];
                setNodes(list);
                if (list.length) {
                    setAId(list[0].id);
                    setBId(list[1] ? list[1].id : list[0].id);
                }
                setStatus('ready');
            })
            .catch(err => {
                if (err.name !== 'AbortError') setStatus('error');
            });

        return () => controller.abort();
    }, []);

    const actorA = useMemo(() => nodes.find(n => n.id === aId) ?? null, [nodes, aId]);
    const actorB = useMemo(() => nodes.find(n => n.id === bId) ?? null, [nodes, bId]);

    return (
        <AppLayout breadcrumbs={[{ label: 'Influence' }, { label: 'Compare' }]}>
            <div className="px-4 md:px-6 py-6 max-w-[900px] mx-auto">
                <div className="mb-5">
                    <h1 className="font-display text-3xl md:text-4xl tracking-wider text-green-bright">
                        COMPARE ACTORS
                    </h1>
                    <p className="font-mono text-xs tracking-widest uppercase text-text-muted mt-1">
                        Side-by-side centrality across all three measures
                    </p>
                </div>

                {status === 'loading' && (
                    <div className="px-4 py-8 font-mono text-xs text-text-muted">Loading…</div>
                )}

                {status === 'error' && (
                    <div className="px-4 py-8 font-mono text-xs text-amber-bright">
                        Could not load centrality data.
                    </div>
                )}

                {status === 'ready' && nodes.length === 0 && (
                    <div className="px-4 py-8 font-mono text-xs text-text-muted">
                        No actor relationships available yet.
                    </div>
                )}

                {status === 'ready' && nodes.length > 0 && (
                    <>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                            <ActorSelect label="Actor A" value={aId} onChange={setAId} nodes={nodes} />
                            <ActorSelect label="Actor B" value={bId} onChange={setBId} nodes={nodes} />
                        </div>

                        <div className="bg-surface-1 border border-border-mid rounded">
                            <div className="grid grid-cols-[8rem_1fr_1fr] gap-3 px-4 py-2 border-b border-border-subtle font-mono text-[10px] tracking-widest uppercase text-text-dim">
                                <div>Metric</div>
                                <div className="truncate" title={actorA?.label}>{actorA?.label ?? 'Actor A'}</div>
                                <div className="truncate" title={actorB?.label}>{actorB?.label ?? 'Actor B'}</div>
                            </div>

                            {METRICS.map(m => {
                                const av = Number(actorA?.[m.key] ?? 0);
                                const bv = Number(actorB?.[m.key] ?? 0);
                                return (
                                    <div
                                        key={m.key}
                                        className="grid grid-cols-[8rem_1fr_1fr] gap-3 px-4 py-3 border-b border-border-subtle/50 items-center"
                                    >
                                        <div
                                            className="font-mono text-[10px] tracking-wider uppercase text-text-secondary"
                                            title={m.blurb}
                                        >
                                            {m.label}
                                        </div>
                                        <MetricCell value={av} leads={av > bv} />
                                        <MetricCell value={bv} leads={bv > av} />
                                    </div>
                                );
                            })}
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function ActorSelect({ label, value, onChange, nodes }) {
    return (
        <label className="block">
            <span className="block font-mono text-[10px] tracking-widest uppercase text-text-muted mb-1">
                {label}
            </span>
            <select
                value={value}
                onChange={e => onChange(e.target.value)}
                className="w-full bg-surface-1 border border-border-mid rounded px-3 py-2 font-mono text-xs text-text-primary"
            >
                {nodes.map(n => (
                    <option key={n.id} value={n.id}>
                        {n.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

function MetricCell({ value, leads }) {
    const pct = Math.max(0, Math.min(100, value * 100));
    return (
        <div>
            <div className={`font-mono text-xs tabular-nums mb-1 ${leads ? 'text-green-bright' : 'text-text-secondary'}`}>
                {value.toFixed(3)}
            </div>
            <div className="h-1.5 bg-surface-2 rounded overflow-hidden">
                <div className={`h-full ${leads ? 'bg-green-base' : 'bg-text-dim'}`} style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}
