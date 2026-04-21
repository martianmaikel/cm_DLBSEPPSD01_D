import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useDashboard } from '../../Contexts/DashboardContext';

function formatTimeAgo(date, locale) {
    if (!date) return '---';
    const diff = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diff < 5) return locale === 'de' ? 'GERADE EBEN' : 'JUST NOW';
    if (diff < 60) return `${diff}s`;
    const mins = Math.floor(diff / 60);
    if (mins < 60) return `${mins}m`;
    return `${Math.floor(mins / 60)}h`;
}

export default function LiveIndicator() {
    const { i18n } = useTranslation();
    const { lastUpdated, isStale, newEvents, events } = useDashboard();
    const [, forceUpdate] = useState(0);
    const locale = i18n.language === 'de' ? 'de' : 'en';

    // Re-render every 5s to keep "time ago" fresh
    useEffect(() => {
        const timer = setInterval(() => forceUpdate((n) => n + 1), 5000);
        return () => clearInterval(timer);
    }, []);

    const newCount = newEvents?.length || 0;
    const totalCount = events?.length || 0;
    const timeAgo = formatTimeAgo(lastUpdated, locale);

    return (
        <div className="live-indicator">
            {/* Live pulse dot */}
            <div className="live-indicator-row">
                <div className={`live-dot${isStale ? ' live-dot--stale' : ''}`}>
                    <div className="live-dot-core" />
                    {!isStale && <div className="live-dot-ping" />}
                </div>
                <span className={`live-status-label${isStale ? ' live-status--stale' : ''}`}>
                    {isStale ? 'OFFLINE' : 'LIVE'}
                </span>
            </div>

            {/* Timestamp + counts */}
            <div className="live-meta">
                <span className="live-meta-item">
                    {timeAgo}
                </span>
                <span className="live-meta-sep">/</span>
                <span className="live-meta-item">
                    {totalCount} {locale === 'de' ? 'EREIGNISSE' : 'EVENTS'}
                </span>
            </div>

            {/* New event badge */}
            {newCount > 0 && (
                <div className="live-new-badge">
                    +{newCount} {locale === 'de' ? 'NEU' : 'NEW'}
                </div>
            )}
        </div>
    );
}
