import { useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import i18n from '../../i18n';
import { useDashboard } from '../../Contexts/DashboardContext';

const AUTO_DISMISS_MS = 8000;

const COUNTRY_NAMES = {
    AF:'Afghanistan',AL:'Albania',DZ:'Algeria',AO:'Angola',AZ:'Azerbaijan',
    BD:'Bangladesh',AM:'Armenia',BA:'Bosnia',BR:'Brazil',BG:'Bulgaria',
    MM:'Myanmar',BI:'Burundi',BY:'Belarus',CM:'Cameroon',CF:'C.A.R.',
    TD:'Chad',CN:'China',CO:'Colombia',CD:'D.R. Congo',CU:'Cuba',
    ET:'Ethiopia',ER:'Eritrea',GE:'Georgia',PS:'Palestine',GH:'Ghana',
    GT:'Guatemala',HT:'Haiti',IN:'India',ID:'Indonesia',IR:'Iran',
    IQ:'Iraq',IL:'Israel',KE:'Kenya',KP:'North Korea',LB:'Lebanon',
    LR:'Liberia',LY:'Libya',ML:'Mali',MX:'Mexico',MZ:'Mozambique',
    NE:'Niger',NG:'Nigeria',PK:'Pakistan',PH:'Philippines',RU:'Russia',
    SO:'Somalia',ZA:'South Africa',SS:'South Sudan',SD:'Sudan',SY:'Syria',
    TH:'Thailand',TR:'Turkey',UA:'Ukraine',YE:'Yemen',VE:'Venezuela',
    XK:'Kosovo',
};

function getSeverityLabel(sev) {
    if (sev >= 9) return 'CRITICAL';
    return 'HIGH ALERT';
}

export default function CriticalEventAlert({ onEventSelect }) {
    const { i18n } = useTranslation();
    const { criticalAlerts, dismissCriticalAlert } = useDashboard();
    const locale = i18n.language === 'de' ? 'de' : 'en';

    // Auto-dismiss alerts after timeout
    useEffect(() => {
        if (criticalAlerts.length === 0) return;

        const timers = criticalAlerts.map((alert) =>
            setTimeout(() => dismissCriticalAlert(alert.id), AUTO_DISMISS_MS)
        );

        return () => timers.forEach(clearTimeout);
    }, [criticalAlerts, dismissCriticalAlert]);

    const handleClick = useCallback((event) => {
        if (onEventSelect) onEventSelect(event);
        dismissCriticalAlert(event.id);
    }, [onEventSelect, dismissCriticalAlert]);

    if (criticalAlerts.length === 0) return null;

    // Show only the most recent 3 alerts
    const visible = criticalAlerts.slice(-3);

    return (
        <div className="critical-alerts-stack">
            {visible.map((event) => {
                const sevLabel = getSeverityLabel(event.severity);
                const countryName = COUNTRY_NAMES[event.country] || event.country || '';

                return (
                    <div
                        key={event.id}
                        className="critical-alert"
                        onClick={(e) => { e.stopPropagation(); handleClick(event); }}
                    >
                        {/* Scanline flash overlay */}
                        <div className="critical-alert-flash" />

                        {/* Content */}
                        <div className="critical-alert-content">
                            {/* Left: severity badge */}
                            <div className="critical-alert-sev">
                                <span className="critical-alert-sev-num">{event.severity}</span>
                                <span className="critical-alert-sev-label">{sevLabel}</span>
                            </div>

                            {/* Center: event info */}
                            <div className="critical-alert-info">
                                <div className="critical-alert-title">{(i18n.language === 'de' && event.title_de) || event.title}</div>
                                <div className="critical-alert-meta">
                                    {countryName && <span>{countryName}</span>}
                                    {event.category && (
                                        <>
                                            <span className="critical-alert-meta-sep">/</span>
                                            <span>{event.category.toUpperCase()}</span>
                                        </>
                                    )}
                                </div>
                            </div>

                            {/* Right: dismiss */}
                            <button
                                className="critical-alert-close"
                                onClick={(e) => { e.stopPropagation(); dismissCriticalAlert(event.id); }}
                            >
                                &times;
                            </button>
                        </div>

                        {/* Auto-dismiss progress bar */}
                        <div className="critical-alert-progress" />
                    </div>
                );
            })}
        </div>
    );
}
