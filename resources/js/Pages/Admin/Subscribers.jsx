import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

const STATUS_BADGE = {
    pending:      'border-amber text-amber',
    confirmed:    'border-green-base text-green-bright',
    unsubscribed: 'border-border-mid text-text-muted',
    bounced:      'border-red-alert text-red-bright',
    complained:   'border-red-alert text-red-bright',
};

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toISOString().slice(0, 16).replace('T', ' ');
    } catch {
        return '—';
    }
}

export default function Subscribers({ subscribers, filters = {}, statusCounts = {}, alertsPaused = false }) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [locale, setLocale] = useState(filters.locale || '');

    const breadcrumbs = [
        { label: t('admin.dashboard'), href: '/admin' },
        { label: t('admin.subscribers') },
    ];

    function applyFilters(e) {
        e?.preventDefault();
        const params = {};
        if (search) params.search = search;
        if (status) params.status = status;
        if (locale) params.locale = locale;
        router.get('/admin/subscribers', params, { preserveScroll: true, preserveState: true });
    }

    function resetFilters() {
        setSearch('');
        setStatus('');
        setLocale('');
        router.get('/admin/subscribers', {}, { preserveScroll: true, preserveState: true });
    }

    function sendTest(subscriber) {
        if (!confirm(`Send test email to ${subscriber.email}?`)) return;
        router.post(`/admin/subscribers/${subscriber.id}/send-test`, {}, { preserveScroll: true });
    }

    function sendDaily(subscriber) {
        if (subscriber.status !== 'confirmed') {
            alert('Subscriber must be confirmed before sending the daily briefing.');
            return;
        }
        if (!confirm(`Queue today's daily briefing for ${subscriber.email}?`)) return;
        router.post(`/admin/subscribers/${subscriber.id}/send-daily`, {}, { preserveScroll: true });
    }

    function deleteSubscriber(subscriber) {
        if (!confirm(`GDPR delete subscriber ${subscriber.email}? This cannot be undone.`)) return;
        router.delete(`/admin/subscribers/${subscriber.id}`, { preserveScroll: true });
    }

    function toggleAlerts() {
        const action = alertsPaused ? 'resume' : 'pause';
        if (!confirm(`${action} all critical SEV≥9 alerts globally?`)) return;
        router.post('/admin/newsletter/toggle-alerts', {}, { preserveScroll: true });
    }

    const inputClass = 'bg-surface-2 border border-border-mid rounded px-3 py-1.5 font-mono text-xs text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors';

    const rows = subscribers?.data || [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                {/* Header */}
                <div className="flex items-center justify-between flex-wrap gap-3">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright uppercase">
                        {t('admin.subscribers')}
                    </h1>
                    <div className="flex items-center gap-4">
                        <button
                            onClick={toggleAlerts}
                            className={`font-mono text-xs tracking-widest uppercase px-3 py-1.5 border rounded transition-colors ${
                                alertsPaused
                                    ? 'border-red-alert text-red-bright hover:bg-red-alert/20'
                                    : 'border-green-base text-green-bright hover:bg-green-dim'
                            }`}
                            title="Toggle global critical alerts"
                        >
                            {alertsPaused ? '⏸ ALERTS PAUSED' : '▶ ALERTS LIVE'}
                        </button>
                        <div className="flex items-center gap-3 font-mono text-xs text-text-muted">
                            {Object.entries(statusCounts).map(([k, v]) => (
                                <span key={k} className="tracking-widest uppercase">
                                    <span className={`${STATUS_BADGE[k]?.split(' ')[1] || 'text-text-secondary'}`}>{v}</span>
                                    <span className="text-text-dim ml-1">{k}</span>
                                </span>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Filters */}
                <form onSubmit={applyFilters} className="bg-surface-1 border border-border-mid rounded p-4">
                    <div className="flex items-end gap-3 flex-wrap">
                        <div className="flex-1 min-w-[200px]">
                            <label className="block font-mono text-xs tracking-widest uppercase text-text-muted mb-1">
                                {t('admin.searchEmail')}
                            </label>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="user@example.com"
                                className={`${inputClass} w-full`}
                            />
                        </div>
                        <div>
                            <label className="block font-mono text-xs tracking-widest uppercase text-text-muted mb-1">
                                {t('admin.filterStatus')}
                            </label>
                            <select value={status} onChange={(e) => setStatus(e.target.value)} className={inputClass}>
                                <option value="">all</option>
                                <option value="pending">pending</option>
                                <option value="confirmed">confirmed</option>
                                <option value="unsubscribed">unsubscribed</option>
                                <option value="bounced">bounced</option>
                                <option value="complained">complained</option>
                            </select>
                        </div>
                        <div>
                            <label className="block font-mono text-xs tracking-widest uppercase text-text-muted mb-1">
                                {t('admin.filterLocale')}
                            </label>
                            <select value={locale} onChange={(e) => setLocale(e.target.value)} className={inputClass}>
                                <option value="">all</option>
                                <option value="en">en</option>
                                <option value="de">de</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            className="font-mono text-xs tracking-widest uppercase px-4 py-1.5 border border-green-base text-green-bright hover:bg-green-dim transition-colors rounded"
                        >
                            {t('common.filter')}
                        </button>
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="font-mono text-xs tracking-widest uppercase px-4 py-1.5 border border-border-mid text-text-muted hover:border-border-active transition-colors rounded"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                {/* Table */}
                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid bg-surface-2">
                                {['Email', 'Status', 'Locale', 'Timezone', 'Confirmed', 'Last Sent', 'Actions'].map((h) => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-4 py-3">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center font-mono text-sm text-text-muted">
                                        {t('common.noData')}
                                    </td>
                                </tr>
                            ) : rows.map((s) => (
                                <tr key={s.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-3">
                                        <div className="font-sans text-sm text-text-primary">{s.email}</div>
                                        {s.bounce_count > 0 && (
                                            <div className="font-mono text-xs text-red-bright">bounces: {s.bounce_count}</div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded ${STATUS_BADGE[s.status] || STATUS_BADGE.pending}`}>
                                            {s.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{s.locale}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{s.timezone}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-dim">{formatDate(s.confirmed_at)}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-dim">{formatDate(s.last_sent_at)}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => sendTest(s)}
                                                className="font-mono text-xs text-text-muted hover:text-green-bright transition-colors"
                                                title="Send test email"
                                            >
                                                {t('admin.sendTest')}
                                            </button>
                                            <span className="text-border-mid">|</span>
                                            <button
                                                onClick={() => sendDaily(s)}
                                                className="font-mono text-xs text-text-muted hover:text-green-bright transition-colors disabled:opacity-40"
                                                title="Queue today's daily briefing"
                                                disabled={s.status !== 'confirmed'}
                                            >
                                                Daily
                                            </button>
                                            <span className="text-border-mid">|</span>
                                            <a
                                                href={`/admin/subscribers/${s.id}/preview/daily`}
                                                target="_blank"
                                                rel="noopener"
                                                className="font-mono text-xs text-text-muted hover:text-blue-bright transition-colors"
                                                title="Preview daily briefing in browser"
                                            >
                                                👁 Daily
                                            </a>
                                            <span className="text-border-mid">|</span>
                                            <a
                                                href={`/admin/subscribers/${s.id}/preview/critical`}
                                                target="_blank"
                                                rel="noopener"
                                                className="font-mono text-xs text-text-muted hover:text-red-bright transition-colors"
                                                title="Preview critical alert in browser"
                                            >
                                                👁 Alert
                                            </a>
                                            <span className="text-border-mid">|</span>
                                            <button
                                                onClick={() => deleteSubscriber(s)}
                                                className="font-mono text-xs text-text-muted hover:text-red-bright transition-colors"
                                            >
                                                {t('common.delete')}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {subscribers?.links && subscribers.last_page > 1 && (
                    <div className="flex items-center justify-between font-mono text-xs text-text-muted">
                        <div>
                            Showing {subscribers.from}–{subscribers.to} of {subscribers.total}
                        </div>
                        <div className="flex gap-1">
                            {subscribers.links.map((link, i) => (
                                <button
                                    key={i}
                                    disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`px-2.5 py-1 border rounded tracking-widest uppercase transition-colors ${
                                        link.active
                                            ? 'border-green-base text-green-bright bg-green-dim'
                                            : link.url
                                                ? 'border-border-mid text-text-secondary hover:border-green-base hover:text-green-bright'
                                                : 'border-border-subtle text-text-dim cursor-not-allowed'
                                    }`}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
