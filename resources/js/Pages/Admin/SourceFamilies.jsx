import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

function FamilyModal({ family, onClose }) {
    const { t } = useTranslation();
    const { data, setData, post, put, processing, errors } = useForm({
        name:                family?.name ?? '',
        editorial_ownership: family?.editorial_ownership ?? '',
        description:         family?.description ?? '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        const payload = { ...data };
        if (!payload.editorial_ownership) payload.editorial_ownership = null;
        if (!payload.description) payload.description = null;

        if (family) {
            router.put(`/admin/source-families/${family.id}`, payload, { onSuccess: onClose });
        } else {
            router.post('/admin/source-families', payload, { onSuccess: onClose });
        }
    }

    const inputClass = 'w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors';
    const labelClass = 'block font-mono text-xs tracking-widest uppercase text-text-muted mb-1';

    return (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
            <div className="bg-surface-1 border border-border-mid rounded w-full max-w-lg">
                <div className="flex items-center justify-between px-5 py-4 border-b border-border-mid">
                    <h2 className="font-display text-xl tracking-wider text-green-bright">
                        {family ? 'EDIT SOURCE FAMILY' : 'ADD SOURCE FAMILY'}
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
                        <label className={labelClass}>Editorial Ownership</label>
                        <input className={inputClass} value={data.editorial_ownership} onChange={e => setData('editorial_ownership', e.target.value)} placeholder="e.g. Reuters Group" />
                        {errors.editorial_ownership && <p className="mt-1 font-mono text-xs text-red-bright">{errors.editorial_ownership}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Description</label>
                        <textarea
                            className={`${inputClass} h-24 resize-y`}
                            value={data.description}
                            onChange={e => setData('description', e.target.value)}
                            placeholder="Optional notes about this source family"
                        />
                        {errors.description && <p className="mt-1 font-mono text-xs text-red-bright">{errors.description}</p>}
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

export default function SourceFamilies({ families = [] }) {
    const { t } = useTranslation();
    const [modal, setModal] = useState(null);

    const breadcrumbs = [
        { label: t('admin.dashboard'), href: '/admin' },
        { label: 'Source Families' },
    ];

    function deleteFamily(family) {
        if (family.sources_count > 0) {
            alert(`Cannot delete "${family.name}" — ${family.sources_count} source(s) still assigned.`);
            return;
        }
        if (!confirm(`Delete source family "${family.name}"?`)) return;
        router.delete(`/admin/source-families/${family.id}`, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright">
                        SOURCE FAMILIES
                    </h1>
                    <button
                        onClick={() => setModal('add')}
                        className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-green-base text-green-bright hover:bg-green-dim transition-colors rounded"
                    >
                        + Add Family
                    </button>
                </div>

                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid bg-surface-2">
                                {['Name', 'Editorial Ownership', 'Sources', 'Actions'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-4 py-3">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {families.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-8 text-center font-mono text-sm text-text-muted">
                                        {t('common.noData')}
                                    </td>
                                </tr>
                            ) : families.map(family => (
                                <tr key={family.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-3">
                                        <div className="font-sans font-semibold text-text-primary text-sm">{family.name}</div>
                                        {family.description && (
                                            <div className="font-mono text-xs text-text-dim truncate max-w-xs">{family.description}</div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">
                                        {family.editorial_ownership || '—'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">
                                        {family.sources_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => setModal(family)}
                                                className="font-mono text-xs text-text-muted hover:text-green-bright transition-colors"
                                            >
                                                {t('common.edit')}
                                            </button>
                                            <span className="text-border-mid">|</span>
                                            <button
                                                onClick={() => deleteFamily(family)}
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

            {modal && (
                <FamilyModal
                    family={modal === 'add' ? null : modal}
                    onClose={() => setModal(null)}
                />
            )}
        </AppLayout>
    );
}
