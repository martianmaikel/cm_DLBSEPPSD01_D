import { useMemo, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

function detectTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
}

function getAllTimezones() {
    try {
        if (typeof Intl.supportedValuesOf === 'function') {
            return Intl.supportedValuesOf('timeZone');
        }
    } catch {
        // fall through
    }
    // Fallback curated list for browsers without supportedValuesOf
    return [
        'UTC', 'Europe/Berlin', 'Europe/London', 'Europe/Paris', 'Europe/Madrid',
        'Europe/Rome', 'Europe/Amsterdam', 'Europe/Vienna', 'Europe/Warsaw',
        'Europe/Moscow', 'Europe/Istanbul', 'Europe/Kyiv',
        'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
        'America/Toronto', 'America/Sao_Paulo', 'America/Mexico_City',
        'Asia/Dubai', 'Asia/Jerusalem', 'Asia/Tehran', 'Asia/Kolkata',
        'Asia/Bangkok', 'Asia/Singapore', 'Asia/Shanghai', 'Asia/Tokyo', 'Asia/Seoul',
        'Australia/Sydney', 'Pacific/Auckland',
        'Africa/Cairo', 'Africa/Lagos', 'Africa/Johannesburg',
    ];
}

export default function Subscribe({ threads = [] }) {
    const { t, i18n } = useTranslation();

    const detectedTz = useMemo(() => detectTimezone(), []);
    const allTimezones = useMemo(() => getAllTimezones(), []);

    const { data, setData, post, processing, errors } = useForm({
        email: '',
        timezone: detectedTz,
        locale: i18n.language === 'de' ? 'de' : 'en',
        thread_ids: [],
    });

    function toggleThread(id) {
        const current = data.thread_ids;
        setData('thread_ids', current.includes(id) ? current.filter(x => x !== id) : [...current, id]);
    }

    // Sync form locale with active i18n locale as user toggles the header switcher
    useEffect(() => {
        setData('locale', i18n.language === 'de' ? 'de' : 'en');
    }, [i18n.language]);

    function handleSubmit(e) {
        e.preventDefault();
        post('/newsletter/subscribe');
    }

    const inputClass = 'w-full bg-surface-2 border border-border-mid rounded px-3 py-2.5 font-mono text-sm text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors';
    const labelClass = 'block font-mono text-xs tracking-widest uppercase text-text-muted mb-1.5';

    const breadcrumbs = [{ label: t('newsletter.pageTitle') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>

            <div className="max-w-xl mx-auto">
                <h1 className="font-display text-4xl tracking-wider text-green-bright uppercase mb-2">
                    {t('newsletter.pageTitle')}
                </h1>
                <p className="font-sans text-sm text-text-secondary leading-relaxed mb-8">
                    {t('newsletter.tagline')}
                </p>

                <form onSubmit={handleSubmit} className="bg-surface-1 border border-border-mid rounded p-6 space-y-5">
                    <div>
                        <label htmlFor="email" className={labelClass}>
                            {t('newsletter.emailLabel')}
                        </label>
                        <input
                            id="email"
                            type="email"
                            required
                            autoComplete="email"
                            placeholder={t('newsletter.emailPlaceholder')}
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className={inputClass}
                        />
                        {errors.email && (
                            <p className="mt-1.5 font-mono text-xs text-red-bright">{errors.email}</p>
                        )}
                    </div>

                    <div>
                        <label htmlFor="timezone" className={labelClass}>
                            {t('newsletter.timezoneLabel')}
                        </label>
                        <select
                            id="timezone"
                            value={data.timezone}
                            onChange={(e) => setData('timezone', e.target.value)}
                            className={inputClass}
                        >
                            {allTimezones.map((tz) => (
                                <option key={tz} value={tz}>{tz}</option>
                            ))}
                        </select>
                        {data.timezone === detectedTz && (
                            <p className="mt-1.5 font-mono text-xs text-text-dim">
                                {t('newsletter.timezoneDetected')}
                            </p>
                        )}
                        {errors.timezone && (
                            <p className="mt-1.5 font-mono text-xs text-red-bright">{errors.timezone}</p>
                        )}
                    </div>

                    <div>
                        <label htmlFor="locale" className={labelClass}>
                            {t('newsletter.localeLabel')}
                        </label>
                        <select
                            id="locale"
                            value={data.locale}
                            onChange={(e) => setData('locale', e.target.value)}
                            className={inputClass}
                        >
                            <option value="en">{t('newsletter.localeEn')}</option>
                            <option value="de">{t('newsletter.localeDe')}</option>
                        </select>
                        {errors.locale && (
                            <p className="mt-1.5 font-mono text-xs text-red-bright">{errors.locale}</p>
                        )}
                    </div>

                    {threads.length > 0 && (
                        <div>
                            <label className={labelClass}>
                                {t('newsletter.threadsLabel')}
                            </label>
                            <p className="font-mono text-xs text-text-dim mb-2">
                                {t('newsletter.threadsHint')}
                            </p>
                            <div className="max-h-56 overflow-y-auto border border-border-mid rounded divide-y divide-border-subtle">
                                {threads.map((thread) => {
                                    const checked = data.thread_ids.includes(thread.id);
                                    return (
                                        <label
                                            key={thread.id}
                                            className={`flex items-start gap-3 px-3 py-2.5 cursor-pointer transition-colors ${
                                                checked ? 'bg-green-dim/40' : 'hover:bg-surface-2'
                                            }`}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                onChange={() => toggleThread(thread.id)}
                                                className="mt-0.5 w-4 h-4 accent-green-base flex-shrink-0"
                                            />
                                            <div className="flex-1 min-w-0">
                                                <div className="font-sans text-sm text-text-primary truncate">{thread.name}</div>
                                                {thread.summary && (
                                                    <div className="font-mono text-xs text-text-muted mt-0.5 line-clamp-2">
                                                        {thread.summary}
                                                    </div>
                                                )}
                                            </div>
                                            {thread.max_severity > 0 && (
                                                <span className="font-mono text-xs text-text-dim tracking-wider flex-shrink-0">
                                                    SEV {thread.max_severity}
                                                </span>
                                            )}
                                        </label>
                                    );
                                })}
                            </div>
                            {errors.thread_ids && (
                                <p className="mt-1.5 font-mono text-xs text-red-bright">{errors.thread_ids}</p>
                            )}
                        </div>
                    )}

                    <p className="font-sans text-xs text-text-muted leading-relaxed">
                        {t('newsletter.privacy')}
                    </p>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full font-mono text-xs tracking-widest uppercase py-3 border border-green-base bg-green-base/20 text-green-bright hover:bg-green-dim hover:border-green-bright disabled:opacity-40 transition-colors rounded"
                    >
                        {processing ? t('newsletter.submitting') : t('newsletter.submit')}
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}
