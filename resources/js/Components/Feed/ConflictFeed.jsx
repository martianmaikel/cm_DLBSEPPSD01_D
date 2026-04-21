import { useState, useMemo, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import i18n from '../../i18n';
import { useDashboard } from '../../Contexts/DashboardContext';

function localizedTitle(event) {
    return (i18n.language === 'de' && event.title_de) || event.title;
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

function fullDateTime(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', timeZoneName: 'short',
    });
}

function sevColor(severity) {
    if (severity >= 7) return '#E74C3C';
    if (severity >= 4) return '#F59E0B';
    return '#52A844';
}

const CATEGORY_COLORS = {
    war: '#E74C3C',
    terrorism: '#E74C3C',
    cyber: '#3498DB',
    protest: '#F39C12',
    disaster: '#E67E22',
    diplomacy: '#2ECC71',
    economic: '#9B59B6',
};

const CATEGORY_SHORT = {
    war: 'WAR',
    terrorism: 'TRR',
    cyber: 'CYB',
    protest: 'PRT',
    disaster: 'DST',
    diplomacy: 'DPL',
    economic: 'ECN',
};

const STATUS_STYLES = {
    confirmed:     { bg: '#2ECC71', text: '#000', label: 'CONFIRMED' },
    corroborated:  { bg: '#3498DB', text: '#fff', label: 'CORROBORATED' },
    unverified:    { bg: '#555', text: '#aaa', label: 'UNVERIFIED' },
    pending_confirmation: { bg: '#F59E0B', text: '#000', label: 'PENDING' },
    disputed:      { bg: '#E74C3C', text: '#fff', label: 'DISPUTED' },
    retracted:     { bg: '#666', text: '#999', label: 'RETRACTED' },
};

const SEV_FILTERS = [
    { key: 'all', label: 'ALL' },
    { key: 'low', label: 'LOW', min: 1, max: 3 },
    { key: 'med', label: 'MED', min: 4, max: 6 },
    { key: 'high', label: 'HIGH', min: 7, max: 9 },
    { key: 'crit', label: 'CRIT', min: 10, max: 10 },
];

/* ── Single event row inside a subthread ── */
function EventRow({ event, onClick }) {
    const cat = event.category || 'war';
    const catShort = CATEGORY_SHORT[cat] || cat.toUpperCase().slice(0, 3);
    const catColor = CATEGORY_COLORS[cat] || '#888';
    const statusKey = event.status || 'unverified';
    const statusStyle = STATUS_STYLES[statusKey] || STATUS_STYLES.unverified;
    const srcCount = (event.corroboration_count || 0) + 1;

    return (
        <button
            onClick={() => onClick(event)}
            className="w-full text-left px-3 py-2 hover:bg-surface-2 transition-colors flex items-start gap-2.5 border-b border-border-subtle last:border-b-0 group"
        >
            {/* Category short code */}
            <div className="flex flex-col items-center gap-0.5 pt-0.5 flex-shrink-0 w-8">
                <span
                    className="font-mono text-[9px] font-bold tracking-wider"
                    style={{ color: catColor }}
                >
                    {catShort}
                </span>
                <span
                    className="font-mono text-[8px] px-1 rounded"
                    style={{ color: catColor + 'cc' }}
                >
                    {(event.subcategory || cat).toUpperCase().slice(0, 3)}
                </span>
            </div>

            {/* Content */}
            <div className="flex-1 min-w-0">
                {/* Status badge + title */}
                <div className="flex items-center gap-1.5 mb-0.5">
                    <span
                        className="font-mono text-[8px] font-bold px-1 py-px rounded flex-shrink-0"
                        style={{
                            backgroundColor: statusStyle.bg + '33',
                            color: statusStyle.bg,
                        }}
                    >
                        {statusStyle.label}
                    </span>
                </div>
                <p className="text-xs text-text-primary leading-snug line-clamp-1 group-hover:text-green-bright transition-colors">
                    {localizedTitle(event)}
                </p>
                {/* Meta row: time | country | region | source */}
                <div className="flex items-center gap-1.5 mt-0.5 font-mono text-[9px] text-text-dim">
                    <span title={fullDateTime(event.occurred_at)}>{timeAgo(event.occurred_at)}</span>
                    {event.country && (
                        <>
                            <span className="text-border-mid">|</span>
                            <span className="uppercase">{event.country}</span>
                        </>
                    )}
                    {event.region && (
                        <>
                            <span className="text-border-mid">|</span>
                            <span className="uppercase">{event.region}</span>
                        </>
                    )}
                    <span className="text-border-mid">|</span>
                    <span>SRC {srcCount}/{srcCount}</span>
                </div>
            </div>

            {/* Severity badge */}
            <span
                className="flex-shrink-0 font-mono text-[10px] font-bold px-1.5 py-0.5 rounded mt-0.5"
                style={{
                    backgroundColor: sevColor(event.severity) + '33',
                    color: sevColor(event.severity),
                }}
            >
                SEV {event.severity}
            </span>
        </button>
    );
}

