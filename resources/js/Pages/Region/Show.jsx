import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

const CATEGORY_COLORS = {
    war: '#E74C3C',
    terrorism: '#E74C3C',
    cyber: '#3498DB',
    protest: '#F39C12',
    disaster: '#E67E22',
    diplomacy: '#2ECC71',
    economic: '#9B59B6',
};

function sevColor(severity) {
    if (severity >= 7) return '#E74C3C';
    if (severity >= 4) return '#F59E0B';
    return '#52A844';
}

function CountryCard({ country }) {
    const topCategories = Object.entries(country.category_breakdown || {})
        .sort((a, b) => b[1] - a[1])
        .slice(0, 3);

    return (
        <Link
            href={`/country/${country.code}`}
            className="block border border-border-mid rounded bg-surface-1 hover:bg-surface-2 hover:border-green-base transition-all group"
            style={{ borderLeft: `3px solid ${sevColor(country.threat_level)}` }}
        >
            <div className="p-4">
                <div className="flex items-start justify-between gap-2 mb-2">
                    <h3 className="font-sans text-sm font-bold text-text-primary uppercase tracking-wide group-hover:text-green-bright transition-colors">
                        {country.name}
                    </h3>
                    <span
                        className="flex-shrink-0 font-mono text-[10px] font-bold px-1.5 py-0.5 rounded"
                        style={{
                            backgroundColor: sevColor(country.threat_level) + '33',
                            color: sevColor(country.threat_level),
                        }}
                    >
                        {country.threat_level}/10
                    </span>
                </div>

                {/* Category tags */}
                {topCategories.length > 0 && (
                    <div className="flex flex-wrap gap-1 mb-2">
                        {topCategories.map(([cat, count]) => (
                            <span
                                key={cat}
                                className="font-mono text-[10px] px-1.5 py-px rounded uppercase"
                                style={{
                                    backgroundColor: `${CATEGORY_COLORS[cat] || '#555'}22`,
                                    color: CATEGORY_COLORS[cat] || '#888',
                                }}
                            >
                                {cat}
                            </span>
                        ))}
                    </div>
                )}

                {/* Stats */}
                <div className="flex items-center gap-3 font-mono text-[10px] text-text-dim">
                    <span>{country.event_count_24h} in 24h</span>
                    <span>{country.event_count_total} total</span>
                    <span>Max: {country.max_severity}</span>
                </div>
            </div>
        </Link>
    );
}

function ConflictRow({ conflict }) {
    return (
        <Link
            href={`/conflict/${conflict.slug}`}
            className="flex items-center gap-3 py-3 px-4 hover:bg-surface-2 transition-colors border-b border-border-subtle last:border-b-0 group"
        >
            <span
                className="w-2 h-2 rounded-full flex-shrink-0"
                style={{ backgroundColor: sevColor(conflict.max_severity) }}
            />
            <div className="flex-1 min-w-0">
                <h4 className="text-sm text-text-primary font-semibold group-hover:text-green-bright transition-colors truncate">
                    {conflict.name}
                </h4>
                <div className="flex items-center gap-2 mt-0.5">
                    {(conflict.countries || []).slice(0, 4).map(c => (
                        <span key={c} className="font-mono text-[10px] text-text-dim uppercase">{c}</span>
                    ))}
                </div>
            </div>
            <div className="text-right flex-shrink-0">
                <span
                    className="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded"
                    style={{
                        backgroundColor: sevColor(conflict.max_severity) + '33',
                        color: sevColor(conflict.max_severity),
                    }}
                >
                    SEV {conflict.max_severity}
                </span>
                <div className="font-mono text-[10px] text-text-dim mt-1">
                    {conflict.event_count_24h} in 24h
                </div>
            </div>
        </Link>
    );
}

export default function RegionShow({ region, countries, activeConflicts }) {
    const { t } = useTranslation();

    return (
        <AppLayout breadcrumbs={[
            { label: 'Conflicts', href: '/conflicts' },
            { label: region.name },
        ]}>
            <div className="max-w-6xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright mb-2">
                        {region.name}
                    </h1>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-xs text-text-dim">
                        <span><strong className="text-text-primary">{region.country_count}</strong> countries</span>
                        <span><strong className="text-text-primary">{region.event_count_24h}</strong> events in 24h</span>
                        <span><strong className="text-text-primary">{region.event_count_total}</strong> total events</span>
                        <span>Max Severity: <strong className="text-text-primary">{region.max_severity}</strong></span>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Country cards grid */}
                    <div className="lg:col-span-2">
                        <h2 className="font-sans text-sm font-bold text-text-primary uppercase tracking-widest mb-3">
                            {t('region.countries', 'Countries')}
                        </h2>
                        {countries.length > 0 ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                {countries.map(c => (
                                    <CountryCard key={c.code} country={c} />
                                ))}
                            </div>
                        ) : (
                            <div className="bg-surface-1 border border-border-mid rounded p-6 text-center font-mono text-xs text-text-dim">
                                No country intelligence data available for this region yet.
                            </div>
                        )}
                    </div>

                    {/* Active conflicts sidebar */}
                    <div>
                        <h2 className="font-sans text-sm font-bold text-text-primary uppercase tracking-widest mb-3">
                            {t('region.conflicts', 'Active Conflicts')}
                        </h2>
                        {activeConflicts.length > 0 ? (
                            <div className="border border-border-mid rounded bg-surface-1 overflow-hidden">
                                {activeConflicts.map(conflict => (
                                    <ConflictRow key={conflict.id} conflict={conflict} />
                                ))}
                            </div>
                        ) : (
                            <div className="bg-surface-1 border border-border-mid rounded p-6 text-center font-mono text-xs text-text-dim">
                                No active conflicts tracked in this region.
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
