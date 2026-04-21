import { useTranslation } from 'react-i18next';
import { useDashboard } from '../../Contexts/DashboardContext';

const TIME_RANGES = [
    { value: '24h', labelKey: 'dashboard.filters.last24h' },
    { value: '48h', labelKey: 'dashboard.filters.last48h' },
    { value: '7d', labelKey: 'dashboard.filters.last7d' },
    { value: 'all', labelKey: 'dashboard.filters.allTime' },
];

const CATEGORIES = [
    'airstrike', 'artillery', 'troop_movement', 'humanitarian',
    'infrastructure', 'diplomatic', 'naval', 'cyber', 'protest',
];

const STATUSES = [
    'unverified', 'corroborated', 'confirmed', 'disputed', 'retracted',
];

const REGIONS = [
    { value: 'africa', label: 'Africa' },
    { value: 'asia', label: 'Asia' },
    { value: 'europe', label: 'Europe' },
    { value: 'middle-east', label: 'Middle East' },
    { value: 'north-america', label: 'North America' },
    { value: 'south-america', label: 'South America' },
    { value: 'oceania', label: 'Oceania' },
];

const selectClass = 'bg-surface-1 border border-border-mid rounded px-2 py-1 font-mono text-xs text-text-secondary focus:border-green-base focus:outline-none hover:border-border-active transition-colors appearance-none cursor-pointer flex-shrink-0';

export default function DashboardFilterBar() {
    const { t } = useTranslation();
    const { filters, setFilters, resetFilters, isStale, lastUpdated, selectedCountry, setSelectedCountry } = useDashboard();

    return (
        <div className="flex items-center gap-2 overflow-x-auto md:flex-wrap px-3 py-2 bg-surface-0 border-b border-border-mid scrollbar-hide">
            {/* Time range */}
            <select
                value={filters.timeRange}
                onChange={e => setFilters({ timeRange: e.target.value })}
                className={selectClass}
            >
                {TIME_RANGES.map(tr => (
                    <option key={tr.value} value={tr.value}>
                        {t(tr.labelKey, tr.value)}
                    </option>
                ))}
            </select>

            {/* Severity min */}
            <select
                value={filters.severityMin}
                onChange={e => setFilters({ severityMin: e.target.value })}
                className={selectClass}
            >
                <option value="">Min Sev</option>
                {[1,2,3,4,5,6,7,8,9,10].map(n => (
                    <option key={n} value={n}>{n}+</option>
                ))}
            </select>

            {/* Category */}
            <select
                value={filters.category}
                onChange={e => setFilters({ category: e.target.value })}
                className={selectClass}
            >
                <option value="">{t('event.category', 'Category')}</option>
                {CATEGORIES.map(c => (
                    <option key={c} value={c}>{c}</option>
                ))}
            </select>

            {/* Region */}
            <select
                value={filters.region}
                onChange={e => setFilters({ region: e.target.value })}
                className={selectClass}
            >
                <option value="">{t('dashboard.filters.region', 'Region')}</option>
                {REGIONS.map(r => (
                    <option key={r.value} value={r.value}>{r.label}</option>
                ))}
            </select>

            {/* Status */}
            <select
                value={filters.status}
                onChange={e => setFilters({ status: e.target.value })}
                className={selectClass}
            >
                <option value="">{t('event.status', 'Status')}</option>
                {STATUSES.map(s => (
                    <option key={s} value={s}>{s}</option>
                ))}
            </select>

            {/* Country chip */}
            {selectedCountry && (
                <span className="inline-flex items-center gap-1 font-mono text-xs bg-surface-1 border border-border-mid rounded px-2 py-1 text-text-secondary flex-shrink-0">
                    {selectedCountry.name}
                    <button
                        onClick={() => setSelectedCountry(null)}
                        className="text-text-dim hover:text-red-bright transition-colors leading-none"
                        aria-label="Clear country filter"
                    >
                        ×
                    </button>
                </span>
            )}

            {/* Clear */}
            <button
                onClick={resetFilters}
                className="font-mono text-xs text-text-muted hover:text-red-bright transition-colors px-2 py-1 flex-shrink-0"
            >
                Clear
            </button>

            {/* Stale data indicator */}
            {isStale && (
                <span className="ml-auto font-mono text-xs text-amber animate-pulse">
                    {t('dashboard.staleData', 'Data may be stale')} · {lastUpdated?.toLocaleTimeString()}
                </span>
            )}
        </div>
    );
}
