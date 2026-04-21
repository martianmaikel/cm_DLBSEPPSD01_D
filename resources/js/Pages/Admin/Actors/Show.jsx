import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import ActorTypeBadge from '../../../Components/ActorTypeBadge';
import EnrichmentStatusBadge from '../../../Components/EnrichmentStatusBadge';
import SeverityBadge from '../../../Components/SeverityBadge';

const ORG_TYPES = [
    '', 'government', 'military', 'militia', 'armed_group', 'political_party',
    'terrorist_group', 'intelligence_agency', 'ngo', 'international_body',
];
const STATUS_OPTIONS = ['active', 'inactive', 'deceased', 'dissolved', 'unknown'];

export default function Show({ actor, events = [] }) {
    const [mergeTargetId, setMergeTargetId] = useState('');
    const [aliasDraft, setAliasDraft] = useState('');

    const { data, setData, put, processing, errors } = useForm({
        canonical_name: actor.canonical_name || '',
        aliases: actor.aliases || [],
        country: actor.country || '',
        region: actor.region || '',
        summary_short: actor.summary_short || '',
        summary_long: actor.summary_long || '',
        relevance_summary: actor.relevance_summary || '',
        categories: actor.categories || [],
        status: actor.status || 'unknown',
        confidence: actor.confidence ?? '',
        image_url: actor.image_url || '',
        full_name: actor.full_name || '',
        role_title: actor.role_title || '',
        birth_year: actor.birth_year ?? '',
        death_year: actor.death_year ?? '',
        nationality: actor.nationality || '',
        org_type: actor.org_type || '',
        founded_year: actor.founded_year ?? '',
        dissolved_year: actor.dissolved_year ?? '',
        headquarters_country: actor.headquarters_country || '',
    });

    function submit(e) {
        e.preventDefault();
        const payload = { ...data };
        ['confidence', 'birth_year', 'death_year', 'founded_year', 'dissolved_year'].forEach(k => {
            if (payload[k] === '' || payload[k] === null) payload[k] = null;
            else payload[k] = Number(payload[k]);
        });
        if (!payload.country) payload.country = null;
        if (!payload.nationality) payload.nationality = null;
        if (!payload.headquarters_country) payload.headquarters_country = null;
        if (!payload.org_type) payload.org_type = null;
        put(`/admin/actors/${actor.id}`, { data: payload, preserveScroll: true });
    }

    function reenrich() {
        router.post(`/admin/actors/${actor.id}/reenrich`, {}, { preserveScroll: true });
    }

    function merge(e) {
        e.preventDefault();
        if (!mergeTargetId) return;
        if (!confirm('Merge this actor into the target? This will delete the current record.')) return;
        router.post(`/admin/actors/${actor.id}/merge`, { target_id: mergeTargetId });
    }

    function addAlias() {
        const v = aliasDraft.trim();
        if (!v) return;
        if (data.aliases.includes(v)) return;
        setData('aliases', [...data.aliases, v]);
        setAliasDraft('');
    }

    function removeAlias(i) {
        setData('aliases', data.aliases.filter((_, idx) => idx !== i));
    }

    const inputClass = 'w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors';
    const labelClass = 'block font-mono text-xs tracking-widest uppercase text-text-muted mb-1';

    const breadcrumbs = [
        { label: 'Dashboard', href: '/admin' },
        { label: 'Actors', href: '/admin/actors' },
        { label: actor.canonical_name },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                <div className="flex items-start justify-between flex-wrap gap-3">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="font-display text-3xl tracking-wider text-green-bright">
                                {actor.canonical_name.toUpperCase()}
                            </h1>
                            <ActorTypeBadge type={actor.actor_type} />
                            <EnrichmentStatusBadge status={actor.enrichment_status} />
                        </div>
                        <p className="font-mono text-xs text-text-dim mt-1">
                            {actor.event_count ?? 0} events · {actor.mention_count ?? 0} mentions
                            {actor.enrichment_mode_used && ` · enriched via ${actor.enrichment_mode_used}`}
                            {actor.enriched_at && ` · ${new Date(actor.enriched_at).toISOString().slice(0, 10)}`}
                        </p>
                    </div>
                    <button
                        onClick={reenrich}
                        className="font-mono text-xs tracking-widest uppercase px-3 py-2 border border-blue-intel text-blue-bright hover:bg-blue-intel/20 transition-colors rounded"
                    >
                        Re-enrich
                    </button>
                </div>

                <form onSubmit={submit} className="bg-surface-1 border border-border-mid rounded p-5 space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>Canonical name</label>
                            <input className={inputClass} value={data.canonical_name} onChange={e => setData('canonical_name', e.target.value)} required />
                            {errors.canonical_name && <p className="mt-1 font-mono text-xs text-red-bright">{errors.canonical_name}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Status</label>
                            <select className={inputClass} value={data.status} onChange={e => setData('status', e.target.value)}>
                                {STATUS_OPTIONS.map(s => <option key={s} value={s}>{s}</option>)}
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className={labelClass}>Summary (short)</label>
                        <textarea className={`${inputClass} h-16 resize-y`} value={data.summary_short} onChange={e => setData('summary_short', e.target.value)} />
                    </div>
                    <div>
                        <label className={labelClass}>Summary (long)</label>
                        <textarea className={`${inputClass} h-28 resize-y`} value={data.summary_long} onChange={e => setData('summary_long', e.target.value)} />
                    </div>
                    <div>
                        <label className={labelClass}>Relevance summary</label>
                        <textarea className={`${inputClass} h-20 resize-y`} value={data.relevance_summary} onChange={e => setData('relevance_summary', e.target.value)} />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className={labelClass}>Country (ISO2)</label>
                            <input className={inputClass} value={data.country} onChange={e => setData('country', e.target.value.toUpperCase())} maxLength={2} />
                        </div>
                        <div>
                            <label className={labelClass}>Region</label>
                            <input className={inputClass} value={data.region} onChange={e => setData('region', e.target.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Confidence (1–10)</label>
                            <input type="number" className={inputClass} value={data.confidence} onChange={e => setData('confidence', e.target.value)} min={1} max={10} />
                        </div>
                    </div>

                    {actor.actor_type === 'person' ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-border-subtle">
                            <div>
                                <label className={labelClass}>Full name</label>
                                <input className={inputClass} value={data.full_name} onChange={e => setData('full_name', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Role / Title</label>
                                <input className={inputClass} value={data.role_title} onChange={e => setData('role_title', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Birth year</label>
                                <input type="number" className={inputClass} value={data.birth_year} onChange={e => setData('birth_year', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Death year</label>
                                <input type="number" className={inputClass} value={data.death_year} onChange={e => setData('death_year', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Nationality (ISO2)</label>
                                <input className={inputClass} value={data.nationality} onChange={e => setData('nationality', e.target.value.toUpperCase())} maxLength={2} />
                            </div>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-border-subtle">
                            <div>
                                <label className={labelClass}>Org type</label>
                                <select className={inputClass} value={data.org_type} onChange={e => setData('org_type', e.target.value)}>
                                    {ORG_TYPES.map(o => <option key={o} value={o}>{o || '—'}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className={labelClass}>HQ country (ISO2)</label>
                                <input className={inputClass} value={data.headquarters_country} onChange={e => setData('headquarters_country', e.target.value.toUpperCase())} maxLength={2} />
                            </div>
                            <div>
                                <label className={labelClass}>Founded year</label>
                                <input type="number" className={inputClass} value={data.founded_year} onChange={e => setData('founded_year', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Dissolved year</label>
                                <input type="number" className={inputClass} value={data.dissolved_year} onChange={e => setData('dissolved_year', e.target.value)} />
                            </div>
                        </div>
                    )}

                    <div className="pt-3 border-t border-border-subtle">
                        <label className={labelClass}>Aliases</label>
                        <div className="flex gap-2 mb-2 flex-wrap">
                            {data.aliases.map((a, i) => (
                                <span key={i} className="font-mono text-xs text-blue-bright bg-blue-intel/10 border border-blue-intel/30 rounded px-2 py-1 flex items-center gap-2">
                                    {a}
                                    <button type="button" onClick={() => removeAlias(i)} className="text-red-bright hover:text-red-alert">&times;</button>
                                </span>
                            ))}
                            {data.aliases.length === 0 && <span className="font-mono text-xs text-text-dim">No aliases yet</span>}
                        </div>
                        <div className="flex gap-2">
                            <input
                                className={inputClass}
                                value={aliasDraft}
                                onChange={e => setAliasDraft(e.target.value)}
                                placeholder="Add alias and press Enter"
                                onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); addAlias(); } }}
                            />
                            <button type="button" onClick={addAlias} className="font-mono text-xs tracking-widest uppercase px-3 py-2 border border-border-mid rounded hover:border-green-base hover:text-green-bright transition-colors">
                                Add
                            </button>
                        </div>
                    </div>

                    <div className="flex gap-3 pt-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="font-mono text-xs tracking-widest uppercase px-5 py-2.5 border border-green-base text-green-bright hover:bg-green-dim disabled:opacity-40 transition-colors rounded"
                        >
                            {processing ? 'Saving…' : 'Save changes'}
                        </button>
                    </div>
                </form>

                <div className="bg-surface-1 border border-border-mid rounded p-5">
                    <h2 className="font-display text-xl tracking-wider text-green-bright mb-3">MERGE INTO ANOTHER ACTOR</h2>
                    <form onSubmit={merge} className="flex gap-2 flex-wrap items-center">
                        <input
                            className={`${inputClass} max-w-md`}
                            placeholder="Target actor UUID"
                            value={mergeTargetId}
                            onChange={e => setMergeTargetId(e.target.value)}
                        />
                        <button type="submit" className="font-mono text-xs tracking-widest uppercase px-3 py-2 border border-red-alert text-red-bright hover:bg-red-alert/20 transition-colors rounded">
                            Merge & Delete current
                        </button>
                    </form>
                    <p className="font-mono text-xs text-text-dim mt-2">
                        All entity_extractions are reassigned to the target; aliases are merged; this actor is deleted.
                    </p>
                </div>

                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <div className="px-5 py-3 border-b border-border-mid bg-surface-2 flex items-center justify-between">
                        <h2 className="font-display text-xl tracking-wider text-green-bright">EVENTS ({events.length})</h2>
                    </div>
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid">
                                {['Date', 'Title', 'Country', 'Category', 'Severity'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-4 py-3">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {events.length === 0 ? (
                                <tr><td colSpan={5} className="px-4 py-8 text-center font-mono text-sm text-text-muted">No events linked yet.</td></tr>
                            ) : events.map(ev => (
                                <tr key={ev.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-3 font-mono text-xs text-text-dim">
                                        {ev.occurred_at ? new Date(ev.occurred_at).toISOString().slice(0, 10) : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link href={`/event/${ev.id}-${ev.slug || ''}`} className="font-sans text-sm text-text-primary hover:text-green-bright transition-colors">
                                            {ev.title}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{ev.country || '—'}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{ev.category || '—'}</td>
                                    <td className="px-4 py-3">
                                        {ev.severity ? <SeverityBadge severity={ev.severity} /> : <span className="font-mono text-xs text-text-dim">—</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
