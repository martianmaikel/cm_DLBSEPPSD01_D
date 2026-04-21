import { createContext, useContext, useReducer, useCallback, useEffect, useState, useRef } from 'react';

const DashboardContext = createContext(null);

const defaultFilters = {
    timeRange: '24h',
    severityMin: '',
    severityMax: '',
    category: '',
    region: '',
    status: '',
    country: '',
};

function parseUrlFilters() {
    if (typeof window === 'undefined') return defaultFilters;
    const params = new URLSearchParams(window.location.search);
    return {
        timeRange: params.get('time_range') || defaultFilters.timeRange,
        severityMin: params.get('severity_min') || defaultFilters.severityMin,
        severityMax: params.get('severity_max') || defaultFilters.severityMax,
        category: params.get('category') || defaultFilters.category,
        region: params.get('region') || defaultFilters.region,
        status: params.get('status') || defaultFilters.status,
        country: params.get('country') || defaultFilters.country,
    };
}

function filtersToParams(filters) {
    const params = new URLSearchParams();
    if (filters.timeRange && filters.timeRange !== '24h') params.set('time_range', filters.timeRange);
    if (filters.severityMin) params.set('severity_min', filters.severityMin);
    if (filters.severityMax) params.set('severity_max', filters.severityMax);
    if (filters.category) params.set('category', filters.category);
    if (filters.region) params.set('region', filters.region);
    if (filters.status) params.set('status', filters.status);
    if (filters.country) params.set('country', filters.country);
    return params;
}

function reducer(state, action) {
    switch (action.type) {
        case 'SET_FILTERS':
            return { ...state, filters: { ...state.filters, ...action.payload } };
        case 'RESET_FILTERS':
            return { ...state, filters: { ...defaultFilters } };
        case 'SET_SELECTED_EVENT':
            return { ...state, selectedEvent: action.payload };
        case 'SET_EVENTS':
            return { ...state, events: action.payload };
        case 'SET_THREADS':
            return { ...state, threads: action.payload };
        case 'SET_TICKER_EVENTS':
            return { ...state, tickerEvents: action.payload };
        case 'SET_LAST_UPDATED':
            return { ...state, lastUpdated: action.payload };
        case 'SET_STALE':
            return { ...state, isStale: action.payload };
        case 'SET_NEW_EVENTS':
            return { ...state, newEvents: action.payload };
        case 'PUSH_CRITICAL_ALERTS':
            return { ...state, criticalAlerts: [...state.criticalAlerts, ...action.payload] };
        case 'DISMISS_CRITICAL_ALERT':
            return { ...state, criticalAlerts: state.criticalAlerts.filter((a) => a.id !== action.payload) };
        default:
            return state;
    }
}