/* ── Subthread card with expandable events ── */
function SubThreadCard({ subThread, events, onEventClick }) {
    const [expanded, setExpanded] = useState(false);
    const categories = [...new Set(events.map(e => e.category).filter(Boolean))];
    const eventsIn24h = events.length;

    return (
        <div className="border-b border-border-subtle last:border-b-0">
            {/* Subthread header */}
            <button
                onClick={() => setExpanded(!expanded)}
                className="w-full text-left px-3 py-2.5 hover:bg-surface-2 transition-colors group"
            >
                <div className="flex items-center gap-1.5 mb-1">
                    <span className="font-mono text-[9px] text-text-dim tracking-wider">SUBTHREAD</span>
                    {categories.slice(0, 3).map(c => (
                        <span
                            key={c}
                            className="font-mono text-[8px] px-1 py-px rounded uppercase"
                            style={{
                                backgroundColor: `${CATEGORY_COLORS[c] || '#555'}22`,
                                color: CATEGORY_COLORS[c] || '#888',
                            }}
                        >
                            {c}
                        </span>
                    ))}
                    <span className="ml-auto font-mono text-[9px] text-text-dim">{timeAgo(subThread.latest_event_at)}</span>
                </div>

                <h4 className="text-xs font-bold text-text-primary uppercase tracking-wide group-hover:text-green-bright transition-colors mb-1">
                    {subThread.name}
                </h4>

                {subThread.summary && (
                    <p className="text-[11px] text-text-secondary line-clamp-1 mb-1.5">{subThread.summary}</p>
                )}

                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3 font-mono text-[9px] text-text-dim">
                        <span>{eventsIn24h} events in 24h</span>
                        <span>{subThread.event_count || eventsIn24h} total</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <span
                            className="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded"
                            style={{
                                backgroundColor: sevColor(subThread.max_severity || 0) + '33',
                                color: sevColor(subThread.max_severity || 0),
                            }}
                        >
                            SEV {subThread.max_severity || 0}
                        </span>
                        <span className="font-mono text-[9px] text-green-base group-hover:text-green-bright">
                            {expanded ? '▲' : '▼'}
                        </span>
                    </div>
                </div>
            </button>

            {/* Expanded events */}
            {expanded && events.length > 0 && (
                <div className="bg-surface-0 border-t border-border-subtle">
                    {events.map(event => (
                        <EventRow key={event.id} event={event} onClick={onEventClick} />
                    ))}
                </div>
            )}
        </div>
    );
}

