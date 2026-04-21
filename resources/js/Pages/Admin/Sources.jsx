import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

const SOURCE_TYPES = ['rss', 'telegram', 'api', 'csv_import', 'scraper', 'manual'];

const TYPE_BADGE = {
    rss:        'border-blue-intel text-blue-bright',
    telegram:   'border-amber text-amber',
    api:        'border-green-base text-green-bright',
    csv_import: 'border-purple-400 text-purple-300',
    scraper:    'border-orange-400 text-orange-300',
    manual:     'border-border-mid text-text-secondary',
};

function SourceModal({ source, sourceFamilies = [], onClose, onSave }) {
    const { t } = useTranslation();
    const { data, setData, post, put, processing, errors } = useForm({
        name:             source?.name ?? '',
        url:              source?.url ?? '',
        type:             source?.type ?? 'rss',
        source_family_id: source?.source_family_id ?? '',
        polling_interval: source?.polling_interval ?? 600,
        reliability_score: source?.reliability_score ?? 5,
        active:        source?.active ?? true,
        connector_class:  source?.connector_class ?? '',
        connector_config: source?.connector_config ? JSON.stringify(source.connector_config, null, 2) : '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        const payload = { ...data };
        // Parse connector_config JSON string into object
        if (payload.connector_config) {
            try {
                payload.connector_config = JSON.parse(payload.connector_config);
            } catch {
                // Leave as-is if invalid JSON — server will reject
            }
        } else {
            payload.connector_config = null;
        }
        if (!payload.connector_class) {
            payload.connector_class = null;
        }
        if (!payload.source_family_id) {
            payload.source_family_id = null;
        }
        if (source) {
            router.put(`/admin/sources/${source.id}`, payload, { onSuccess: onClose });
        } else {
            router.post('/admin/sources', payload, { onSuccess: onClose });
        }
    }

    const inputClass = 'w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors';
    const labelClass = 'block font-mono text-xs tracking-widest uppercase text-text-muted mb-1';

    return (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
            <div className="bg-surface-1 border border-border-mid rounded w-full max-w-lg">
                <div className="flex items-center justify-between px-5 py-4 border-b border-border-mid">
                    <h2 className="font-display text-xl tracking-wider text-green-bright">
                        {source ? t('admin.editSource').toUpperCase() : t('admin.addSource').toUpperCase()}
                    </h2>
                    <button onClick={onClose} className="font-mono text-text-muted hover:text-red-bright transition-colors">✕</button>
                </div>

                <form onSubmit={handleSubmit} className="p-5 space-y-4">
                    <div>
                        <label className={labelClass}>Name</label>
                        <input className={inputClass} value={data.name} onChange={e => setData('name', e.target.value)} required />
                        {errors.name && <p className="mt-1 font-mono text-xs text-red-bright">{errors.name}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>URL / Handle</label>
                        <input className={inputClass} value={data.url} onChange={e => setData('url', e.target.value)} />
                        {errors.url && <p className="mt-1 font-mono text-xs text-red-bright">{errors.url}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>Type</label>
                            <select className={inputClass} value={data.type} onChange={e => setData('type', e.target.value)}>
                                {SOURCE_TYPES.map(t => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass}>Source Family</label>
                            <select className={inputClass} value={data.source_family_id} onChange={e => setData('source_family_id', e.target.value ? Number(e.target.value) : '')}>
                                <option value="">— None —</option>
                                {sourceFamilies.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>Polling Interval (s)</label>
                            <input type="number" min={60} className={inputClass} value={data.polling_interval} onChange={e => setData('polling_interval', Number(e.target.value))} />
                        </div>
                        <div>
                            <label className={labelClass}>Reliability (1–10)</label>
                            <input type="number" min={1} max={10} className={inputClass} value={data.reliability_score} onChange={e => setData('reliability_score', Number(e.target.value))} />
                        </div>
                    </div>

                    {/* Connector fields — shown for api/scraper/csv_import types */}
                    {['api', 'scraper', 'csv_import'].includes(data.type) && (
                        <>
                            <div>
                                <label className={labelClass}>Connector Class</label>
                                <input className={inputClass} value={data.connector_class} onChange={e => setData('connector_class', e.target.value)} placeholder="App\Services\Ingestion\ApiConnectors\..." />
                            </div>
                            <div>
                                <label className={labelClass}>Connector Config (JSON)</label>
                                <textarea
                                    className={`${inputClass} h-24 resize-y`}
                                    value={data.connector_config}
                                    onChange={e => setData('connector_config', e.target.value)}
                                    placeholder='{"query": "conflict", "max_records": 75}'
                                />
                                {errors.connector_config && <p className="mt-1 font-mono text-xs text-red-bright">{errors.connector_config}</p>}
                            </div>
                        </>
                    )}

                    <div className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            id="active"
                            checked={data.active}
                            onChange={e => setData('active', e.target.checked)}
                            className="w-4 h-4 accent-green-base"
                        />
                        <label htmlFor="active" className="font-mono text-sm text-text-secondary cursor-pointer">
                            Active
                        </label>
                    </div>

                    <div className="flex gap-3 pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex-1 font-mono text-xs tracking-widest uppercase py-2.5 border border-green-base text-green-bright hover:bg-green-dim disabled:opacity-40 transition-colors rounded"
                        >
                            {processing ? t('common.loading') : t('common.save')}
                        </button>
                        <button
                            type="button"
                            onClick={onClose}
                            className="font-mono text-xs tracking-widest uppercase px-4 py-2.5 border border-border-mid text-text-muted hover:border-border-active transition-colors rounded"
                        >
                            {t('common.cancel')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Sources({ sources = [], sourceFamilies = [] }) {
    const { t } = useTranslation();
    const [modal, setModal] = useState(null); // null | 'add' | source object

    const breadcrumbs = [
        { label: t('admin.dashboard'), href: '/admin' },
        { label: t('admin.sources') },
    ];

    function toggleActive(source) {
        router.patch(`/admin/sources/${source.id}/toggle`, {}, { preserveScroll: true });
    }

    function deleteSource(source) {
        if (!confirm(`Delete source "${source.name}"?`)) return;
        router.delete(`/admin/sources/${source.id}`, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright">
                        {t('admin.sources').toUpperCase()}
                    </h1>
                    <button
                        onClick={() => setModal('add')}
                        className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-green-base text-green-bright hover:bg-green-dim transition-colors rounded"
                    >
                        + {t('admin.addSource')}
                    </button>
                </div>

                {/* Table */}
                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid bg-surface-2">
                                {['Name', 'Type', 'Family', 'Interval', 'Reliability', 'Status', 'Actions'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-4 py-3">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {sources.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center font-mono text-sm text-text-muted">
                                        {t('common.noData')}
                                    </td>
                                </tr>
                            ) : sources.map(source => (
                                <tr key={source.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-3">
                                        <div className="font-sans font-semibold text-text-primary text-sm">{source.name}</div>
                                        {source.url && (
                                            <div className="font-mono text-xs text-text-dim truncate max-w-xs">{source.url}</div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded ${TYPE_BADGE[source.type] || TYPE_BADGE.manual}`}>
                                            {source.type}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">
                                        {source.source_family?.name || '—'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">
                                        {source.polling_interval}s
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-1.5">
                                            <div className="h-1 w-16 bg-surface-3 rounded-full overflow-hidden">
                                                <div className="h-full bg-green-base rounded-full" style={{ width: `${(source.reliability_score / 10) * 100}%` }} />
                                            </div>
                                            <span className="font-mono text-xs text-text-secondary">{source.reliability_score}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <button
                                            onClick={() => toggleActive(source)}
                                            className={`font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded transition-colors ${
                                                source.active
                                                    ? 'border-green-base text-green-bright hover:bg-green-dim'
                                                    : 'border-border-mid text-text-muted hover:border-border-active'
                                            }`}
                                        >
                                            {source.active ? 'Active' : 'Inactive'}
                                        </button>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => setModal(source)}
                                                className="font-mono text-xs text-text-muted hover:text-green-bright transition-colors"
                                            >
                                                {t('common.edit')}
                                            </button>
                                            <span className="text-border-mid">|</span>
                                            <button
                                                onClick={() => deleteSource(source)}
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
            </div>

            {/* Modal */}
            {modal && (
                <SourceModal
                    source={modal === 'add' ? null : modal}
                    sourceFamilies={sourceFamilies}
                    onClose={() => setModal(null)}
                />
            )}
        </AppLayout>
    );
}
