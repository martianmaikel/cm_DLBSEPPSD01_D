import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import ActorTypeBadge from '../../../Components/ActorTypeBadge';
import EnrichmentStatusBadge from '../../../Components/EnrichmentStatusBadge';

export default function Index({ actors, filters = {}, candidatesCount = 0, promotionThreshold, enrichmentMode }) {
    const [search, setSearch] = useState(filters.search || '');
    const [type, setType] = useState(filters.type || '');
    const [country, setCountry] = useState(filters.country || '');
    const [enrichmentStatus, setEnrichmentStatus] = useState(filters.enrichment_status || '');
    const [minEventCount, setMinEventCount] = useState(filters.min_event_count || '');

    const items = actors?.data ?? [];
    const links = actors?.links ?? [];

    const breadcrumbs = [
        { label: 'Dashboard', href: '/admin' },
        { label: 'Actors' },
    ];

    function applyFilters(e) {
        e?.preventDefault();
        router.get('/admin/actors', {
            search: search || undefined,
            type: type || undefined,
            country: country || undefined,
            enrichment_status: enrichmentStatus || undefined,
            min_event_count: minEventCount || undefined,
        }, { preserveState: true, replace: true });
    }

    const inputClass = 'bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                <div className="flex items-end justify-between flex-wrap gap-4">
                    <div>
                        <h1 className="font-display text-3xl tracking-wider text-green-bright">
                            ACTORS DIRECTORY
                        </h1>
                        <p className="font-mono text-xs tracking-widest uppercase text-text-muted mt-1">
                            Promotion ≥ {promotionThreshold} events · Enrichment mode: {enrichmentMode}
                        </p>
                    </div>
                    <Link
                        href="/admin/actors/candidates"
                        className="font-mono text-xs tracking-widest uppercase px-3 py-2 border border-border-mid rounded hover:border-green-base hover:text-green-bright transition-colors"
                    >
                        Candidates ({candidatesCount})
                    </Link>
                </div>

                <form onSubmit={applyFilters} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 bg-surface-1 border border-border-mid rounded p-4">
                    <input
                        className={inputClass}
                        placeholder="Search name/alias"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                    />
                    <select className={inputClass} value={type} onChange={e => setType(e.target.value)}>
                        <option value="">All types</option>
                        <option value="person">Person</option>
                        <option value="organization">Organization</option>
                    </select>
                    <input
                        className={inputClass}
                        placeholder="Country (ISO2)"
                        value={country}
                        onChange={e => setCountry(e.target.value.toUpperCase())}
                        maxLength={2}
                    />
                    <select className={inputClass} value={enrichmentStatus} onChange={e => setEnrichmentStatus(e.target.value)}>
                        <option value="">Any enrichment</option>
                        <option value="pending">Pending</option>
                        <option value="enriching">Enriching</option>
                        <option value="enriched">Enriched</option>
                        <option value="failed">Failed</option>
                    </select>
                    <input
                        className={inputClass}
                        type="number"
                        placeholder="Min events"
                        value={minEventCount}
                        onChange={e => setMinEventCount(e.target.value)}
                    />
                    <button
                        type="submit"
                        className="font-mono text-xs tracking-widest uppercase py-2 border border-green-base text-green-bright hover:bg-green-dim transition-colors rounded"
                    >
                        Filter
                    </button>
                </form>

                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid bg-surface-2">
                                {['Name', 'Type', 'Role / Org Type', 'Country', 'Events', 'Mentions', 'Enrichment', 'Last mention'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-4 py-3">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center font-mono text-sm text-text-muted">
                                        No actors found.
                                    </td>
                                </tr>
                            ) : items.map(actor => (
                                <tr key={actor.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`/admin/actors/${actor.id}`}
                                            className="font-sans font-semibold text-text-primary text-sm hover:text-green-bright transition-colors"
                                        >
                                            {actor.canonical_name}
                                        </Link>
                                        {actor.summary_short && (
                                            <div className="font-mono text-xs text-text-dim mt-0.5 truncate max-w-md">
                                                {actor.summary_short}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3"><ActorTypeBadge type={actor.actor_type} /></td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">
                                        {actor.actor_type === 'person'
                                            ? (actor.role_title || <span className="text-text-dim">—</span>)
                                            : (actor.org_type || <span className="text-text-dim">—</span>)}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">
                                        {actor.country || <span className="text-text-dim">—</span>}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-sm text-text-secondary">{actor.event_count ?? 0}</td>
                                    <td className="px-4 py-3 font-mono text-sm text-text-secondary">{actor.mention_count ?? 0}</td>
                                    <td className="px-4 py-3"><EnrichmentStatusBadge status={actor.enrichment_status} /></td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-dim">
                                        {actor.last_mentioned_at
                                            ? new Date(actor.last_mentioned_at).toISOString().slice(0, 10)
                                            : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {links.length > 3 && (
                    <div className="flex gap-2 flex-wrap">
                        {links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url || '#'}
                                className={`font-mono text-xs px-3 py-1.5 border rounded ${
                                    link.active
                                        ? 'border-green-base text-green-bright'
                                        : link.url
                                            ? 'border-border-mid text-text-muted hover:border-border-active'
                                            : 'border-border-subtle text-text-dim opacity-40 pointer-events-none'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
