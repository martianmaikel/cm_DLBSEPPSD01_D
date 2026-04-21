import { useMemo } from 'react';
import i18n from '../../i18n';
import { useDashboard } from '../../Contexts/DashboardContext';

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    return `${hrs}h ago`;
}

function getSeverityDotColor(severity) {
    if (severity >= 7) return '#E74C3C';
    if (severity >= 4) return '#F59E0B';
    return '#52A844';
}

export default function EventTicker() {
    const { tickerEvents, setSelectedEvent } = useDashboard();

    // Duplicate items for seamless loop
    const items = useMemo(() => {
        if (!tickerEvents || tickerEvents.length === 0) return [];
        return [...tickerEvents, ...tickerEvents];
    }, [tickerEvents]);

    if (items.length === 0) {
        return (
            <div className="h-8 bg-surface-0 border-b border-border-mid flex items-center px-4">
                <span className="font-mono text-xs text-text-dim tracking-wider">
                    NO ACTIVE ALERTS
                </span>
            </div>
        );
    }

    return (
        <div className="h-8 bg-surface-0 border-b border-border-mid overflow-hidden relative group">
            <div className="ticker-scroll flex items-center h-full whitespace-nowrap gap-8 group-hover:[animation-play-state:paused]">
                {items.map((event, i) => (
                    <button
                        key={`${event.id}-${i}`}
                        onClick={() => setSelectedEvent(event)}
                        className="inline-flex items-center gap-2 flex-shrink-0 hover:text-green-bright transition-colors"
                    >
                        <span
                            className="w-1.5 h-1.5 rounded-full flex-shrink-0"
                            style={{ backgroundColor: getSeverityDotColor(event.severity) }}
                        />
                        <span className="font-mono text-xs text-text-secondary">
                            {(i18n.language === 'de' && event.title_de) || event.title}
                        </span>
                        <span className="font-mono text-xs text-text-dim">
                            {event.country && `${event.country} · `}{timeAgo(event.occurred_at)}
                        </span>
                    </button>
                ))}
            </div>

            <style>{`
                @keyframes ticker-scroll {
                    0% { transform: translateX(0); }
                    100% { transform: translateX(-50%); }
                }
                .ticker-scroll {
                    animation: ticker-scroll ${Math.max(items.length * 3, 30)}s linear infinite;
                }
            `}</style>
        </div>
    );
}
