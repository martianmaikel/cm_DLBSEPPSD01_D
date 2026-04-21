import { useEffect, useRef, useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';
import { DashboardProvider, useDashboard, filtersToParams } from '../../Contexts/DashboardContext';
import ConflictGlobe from '../../Components/Globe/ConflictGlobe';
import ConflictFeed from '../../Components/Feed/ConflictFeed';
import EventTicker from '../../Components/Ticker/EventTicker';
import DashboardFilterBar from '../../Components/Filters/DashboardFilterBar';
import EventDetailPanel from '../../Components/EventDetailPanel';
import CountryInfoPanel from '../../Components/CountryInfoPanel';
import ClusterEventsPanel from '../../Components/ClusterEventsPanel';
import DailyBriefingCard from '../../Components/Briefing/DailyBriefingCard';
import SocialLinks from '../../Components/SocialLinks';
import { Link } from '@inertiajs/react';

function DashboardContent() {
    const { t } = useTranslation();
    const {
        events, filters, selectedEvent, setSelectedEvent,
        setEvents, setThreads, setTickerEvents, markStale, briefing,
    } = useDashboard();

    const [mobileTab, setMobileTab] = useState('map');
    const [countryPanelCode, setCountryPanelCode] = useState(null);
    const [clusterEvents, setClusterEvents] = useState(null);
    const abortRef = useRef(null);

    // Polling: events + threads every 60s
    const fetchDashboard = useCallback(async () => {
        try {
            abortRef.current?.abort();
            abortRef.current = new AbortController();

            const params = filtersToParams(filters);
            const res = await fetch(`/api/dashboard?${params.toString()}`, {
                signal: abortRef.current.signal,
            });

            if (!res.ok) throw new Error('fetch failed');
            const data = await res.json();
            setEvents(data.events);
            setThreads(data.threads);
        } catch (err) {
            if (err.name !== 'AbortError') {
                markStale();
            }
        }
    }, [filters, setEvents, setThreads, markStale]);

    // Polling: ticker every 30s
    const fetchTicker = useCallback(async () => {
        try {
            const res = await fetch('/api/dashboard/ticker');
            if (!res.ok) return;
            const data = await res.json();
            setTickerEvents(data);
        } catch {
            // Ticker failure is non-critical
        }
    }, [setTickerEvents]);

    // Initial ticker fetch + intervals
    useEffect(() => {
        fetchTicker();
        const dashInterval = setInterval(fetchDashboard, 60000);
        const tickerInterval = setInterval(fetchTicker, 30000);
        return () => {
            clearInterval(dashInterval);
            clearInterval(tickerInterval);
            abortRef.current?.abort();
        };
    }, [fetchDashboard, fetchTicker]);

    // Re-fetch when filters change
    useEffect(() => {
        fetchDashboard();
    }, [fetchDashboard]);

    return (
        <div className="flex flex-col h-[calc(100vh-64px)]">
            {/* Ticker */}
            <EventTicker />

            {/* Filter bar */}
            <DashboardFilterBar />

            {/* Mobile tab switcher */}
            <div className="flex md:hidden border-b border-border-mid bg-surface-0">
                <button
                    onClick={() => setMobileTab('map')}
                    className={`flex-1 font-mono text-[10px] tracking-widest uppercase py-2.5 transition-colors border-b-2 ${
                        mobileTab === 'map'
                            ? 'text-green-bright border-green-bright bg-surface-1'
                            : 'text-text-muted hover:text-text-secondary border-transparent'
                    }`}
                >
                    {t('dashboard.mobile.map', 'MAP')}
                </button>
                <button
                    onClick={() => setMobileTab('feed')}
                    className={`flex-1 font-mono text-[10px] tracking-widest uppercase py-2.5 transition-colors border-b-2 ${
                        mobileTab === 'feed'
                            ? 'text-green-bright border-green-bright bg-surface-1'
                            : 'text-text-muted hover:text-text-secondary border-transparent'
                    }`}
                >
                    {t('dashboard.mobile.feed', 'FEED')}
                </button>
            </div>

            {/* Main body — flex-col on mobile (panels stacked, one visible), flex-row on desktop */}
            <div className="flex flex-col md:flex-row flex-1 min-h-0 overflow-hidden">
                {/* Left: Conflict Feed + Briefing + Footer */}
                <div className={`${mobileTab === 'feed' ? 'flex' : 'hidden'} md:flex flex-1 md:flex-none min-h-0 md:w-[380px] md:flex-shrink-0 overflow-hidden border-r border-border-mid flex-col`}>
                    <div className="flex-1 min-h-0 overflow-hidden">
                        <ConflictFeed />
                    </div>
                    <div className="p-2 border-t border-border-mid">
                        <DailyBriefingCard briefing={briefing} />
                    </div>
                    <div className="border-t border-border-mid bg-surface-0">
                        <Link
                            href="/digest"
                            className="flex items-center gap-2.5 px-3 py-2.5 hover:bg-surface-2 transition-colors group border-b border-border-subtle"
                        >
                            <svg className="w-3.5 h-3.5 text-amber group-hover:text-green-bright transition-colors flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 17v-6h13M9 5h13M4 7h1m-1 6h1m-1 6h1" />
                            </svg>
                            <div className="flex-1 min-w-0">
                                <span className="block font-mono text-[10px] text-amber group-hover:text-green-neon tracking-wider uppercase">
                                    {t('cta.weeklyDigest', 'Weekly Digest')}
                                </span>
                                <span className="block font-mono text-[9px] text-text-dim mt-0.5">
                                    {t('cta.weeklyDigestCta', 'Last 7 days in numbers')}
                                </span>
                            </div>
                            <span className="font-mono text-[9px] text-text-dim group-hover:text-green-bright transition-colors">
                                →
                            </span>
                        </Link>
                        <Link
                            href="/newsletter"
                            className="flex items-center gap-2.5 px-3 py-2.5 hover:bg-surface-2 transition-colors group"
                        >
                            <svg className="w-3.5 h-3.5 text-green-base group-hover:text-green-bright transition-colors flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <div className="flex-1 min-w-0">
                                <span className="block font-mono text-[10px] text-green-bright group-hover:text-green-neon tracking-wider uppercase">
                                    {t('cta.morningBriefing')}
                                </span>
                                <span className="block font-mono text-[9px] text-text-dim mt-0.5">
                                    {t('cta.briefingCta')}
                                </span>
                            </div>
                            <span className="font-mono text-[9px] text-text-dim group-hover:text-green-bright transition-colors">
                                →
                            </span>
                        </Link>
                        <div className="px-3 py-1.5 border-t border-border-subtle flex items-center justify-between">
                            <span className="font-mono text-[10px] text-text-dim tracking-wider uppercase">
                                {t('dashboard.footer.joinMission', 'Join the mission')}
                            </span>
                            <SocialLinks />
                        </div>
                    </div>
                </div>

                {/* Center: Globe */}
                <div className={`${mobileTab === 'map' ? 'flex' : 'hidden'} md:flex flex-1 min-w-0 min-h-0 relative bg-black`}>
                    <ConflictGlobe
                        events={events}
                        onEventSelect={setSelectedEvent}
                        onCountryCardClick={(code) => setCountryPanelCode(code)}
                        onClusterClick={(evts) => setClusterEvents(evts)}
                    />
                </div>
            </div>

            {/* Mobile-only: Daily Intel + Newsletter CTA + Social — visible only on map tab (feed tab has its own) */}
            <div className={`${mobileTab === 'map' ? 'block' : 'hidden'} md:hidden border-t border-border-mid bg-surface-0`}>
                <div className="p-2">
                    <DailyBriefingCard briefing={briefing} />
                </div>
                <Link
                    href="/newsletter"
                    className="flex items-center gap-2.5 mx-2 mb-2 px-3 py-2.5 border border-green-base/30 bg-green-base/5 rounded hover:bg-green-dim transition-colors group"
                >
                    <svg className="w-3.5 h-3.5 text-green-base group-hover:text-green-bright transition-colors flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <div className="flex-1 min-w-0">
                        <span className="block font-mono text-[10px] text-green-bright tracking-wider uppercase">
                            {t('cta.subscribeToBriefing')}
                        </span>
                        <span className="block font-mono text-[9px] text-text-dim mt-0.5">
                            {t('cta.dailyAt7')}
                        </span>
                    </div>
                    <span className="font-mono text-[9px] text-text-dim group-hover:text-green-bright transition-colors">
                        →
                    </span>
                </Link>
                <div className="px-3 py-2 border-t border-border-mid flex items-center justify-between">
                    <span className="font-mono text-[10px] text-text-dim tracking-wider uppercase">
                        {t('dashboard.footer.joinMission', 'Join the mission')}
                    </span>
                    <SocialLinks />
                </div>
            </div>

            {/* Slide-in detail panel */}
            {selectedEvent && (
                <>
                    <div
                        className="fixed inset-0 bg-black/50 z-40"
                        onClick={() => setSelectedEvent(null)}
                    />
                    <EventDetailPanel
                        event={selectedEvent}
                        onClose={() => setSelectedEvent(null)}
                    />
                </>
            )}

            {/* Country info panel */}
            {countryPanelCode && !selectedEvent && !clusterEvents && (
                <CountryInfoPanel
                    countryCode={countryPanelCode}
                    onClose={() => setCountryPanelCode(null)}
                />
            )}

            {/* Cluster events panel */}
            {clusterEvents && !selectedEvent && (
                <ClusterEventsPanel
                    events={clusterEvents}
                    onClose={() => setClusterEvents(null)}
                    onEventSelect={(ev) => { setClusterEvents(null); setSelectedEvent(ev); }}
                />
            )}
        </div>
    );
}

export default function Dashboard({ events = [], threads = [], briefing = null }) {
    return (
        <AppLayout isDashboard>
            <DashboardProvider
                initialEvents={events}
                initialThreads={threads}
                initialBriefing={briefing}
            >
                <DashboardContent />
            </DashboardProvider>
        </AppLayout>
    );
}
