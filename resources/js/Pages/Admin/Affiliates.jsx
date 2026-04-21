import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

function AffiliateModal({ affiliate, onClose }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: affiliate?.name ?? '',
        slug: affiliate?.slug ?? '',
        headline_en: affiliate?.headline_en ?? '',
        headline_de: affiliate?.headline_de ?? '',
        body_en: affiliate?.body_en ?? '',
        body_de: affiliate?.body_de ?? '',
        image_url: affiliate?.image_url ?? '',
        target_url: affiliate?.target_url ?? '',
        cta_en: affiliate?.cta_en ?? 'Learn more',
        cta_de: affiliate?.cta_de ?? 'Mehr erfahren',
        utm_source: affiliate?.utm_source ?? 'clashmonitor',
        utm_medium: affiliate?.utm_medium ?? 'email',
        utm_campaign: affiliate?.utm_campaign ?? '',
        weight: affiliate?.weight ?? 1,
        active: affiliate?.active ?? true,
        starts_at: affiliate?.starts_at ? affiliate.starts_at.slice(0, 16) : '',
        ends_at: affiliate?.ends_at ? affiliate.ends_at.slice(0, 16) : '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (affiliate) {
            router.put(`/admin/affiliates/${affiliate.id}`, data, { onSuccess: onClose });
        } else {
            router.post('/admin/affiliates', data, { onSuccess: onClose });
        }
    }

    const input = 'w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors';
    const label = 'block font-mono text-xs tracking-widest uppercase text-text-muted mb-1';

    return (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 overflow-y-auto">
            <div className="bg-surface-1 border border-border-mid rounded w-full max-w-2xl my-8">
                <div className="flex items-center justify-between px-5 py-4 border-b border-border-mid sticky top-0 bg-surface-1 z-10">
                    <h2 className="font-display text-xl tracking-wider text-green-bright">
                        {affiliate ? 'EDIT AFFILIATE' : 'ADD AFFILIATE'}
                    </h2>
                    <button onClick={onClose} className="font-mono text-text-muted hover:text-red-bright transition-colors">✕</button>
                </div>

                <form onSubmit={handleSubmit} className="p-5 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className={label}>Name *</label>
                            <input className={input} value={data.name} onChange={e => setData('name', e.target.value)} required />
                            {errors.name && <p className="mt-1 font-mono text-xs text-red-bright">{errors.name}</p>}
                        </div>
                        <div>
                            <label className={label}>Slug</label>
                            <input className={input} value={data.slug} onChange={e => setData('slug', e.target.value)} placeholder="auto from name" />
                            {errors.slug && <p className="mt-1 font-mono text-xs text-red-bright">{errors.slug}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className={label}>Headline (EN) *</label>
                            <input className={input} value={data.headline_en} onChange={e => setData('headline_en', e.target.value)} required />
                            {errors.headline_en && <p className="mt-1 font-mono text-xs text-red-bright">{errors.headline_en}</p>}
                        </div>
                        <div>
                            <label className={label}>Headline (DE) *</label>
                            <input className={input} value={data.headline_de} onChange={e => setData('headline_de', e.target.value)} required />
                            {errors.headline_de && <p className="mt-1 font-mono text-xs text-red-bright">{errors.headline_de}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className={label}>Body (EN)</label>
                            <textarea className={`${input} h-20 resize-y`} value={data.body_en} onChange={e => setData('body_en', e.target.value)} />
                        </div>
                        <div>
                            <label className={label}>Body (DE)</label>
                            <textarea className={`${input} h-20 resize-y`} value={data.body_de} onChange={e => setData('body_de', e.target.value)} />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className={label}>CTA (EN)</label>
                            <input className={input} value={data.cta_en} onChange={e => setData('cta_en', e.target.value)} />
                        </div>
                        <div>
                            <label className={label}>CTA (DE)</label>
                            <input className={input} value={data.cta_de} onChange={e => setData('cta_de', e.target.value)} />
                        </div>
                    </div>

                    <div>
                        <label className={label}>Target URL *</label>
                        <input className={input} type="url" value={data.target_url} onChange={e => setData('target_url', e.target.value)} placeholder="https://partner.com/landing" required />
                        {errors.target_url && <p className="mt-1 font-mono text-xs text-red-bright">{errors.target_url}</p>}
                    </div>

                    <div>
                        <label className={label}>Image URL</label>
                        <input className={input} type="url" value={data.image_url} onChange={e => setData('image_url', e.target.value)} placeholder="https://.../banner.png" />
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className={label}>UTM Campaign</label>
                            <input className={input} value={data.utm_campaign} onChange={e => setData('utm_campaign', e.target.value)} placeholder="auto from slug" />
                        </div>
                        <div>
                            <label className={label}>UTM Source</label>
                            <input className={input} value={data.utm_source} onChange={e => setData('utm_source', e.target.value)} />
                        </div>
                        <div>
                            <label className={label}>UTM Medium</label>
                            <input className={input} value={data.utm_medium} onChange={e => setData('utm_medium', e.target.value)} />
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className={label}>Weight (1-100) *</label>
                            <input className={input} type="number" min={1} max={100} value={data.weight} onChange={e => setData('weight', Number(e.target.value))} required />
                        </div>
                        <div>
                            <label className={label}>Starts At</label>
                            <input className={input} type="datetime-local" value={data.starts_at} onChange={e => setData('starts_at', e.target.value)} />
                        </div>
                        <div>
                            <label className={label}>Ends At</label>
                            <input className={input} type="datetime-local" value={data.ends_at} onChange={e => setData('ends_at', e.target.value)} />
                        </div>
                    </div>

                    <label className="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" checked={data.active} onChange={e => setData('active', e.target.checked)} className="w-4 h-4 accent-green-base" />
                        <span className="font-mono text-sm text-text-secondary">Active</span>
                    </label>

                    <div className="flex gap-3 pt-2">
                        <button type="submit" disabled={processing} className="flex-1 font-mono text-xs tracking-widest uppercase py-2.5 border border-green-base text-green-bright hover:bg-green-dim disabled:opacity-40 transition-colors rounded">
                            {processing ? 'SAVING...' : 'SAVE'}
                        </button>
                        <button type="button" onClick={onClose} className="font-mono text-xs tracking-widest uppercase px-4 py-2.5 border border-border-mid text-text-muted hover:border-border-active transition-colors rounded">
                            CANCEL
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Affiliates({ affiliates = [] }) {
    const { t } = useTranslation();
    const [modal, setModal] = useState(null);

    const breadcrumbs = [
        { label: t('admin.dashboard'), href: '/admin' },
        { label: 'Affiliates' },
    ];

    function del(a) {
        if (!confirm(`Delete affiliate "${a.name}"?`)) return;
        router.delete(`/admin/affiliates/${a.id}`, { preserveScroll: true });
    }

    function ctr(a) {
        if (!a.impression_count) return '—';
        return ((a.click_count / a.impression_count) * 100).toFixed(2) + '%';
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright uppercase">Affiliates</h1>
                    <button
                        onClick={() => setModal('add')}
                        className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-green-base text-green-bright hover:bg-green-dim transition-colors rounded"
                    >
                        + ADD AFFILIATE
                    </button>
                </div>

                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid bg-surface-2">
                                {['Name', 'Weight', 'Active', 'Window', 'Impressions', 'Clicks', 'CTR', 'Actions'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-4 py-3">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {affiliates.length === 0 ? (
                                <tr><td colSpan={8} className="px-4 py-8 text-center font-mono text-sm text-text-muted">No affiliates yet</td></tr>
                            ) : affiliates.map(a => (
                                <tr key={a.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-3">
                                        <div className="font-sans text-sm text-text-primary">{a.name}</div>
                                        <div className="font-mono text-xs text-text-dim">/{a.slug}</div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{a.weight}</td>
                                    <td className="px-4 py-3">
                                        <span className={`font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded ${
                                            a.active ? 'border-green-base text-green-bright' : 'border-border-mid text-text-muted'
                                        }`}>
                                            {a.active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-dim">
                                        {a.starts_at || a.ends_at
                                            ? `${a.starts_at?.slice(0, 10) || '—'} → ${a.ends_at?.slice(0, 10) || '∞'}`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{a.impression_count}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">{a.click_count}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-green-bright">{ctr(a)}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <button onClick={() => setModal(a)} className="font-mono text-xs text-text-muted hover:text-green-bright transition-colors">EDIT</button>
                                            <span className="text-border-mid">|</span>
                                            <button onClick={() => del(a)} className="font-mono text-xs text-text-muted hover:text-red-bright transition-colors">DELETE</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {modal && <AffiliateModal affiliate={modal === 'add' ? null : modal} onClose={() => setModal(null)} />}
        </AppLayout>
    );
}
