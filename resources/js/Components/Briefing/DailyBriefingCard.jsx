import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import BriefingArchive from './BriefingArchive';
import BriefingModal from './BriefingModal';

function formatDate(dateStr, locale) {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(locale === 'de' ? 'de-DE' : 'en-US', {
        day: 'numeric', month: 'short', year: 'numeric',
    });
}

export default function DailyBriefingCard({ briefing }) {
    const { t, i18n } = useTranslation();
    const [showModal, setShowModal] = useState(false);
    const [showArchive, setShowArchive] = useState(false);

    if (!briefing) {
        return (
            <div className="bg-surface-1 border border-border-mid rounded p-3">
                <h3 className="font-display text-sm tracking-wider text-text-muted">
                    {t('dashboard.briefing.title', 'DAILY INTEL BRIEFING')}
                </h3>
                <p className="font-mono text-xs text-text-dim mt-1">
                    {t('dashboard.briefing.noData', 'No briefing available')}
                </p>
            </div>
        );
    }

    const stats = briefing.statistics || {};

    return (
        <>
            <div className="bg-surface-1 border border-border-mid rounded overflow-hidden">
                <button
                    onClick={() => setShowModal(true)}
                    className="w-full text-left px-3 py-2 flex items-center justify-between hover:bg-surface-2 transition-colors group"
                >
                    <div className="min-w-0">
                        <h3 className="font-display text-sm tracking-wider text-green-bright">
                            {t('dashboard.briefing.title', 'DAILY INTEL BRIEFING')}
                        </h3>
                        <p className="font-mono text-[10px] text-text-dim truncate mt-0.5">
                            {formatDate(briefing.briefing_date, i18n.language)} — {briefing.title}
                        </p>
                    </div>
                    <div className="flex items-center gap-2 flex-shrink-0 ml-2">
                        {stats.total_events && (
                            <span className="font-mono text-[10px] text-text-muted">
                                {stats.total_events} events
                            </span>
                        )}
                        <span className="font-mono text-xs text-text-dim group-hover:text-green-bright transition-colors">
                            ▸
                        </span>
                    </div>
                </button>
            </div>

            {showModal && (
                <BriefingModal
                    briefing={briefing}
                    onClose={() => setShowModal(false)}
                    onOpenArchive={() => { setShowModal(false); setShowArchive(true); }}
                />
            )}

            {showArchive && (
                <BriefingArchive onClose={() => setShowArchive(false)} />
            )}
        </>
    );
}
