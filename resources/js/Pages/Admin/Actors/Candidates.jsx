import { Link, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import ActorTypeBadge from '../../../Components/ActorTypeBadge';

export default function Candidates({ candidates, filters = {}, promotionThreshold }) {
    const items = candidates?.data ?? [];
    const links = candidates?.links ?? [];

    const breadcrumbs = [
        { label: 'Dashboard', href: '/admin' },
        { label: 'Actors', href: '/admin/actors' },
        { label: 'Candidates' },
    ];

    function setFilter(key, value) {
        router.get('/admin/actors/candidates', { ...filters, [key]: value || undefined }, { preserveState: true, replace: true });
    }

    function promote(candidate) {
        if (!confirm(`Promote "${candidate.display_name}" to actor now?`)) return;
        router.post(`/admin/actors/candidates/${candidate.id}/promote`, {}, { preserveScroll: true });
    }

    function block(candidate) {
        if (!confirm(`Block "${candidate.display_name}" from promotion?`)) return;
        router.post(`/admin/actors/candidates/${candidate.id}/block`, {}, { preserveScroll: true });
    }

    function unblock(candidate) {
        router.post(`/admin/actors/candidates/${candidate.id}/unblock`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                <div className="flex items-end justify-between flex-wrap gap-3">
                    <div>
                        <h1 className="font-display text-3xl tracking-wider text-green-bright">
                            ACTOR CANDIDATES
                        </h1>
                        <p className="font-mono text-xs tracking-widest uppercase text-text-muted mt-1">
                            Promotion threshold: {promotionThreshold} events
                        </p>
                    </div>
                    <div className="flex gap-2 font-mono text-xs tracking-widest uppercase">
                        <button onClick={() => setFilter('type', filters.type === 'person' ? '' : 'person')} className={`px-3 py-2 border rounded ${filters.type === 'person' ? 'border-green-base text-green-bright' : 'border-border-mid text-text-muted'}`}>Person</button>
                        <button onClick={() => setFilter('type', filters.type === 'organization' ? '' : 'organization')} className={`px-3 py-2 border rounded ${filters.type === 'organization' ? 'border-blue-intel text-blue-bright' : 'border-border-mid text-text-muted'}`}>Org</button>
                        <button onClick={() => setFilter('blocked', filters.blocked ? '' : '1')} className={`px-3 py-2 border rounded ${filters.blocked ? 'border-red-alert text-red-bright' : 'border-border-mid text-text-muted'}`}>Show blocked</button>
                    </div>
                </div>

                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid bg-surface-2">
                                {['Name', 'Type', 'Events', 'First seen', 'Last seen', 'Actions'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-4 py-3">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr><td colSpan={6} className="px-4 py-8 text-center font-mono text-sm text-text-muted">No candidates.</td></tr>
                            ) : items.map(c => (
                                <tr key={c.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-3">
                                        <div className="font-sans text-sm text-text-primary">{c.display_name}</div>
                                        <div className="font-mono text-xs text-text-dim mt-0.5">{c.normalized_name}</div>
                                    </td>
                                    <td className="px-4 py-3"><ActorTypeBadge type={c.actor_type} /></td>
                                    <td className="px-4 py-3 font-mono text-sm text-text-secondary">{c.event_count}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-dim">
                                        {c.first_seen_at ? new Date(c.first_seen_at).toISOString().slice(0, 10) : '—'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-dim">
                                        {c.last_seen_at ? new Date(c.last_seen_at).toISOString().slice(0, 10) : '—'}
                                    </td>
                                    <td className="px-4 py-3 flex gap-2">
                                        <button onClick={() => promote(c)} className="font-mono text-xs tracking-widest uppercase px-2 py-1 border border-green-base text-green-bright hover:bg-green-dim transition-colors rounded">
                                            Promote
                                        </button>
                                        {c.blocked ? (
                                            <button onClick={() => unblock(c)} className="font-mono text-xs tracking-widest uppercase px-2 py-1 border border-border-mid text-text-muted hover:border-border-active transition-colors rounded">
                                                Unblock
                                            </button>
                                        ) : (
                                            <button onClick={() => block(c)} className="font-mono text-xs tracking-widest uppercase px-2 py-1 border border-red-alert text-red-bright hover:bg-red-alert/20 transition-colors rounded">
                                                Block
                                            </button>
                                        )}
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
