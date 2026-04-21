import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';

const CATEGORIES = [
    'airstrike', 'artillery', 'troop_movement', 'humanitarian',
    'infrastructure', 'diplomatic', 'naval', 'cyber', 'protest', 'other',
];

const STATUSES = ['unverified', 'corroborated', 'confirmed', 'disputed', 'retracted'];

const selectClass =
    'bg-surface-2 border border-border-mid text-text-secondary font-mono text-xs tracking-wide px-3 py-1.5 rounded focus:outline-none focus:border-green-base focus:text-text-primary transition-colors';

export default function FilterBar({ filters = {}, baseUrl = '' }) {
    const { t } = useTranslation();

    function applyFilter(key, value) {
        const params = new URLSearchParams(window.location.search);
        if (value) {
            params.set(key, value);
        } else {
            params.delete(key);
        }
        params.delete('page');
        router.get(baseUrl || window.location.pathname, Object.fromEntries(params), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <div className="flex flex-wrap items-center gap-2 sm:gap-3 py-3 px-3 sm:px-4 bg-surface-1 border border-border-mid rounded">
            <span className="font-mono text-xs tracking-widest uppercase text-text-muted">
                {t('common.filter')}
            </span>

            {/* Category */}
            <select
                className={selectClass}
                value={filters.category || ''}
                onChange={e => applyFilter('category', e.target.value)}
            >
                <option value="">{t('event.category')}: {t('common.noData').replace('No data', 'All')}</option>
                {CATEGORIES.map(cat => (
                    <option key={cat} value={cat}>{t(`category.${cat}`)}</option>
                ))}
            </select>

            {/* Status */}
            <select
                className={selectClass}
                value={filters.status || ''}
                onChange={e => applyFilter('status', e.target.value)}
            >
                <option value="">{t('event.status')}: All</option>
                {STATUSES.map(s => (
                    <option key={s} value={s}>{t(`status.${s}`)}</option>
                ))}
            </select>

            {/* Severity min */}
            <div className="flex items-center gap-2">
                <span className="font-mono text-xs text-text-muted">{t('event.severity')} &ge;</span>
                <select
                    className={selectClass}
                    value={filters.min_severity || ''}
                    onChange={e => applyFilter('min_severity', e.target.value)}
                >
                    <option value="">Any</option>
                    {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map(n => (
                        <option key={n} value={n}>{n}</option>
                    ))}
                </select>
            </div>

            {/* Date from */}
            <input
                type="date"
                className={selectClass}
                value={filters.date_from || ''}
                onChange={e => applyFilter('date_from', e.target.value)}
                title="From date"
            />
            <span className="font-mono text-xs text-text-muted">—</span>
            <input
                type="date"
                className={selectClass}
                value={filters.date_to || ''}
                onChange={e => applyFilter('date_to', e.target.value)}
                title="To date"
            />

            {/* Clear */}
            {Object.keys(filters).some(k => filters[k]) && (
                <button
                    onClick={() => router.get(baseUrl || window.location.pathname, {}, { replace: true })}
                    className="font-mono text-xs text-text-muted hover:text-red-bright transition-colors underline"
                >
                    Clear
                </button>
            )}
        </div>
    );
}
