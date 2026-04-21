import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

const SEVERITY_COLOR = {
    low:  'text-green-bright',
    mid:  'text-amber',
    high: 'text-red-bright',
};

function severityClass(s) {
    if (s >= 7) return SEVERITY_COLOR.high;
    if (s >= 4) return SEVERITY_COLOR.mid;
    return SEVERITY_COLOR.low;
}

function EditPanel({ thread, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        name:     thread.name ?? '',
        summary:  thread.summary ?? '',
        status:   thread.status ?? 'open',
        hashtags: (thread.hashtags ?? []).join(', '),
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(`/admin/threads/${thread.id}`, { onSuccess: onClose });
    }

    const inputClass = 'w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors';
    const labelClass = 'block font-mono text-xs tracking-widest uppercase text-text-muted mb-1';

    return (
        <div className="bg-surface-1 border border-border-mid rounded p-5 space-y-4">
            <h2 className="font-display text-lg tracking-wider text-green-bright">EDIT THREAD</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <label className={labelClass}>Name</label>
                    <input className={inputClass} value={data.name} onChange={e => setData('name', e.target.value)} required />
                    {errors.name && <p className="mt-1 font-mono text-xs text-red-bright">{errors.name}</p>}
                </div>
                <div>
                    <label className={labelClass}>Summary</label>
                    <textarea className={`${inputClass} h-24 resize-y`} value={data.summary} onChange={e => setData('summary', e.target.value)} />
                </div>
                <div>
                    <label className={labelClass}>Hashtags</label>
                    <input
                        className={inputClass}
                        value={data.hashtags}
                        onChange={e => setData('hashtags', e.target.value)}
                        placeholder="#Ukraine, #Russia"
                    />
                    <p className="mt-1 font-mono text-xs text-text-dim">
                        Used in X/Twitter posts. Comma or space separated. Sub-threads inherit parent hashtags if empty.
                    </p>
                </div>
                <div>
                    <label className={labelClass}>Status</label>
                    <select className={inputClass} value={data.status} onChange={e => setData('status', e.target.value)}>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div className="flex gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="font-mono text-xs tracking-widest uppercase px-4 py-2.5 border border-green-base text-green-bright hover:bg-green-dim disabled:opacity-40 transition-colors rounded"
                    >
                        {processing ? 'Saving...' : 'Save'}
                    </button>
                    <button
                        type="button"
                        onClick={onClose}
                        className="font-mono text-xs tracking-widest uppercase px-4 py-2.5 border border-border-mid text-text-muted hover:border-border-active transition-colors rounded"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    );
}

export default function Show({ thread, events = [] }) {
    const [editing, setEditing] = useState(false);

    const breadcrumbs = [
        { label: 'Dashboard', href: '/admin' },
        { label: 'Conflict Threads', href: '/admin/threads' },
        { label: thread.name },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="font-display text-3xl tracking-wider text-green-bright">
                            {thread.name.toUpperCase()}
                        </h1>
                        {thread.summary && (
                            <p className="mt-2 font-sans text-sm text-text-secondary max-w-2xl">{thread.summary}</p>
                        )}
                        <div className="flex flex-wrap items-center gap-3 mt-3">
                            <span className={`font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded ${thread.status === 'open' ? 'border-green-base text-green-bright' : 'border-border-mid text-text-muted'}`}>
                                {thread.status}
                            </span>
                            {thread.countries?.length > 0 && (
                                <span className="font-mono text-xs text-text-dim">
                                    {thread.countries.join(', ')}
                                </span>
                            )}
                            {thread.hashtags?.length > 0 && thread.hashtags.map(tag => (
                                <span key={tag} className="font-mono text-xs text-blue-bright bg-blue-intel/10 border border-blue-intel/30 rounded px-1.5 py-0.5">
                                    {tag}
                                </span>
                            ))}
                        </div>
                    </div>
                    <button
                        onClick={() => setEditing(!editing)}
                        className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-green-base text-green-bright hover:bg-green-dim transition-colors rounded shrink-0"
                    >
                        {editing ? 'Close' : 'Edit'}
                    </button>
                </div>

                {/* Edit panel */}
                {editing && (
                    <EditPanel thread={thread} onClose={() => setEditing(false)} />
                )}

                {/* Stats row */}
                <div className="grid grid-cols-3 gap-4">
                    {[
                        { label: 'Events (24h)', value: thread.event_count_24h ?? 0 },
                        { label: 'Events (total)', value: thread.event_count_total ?? 0 },
                        { label: 'Max Severity', value: thread.max_severity ?? 0, color: severityClass(thread.max_severity ?? 0) },
                    ].map(stat => (
                        <div key={stat.label} className="bg-surface-1 border border-border-mid rounded p-4 text-center">
                            <div className={`font-display text-2xl tracking-wider ${stat.color ?? 'text-text-primary'}`}>{stat.value}</div>
                            <div className="font-mono text-xs tracking-widest uppercase text-text-muted mt-1">{stat.label}</div>
                        </div>
                    ))}
                </div>

                {/* Events table */}
                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <div className="px-4 py-3 border-b border-border-mid bg-surface-2">
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted">
                            Events ({events.length})
                        </h2>
                    </div>
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-subtle">
                                {['Title', 'Category', 'Severity', 'Status', 'Source', 'Occurred'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-dim px-4 py-2">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {events.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center font-mono text-sm text-text-muted">
                                        No events assigned to this thread.
                                    </td>
                                </tr>
                            ) : events.map(event => (
                                <tr key={event.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-3">
                                        <div className="font-sans text-sm text-text-primary truncate max-w-md">{event.title}</div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{event.category ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <span className={`font-mono text-sm ${severityClass(event.severity ?? 0)}`}>
                                            {event.severity ?? '—'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{event.status}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-dim">{event.source?.name ?? '—'}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-dim">
                                        {event.occurred_at ? new Date(event.occurred_at).toLocaleString() : '—'}
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
