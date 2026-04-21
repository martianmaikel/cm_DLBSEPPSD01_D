import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

function formatDate(dateStr, locale) {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(locale === 'de' ? 'de-DE' : 'en-US', {
        day: 'numeric', month: 'short', year: 'numeric',
    });
}

export default function BriefingArchive({ onClose }) {
    const { t, i18n } = useTranslation();
    const [briefings, setBriefings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedDate, setSelectedDate] = useState('');

    // Fetch recent briefings
    useEffect(() => {
        const fetchBriefings = async () => {
            try {
                const res = await fetch('/api/dashboard/briefings?limit=7');
                if (res.ok) {
                    setBriefings(await res.json());
                }
            } catch {
                // Silently fail
            } finally {
                setLoading(false);
            }
        };
        fetchBriefings();
    }, []);

    const selectedBriefing = selectedDate
        ? briefings.find(b => b.briefing_date === selectedDate)
        : briefings[0];

    const summary = selectedBriefing
        ? (i18n.language === 'de' ? selectedBriefing.summary_de : selectedBriefing.summary_en)
        : '';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
            <div className="bg-surface-1 border border-border-mid rounded w-full max-w-2xl max-h-[80vh] flex flex-col overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between px-4 py-3 border-b border-border-mid">
                    <h2 className="font-display text-lg tracking-wider text-green-bright">
                        {t('dashboard.briefing.archive', 'BRIEFING ARCHIVE')}
                    </h2>
                    <button
                        onClick={onClose}
                        className="font-mono text-text-muted hover:text-red-bright transition-colors text-lg"
                    >
                        ✕
                    </button>
                </div>

                {/* Date selector */}
                <div className="flex gap-2 px-4 py-2 border-b border-border-subtle overflow-x-auto">
                    {briefings.map(b => (
                        <button
                            key={b.briefing_date}
                            onClick={() => setSelectedDate(b.briefing_date)}
                            className={`font-mono text-xs px-3 py-1 rounded border transition-colors flex-shrink-0 ${
                                (selectedBriefing?.briefing_date === b.briefing_date)
                                    ? 'border-green-base text-green-bright bg-green-dim'
                                    : 'border-border-mid text-text-secondary hover:border-border-active'
                            }`}
                        >
                            {formatDate(b.briefing_date, i18n.language)}
                        </button>
                    ))}
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto p-4">
                    {loading ? (
                        <div className="text-center font-mono text-xs text-text-dim py-8">
                            Loading...
                        </div>
                    ) : selectedBriefing ? (
                        <div className="space-y-4">
                            <h3 className="font-sans font-semibold text-text-primary">
                                {selectedBriefing.title}
                            </h3>
                            <p className="text-sm text-text-secondary leading-relaxed whitespace-pre-line">
                                {summary}
                            </p>
                            {selectedBriefing.key_developments?.length > 0 && (
                                <div>
                                    <h4 className="font-mono text-xs text-text-muted tracking-wider uppercase mb-2">
                                        Key Developments
                                    </h4>
                                    <div className="space-y-2">
                                        {selectedBriefing.key_developments.map((dev, i) => (
                                            <div key={i} className="bg-surface-2 border border-border-subtle rounded p-2">
                                                <div className="font-sans text-sm text-text-primary">{dev.title}</div>
                                                <div className="text-xs text-text-secondary mt-0.5">{dev.description}</div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="text-center font-mono text-xs text-text-dim py-8">
                            No briefings available
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