export function DashboardProvider({ children, initialEvents = [], initialThreads = [], initialBriefing = null }) {
    const [state, dispatch] = useReducer(reducer, {
        filters: parseUrlFilters(),
        selectedEvent: null,
        events: initialEvents,
        threads: initialThreads,
        tickerEvents: [],
        briefing: initialBriefing,
        lastUpdated: new Date(),
        isStale: false,
        newEvents: [],
        criticalAlerts: [],
    });

    const [selectedCountry, setSelectedCountryState] = useState(null);
    const seenEventIdsRef = useRef(new Set(initialEvents.map((e) => e.id)));

    // Sync filters to URL on change (300ms debounce)
    useEffect(() => {
        const timer = setTimeout(() => {
            if (typeof window === 'undefined') return;
            const params = filtersToParams(state.filters);
            const search = params.toString();
            const newUrl = search ? `${window.location.pathname}?${search}` : window.location.pathname;
            window.history.replaceState(null, '', newUrl);
        }, 300);
        return () => clearTimeout(timer);
    }, [state.filters]);

    const setFilters = useCallback((updates) => {
        // Changing region clears selectedCountry
        if (updates.region !== undefined && updates.region !== state.filters.region) {
            setSelectedCountryState(null);
            dispatch({ type: 'SET_FILTERS', payload: { ...updates, country: '' } });
        } else {
            dispatch({ type: 'SET_FILTERS', payload: updates });
        }
    }, [state.filters.region]);

    const resetFilters = useCallback(() => {
        setSelectedCountryState(null);
        dispatch({ type: 'RESET_FILTERS' });
    }, []);

    const setSelectedCountry = useCallback((countryOrNull) => {
        if (countryOrNull) {
            const { code, name, lat, lng } = countryOrNull;
            setSelectedCountryState({ code, name, lat, lng });
            dispatch({ type: 'SET_FILTERS', payload: { country: code, region: '' } });
        } else {
            setSelectedCountryState(null);
            dispatch({ type: 'SET_FILTERS', payload: { country: '' } });
        }
    }, []);

    const setSelectedEvent = useCallback((event) => {
        if (event && event.confidence == null) {
            // Ticker events have minimal data — look up full version from loaded events
            const full = state.events.find((e) => e.id === event.id);
            if (full) {
                dispatch({ type: 'SET_SELECTED_EVENT', payload: full });
                return;
            }
            // Fallback: fetch full event from API
            fetch(`/api/events/${event.id}`)
                .then((r) => r.json())
                .then((data) => {
                    if (data.event) {
                        const e = data.event;
                        dispatch({ type: 'SET_SELECTED_EVENT', payload: {
                            id: e.id,
                            title: e.title,
                            title_de: e.title_de,
                            summary: e.summary,
                            summary_de: e.summary_de,
                            severity: e.severity,
                            severity_factors: e.severity_factors,
                            confidence: e.confidence,
                            status: e.status,
                            category: e.category,
                            country: e.country,
                            country_code: e.country,
                            region: e.region,
                            coordinates: e.coordinates,
                            occurred_at: e.occurred_at,
                            source_name: e.source?.name,
                            source_url: e.source_url || e.source?.url,
                            source_reliability: e.source?.reliability_score,
                            entities_json: e.entities_json,
                            conflict_thread_id: e.conflict_thread_id,
                            corroboration_count: e.corroboration_count,
                        }});
                    } else {
                        dispatch({ type: 'SET_SELECTED_EVENT', payload: event });
                    }
                })
                .catch(() => {
                    dispatch({ type: 'SET_SELECTED_EVENT', payload: event });
                });
            // Show immediately with partial data while fetching
            dispatch({ type: 'SET_SELECTED_EVENT', payload: event });
            return;
        }
        dispatch({ type: 'SET_SELECTED_EVENT', payload: event });
    }, [state.events]);

    const setEvents = useCallback((events) => {
        // Detect new events by comparing IDs
        const fresh = events.filter((e) => !seenEventIdsRef.current.has(e.id));

        // Accumulate seen IDs (don't replace — prevents re-alerting on filter/zoom changes)
        for (const e of events) {
            seenEventIdsRef.current.add(e.id);
        }

        dispatch({ type: 'SET_EVENTS', payload: events });
        dispatch({ type: 'SET_LAST_UPDATED', payload: new Date() });
        dispatch({ type: 'SET_STALE', payload: false });

        if (fresh.length > 0) {
            dispatch({ type: 'SET_NEW_EVENTS', payload: fresh });

            // Push critical alerts only for recent high-severity events (occurred within last 5 min)
            const fiveMinAgo = Date.now() - 5 * 60 * 1000;
            const critical = fresh.filter(
                (e) => e.severity >= 8 && new Date(e.occurred_at).getTime() > fiveMinAgo
            );
            if (critical.length > 0) {
                dispatch({ type: 'PUSH_CRITICAL_ALERTS', payload: critical });
            }
        }
    }, []);

    const setThreads = useCallback((threads) => {
        dispatch({ type: 'SET_THREADS', payload: threads });
    }, []);

    const setTickerEvents = useCallback((events) => {
        dispatch({ type: 'SET_TICKER_EVENTS', payload: events });
    }, []);

    const markStale = useCallback(() => {
        dispatch({ type: 'SET_STALE', payload: true });
    }, []);

    const dismissCriticalAlert = useCallback((id) => {
        dispatch({ type: 'DISMISS_CRITICAL_ALERT', payload: id });
    }, []);

    return (
        <DashboardContext.Provider value={{
            ...state,
            selectedCountry,
            setFilters,
            resetFilters,
            setSelectedCountry,
            setSelectedEvent,
            setEvents,
            setThreads,
            setTickerEvents,
            markStale,
            dismissCriticalAlert,
        }}>
            {children}
        </DashboardContext.Provider>
    );
}

export function useDashboard() {
    const context = useContext(DashboardContext);
    if (!context) {
        throw new Error('useDashboard must be used within a DashboardProvider');
    }
    return context;
}

export { defaultFilters, filtersToParams };
