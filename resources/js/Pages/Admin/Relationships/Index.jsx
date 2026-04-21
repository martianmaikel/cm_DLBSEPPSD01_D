import { useMemo, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

const NODE_TYPES = ['actor', 'country', 'conflict', 'event'];
const SOURCE_STYLES = {
    derived: 'border-text-muted text-text-muted',
    manual: 'border-green-base text-green-bright',
    ai: 'border-blue-intel text-blue-bright',
};

function EntitySelect({ type, value, onChange, suggestions }) {
    if (type === 'country') {
        return (
            <select value={value} onChange={e => onChange(e.target.value)} className="w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm">
                <option value="">— pick country —</option>
                {suggestions.country.map(c => (
                    <option key={c.id} value={c.id}>{c.label}</option>
                ))}
            </select>
        );
    }
    if (type === 'actor' || type === 'conflict') {
        const list = suggestions[type] || [];
        return (
            <select value={value} onChange={e => onChange(e.target.value)} className="w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm">
                <option value="">— pick {type} —</option>
                {list.map(c => (
                    <option key={c.id} value={c.id}>{c.label}</option>
                ))}
            </select>
        );
    }
    return (
        <input value={value} onChange={e => onChange(e.target.value)} placeholder={`${type} id`} className="w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm" />
    );
}

export default function Index({ relationships, filters = {}, relationTypes, actorSuggestions, conflictSuggestions, countrySuggestions }) {
    const suggestions = { actor: actorSuggestions, conflict: conflictSuggestions, country: countrySuggestions };

    const { data, setData, post, processing, errors, reset } = useForm({
        from_type: 'actor',
        from_id: '',
        to_type: 'country',
        to_id: '',
        relation_type: '',
        directed: true,
        weight: '',
        active_from: '',
        active_to: '',
    });

    const availableRelations = useMemo(() => {
        const key = `${data.from_type}.${data.to_type}`;
        return relationTypes[key] ?? [];
    }, [data.from_type, data.to_type, relationTypes]);

    function submit(e) {
        e.preventDefault();
        post('/admin/relationships', {
            onSuccess: () => reset('from_id', 'to_id', 'relation_type', 'weight', 'active_from', 'active_to'),
        });
    }

    function del(rel) {
        if (!confirm('Delete this relationship?')) return;
        router.delete(`/admin/relationships/${rel.id}`);
    }

    function rebuild() {
        if (!confirm('Rebuild all derived relationships now?')) return;
        router.post('/admin/relationships/rebuild-derived', {}, { preserveScroll: true });
    }

    function setFilter(k, v) {
        router.get('/admin/relationships', { ...filters, [k]: v || undefined }, { preserveState: true, replace: true });
    }

    const items = relationships?.data ?? [];
    const links = relationships?.links ?? [];

    const breadcrumbs = [
        { label: 'Dashboard', href: '/admin' },
        { label: 'Relationships' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                <div className="flex items-end justify-between flex-wrap gap-3">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright">RELATIONSHIPS</h1>
                    <button onClick={rebuild} className="font-mono text-xs tracking-widest uppercase px-3 py-2 border border-blue-intel text-blue-bright hover:bg-blue-intel/20 transition-colors rounded">
                        Rebuild derived
                    </button>
                </div>

                <form onSubmit={submit} className="bg-surface-1 border border-border-mid rounded p-4 space-y-3">
                    <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
                        <div>
                            <label className="block font-mono text-[10px] tracking-widest uppercase text-text-muted mb-1">From type</label>
                            <select value={data.from_type} onChange={e => { setData('from_type', e.target.value); setData('from_id', ''); }} className="w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm">
                                {NODE_TYPES.map(t => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block font-mono text-[10px] tracking-widest uppercase text-text-muted mb-1">From</label>
                            <EntitySelect type={data.from_type} value={data.from_id} onChange={v => setData('from_id', v)} suggestions={suggestions} />
                        </div>
                        <div>
                            <label className="block font-mono text-[10px] tracking-widest uppercase text-text-muted mb-1">To type</label>
                            <select value={data.to_type} onChange={e => { setData('to_type', e.target.value); setData('to_id', ''); setData('relation_type', ''); }} className="w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm">
                                {NODE_TYPES.map(t => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block font-mono text-[10px] tracking-widest uppercase text-text-muted mb-1">To</label>
                            <EntitySelect type={data.to_type} value={data.to_id} onChange={v => setData('to_id', v)} suggestions={suggestions} />
                        </div>
                        <div>
                            <label className="block font-mono text-[10px] tracking-widest uppercase text-text-muted mb-1">Relation</label>
                            <select value={data.relation_type} onChange={e => setData('relation_type', e.target.value)} className="w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm">
                                <option value="">— pick —</option>
                                {availableRelations.map(r => <option key={r} value={r}>{r}</option>)}
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                        <label className="flex items-center gap-2 font-mono text-xs text-text-muted">
                            <input type="checkbox" checked={data.directed} onChange={e => setData('directed', e.target.checked)} /> Directed
                        </label>
                        <input type="number" step="0.01" min="0" max="1" value={data.weight} onChange={e => setData('weight', e.target.value)} placeholder="weight 0–1" className="bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm" />
                        <input type="date" value={data.active_from} onChange={e => setData('active_from', e.target.value)} className="bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm" />
                        <input type="date" value={data.active_to} onChange={e => setData('active_to', e.target.value)} className="bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm" />
                        <button type="submit" disabled={processing || !data.from_id || !data.to_id || !data.relation_type} className="font-mono text-xs tracking-widest uppercase py-2 border border-green-base text-green-bright hover:bg-green-dim disabled:opacity-40 transition-colors rounded">
                            {processing ? 'Saving…' : 'Add relation'}
                        </button>
                    </div>
                    {Object.keys(errors).length > 0 && (
                        <p className="font-mono text-xs text-red-bright">{Object.values(errors).join(' · ')}</p>
                    )}
                </form>

                <div className="flex gap-2 flex-wrap font-mono text-xs tracking-widest uppercase">
                    {['derived', 'manual', 'ai'].map(s => (
                        <button key={s} onClick={() => setFilter('source', filters.source === s ? '' : s)}
                            className={`px-3 py-1.5 border rounded ${filters.source === s ? SOURCE_STYLES[s] : 'border-border-mid text-text-muted'}`}>
                            {s}
                        </button>
                    ))}
                </div>

                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid bg-surface-2">
                                {['From', 'Relation', 'To', 'Source', 'Weight', 'Window', 'Actions'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-4 py-3">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr><td colSpan={7} className="px-4 py-8 text-center font-mono text-sm text-text-muted">No relationships.</td></tr>
                            ) : items.map(rel => (
                                <tr key={rel.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-2 font-mono text-xs">
                                        <span className="text-text-dim">{rel.from_type}</span> · <span className="text-text-primary">{rel.from_label}</span>
                                    </td>
                                    <td className="px-4 py-2 font-mono text-xs text-green-bright">
                                        {rel.relation_type} {rel.directed ? '→' : '↔'}
                                    </td>
                                    <td className="px-4 py-2 font-mono text-xs">
                                        <span className="text-text-dim">{rel.to_type}</span> · <span className="text-text-primary">{rel.to_label}</span>
                                    </td>
                                    <td className="px-4 py-2">
                                        <span className={`inline-block font-mono text-[10px] tracking-widest uppercase px-2 py-0.5 border rounded ${SOURCE_STYLES[rel.source]}`}>
                                            {rel.source}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2 font-mono text-xs text-text-secondary">{rel.weight ?? '—'}</td>
                                    <td className="px-4 py-2 font-mono text-[10px] text-text-dim">
                                        {rel.active_from ? rel.active_from.slice(0, 10) : '∞'} → {rel.active_to ? rel.active_to.slice(0, 10) : '∞'}
                                    </td>
                                    <td className="px-4 py-2">
                                        <button onClick={() => del(rel)} className="font-mono text-xs text-red-bright hover:underline">delete</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {links.length > 3 && (
                    <div className="flex gap-2 flex-wrap">
                        {links.map((link, i) => (
                            <Link key={i} href={link.url || '#'}
                                className={`font-mono text-xs px-3 py-1.5 border rounded ${
                                    link.active
                                        ? 'border-green-base text-green-bright'
                                        : link.url
                                            ? 'border-border-mid text-text-muted hover:border-border-active'
                                            : 'border-border-subtle text-text-dim opacity-40 pointer-events-none'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
