import { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

function getAllTimezones() {
    try {
        if (typeof Intl.supportedValuesOf === 'function') {
            return Intl.supportedValuesOf('timeZone');
        }
    } catch { /* noop */ }
    return ['UTC', 'Europe/Berlin', 'Europe/London', 'America/New_York', 'Asia/Tokyo'];
}

export default function Preferences({ subscriber, threads = [], subscribed_thread_ids = [], thread_prefs = {} }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flashSuccess = props.flash?.success;

    const allTimezones = useMemo(() => getAllTimezones(), []);

    // Build initial threadsState: for each available thread, flags for digest/critical
    const initialThreads = useMemo(() => {
        const map = {};
        for (const t of threads) {
            const subscribed = subscribed_thread_ids.includes(t.id);
            const pref = thread_prefs[t.id] || {};
            map[t.id] = {
                subscribed,
                wants_digest: subscribed ? (pref.wants_digest ?? true) : true,
                wants_critical: subscribed ? (pref.wants_critical ?? true) : true,
            };
        }
        return map;
    }, [threads, subscribed_thread_ids, thread_prefs]);

    const [threadState, setThreadState] = useState(initialThreads);

    const { data, setData, processing, errors } = useForm({
        timezone: subscriber.timezone,
        locale: subscriber.locale,
        wants_global_digest: !!subscriber.wants_global_digest,
    });

    function toggleSubscribed(id) {
        setThreadState((s) => ({ ...s, [id]: { ...s[id], subscribed: !s[id].subscribed } }));
    }

    function togglePref(id, key) {
        setThreadState((s) => ({ ...s, [id]: { ...s[id], [key]: !s[id][key] } }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        const subscribedThreads = Object.entries(threadState)
            .filter(([, v]) => v.subscribed)
            .map(([id, v]) => ({
                id: parseInt(id, 10),
                wants_digest: v.wants_digest,
                wants_critical: v.wants_critical,
            }));

        router.patch(`/newsletter/preferences/${window.location.pathname.split('/').pop()}`, {
            timezone: data.timezone,
            locale: data.locale,
            wants_global_digest: data.wants_global_digest,
            threads: subscribedThreads,
        }, { preserveScroll: true });
    }

    const inputClass = 'w-full bg-surface-2 border border-border-mid rounded px-3 py-2.5 font-mono text-sm text-text-primary focus:outline-none focus:border-green-base transition-colors';
    const labelClass = 'block font-mono text-xs tracking-widest uppercase text-text-muted mb-1.5';

    return (
        <AppLayout breadcrumbs={[{ label: t('newsletter.preferencesTitle') }]}>
            <Head title={t('newsletter.preferencesTitle')} />

            <div className="max-w-2xl mx-auto">
                <h1 className="font-display text-3xl tracking-wider text-green-bright uppercase mb-2">
                    {t('newsletter.preferencesTitle')}
                </h1>
                <p className="font-mono text-xs text-text-dim mb-8">{subscriber.email}</p>

                {flashSuccess && (
                    <div className="mb-5 px-4 py-3 bg-green-dim/30 border border-green-base rounded">
                        <p className="font-mono text-xs tracking-widest uppercase text-green-bright">
                            ✓ {t('newsletter.preferencesSaved')}
                        </p>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic settings */}
                    <div className="bg-surface-1 border border-border-mid rounded p-5 space-y-4">
                        <div>
                            <label htmlFor="timezone" className={labelClass}>{t('newsletter.timezoneLabel')}</label>
                            <select
                                id="timezone"
                                value={data.timezone}
                                onChange={(e) => setData('timezone', e.target.value)}
                                className={inputClass}
                            >
                                {allTimezones.map((tz) => <option key={tz} value={tz}>{tz}</option>)}
                            </select>
                            {errors.timezone && <p className="mt-1.5 font-mono text-xs text-red-bright">{errors.timezone}</p>}
                        </div>

                        <div>
                            <label htmlFor="locale" className={labelClass}>{t('newsletter.localeLabel')}</label>
                            <select
                                id="locale"
                                value={data.locale}
                                onChange={(e) => setData('locale', e.target.value)}
                                className={inputClass}
                            >
                                <option value="en">{t('newsletter.localeEn')}</option>
                                <option value="de">{t('newsletter.localeDe')}</option>
                            </select>
                        </div>

                        <label className="flex items-start gap-3 cursor-pointer pt-2 border-t border-border-subtle">
                            <input
                                type="checkbox"
                                checked={data.wants_global_digest}
                                onChange={(e) => setData('wants_global_digest', e.target.checked)}
                                className="mt-1 w-4 h-4 accent-green-base"
                            />
                            <div className="flex-1">
                                <div className="font-sans text-sm text-text-primary">{t('newsletter.globalDigestLabel')}</div>
                                <div className="font-mono text-xs text-text-muted mt-0.5">{t('newsletter.globalDigestHint')}</div>
                            </div>
                        </label>
                    </div>

                    {/* Threads */}
                    {threads.length > 0 && (
                        <div className="bg-surface-1 border border-border-mid rounded p-5">
                            <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-1">
                                {t('newsletter.threadsLabel')}
                            </h2>
                            <p className="font-mono text-xs text-text-dim mb-4">{t('newsletter.threadsHint')}</p>

                            <div className="divide-y divide-border-subtle">
                                {threads.map((thread) => {
                                    const state = threadState[thread.id] || { subscribed: false, wants_digest: true, wants_critical: true };
                                    return (
                                        <div key={thread.id} className="py-3">
                                            <label className="flex items-start gap-3 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={state.subscribed}
                                                    onChange={() => toggleSubscribed(thread.id)}
                                                    className="mt-1 w-4 h-4 accent-green-base"
                                                />
                                                <div className="flex-1 min-w-0">
                                                    <div className="font-sans text-sm text-text-primary">{thread.name}</div>
                                                    {thread.summary && (
                                                        <div className="font-mono text-xs text-text-muted mt-0.5 line-clamp-2">
                                                            {thread.summary}
                                                        </div>
                                                    )}
                                                </div>
                                            </label>
                                            {state.subscribed && (
                                                <div className="mt-3 ml-7 flex items-center gap-5">
                                                    <label className="flex items-center gap-2 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            checked={state.wants_digest}
                                                            onChange={() => togglePref(thread.id, 'wants_digest')}
                                                            className="w-3.5 h-3.5 accent-green-base"
                                                        />
                                                        <span className="font-mono text-xs text-text-secondary">{t('newsletter.perThreadDigest')}</span>
                                                    </label>
                                                    <label className="flex items-center gap-2 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            checked={state.wants_critical}
                                                            onChange={() => togglePref(thread.id, 'wants_critical')}
                                                            className="w-3.5 h-3.5 accent-green-base"
                                                        />
                                                        <span className="font-mono text-xs text-text-secondary">{t('newsletter.perThreadCritical')}</span>
                                                    </label>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Actions */}
                    <div className="flex items-center justify-between">
                        <Link
                            href={`/newsletter/unsubscribe/${subscriber.unsubscribe_token}`}
                            className="font-mono text-xs tracking-widest uppercase text-text-muted hover:text-red-bright transition-colors"
                        >
                            {t('newsletter.unsubscribeCta')}
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="font-mono text-xs tracking-widest uppercase px-6 py-3 border border-green-base bg-green-base/20 text-green-bright hover:bg-green-dim hover:border-green-bright disabled:opacity-40 transition-colors rounded"
                        >
                            {processing ? t('newsletter.submitting') : t('newsletter.save')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
