import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import SeverityBadge from '../../Components/SeverityBadge';
import RelationshipGraph from '../../Components/Graph/RelationshipGraph';

const STATUS_STYLES = {
    active: 'border-green-base text-green-bright bg-green-base/10',
    inactive: 'border-text-muted text-text-muted bg-surface-2',
    deceased: 'border-red-alert text-red-bright bg-red-alert/10',
    dissolved: 'border-red-alert text-red-bright bg-red-alert/10',
    unknown: 'border-text-dim text-text-dim bg-surface-2',
};

const STATUS_LABELS_EN = {
    active: 'ACTIVE',
    inactive: 'INACTIVE',
    deceased: 'DECEASED',
    dissolved: 'DISSOLVED',
    unknown: 'UNKNOWN',
};

function StatusBadge({ status }) {
    const style = STATUS_STYLES[status] || STATUS_STYLES.unknown;
    return (
        <span className={`inline-flex items-center font-mono text-xs tracking-widest uppercase px-2.5 py-1 border rounded ${style}`}>
            {STATUS_LABELS_EN[status] || status}
        </span>
    );
}

export default function Show({ actor, events = [] }) {
    const isPerson = actor.actor_type === 'person';
    const breadcrumbs = [
        { label: 'Actors', href: '/actors' },
        { label: actor.canonical_name },
    ];

    const typeLabel = isPerson ? 'PERSON' : 'ORGANIZATION';
    const typeColor = isPerson ? 'text-green-bright border-green-base' : 'text-blue-bright border-blue-intel';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="px-4 md:px-6 py-6 max-w-6xl mx-auto space-y-6">
                <header className="grid grid-cols-1 md:grid-cols-[260px_1fr] gap-6 md:gap-8">
                    <div>
                        {actor.image_url ? (
                            <div className="aspect-square bg-surface-2 border border-border-mid rounded overflow-hidden">
                                <img
                                    src={actor.image_url}
                                    alt={actor.canonical_name}
                                    className="w-full h-full object-cover"
                                />
                            </div>
                        ) : (
                            <div className="aspect-square bg-surface-2 border border-border-mid rounded flex items-center justify-center">
                                <span className="font-display text-7xl tracking-wider text-text-dim">
                                    {actor.canonical_name?.charAt(0) ?? '?'}
                                </span>
                            </div>
                        )}
                        <div className="mt-3 grid grid-cols-2 gap-2 font-mono text-[10px] tracking-widest uppercase text-text-muted">
                            <div className="bg-surface-1 border border-border-subtle rounded px-2 py-2 text-center">
                                <div className="text-text-primary text-base font-display tracking-wider">{actor.event_count ?? 0}</div>
                                <div>Events</div>
                            </div>
                            <div className="bg-surface-1 border border-border-subtle rounded px-2 py-2 text-center">
                                <div className="text-text-primary text-base font-display tracking-wider">{actor.mention_count ?? 0}</div>
                                <div>Mentions</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className={`font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded ${typeColor}`}>
                                {typeLabel}
                            </span>
                            <StatusBadge status={actor.status} />
                            {actor.country && (
                                <span className="font-mono text-xs tracking-widest uppercase px-2 py-0.5 border border-border-mid text-text-secondary rounded">
                                    {actor.country}
                                </span>
                            )}
                        </div>
                        <h1 className="font-display text-4xl md:text-5xl tracking-wider text-green-bright mt-3">
                            {actor.canonical_name}
                        </h1>
                        {(isPerson ? actor.role_title : actor.org_type) && (
                            <p className="font-mono text-sm text-text-secondary mt-1 tracking-wide">
                                {isPerson
                                    ? actor.role_title
                                    : actor.org_type?.replace(/_/g, ' ').toUpperCase()}
                            </p>
                        )}
                        {actor.summary_short && (
                            <p className="text-lg text-text-primary mt-4 leading-relaxed">
                                {actor.summary_short}
                            </p>
                        )}
                        {actor.aliases?.length > 0 && (
                            <div className="mt-4">
                                <div className="font-mono text-[10px] tracking-widest uppercase text-text-muted mb-1">Also known as</div>
                                <div className="flex flex-wrap gap-1">
                                    {actor.aliases.slice(0, 8).map((a, i) => (
                                        <span key={i} className="font-mono text-xs text-text-secondary bg-surface-2 border border-border-subtle rounded px-2 py-0.5">
                                            {a}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </header>

                {(actor.summary_long || actor.relevance_summary) && (
                    <section className="bg-surface-1 border border-border-mid rounded p-5 space-y-4">
                        {actor.summary_long && (
                            <div>
                                <div className="font-mono text-[10px] tracking-widest uppercase text-text-muted mb-2">Overview</div>
                                <p className="text-text-primary leading-relaxed whitespace-pre-line">{actor.summary_long}</p>
                            </div>
                        )}
                        {actor.relevance_summary && (
                            <div>
                                <div className="font-mono text-[10px] tracking-widest uppercase text-text-muted mb-2">Conflict relevance</div>
                                <p className="text-text-secondary leading-relaxed whitespace-pre-line">{actor.relevance_summary}</p>
                            </div>
                        )}
                    </section>
                )}

                <section className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="bg-surface-1 border border-border-mid rounded p-5 space-y-3">
                        <div className="font-display text-lg tracking-wider text-green-bright">DETAILS</div>
                        <dl className="font-mono text-sm grid grid-cols-[130px_1fr] gap-y-2 gap-x-3">
                            {isPerson ? (
                                <>
                                    {actor.full_name && <><dt className="text-text-muted">Full name</dt><dd className="text-text-primary">{actor.full_name}</dd></>}
                                    {actor.role_title && <><dt className="text-text-muted">Role</dt><dd className="text-text-primary">{actor.role_title}</dd></>}
                                    {actor.nationality && <><dt className="text-text-muted">Nationality</dt><dd className="text-text-primary">{actor.nationality}</dd></>}
                                    {actor.birth_year && <><dt className="text-text-muted">Born</dt><dd className="text-text-primary">{actor.birth_year}</dd></>}
                                    {actor.death_year && <><dt className="text-text-muted">Died</dt><dd className="text-text-primary">{actor.death_year}</dd></>}
                                    {actor.affiliation && (
                                        <>
                                            <dt className="text-text-muted">Affiliation</dt>
                                            <dd>
                                                <Link href={`/actor/${actor.affiliation.slug}`} className="text-green-bright hover:underline">
                                                    {actor.affiliation.canonical_name}
                                                </Link>
                                            </dd>
                                        </>
                                    )}
                                </>
                            ) : (
                                <>
                                    {actor.org_type && <><dt className="text-text-muted">Type</dt><dd className="text-text-primary">{actor.org_type.replace(/_/g, ' ')}</dd></>}
                                    {actor.headquarters_country && <><dt className="text-text-muted">HQ country</dt><dd className="text-text-primary">{actor.headquarters_country}</dd></>}
                                    {actor.founded_year && <><dt className="text-text-muted">Founded</dt><dd className="text-text-primary">{actor.founded_year}</dd></>}
                                    {actor.dissolved_year && <><dt className="text-text-muted">Dissolved</dt><dd className="text-text-primary">{actor.dissolved_year}</dd></>}
                                    {actor.parent && (
                                        <>
                                            <dt className="text-text-muted">Parent</dt>
                                            <dd>
                                                <Link href={`/actor/${actor.parent.slug}`} className="text-green-bright hover:underline">
                                                    {actor.parent.canonical_name}
                                                </Link>
                                            </dd>
                                        </>
                                    )}
                                </>
                            )}
                            {actor.categories?.length > 0 && (
                                <>
                                    <dt className="text-text-muted">Categories</dt>
                                    <dd className="flex flex-wrap gap-1">
                                        {actor.categories.map((c, i) => (
                                            <span key={i} className="text-xs text-text-secondary bg-surface-2 border border-border-subtle rounded px-1.5 py-0.5">
                                                {c}
                                            </span>
                                        ))}
                                    </dd>
                                </>
                            )}
                            {actor.first_mentioned_at && <><dt className="text-text-muted">First tracked</dt><dd className="text-text-primary">{actor.first_mentioned_at.slice(0, 10)}</dd></>}
                            {actor.last_mentioned_at && <><dt className="text-text-muted">Last mention</dt><dd className="text-text-primary">{actor.last_mentioned_at.slice(0, 10)}</dd></>}
                        </dl>
                    </div>

                    {actor.sources_json?.length > 0 && (
                        <div className="bg-surface-1 border border-border-mid rounded p-5 space-y-3">
                            <div className="font-display text-lg tracking-wider text-green-bright">SOURCES</div>
                            <ul className="space-y-1.5 font-mono text-xs">
                                {actor.sources_json.map((s, i) => (
                                    <li key={i}>
                                        <a href={s} target="_blank" rel="noopener noreferrer nofollow" className="text-text-secondary hover:text-green-bright break-all">
                                            {s.replace(/^https?:\/\//, '')}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </section>

                <RelationshipGraph type="actor" id={actor.id} depth={1} height={480} />

                <section className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <div className="flex items-center justify-between px-5 py-3 border-b border-border-mid bg-surface-2">
                        <h2 className="font-display text-xl tracking-wider text-green-bright">EVENTS ({events.length})</h2>
                    </div>
                    {events.length === 0 ? (
                        <div className="px-5 py-8 text-center font-mono text-sm text-text-muted">
                            No events linked to this actor yet.
                        </div>
                    ) : (
                        <ul className="divide-y divide-border-subtle">
                            {events.map(ev => (
                                <li key={ev.id}>
                                    <Link
                                        href={`/event/${ev.id}${ev.slug ? `-${ev.slug}` : ''}`}
                                        className="block px-5 py-3 hover:bg-surface-2 transition-colors"
                                    >
                                        <div className="flex items-center justify-between gap-3 flex-wrap">
                                            <div className="flex items-center gap-2 font-mono text-[10px] tracking-widest uppercase text-text-dim">
                                                {ev.occurred_at && <span>{ev.occurred_at.slice(0, 10)}</span>}
                                                {ev.country && <span className="text-text-muted">· {ev.country}</span>}
                                                {ev.category && <span className="text-text-muted">· {ev.category}</span>}
                                            </div>
                                            {ev.severity && <SeverityBadge severity={ev.severity} />}
                                        </div>
                                        <div className="mt-1 text-text-primary group-hover:text-green-bright">
                                            {ev.title}
                                        </div>
                                        {ev.summary && (
                                            <p className="text-text-secondary text-sm mt-1 line-clamp-2">{ev.summary}</p>
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
