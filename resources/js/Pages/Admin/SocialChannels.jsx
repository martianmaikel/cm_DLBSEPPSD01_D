import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

const PLATFORMS = ['threads', 'facebook', 'telegram', 'bluesky', 'x'];
const LOCALES = ['en', 'de'];

const PLATFORM_COLORS = {
    threads:  'border-purple-500 text-purple-400',
    facebook: 'border-blue-500 text-blue-400',
    telegram: 'border-sky-500 text-sky-400',
    bluesky:  'border-blue-400 text-blue-300',
    x:        'border-neutral-400 text-neutral-300',
};

const CREDENTIAL_FIELDS = {
    telegram: [
        { key: 'bot_token', label: 'Bot Token', required: true },
        { key: 'chat_id', label: 'Chat ID (@channel or numeric)', required: true },
    ],
    facebook: [
        { key: 'page_id', label: 'Page ID', required: true },
        { key: 'access_token', label: 'Page Access Token', required: true },
        { key: 'long_lived_user_token', label: 'Long-lived User Token (for refresh)', required: false },
    ],
    threads: [
        { key: 'user_id', label: 'Threads User ID', required: true },
        { key: 'access_token', label: 'Access Token', required: true },
    ],
    bluesky: [
        { key: 'identifier', label: 'Handle or DID (e.g. clashmonitor.bsky.social)', required: true },
        { key: 'app_password', label: 'App Password', required: true },
    ],
    x: [
        { key: 'api_key', label: 'API Key (Consumer Key)', required: true },
        { key: 'api_secret', label: 'API Secret (Consumer Secret)', required: true },
        { key: 'access_token', label: 'Access Token', required: true },
        { key: 'access_token_secret', label: 'Access Token Secret', required: true },
    ],
};

