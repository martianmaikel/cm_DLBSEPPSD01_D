import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import SeverityBadge from '../../Components/SeverityBadge';
import StatusBadge from '../../Components/StatusBadge';
import CategoryBadge from '../../Components/CategoryBadge';
import FilterBar from '../../Components/FilterBar';

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString(undefined, {
        month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function Pagination({ links, meta }) {
    if (!meta || meta.last_page <= 1) return null;
    return (
        <div className="flex items-center justify-between pt-4 font-mono text-xs text-text-muted">
            <span>Page {meta.current_page} of {meta.last_page} · {meta.total} total</span>
            <div className="flex gap-2">
                {links.map((link, i) => (
                    <button
                        key={i}
                        disabled={!link.url}
                        onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                        className={`px-3 py-1 border rounded transition-colors ${
                            link.active
                                ? 'border-green-base text-green-bright bg-green-dim'
                                : link.url
                                    ? 'border-border-mid text-text-secondary hover:border-border-active'
                                    : 'border-border-subtle text-text-dim cursor-default'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
}

export default function Events({ events, filters = {} }) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState(new Set());

    const breadcrumbs = [
        { label: t('admin.dashboard'), href: '/admin' },
        { label: t('admin.events') },
    ];

    const eventList = events?.data ?? [];
    const paginationLinks = events?.links ?? [];
    const paginationMeta = events?.meta ?? null;

    function toggleSelect(id) {
        setSelected(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    }

    function selectAll() {
        if (selected.size === eventList.length) {
            setSelected(new Set());
        } else {
            setSelected(new Set(eventList.map(e => e.id)));
        }
    }

    function bulkAction(action) {
        if (selected.size === 0) return;
        router.post(`/admin/events/bulk-${action}`, { ids: [...selected] }, {
            preserveScroll: true,
            onSuccess: () => setSelected(new Set()),
        });
    }

    function quickStatus(eventId, status) {
        router.patch(`/admin/events/${eventId}/status`, { status }, { preserveScroll: true });
    }

    function reassignThread(eventId) {
        const input = window.prompt(
            t('admin.reassignThreadPrompt') || 'Enter conflict_thread_id (leave empty to unassign):'
        );
        if (input === null) return;
        const trimmed = input.trim();
        const payload = trimmed === ''
            ? { conflict_thread_id: null }
            : { conflict_thread_id: Number(trimmed) };
        router.patch(`/admin/events/${eventId}/thread`, payload, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright">
                        {t('admin.events').toUpperCase()}
                    </h1>
                    <div className="font-mono text-xs text-text-muted">
                        {paginationMeta?.total ?? eventList.length} events
                    </div>
                </div>

                {/* Filter bar */}
                <FilterBar filters={filters} baseUrl="/admin/events" />

                {/* Bulk actions */}
                {selected.size > 0 && (
                    <div className="flex items-center gap-3 px-4 py-2.5 bg-surface-2 border border-border-mid rounded font-mono text-xs">
                        <span className="text-text-secondary">{selected.size} selected</span>
                        <span className="text-border-mid">|</span>
                        <button onClick={() => bulkAction('dispute')}  className="text-amber hover:text-amber-bright transition-colors">{t('admin.markDisputed')}</button>
                        <button onClick={() => bulkAction('retract')}  className="text-red-bright hover:text-red-alert transition-colors">{t('admin.markRetracted')}</button>
                        <button onClick={() => setSelected(new Set())} className="text-text-muted hover:text-text-secondary transition-colors ml-auto">{t('common.cancel')}</button>
                    </div>
                )}

                {/* Table */}
                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid bg-surface-2">
                                <th className="px-4 py-3 w-8">
                                    <input
                                        type="checkbox"
                                        checked={selected.size === eventList.length && eventList.length > 0}
                                        onChange={selectAll}
                                        className="accent-green-base"
                                    />
                                </th>
                                {['Event', 'Category', 'Sev', 'Status', 'Source', 'Occurred', 'Actions'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-3 py-3">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {eventList.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center font-mono text-sm text-text-muted">
                                        {t('common.noData')}
                                    </td>
                                </tr>
                            ) : eventList.map(event => (
                                <tr
                                    key={event.id}
                                    className={`border-b border-border-subtle hover:bg-surface-2 transition-colors ${selected.has(event.id) ? 'bg-surface-2' : ''}`}
                                >
                                    <td className="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={selected.has(event.id)}
                                            onChange={() => toggleSelect(event.id)}
                                            className="accent-green-base"
                                        />
                                    </td>
                                    <td className="px-3 py-3 max-w-xs">
                                        <Link
                                            href={`/event/${event.id}`}
                                            className="font-sans text-sm text-text-primary hover:text-green-bright transition-colors line-clamp-2"
                                        >
                                            {event.title}
                                        </Link>
                                        {event.country && (
                                            <div className="font-mono text-xs text-text-dim mt-0.5">{event.country}</div>
                                        )}
                                    </td>
                                    <td className="px-3 py-3">
                                        <CategoryBadge category={event.category} />
                                    </td>
                                    <td className="px-3 py-3">
                                        <SeverityBadge severity={event.severity} />
                                    </td>
                                    <td className="px-3 py-3">
                                        <StatusBadge status={event.status} />
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-blue-bright">
                                        {event.source_name ?? '—'}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-text-muted whitespace-nowrap">
                                        {formatDate(event.occurred_at)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex flex-col gap-1">
                                            {event.status !== 'disputed' && (
                                                <button
                                                    onClick={() => quickStatus(event.id, 'disputed')}
                                                    className="font-mono text-xs text-amber hover:text-amber-bright transition-colors text-left whitespace-nowrap"
                                                >
                                                    {t('admin.markDisputed')}
                                                </button>
                                            )}
                                            {event.status !== 'retracted' && (
                                                <button
                                                    onClick={() => quickStatus(event.id, 'retracted')}
                                                    className="font-mono text-xs text-red-bright hover:text-red-alert transition-colors text-left whitespace-nowrap"
                                                >
                                                    {t('admin.markRetracted')}
                                                </button>
                                            )}
                                            <button
                                                onClick={() => reassignThread(event.id)}
                                                className="font-mono text-xs text-text-muted hover:text-text-secondary transition-colors text-left whitespace-nowrap"
                                            >
                                                {t('admin.reassignThread')}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination links={paginationLinks} meta={paginationMeta} />
            </div>
        </AppLayout>
    );
}
