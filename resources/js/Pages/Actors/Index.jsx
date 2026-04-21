import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function StatusDot({ status }) {
    const colors = {
        active: 'bg-green-bright',
        inactive: 'bg-text-muted',
        deceased: 'bg-red-bright',
        dissolved: 'bg-red-bright',
        unknown: 'bg-text-dim',
    };
    return <span className={`inline-block w-2 h-2 rounded-full ${colors[status] || colors.unknown}`} />;
}

export default function Index({ actors, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [type, setType] = useState(filters.type || '');
    const [country, setCountry] = useState(filters.country || '');

    const items = actors?.data ?? [];
    const links = actors?.links ?? [];

    function applyFilters(e) {
        e?.preventDefault();
        router.get('/actors', {
            search: search || undefined,
            type: type || undefined,
            country: country || undefined,
        }, { preserveState: true, replace: true });
    }

    const inputClass = 'bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors';

    return (
        <AppLayout breadcrumbs={[{ label: 'Actors' }]}>
            <div className="space-y-5 px-4 md:px-6 py-6 max-w-7xl mx-auto">
                <div className="flex items-end justify-between flex-wrap gap-3">
                    <div>
                        <h1 className="font-display text-3xl md:text-4xl tracking-wider text-green-bright">
                            ACTORS
                        </h1>
                        <p className="font-mono text-xs tracking-widest uppercase text-text-muted mt-1">
                            Conflict-relevant persons &amp; organizations
                        </p>
                    </div>
                </div>

                <form onSubmit={applyFilters} className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 bg-surface-1 border border-border-mid rounded p-4">
                    <input
                        className={inputClass}
                        placeholder="Search name or alias"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                    />
                    <select className={inputClass} value={type} onChange={e => setType(e.target.value)}>
                        <option value="">All types</option>
                        <option value="person">Persons</option>
                        <option value="organization">Organizations</option>
                    </select>
                    <input
                        className={inputClass}
                        placeholder="Country (ISO2, e.g. US)"
                        value={country}
                        onChange={e => setCountry(e.target.value.toUpperCase())}
                        maxLength={2}
                    />
                    <button
                        type="submit"
                        className="font-mono text-xs tracking-widest uppercase py-2 border border-green-base text-green-bright hover:bg-green-dim transition-colors rounded"
                    >
                        Filter
                    </button>
                </form>

                {items.length === 0 ? (
                    <div className="bg-surface-1 border border-border-mid rounded p-10 text-center">
                        <p className="font-mono text-sm text-text-muted">No actors found.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        {items.map(actor => (
                            <Link
                                key={actor.id}
                                href={`/actor/${actor.slug}`}
                                className="group bg-surface-1 border border-border-mid hover:border-green-base rounded overflow-hidden transition-colors flex flex-col"
                            >
                                <div className="relative aspect-square bg-surface-2 border-b border-border-mid">
                                    {actor.image_url ? (
                                        <img
                                            src={actor.image_url}
                                            alt={actor.canonical_name}
                                            loading="lazy"
                                            className="absolute inset-0 w-full h-full object-cover"
                                        />
                                    ) : (
                                        <div className="absolute inset-0 flex items-center justify-center">
                                            <span className="font-display text-5xl tracking-wider text-text-dim">
                                                {actor.canonical_name?.charAt(0) ?? '?'}
                                            </span>
                                        </div>
                                    )}
                                    <div className="absolute top-2 left-2 flex items-center gap-1.5 bg-black/70 backdrop-blur px-2 py-1 rounded">
                                        <StatusDot status={actor.status} />
                                        <span className="font-mono text-[10px] tracking-widest uppercase text-text-secondary">
                                            {actor.status}
                                        </span>
                                    </div>
                                    {actor.country && (
                                        <div className="absolute top-2 right-2 bg-black/70 backdrop-blur px-2 py-1 rounded">
                                            <span className="font-mono text-[10px] tracking-widest uppercase text-text-secondary">
                                                {actor.country}
                                            </span>
                                        </div>
                                    )}
                                </div>
                                <div className="p-3 flex-1 flex flex-col">
                                    <div className="font-display text-lg tracking-wider text-text-primary group-hover:text-green-bright transition-colors line-clamp-2">
                                        {actor.canonical_name}
                                    </div>
                                    <div className="font-mono text-xs text-text-muted mt-1 line-clamp-1">
                                        {actor.actor_type === 'person'
                                            ? (actor.role_title || 'Person')
                                            : (actor.org_type ? actor.org_type.replace(/_/g, ' ') : 'Organization')}
                                    </div>
                                    <div className="flex-1" />
                                    <div className="flex items-center gap-3 mt-3 pt-3 border-t border-border-subtle font-mono text-[10px] tracking-widest uppercase text-text-dim">
                                        <span>{actor.event_count ?? 0} events</span>
                                        <span>·</span>
                                        <span>{actor.mention_count ?? 0} mentions</span>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                {links.length > 3 && (
                    <div className="flex gap-2 flex-wrap justify-center pt-2">
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
