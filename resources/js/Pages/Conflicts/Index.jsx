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

function ConflictCard({ conflict }) {
    return (
        <Link
            href={`/conflict/${conflict.slug}`}
            className="block border border-border-mid rounded bg-surface-1 hover:bg-surface-2 hover:border-green-base transition-all group"
            style={{ borderLeft: `3px solid ${sevColor(conflict.max_severity)}` }}
        >
            <div className="p-4">
                {/* Header */}
                <div className="flex items-start justify-between gap-2 mb-2">
                    <h3 className="font-sans text-sm font-bold text-text-primary uppercase tracking-wide group-hover:text-green-bright transition-colors">
                        {conflict.name}
                    </h3>
                    <span
                        className="flex-shrink-0 font-mono text-[10px] font-bold px-1.5 py-0.5 rounded"
                        style={{
                            backgroundColor: sevColor(conflict.max_severity) + '33',
                            color: sevColor(conflict.max_severity),
                            border: `1px solid ${sevColor(conflict.max_severity)}`,
                        }}
                    >
                        SEV {conflict.max_severity}
                    </span>
                </div>

                {/* Summary */}
                {conflict.summary && (
                    <p className="text-xs text-text-secondary leading-relaxed line-clamp-2 mb-3">
                        {conflict.summary}
                    </p>
                )}

                {/* Tags */}
                <div className="flex flex-wrap gap-1 mb-3">
                    {(conflict.countries || []).slice(0, 5).map(c => (
                        <span
                            key={c}
                            className="font-mono text-[10px] px-1.5 py-px bg-surface-3 border border-border-mid text-text-muted rounded uppercase"
                        >
                            {c}
                        </span>
                    ))}
                    {(conflict.categories || []).map(c => (
                        <span
                            key={c}
                            className="font-mono text-[10px] px-1.5 py-px rounded uppercase"
                            style={{
                                backgroundColor: `${CATEGORY_COLORS[c] || '#555'}22`,
                                color: CATEGORY_COLORS[c] || '#888',
                                border: `1px solid ${CATEGORY_COLORS[c] || '#555'}44`,
                            }}
                        >
                            {c}
                        </span>
                    ))}
                </div>

                {/* Stats */}
                <div className="flex items-center gap-4 font-mono text-[10px] text-text-dim">
                    <span>{conflict.event_count_24h} in 24h</span>
                    <span>{conflict.event_count_total} total</span>
                    {conflict.sub_thread_count >= 2 && (
                        <span className="text-amber">{conflict.sub_thread_count} sub-threads</span>
                    )}
                </div>
            </div>
        </Link>
    );
}

function CountryTrackerCard({ code, name }) {
    return (
        <Link
            href={`/country/${code}`}
            className="block border border-border-mid rounded bg-surface-1 hover:bg-surface-2 hover:border-green-base transition-all p-3 text-center group"
        >
            <span className="font-sans text-sm font-bold text-text-primary uppercase tracking-wide group-hover:text-green-bright transition-colors">
                {name}
            </span>
            <p className="font-mono text-[10px] text-text-dim mt-1">
                Track events in real time
            </p>
        </Link>
    );
}

const COUNTRY_TRACKERS = [
    { code: 'UA', name: 'Ukraine' },
    { code: 'IL', name: 'Israel' },
    { code: 'PS', name: 'Palestine' },
    { code: 'RU', name: 'Russia' },
    { code: 'SY', name: 'Syria' },
    { code: 'SD', name: 'Sudan' },
    { code: 'MM', name: 'Myanmar' },
    { code: 'YE', name: 'Yemen' },
    { code: 'IR', name: 'Iran' },
    { code: 'LB', name: 'Lebanon' },
    { code: 'TW', name: 'Taiwan' },
    { code: 'KP', name: 'North Korea' },
    { code: 'ET', name: 'Ethiopia' },
    { code: 'SO', name: 'Somalia' },
    { code: 'CD', name: 'DR Congo' },
];

const REGION_LINKS = [
    { slug: 'middle-east', name: 'Middle East' },
    { slug: 'europe', name: 'Eastern Europe' },
    { slug: 'africa', name: 'Africa' },
    { slug: 'east-asia', name: 'East Asia' },
    { slug: 'south-asia', name: 'South Asia' },
    { slug: 'americas', name: 'Americas' },
];

export default function ConflictsIndex({ conflicts, continents }) {
    const { t } = useTranslation();

    return (
        <AppLayout breadcrumbs={[{ label: 'Conflicts' }]}>
            <div className="max-w-6xl mx-auto">
                {/* Page header */}
                <div className="mb-8">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright mb-2">
                        {t('conflicts.title', 'Active Conflicts & War Zones')}
                    </h1>
                    <p className="font-mono text-xs text-text-secondary leading-relaxed max-w-3xl">
                        {t('conflicts.subtitle', `ClashMonitor tracks ${conflicts.length} active conflict zones worldwide using real-time multi-source intelligence. Select a conflict below to view live events, AI-classified severity scoring, and conflict thread analysis.`)}
                    </p>
                </div>

                {/* Regions quick-nav */}
                <div className="mb-8">
                    <h2 className="font-sans text-sm font-bold text-text-primary uppercase tracking-widest mb-3">
                        {t('conflicts.regions', 'Regions')}
                    </h2>
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
                        {REGION_LINKS.map(r => (
                            <Link
                                key={r.slug}
                                href={`/region/${r.slug}`}
                                className="block border border-border-mid rounded bg-surface-1 hover:bg-surface-2 hover:border-green-base transition-all p-3 text-center group"
                            >
                                <span className="font-sans text-xs font-bold text-text-primary uppercase tracking-wide group-hover:text-green-bright transition-colors">
                                    {r.name}
                                </span>
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Active conflicts */}
                <div className="mb-8">
                    <h2 className="font-sans text-sm font-bold text-text-primary uppercase tracking-widest mb-3">
                        {t('conflicts.active', 'Active Conflicts')}
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {conflicts.map(conflict => (
                            <ConflictCard key={conflict.id} conflict={conflict} />
                        ))}
                    </div>
                </div>

                {/* Country trackers */}
                <div className="mb-8">
                    <h2 className="font-sans text-sm font-bold text-text-primary uppercase tracking-widest mb-3">
                        {t('conflicts.countryTrackers', 'Country Trackers')}
                    </h2>
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2">
                        {COUNTRY_TRACKERS.map(ct => (
                            <CountryTrackerCard key={ct.code} {...ct} />
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