/* ── Top-level conflict card ── */
function ConflictCard({ thread, events: propEvents, subThreads, onEventClick }) {
    const [expanded, setExpanded] = useState(false);
    const [segment, setSegment] = useState('all');
    const [fetchedEvents, setFetchedEvents] = useState(null);
    const [loadingEvents, setLoadingEvents] = useState(false);

    // Lazy-load events when expanded but no events available from dashboard payload
    useEffect(() => {
        if (!expanded || thread.id === '__unassigned__' || propEvents.length > 0) {
            return;
        }

        const controller = new AbortController();
        setLoadingEvents(true);

        fetch(`/api/threads/${thread.id}/events`, { signal: controller.signal })
            .then(res => res.json())
            .then(data => {
                setFetchedEvents(data);
                setLoadingEvents(false);
            })
            .catch(err => {
                if (err.name !== 'AbortError') setLoadingEvents(false);
            });

        return () => controller.abort();
    }, [expanded, thread.id, propEvents.length]);

    const events = propEvents.length > 0 ? propEvents : (fetchedEvents || propEvents);

    const countries = thread.countries || [...new Set(events.map(e => e.country).filter(Boolean))];
    const categories = thread.categories || [...new Set(events.map(e => e.category).filter(Boolean))];
    const [showAllCountries, setShowAllCountries] = useState(false);
    const visibleCountries = showAllCountries ? countries : countries.slice(0, 4);

    const totalEvents = thread.event_count_total || events.length;
    const events24h = thread.event_count_24h || events.length;
    const subCount = thread.sub_thread_count || subThreads.length;

    // Group events by sub-thread
    const { subThreadEvents, directEvents } = useMemo(() => {
        const map = new Map();
        subThreads.forEach(st => map.set(st.id, []));
        const direct = [];
        events.forEach(e => {
            if (map.has(e.conflict_thread_id)) {
                map.get(e.conflict_thread_id).push(e);
            } else {
                direct.push(e);
            }
        });
        return { subThreadEvents: map, directEvents: direct };
    }, [events, subThreads]);

    // Build sub-thread description for the card
    const subThreadNames = subThreads.slice(0, 3).map(s => s.name).join('; ');

    return (
        <div
            className="border-b border-border-mid last:border-b-0"
            style={{ borderLeft: `3px solid ${sevColor(thread.max_severity)}` }}
        >
            {/* ── Conflict header ── */}
            <div
                role="button"
                tabIndex={0}
                onClick={() => setExpanded(!expanded)}
                onKeyDown={e => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        setExpanded(!expanded);
                    }
                }}
                className="w-full text-left px-3 py-3 hover:bg-surface-2 transition-colors group cursor-pointer"
            >
                {/* Category tags + time */}
                <div className="flex items-center gap-1.5 mb-1.5">
                    {categories.map(c => (
                        <span
                            key={c}
                            className="font-mono text-[8px] px-1.5 py-px rounded uppercase font-bold tracking-wider"
                            style={{
                                backgroundColor: `${CATEGORY_COLORS[c] || '#555'}22`,
                                color: CATEGORY_COLORS[c] || '#888',
                            }}
                        >
                            {c}
                        </span>
                    ))}
                    <span className="ml-auto font-mono text-[9px] text-text-dim">
                        {timeAgo(thread.latest_event_at)}
                    </span>
                </div>

                {/* Title + SEV */}
                <div className="flex items-start justify-between gap-2 mb-1">
                    <h3 className="font-sans text-sm font-bold text-text-primary leading-snug uppercase tracking-wide group-hover:text-green-bright transition-colors">
                        {thread.name}
                    </h3>
                    <span
                        className="flex-shrink-0 font-mono text-[10px] font-bold px-1.5 py-0.5 rounded"
                        style={{
                            backgroundColor: sevColor(thread.max_severity) + '33',
                            color: sevColor(thread.max_severity),
                            border: `1px solid ${sevColor(thread.max_severity)}44`,
                        }}
                    >
                        SEV&nbsp;{thread.max_severity}
                    </span>
                </div>

                {/* Description: subthread names */}
                {subThreadNames && (
                    <p className="text-[11px] text-text-secondary leading-relaxed line-clamp-2 mb-2">
                        {subCount > 0 && <span className="text-text-dim">Includes: </span>}
                        {subThreadNames}
                    </p>
                )}

                {/* Country tags */}
                <div className="flex flex-wrap items-center gap-1 mb-2">
                    {visibleCountries.map(c => (
                        <span
                            key={c}
                            className="font-mono text-[9px] px-1.5 py-px bg-surface-3 border border-border-mid text-text-muted rounded uppercase tracking-wider"
                        >
                            {c}
                        </span>
                    ))}
                    {!showAllCountries && countries.length > 4 && (
                        <button
                            onClick={e => { e.stopPropagation(); setShowAllCountries(true); }}
                            className="font-mono text-[9px] text-green-base hover:text-green-bright transition-colors"
                        >
                            Show more ({countries.length - 4})
                        </button>
                    )}
                </div>

                {/* Stats row */}
                <div className="flex items-center gap-4 font-mono text-[9px] text-text-dim">
                    <span><strong className="text-text-secondary">{events24h}</strong> in 24h</span>
                    <span><strong className="text-text-secondary">{totalEvents}</strong> all-time</span>
                    {subCount >= 2 && (
                        <span><strong className="text-text-secondary">{subCount}</strong> subthreads</span>
                    )}
                </div>
            </div>

            {/* ── Expanded: segment tabs + subthreads + events ── */}
            {expanded && (
                <div className="bg-surface-0 border-t border-border-mid">
                    {/* Loading indicator when lazy-loading */}
                    {loadingEvents && events.length === 0 && (
                        <div className="px-3 py-4 text-center font-mono text-xs text-text-dim animate-pulse">
                            Loading events...
                        </div>
                    )}

                    {/* Segment tabs */}
                    {events.length > 0 && subThreads.length > 0 && (
                        <div className="flex border-b border-border-subtle overflow-x-auto">
                            <button
                                onClick={() => setSegment('all')}
                                className={`font-mono text-[9px] tracking-wider uppercase px-3 py-1.5 flex-shrink-0 border-b-2 transition-colors ${
                                    segment === 'all'
                                        ? 'text-green-bright border-green-bright'
                                        : 'text-text-dim border-transparent hover:text-text-secondary'
                                }`}
                            >
                                ALL ({events.length})
                            </button>
                            {subThreads.map(st => {
                                const stEvents = subThreadEvents.get(st.id) || [];
                                return (
                                    <button
                                        key={st.id}
                                        onClick={() => setSegment(st.id)}
                                        className={`font-mono text-[9px] tracking-wider uppercase px-3 py-1.5 flex-shrink-0 border-b-2 transition-colors truncate max-w-[150px] ${
                                            segment === st.id
                                                ? 'text-green-bright border-green-bright'
                                                : 'text-text-dim border-transparent hover:text-text-secondary'
                                        }`}
                                    >
                                        {st.name.split(':')[0].trim()} ({stEvents.length})
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    {/* Content based on segment */}
                    {segment === 'all' ? (
                        <>
                            {subThreads.map(st => {
                                const stEvents = subThreadEvents.get(st.id) || [];
                                if (stEvents.length === 0 && !st.event_count) return null;
                                return (
                                    <SubThreadCard
                                        key={st.id}
                                        subThread={st}
                                        events={stEvents}
                                        onEventClick={onEventClick}
                                    />
                                );
                            })}
                            {directEvents.length > 0 && directEvents.map(event => (
                                <EventRow key={event.id} event={event} onClick={onEventClick} />
                            ))}
                        </>
                    ) : (
                        /* Single subthread selected */
                        (subThreadEvents.get(segment) || []).map(event => (
                            <EventRow key={event.id} event={event} onClick={onEventClick} />
                        ))
                    )}
                </div>
            )}
        </div>
    );
}

/* ── Flat event row for "all events" view ── */
function FlatEventRow({ event, onClick }) {
    const cat = event.category || 'war';
    const catColor = CATEGORY_COLORS[cat] || '#888';
    const statusKey = event.status || 'unverified';
    const statusStyle = STATUS_STYLES[statusKey] || STATUS_STYLES.unverified;

    return (
        <button
            onClick={() => onClick(event)}
            className="w-full text-left px-3 py-2.5 hover:bg-surface-2 transition-colors border-b border-border-subtle last:border-b-0 group"
            style={{ borderLeft: `3px solid ${sevColor(event.severity)}` }}
        >
            <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-1.5 mb-0.5">
                        <span
                            className="font-mono text-[9px] uppercase tracking-wider font-bold"
                            style={{ color: catColor }}
                        >
                            {cat}
                        </span>
                        <span
                            className="font-mono text-[8px] px-1 py-px rounded"
                            style={{
                                backgroundColor: statusStyle.bg + '33',
                                color: statusStyle.bg,
                            }}
                        >
                            {statusStyle.label}
                        </span>
                    </div>
                    <h3 className="text-xs text-text-primary font-semibold leading-snug truncate group-hover:text-green-bright transition-colors">
                        {localizedTitle(event)}
                    </h3>
                    <div className="flex items-center gap-1.5 mt-0.5 font-mono text-[9px] text-text-dim">
                        <span title={fullDateTime(event.occurred_at)}>{timeAgo(event.occurred_at)}</span>
                        {event.country && (
                            <>
                                <span className="text-border-mid">|</span>
                                <span className="uppercase">{event.country}</span>
                            </>
                        )}
                        {event.source_name && (
                            <>
                                <span className="text-border-mid">|</span>
                                <span className="text-blue-bright">{event.source_name}</span>
                            </>
                        )}
                    </div>
                </div>
                <span
                    className="flex-shrink-0 font-mono text-[10px] font-bold px-1.5 py-0.5 rounded"
                    style={{
                        backgroundColor: sevColor(event.severity) + '33',
                        color: sevColor(event.severity),
                    }}
                >
                    SEV {event.severity}
                </span>
            </div>
        </button>
    );
}

/* ── Main feed ── */
export default function ConflictFeed() {
    const { t } = useTranslation();
    const { events, threads, filters, setSelectedEvent } = useDashboard();

    const [viewMode, setViewMode] = useState('conflicts');
    const [searchQuery, setSearchQuery] = useState('');
    const [sevFilter, setSevFilter] = useState('all');

    /* ── Filtering ── */
    const filteredEvents = useMemo(() => {
        return events.filter(e => {
            if (filters.severityMin && e.severity < Number(filters.severityMin)) return false;
            if (filters.severityMax && e.severity > Number(filters.severityMax)) return false;
            if (filters.category && e.category !== filters.category) return false;
            if (filters.status && e.status !== filters.status) return false;
            if (filters.country && e.country !== filters.country) return false;

            const sf = SEV_FILTERS.find(f => f.key === sevFilter);
            if (sf?.min !== undefined) {
                if (e.severity < sf.min || e.severity > sf.max) return false;
            }

            if (searchQuery) {
                const q = searchQuery.toLowerCase();
                const haystack = `${e.title} ${e.title_de || ''} ${e.summary || ''} ${e.summary_de || ''} ${e.country || ''} ${e.category || ''}`.toLowerCase();
                if (!haystack.includes(q)) return false;
            }

            return true;
        });
    }, [events, filters, sevFilter, searchQuery]);

    /* ── Hierarchical thread grouping ── */
    const { threadGroups, unassignedEvents } = useMemo(() => {
        const eventsByThread = new Map();
        filteredEvents.forEach(e => {
            const key = e.conflict_thread_id || '__unassigned__';
            if (!eventsByThread.has(key)) eventsByThread.set(key, []);
            eventsByThread.get(key).push(e);
        });

        const groups = [];

        threads.forEach(thread => {
            const children = thread.children || [];
            const childIds = new Set(children.map(c => c.id));

            const allEvents = [...(eventsByThread.get(thread.id) || [])];
            children.forEach(child => {
                allEvents.push(...(eventsByThread.get(child.id) || []));
            });

            if (allEvents.length > 0 || thread.event_count_total > 0) {
                groups.push({
                    thread: { ...thread, event_count: allEvents.length },
                    events: allEvents,
                    subThreads: children.filter(c => {
                        const ce = eventsByThread.get(c.id) || [];
                        return ce.length > 0 || c.event_count > 0;
                    }),
                });
            }
        });

        groups.sort((a, b) => (b.thread.max_severity || 0) - (a.thread.max_severity || 0));
        const unassigned = eventsByThread.get('__unassigned__') || [];

        return { threadGroups: groups, unassignedEvents: unassigned };
    }, [filteredEvents, threads]);

    return (
        <div className="flex flex-col h-full bg-surface-0 overflow-hidden">
            {/* ── Stats header ── */}
            <div className="px-3 py-2 border-b border-border-mid flex items-center justify-between bg-surface-1">
                <span className="font-mono text-xs text-text-muted tracking-wider">
                    <span className="text-text-primary font-bold">{filteredEvents.length}</span>
                    {' '}{t('dashboard.feed.hits', 'HITS')}
                </span>
                <span className="font-mono text-[9px] text-text-dim">
                    {threadGroups.length} conflicts
                    {unassignedEvents.length > 0 && <> / {unassignedEvents.length} unassigned</>}
                </span>
            </div>

            {/* ── View tabs ── */}
            <div className="flex border-b border-border-mid bg-surface-0">
                {['conflicts', 'all'].map(mode => (
                    <button
                        key={mode}
                        onClick={() => setViewMode(mode)}
                        className={`flex-1 font-mono text-[10px] tracking-widest uppercase py-2 transition-colors border-b-2 ${
                            viewMode === mode
                                ? 'text-green-bright border-green-bright bg-surface-1'
                                : 'text-text-muted hover:text-text-secondary border-transparent'
                        }`}
                    >
                        {mode === 'conflicts' ? 'CONFLICTS' : 'ALL EVENTS'}
                    </button>
                ))}
            </div>

            {/* ── Search ── */}
            <div className="px-3 py-2 border-b border-border-mid bg-surface-0">
                <div className="relative">
                    <svg className="absolute left-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-text-dim pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={e => setSearchQuery(e.target.value)}
                        placeholder="Search events..."
                        className="w-full bg-surface-1 border border-border-mid rounded pl-7 pr-2.5 py-1.5 font-mono text-xs text-text-secondary placeholder:text-text-dim focus:border-green-base focus:outline-none transition-colors"
                    />
                </div>
            </div>

            {/* ── Severity quick-filters ── */}
            <div className="px-3 py-2 border-b border-border-mid flex flex-wrap gap-1.5 bg-surface-0">
                {SEV_FILTERS.map(f => {
                    const active = sevFilter === f.key;
                    let activeColor = 'bg-green-dim border-green-base text-green-bright';
                    if (f.key === 'high') activeColor = 'bg-red-alert/20 border-red-alert text-red-bright';
                    if (f.key === 'crit') activeColor = 'bg-red-alert/30 border-red-bright text-red-bright';
                    if (f.key === 'med') activeColor = 'bg-amber/20 border-amber text-amber-bright';

                    return (
                        <button
                            key={f.key}
                            onClick={() => setSevFilter(f.key)}
                            className={`font-mono text-[10px] tracking-wider px-2 py-0.5 rounded border transition-colors ${
                                active ? activeColor : 'bg-surface-1 border-border-mid text-text-muted hover:border-border-active hover:text-text-secondary'
                            }`}
                        >
                            {f.label}
                        </button>
                    );
                })}
            </div>

            {/* ── Scrollable feed body ── */}
            <div className="flex-1 overflow-y-auto">
                {viewMode === 'conflicts' ? (
                    threadGroups.length === 0 && unassignedEvents.length === 0 ? (
                        <div className="p-6 text-center font-mono text-xs text-text-dim">
                            No conflict threads in current view
                        </div>
                    ) : (
                        <>
                            {threadGroups.map(({ thread, events: tEvents, subThreads }) => (
                                <ConflictCard
                                    key={thread.id}
                                    thread={thread}
                                    events={tEvents}
                                    subThreads={subThreads}
                                    onEventClick={setSelectedEvent}
                                />
                            ))}
                            {unassignedEvents.length > 0 && (
                                <ConflictCard
                                    thread={{
                                        id: '__unassigned__',
                                        name: 'Unassigned Events',
                                        summary: null,
                                        event_count: unassignedEvents.length,
                                        event_count_total: unassignedEvents.length,
                                        event_count_24h: unassignedEvents.length,
                                        max_severity: unassignedEvents.length > 0
                                            ? Math.max(...unassignedEvents.map(e => e.severity))
                                            : 0,
                                        latest_event_at: unassignedEvents[0]?.occurred_at,
                                        countries: [...new Set(unassignedEvents.map(e => e.country).filter(Boolean))],
                                        categories: [...new Set(unassignedEvents.map(e => e.category).filter(Boolean))],
                                        sub_thread_count: 0,
                                    }}
                                    events={unassignedEvents}
                                    subThreads={[]}
                                    onEventClick={setSelectedEvent}
                                />
                            )}
                        </>
                    )
                ) : filteredEvents.length === 0 ? (
                    <div className="p-6 text-center font-mono text-xs text-text-dim">
                        No events match current filters
                    </div>
                ) : (
                    filteredEvents.map(event => (
                        <FlatEventRow key={event.id} event={event} onClick={setSelectedEvent} />
                    ))
                )}
            </div>
        </div>
    );
}
