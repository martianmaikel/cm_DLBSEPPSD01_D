import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import RelationshipGraph from '../../Components/Graph/RelationshipGraph';

const NODE_TYPE_OPTIONS = [
    { value: 'actor', label: 'Actors' },
    { value: 'country', label: 'Countries' },
    { value: 'conflict', label: 'Conflicts' },
];

export default function Index() {
    const [nodeTypes, setNodeTypes] = useState(['actor', 'country', 'conflict']);
    const [topActors, setTopActors] = useState(40);
    const [topConflicts, setTopConflicts] = useState(30);
    const [topCountries, setTopCountries] = useState(40);

    const filters = {};
    nodeTypes.forEach((t, i) => { filters[`node_types[${i}]`] = t; });
    filters.top_actors = topActors;
    filters.top_conflicts = topConflicts;
    filters.top_countries = topCountries;

    function toggleType(t) {
        setNodeTypes(prev => prev.includes(t) ? prev.filter(x => x !== t) : [...prev, t]);
    }

    return (
        <AppLayout breadcrumbs={[{ label: 'Graph' }]}>
            <div className="px-4 md:px-6 py-6 max-w-[1600px] mx-auto">
                <div className="flex items-end justify-between flex-wrap gap-3 mb-5">
                    <div>
                        <h1 className="font-display text-3xl md:text-4xl tracking-wider text-green-bright">
                            RELATIONSHIP GRAPH
                        </h1>
                        <p className="font-mono text-xs tracking-widest uppercase text-text-muted mt-1">
                            Actors · Countries · Conflicts — click to open, right-click to expand
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-4">
                    <aside className="bg-surface-1 border border-border-mid rounded p-4 space-y-4 h-fit">
                        <div>
                            <div className="font-mono text-[10px] tracking-widest uppercase text-text-muted mb-2">Node types</div>
                            <div className="flex flex-col gap-1">
                                {NODE_TYPE_OPTIONS.map(o => (
                                    <label key={o.value} className="flex items-center gap-2 font-mono text-xs text-text-secondary cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={nodeTypes.includes(o.value)}
                                            onChange={() => toggleType(o.value)}
                                            className="accent-green-base"
                                        />
                                        {o.label}
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div>
                            <div className="font-mono text-[10px] tracking-widest uppercase text-text-muted mb-2">Top N</div>
                            <label className="block font-mono text-[10px] text-text-muted">Actors: {topActors}</label>
                            <input type="range" min="10" max="150" step="5" value={topActors} onChange={e => setTopActors(Number(e.target.value))} className="w-full accent-green-base" />
                            <label className="block font-mono text-[10px] text-text-muted mt-2">Conflicts: {topConflicts}</label>
                            <input type="range" min="5" max="80" step="5" value={topConflicts} onChange={e => setTopConflicts(Number(e.target.value))} className="w-full accent-green-base" />
                            <label className="block font-mono text-[10px] text-text-muted mt-2">Countries: {topCountries}</label>
                            <input type="range" min="10" max="120" step="5" value={topCountries} onChange={e => setTopCountries(Number(e.target.value))} className="w-full accent-green-base" />
                        </div>
                        <div className="pt-3 border-t border-border-subtle font-mono text-[10px] tracking-widest uppercase text-text-dim space-y-1">
                            <div><span className="text-green-bright">green</span> = manual edge</div>
                            <div><span className="text-blue-bright">blue</span> = ai edge</div>
                            <div><span className="text-text-muted">grey</span> = derived edge</div>
                        </div>
                    </aside>

                    <div>
                        <RelationshipGraph
                            mode="global"
                            type="global"
                            id="global"
                            height={720}
                            filters={filters}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