function ChannelModal({ channel, onClose }) {
    const isEdit = !!channel;
    const [data, setData] = useState({
        platform: channel?.platform ?? 'telegram',
        locale: channel?.locale ?? 'en',
        name: channel?.name ?? '',
        handle: channel?.handle ?? '',
        posts_event: channel?.posts_event ?? true,
        posts_briefing: channel?.posts_briefing ?? true,
        enabled: channel?.enabled ?? false,
        unlimited_chars: channel?.unlimited_chars ?? false,
        daily_post_limit: channel?.daily_post_limit ?? 50,
        min_post_interval: channel?.min_post_interval ?? 60,
        credentials: {},
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    function set(key, value) {
        setData(d => ({ ...d, [key]: value }));
    }

    function setCred(key, value) {
        setData(d => ({ ...d, credentials: { ...d.credentials, [key]: value } }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        setProcessing(true);

        const payload = { ...data };
        // Remove empty credential values
        payload.credentials = Object.fromEntries(
            Object.entries(payload.credentials).filter(([, v]) => v)
        );
        // For edits: if no credentials entered, don't send the field
        if (isEdit && Object.keys(payload.credentials).length === 0) {
            delete payload.credentials;
        }

        const opts = {
            onSuccess: () => onClose(),
            onError: (errs) => { setErrors(errs); setProcessing(false); },
            onFinish: () => setProcessing(false),
        };

        if (isEdit) {
            router.put(`/admin/social-channels/${channel.id}`, payload, opts);
        } else {
            router.post('/admin/social-channels', payload, opts);
        }
    }

    const credFields = CREDENTIAL_FIELDS[data.platform] || [];

    const input = 'w-full bg-surface-2 border border-border-mid rounded px-3 py-2 font-mono text-sm text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors';
    const label = 'block font-mono text-xs tracking-widest uppercase text-text-muted mb-1';
    const select = input + ' appearance-none';

    return (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 overflow-y-auto">
            <div className="bg-surface-1 border border-border-mid rounded w-full max-w-2xl my-8">
                <div className="flex items-center justify-between px-5 py-4 border-b border-border-mid sticky top-0 bg-surface-1 z-10">
                    <h2 className="font-display text-xl tracking-wider text-green-bright">
                        {isEdit ? 'EDIT CHANNEL' : 'ADD CHANNEL'}
                    </h2>
                    <button onClick={onClose} className="font-mono text-text-muted hover:text-red-bright transition-colors">✕</button>
                </div>

                <form onSubmit={handleSubmit} className="p-5 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className={label}>Platform *</label>
                            <select className={select} value={data.platform} onChange={e => set('platform', e.target.value)} disabled={isEdit}>
                                {PLATFORMS.map(p => <option key={p} value={p}>{p.charAt(0).toUpperCase() + p.slice(1)}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={label}>Locale *</label>
                            <select className={select} value={data.locale} onChange={e => set('locale', e.target.value)}>
                                {LOCALES.map(l => <option key={l} value={l}>{l.toUpperCase()}</option>)}
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className={label}>Display Name *</label>
                            <input className={input} value={data.name} onChange={e => set('name', e.target.value)} placeholder="Clash Monitor" required />
                            {errors.name && <p className="mt-1 font-mono text-xs text-red-bright">{errors.name}</p>}
                        </div>
                        <div>
                            <label className={label}>Handle / ID *</label>
                            <input className={input} value={data.handle} onChange={e => set('handle', e.target.value)} placeholder="@clashmonitor" required />
                            {errors.handle && <p className="mt-1 font-mono text-xs text-red-bright">{errors.handle}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className={label}>Daily Post Limit *</label>
                            <input className={input} type="number" min={1} max={1000} value={data.daily_post_limit} onChange={e => set('daily_post_limit', Number(e.target.value))} required />
                        </div>
                        <div>
                            <label className={label}>Min Interval (sec) *</label>
                            <input className={input} type="number" min={0} max={86400} value={data.min_post_interval} onChange={e => set('min_post_interval', Number(e.target.value))} required />
                            <p className="mt-1 font-mono text-[10px] text-text-dim">Minimum seconds between posts</p>
                        </div>
                    </div>

                    {/* Credentials */}
                    <div className="border border-border-mid rounded p-4 space-y-3">
                        <h3 className="font-mono text-xs tracking-widest uppercase text-text-muted">
                            Credentials {isEdit && '(leave blank to keep existing)'}
                        </h3>
                        {credFields.map(f => (
                            <div key={f.key}>
                                <label className={label}>{f.label} {!isEdit && f.required && '*'}</label>
                                <input
                                    className={input}
                                    type="password"
                                    value={data.credentials[f.key] ?? ''}
                                    onChange={e => setCred(f.key, e.target.value)}
                                    placeholder={isEdit && channel?.credential_keys?.includes(f.key) ? '••••••• (set)' : ''}
                                    required={!isEdit && f.required}
                                    autoComplete="off"
                                />
                            </div>
                        ))}
                        {errors.credentials && <p className="font-mono text-xs text-red-bright">{errors.credentials}</p>}
                    </div>

                    {/* Toggles */}
                    <div className="flex flex-wrap gap-6">
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" checked={data.posts_event} onChange={e => set('posts_event', e.target.checked)} className="w-4 h-4 accent-green-base" />
                            <span className="font-mono text-sm text-text-secondary">Post events</span>
                        </label>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" checked={data.posts_briefing} onChange={e => set('posts_briefing', e.target.checked)} className="w-4 h-4 accent-green-base" />
                            <span className="font-mono text-sm text-text-secondary">Post briefings</span>
                        </label>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" checked={data.enabled} onChange={e => set('enabled', e.target.checked)} className="w-4 h-4 accent-green-base" />
                            <span className="font-mono text-sm text-text-secondary">Enabled</span>
                        </label>
                        {data.platform === 'x' && (
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" checked={data.unlimited_chars} onChange={e => set('unlimited_chars', e.target.checked)} className="w-4 h-4 accent-green-base" />
                                <span className="font-mono text-sm text-text-secondary">Unlimited characters (X Premium)</span>
                            </label>
                        )}
                    </div>

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

function StatusDot({ status }) {
    const color = {
        published: 'bg-green-bright',
        queued: 'bg-amber-bright',
        failed: 'bg-red-bright',
        skipped: 'bg-text-dim',
    }[status] || 'bg-text-dim';

    return <span className={`inline-block w-2 h-2 rounded-full ${color}`} />;
}

export default function SocialChannels({ channels = [], recentPosts = [] }) {
    const { t } = useTranslation();
    const [modal, setModal] = useState(null);

    const breadcrumbs = [
        { label: t('admin.dashboard'), href: '/admin' },
        { label: 'Social Channels' },
    ];

    function del(ch) {
        if (!confirm(`Delete channel "${ch.name}" (${ch.platform}/${ch.locale})?`)) return;
        router.delete(`/admin/social-channels/${ch.id}`, { preserveScroll: true });
    }

    function postableLabel(type) {
        if (type.includes('Event')) return 'Event';
        if (type.includes('DailyBriefing')) return 'Briefing';
        return type.split('\\').pop();
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright uppercase">Social Channels</h1>
                    <button
                        onClick={() => setModal('add')}
                        className="font-mono text-xs tracking-widest uppercase px-4 py-2 border border-green-base text-green-bright hover:bg-green-dim transition-colors rounded"
                    >
                        + ADD CHANNEL
                    </button>
                </div>

                {/* Channels table */}
                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-mid bg-surface-2">
                                {['Platform', 'Locale', 'Name', 'Events', 'Briefings', 'Posts Today', 'Status', 'Actions'].map(h => (
                                    <th key={h} className="text-left font-mono text-xs tracking-widest uppercase text-text-muted px-4 py-3">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {channels.length === 0 ? (
                                <tr><td colSpan={8} className="px-4 py-8 text-center font-mono text-sm text-text-muted">No social channels configured</td></tr>
                            ) : channels.map(ch => (
                                <tr key={ch.id} className="border-b border-border-subtle hover:bg-surface-2 transition-colors">
                                    <td className="px-4 py-3">
                                        <span className={`font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded ${PLATFORM_COLORS[ch.platform] || 'border-border-mid text-text-muted'}`}>
                                            {ch.platform}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary uppercase">{ch.locale}</td>
                                    <td className="px-4 py-3">
                                        <div className="font-sans text-sm text-text-primary">{ch.name}</div>
                                        <div className="font-mono text-xs text-text-dim">{ch.handle}</div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`font-mono text-xs ${ch.posts_event ? 'text-green-bright' : 'text-text-dim'}`}>
                                            {ch.posts_event ? 'ON' : 'OFF'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`font-mono text-xs ${ch.posts_briefing ? 'text-green-bright' : 'text-text-dim'}`}>
                                            {ch.posts_briefing ? 'ON' : 'OFF'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-text-secondary">
                                        {ch.daily_post_count} / {ch.daily_post_limit}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded ${
                                            ch.enabled
                                                ? 'border-green-base text-green-bright'
                                                : 'border-border-mid text-text-muted'
                                        }`}>
                                            {ch.enabled ? 'Active' : 'Disabled'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <button onClick={() => setModal(ch)} className="font-mono text-xs text-text-muted hover:text-green-bright transition-colors">EDIT</button>
                                            <span className="text-border-mid">|</span>
                                            <button onClick={() => del(ch)} className="font-mono text-xs text-text-muted hover:text-red-bright transition-colors">DELETE</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Token expiry warnings */}
                {channels.filter(ch => ch.token_expires_at).length > 0 && (
                    <div className="bg-surface-1 border border-border-mid rounded p-4">
                        <h3 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-3">Token Expiry</h3>
                        <div className="space-y-1">
                            {channels.filter(ch => ch.token_expires_at).map(ch => {
                                const expires = new Date(ch.token_expires_at);
                                const daysLeft = Math.ceil((expires - Date.now()) / 86400000);
                                const urgent = daysLeft <= 7;
                                return (
                                    <div key={ch.id} className="flex items-center justify-between font-mono text-xs">
                                        <span className="text-text-secondary">{ch.name} ({ch.platform})</span>
                                        <span className={urgent ? 'text-red-bright' : 'text-text-muted'}>
                                            {daysLeft > 0 ? `${daysLeft} days left` : 'EXPIRED'}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Recent posts */}
                <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                    <div className="px-4 py-3 border-b border-border-mid bg-surface-2">
                        <h3 className="font-mono text-xs tracking-widest uppercase text-text-muted">Recent Social Posts</h3>
                    </div>
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-border-subtle">
                                {['Status', 'Channel', 'Type', 'Platform', 'Time', 'Error'].map(h => (
                                    <th key={h} className="text-left font-mono text-[10px] tracking-widest uppercase text-text-dim px-4 py-2">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {recentPosts.length === 0 ? (
                                <tr><td colSpan={6} className="px-4 py-6 text-center font-mono text-xs text-text-muted">No posts yet</td></tr>
                            ) : recentPosts.map(p => (
                                <tr key={p.id} className="border-b border-border-subtle">
                                    <td className="px-4 py-2">
                                        <div className="flex items-center gap-2">
                                            <StatusDot status={p.status} />
                                            <span className="font-mono text-xs text-text-secondary">{p.status}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-2 font-mono text-xs text-text-secondary">
                                        {p.social_channel?.name || '—'}
                                    </td>
                                    <td className="px-4 py-2 font-mono text-xs text-text-muted">
                                        {postableLabel(p.postable_type)}
                                    </td>
                                    <td className="px-4 py-2">
                                        <span className={`font-mono text-[10px] tracking-widest uppercase ${PLATFORM_COLORS[p.platform]?.split(' ')[1] || 'text-text-muted'}`}>
                                            {p.platform}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2 font-mono text-[10px] text-text-dim">
                                        {p.published_at || p.created_at
                                            ? new Date(p.published_at || p.created_at).toLocaleString()
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-2 font-mono text-[10px] text-red-bright truncate max-w-[200px]" title={p.error || ''}>
                                        {p.error ? p.error.slice(0, 80) : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {modal && <ChannelModal channel={modal === 'add' ? null : modal} onClose={() => setModal(null)} />}
        </AppLayout>
    );
}
