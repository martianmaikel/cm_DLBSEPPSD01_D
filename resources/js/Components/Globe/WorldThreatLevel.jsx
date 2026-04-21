import { useEffect, useState, useRef, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

function getThreatColor(level) {
    if (level >= 7) return '#E74C3C';
    if (level >= 4) return '#F59E0B';
    return '#52A844';
}

function getTrendArrow(trend) {
    if (trend === 'rising') return '\u2191';   // ↑
    if (trend === 'falling') return '\u2193';   // ↓
    return '\u2192';                            // →
}

function getTrendLabel(trend, locale) {
    if (locale === 'de') {
        return { rising: 'STEIGEND', falling: 'FALLEND', stable: 'STABIL' }[trend] || 'STABIL';
    }
    return { rising: 'RISING', falling: 'FALLING', stable: 'STABLE' }[trend] || 'STABLE';
}

export default function WorldThreatLevel() {
    const { i18n } = useTranslation();
    const [data, setData] = useState(null);
    const [expanded, setExpanded] = useState(false);
    const panelRef = useRef(null);

    const fetchThreatLevel = useCallback(async () => {
        try {
            const res = await fetch('/api/map/threat-level');
            if (res.ok) {
                setData(await res.json());
            }
        } catch {
            // Silently fail — indicator just won't show
        }
    }, []);

    useEffect(() => {
        fetchThreatLevel();
        const interval = setInterval(fetchThreatLevel, 60000);
        return () => clearInterval(interval);
    }, [fetchThreatLevel]);

    // Close on click outside
    useEffect(() => {
        if (!expanded) return;
        const handleClick = (e) => {
            if (panelRef.current && !panelRef.current.contains(e.target)) {
                setExpanded(false);
            }
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, [expanded]);

    if (!data) return null;

    const locale = i18n.language === 'de' ? 'de' : 'en';
    const label = locale === 'de' ? data.label_de : data.label_en;
    const summary = locale === 'de' ? data.summary_de : data.summary_en;
    const color = getThreatColor(data.level);
    const stats = data.statistics || {};
    const isPulsing = data.level >= 7;
    const trendArrow = getTrendArrow(stats.escalation_trend);
    const trendLabel = getTrendLabel(stats.escalation_trend, locale);

    return (
        <div ref={panelRef} className="threat-level-wrapper">
            {/* Expanded panel — renders above the bar */}
            {expanded && summary && (
                <div className="threat-level-expanded">
                    <div className="threat-level-expanded-inner">
                        {/* Header */}
                        <div className="threat-level-expanded-header">
                            <span className="threat-level-expanded-title">
                                {locale === 'de' ? 'WELTWEITE BEDROHUNGSLAGE' : 'WORLD THREAT ASSESSMENT'}
                            </span>
                            <button
                                className="threat-level-close"
                                onClick={(e) => { e.stopPropagation(); setExpanded(false); }}
                            >
                                &times;
                            </button>
                        </div>

                        {/* AI Summary */}
                        <p className="threat-level-summary">{summary}</p>

                        {/* Stats row */}
                        <div className="threat-level-stats">
                            <div className="threat-level-stat">
                                <span className="threat-level-stat-value">{stats.total_events ?? 0}</span>
                                <span className="threat-level-stat-label">
                                    {locale === 'de' ? 'EREIGNISSE' : 'EVENTS'}
                                </span>
                            </div>
                            <div className="threat-level-stat-sep" />
                            <div className="threat-level-stat">
                                <span className="threat-level-stat-value">{stats.active_zones ?? 0}</span>
                                <span className="threat-level-stat-label">
                                    {locale === 'de' ? 'KONFLIKTZONEN' : 'CONFLICT ZONES'}
                                </span>
                            </div>
                            <div className="threat-level-stat-sep" />
                            <div className="threat-level-stat">
                                <span className="threat-level-stat-value" style={{ color }}>
                                    {trendArrow}
                                </span>
                                <span className="threat-level-stat-label">{trendLabel}</span>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Collapsed bar — always visible */}
            <div
                className="threat-level-bar"
                onClick={(e) => { e.stopPropagation(); setExpanded((v) => !v); }}
            >
                {/* Color stripe */}
                <div
                    className={`threat-level-stripe${isPulsing ? ' threat-level-pulse' : ''}`}
                    style={{ backgroundColor: color }}
                />

                {/* Threat number */}
                <span className="threat-level-number" style={{ color }}>
                    {data.level}
                </span>

                {/* Divider */}
                <div className="threat-level-divider" />

                {/* Label */}
                <span className="threat-level-label">{label || '---'}</span>

                {/* Expand chevron */}
                <span className={`threat-level-chevron${expanded ? ' threat-level-chevron--up' : ''}`}>
                    &#x25B2;
                </span>
            </div>
        </div>
    );
}
