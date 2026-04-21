import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

const STATUS_BADGE = {
    open:   'border-green-base text-green-bright',
    closed: 'border-border-mid text-text-muted',
};

function EditModal({ thread, onClose }) {
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
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
            <div className="bg-surface-1 border border-border-mid rounded w-full max-w-lg">
                <div className="flex items-center justify-between px-5 py-4 border-b border-border-mid">
                    <h2 className="font-display text-xl tracking-wider text-green-bright">
                        EDIT THREAD
                    </h2>
                    <button onClick={onClose} className="font-mono text-text-muted hover:text-red-bright transition-colors">
                        &times;
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-5 space-y-4">
                    <div>
                        <label className={labelClass}>Name</label>
                        <input className={inputClass} value={data.name} onChange={e => setData('name', e.target.value)} required />
                        {errors.name && <p className="mt-1 font-mono text-xs text-red-bright">{errors.name}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Summary</label>
                        <textarea
                            className={`${inputClass} h-24 resize-y`}
                            value={data.summary}
                            onChange={e => setData('summary', e.target.value)}
                        />
                    </div>

                    <div>
                        <label className={labelClass}>Hashtags</label>
                        <input
                            className={inputClass}
                            value={data.hashtags}
                            onChange={e => setData('hashtags', e.target.value)}
                            placeholder="#Ukraine, #Russia, #Conflict"
                        />
                        <p className="mt-1 font-mono text-xs text-text-dim">
                            Comma or space separated. Used in X/Twitter posts for events in this conflict.
                        </p>
                        {errors.hashtags && <p className="mt-1 font-mono text-xs text-red-bright">{errors.hashtags}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Status</label>
                        <select className={inputClass} value={data.status} onChange={e => setData('status', e.target.value)}>
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>

                    <div className="flex gap-3 pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex-1 font-mono text-xs tracking-widest uppercase py-2.5 border border-green-base text-green-bright hover:bg-green-dim disabled:opacity-40 transition-colors rounded"
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
        </div>
    );
}

export default function Index({ threads }) {
    const [editThread, setEditThread] = useState(null);
    const items = threads?.data ?? threads ?? [];

    const breadcrumbs = [
        { label: 'Dashboard', href: '/admin' },
        { label: 'Conflict Threads' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                <h1 className="font-display text-3xl tracking-wider text-green-bright">
                    CONFLICT THREADS
                </h1>

                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid bg-surface-2">
                                {['Name', 'Status', 'Hashtags', 'Events (24h)', 'Events (total)', 'Max Severity', 'Actions'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-4 py-3">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center font-mono text-sm text-text-muted">
                                        No conflict threads found.
                                    </td>
                                </tr>
                            ) : items.map(thread => (
                                <tr key={thread.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-3">
                                        <a href={`/admin/threads/${thread.id}`} className="font-sans font-semibold text-text-primary text-sm hover:text-green-bright transition-colors">
                                            {thread.name}
                                        </a>
                                        {thread.countries?.length > 0 && (
                                            <div className="font-mono text-xs text-text-dim mt-0.5">
                                                {thread.countries.join(', ')}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded ${STATUS_BADGE[thread.status] || STATUS_BADGE.closed}`}>
                                            {thread.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {thread.hashtags?.length > 0 ? (
                                            <div className="flex flex-wrap gap-1">
                                                {thread.hashtags.map(tag => (
                                                    <span key={tag} className="font-mono text-xs text-blue-bright bg-blue-intel/10 border border-blue-intel/30 rounded px-1.5 py-0.5">
                                                        {tag}
                                                    </span>
                                                ))}
                                            </div>
                                        ) : (
                                            <span className="font-mono text-xs text-text-dim">&mdash;</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-sm text-text-secondary">
                                        {thread.event_count_24h ?? 0}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-sm text-text-secondary">
                                        {thread.events_count ?? thread.event_count_total ?? 0}
                                    </td>
                                    <td className="px-4 py-3">
                                        {thread.max_severity > 0 ? (
                                            <span className={`font-mono text-sm ${thread.max_severity >= 7 ? 'text-red-bright' : thread.max_severity >= 4 ? 'text-amber' : 'text-green-bright'}`}>
                                                {thread.max_severity}
                                            </span>
                                        ) : (
                                            <span className="font-mono text-xs text-text-dim">&mdash;</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <button
                                            onClick={() => setEditThread(thread)}
                                            className="font-mono text-xs text-text-muted hover:text-green-bright transition-colors"
                                        >
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {editThread && (
                <EditModal
                    thread={editThread}
                    onClose={() => setEditThread(null)}
                />
            )}
        </AppLayout>
    );
}
