import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';

function formatDate(dateStr, locale) {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(locale === 'de' ? 'de-DE' : 'en-US', {
        day: 'numeric', month: 'short', year: 'numeric',
    });
}

function getSeverityColor(severity) {
    if (severity >= 7) return 'text-red-bright';
    if (severity >= 4) return 'text-amber-bright';
    return 'text-green-bright';
}

function getSeverityBg(severity) {
    if (severity >= 7) return 'bg-red-bright/10 border-red-bright/30';
    if (severity >= 4) return 'bg-amber-bright/10 border-amber-bright/30';
    return 'bg-green-bright/10 border-green-bright/30';
}

function getSeverityBar(severity) {
    if (severity >= 7) return 'bg-red-bright';
    if (severity >= 4) return 'bg-amber';
    return 'bg-green-base';
}

function ConflictSection({ section }) {
    return (
        <div className="relative pl-3 border-l-2 border-border-mid">
            {/* Severity accent bar */}
            <div className={`absolute left-[-1px] top-0 w-0.5 h-full ${getSeverityBar(section.max_severity)}`} />

            <div className="flex items-start justify-between gap-3 mb-1">
                <h4 className="font-ui text-sm font-semibold text-text-primary leading-tight">
                    {section.conflict_name}
                </h4>
                <div className="flex items-center gap-2 flex-shrink-0">
                    <span className={`font-mono text-[10px] px-1.5 py-0.5 rounded border ${getSeverityBg(section.max_severity)}`}>
                        SEV {section.max_severity}
                    </span>
                    <span className="font-mono text-[10px] text-text-muted">
                        {section.event_count} events
                    </span>
                </div>
            </div>
            <p className="text-xs text-text-secondary leading-relaxed">
                {section.summary}
            </p>
        </div>
    );
}

function KeyDevelopment({ dev, index }) {
    return (
        <div className="flex items-start gap-2 text-xs">
            <span className={`font-mono text-[10px] w-4 text-center flex-shrink-0 mt-0.5 ${getSeverityColor(dev.severity)}`}>
                {index + 1}
            </span>
            <div className="min-w-0">
                <span className="text-text-primary font-medium">{dev.title}</span>
                {dev.description && (
                    <span className="text-text-muted ml-1">— {dev.description}</span>
                )}
            </div>
        </div>
    );
}

export default function BriefingModal({ briefing, onClose, onOpenArchive }) {
    const { t, i18n } = useTranslation();

    const summary = i18n.language === 'de' ? briefing.summary_de : briefing.summary_en;
    const conflictSections = briefing.conflict_sections?.[i18n.language] || briefing.conflict_sections?.en || [];
    const developments = briefing.key_developments || [];
    const stats = briefing.statistics || {};

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60" onClick={onClose}>
            <div
                className="bg-surface-1 border border-border-mid rounded w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-5 py-3 border-b border-border-mid bg-surface-0">
                    <div>
                        <h2 className="font-display text-lg tracking-wider text-green-bright">
                            {t('dashboard.briefing.title', 'DAILY INTEL BRIEFING')}
                        </h2>
                        <span className="font-mono text-[10px] text-text-dim">
                            {formatDate(briefing.briefing_date, i18n.language)}
                        </span>
                    </div>
                    <button
                        onClick={onClose}
                        className="font-mono text-text-muted hover:text-red-bright transition-colors text-lg"
                    >
                        ✕
                    </button>
                </div>

                {/* Scrollable content */}
                <div className="flex-1 overflow-y-auto p-5 space-y-5">
                    {/* Title + Global Summary */}
                    <div>
                        <h3 className="font-ui text-base font-semibold text-text-primary mb-2">
                            {briefing.title}
                        </h3>
                        <p className="text-sm text-text-secondary leading-relaxed">
                            {summary}
                        </p>
                    </div>

                    {/* Statistics strip */}
                    {stats.total_events && (
                        <div className="flex flex-wrap gap-x-4 gap-y-1 py-2 px-3 bg-surface-2 rounded border border-border-subtle font-mono text-[10px] text-text-muted tracking-wider uppercase">
                            <span>{stats.total_events} {t('dashboard.briefing.events', 'events')}</span>
                            <span>{t('dashboard.briefing.avgSeverity', 'avg severity')} {stats.avg_severity}</span>
                            {stats.top_categories?.slice(0, 3).map((cat) => (
                                <span key={cat}>{cat}</span>
                            ))}
                        </div>
                    )}

                    {/* Conflict Sections */}
                    {conflictSections.length > 0 && (
                        <div>
                            <h4 className="font-mono text-[10px] text-text-muted tracking-widest uppercase mb-3">
                                {t('dashboard.briefing.conflictBreakdown', 'Conflict Breakdown')}
                            </h4>
                            <div className="space-y-4">
                                {conflictSections.map((section, i) => (
                                    <ConflictSection key={i} section={section} />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Key Developments */}
                    {developments.length > 0 && (
                        <div>
                            <h4 className="font-mono text-[10px] text-text-muted tracking-widest uppercase mb-2">
                                {t('dashboard.briefing.keyDevelopments', 'Key Developments')}
                            </h4>
                            <div className="space-y-1.5">
                                {developments.map((dev, i) => (
                                    <KeyDevelopment key={i} dev={dev} index={i} />
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Newsletter CTA */}
                <Link
                    href="/newsletter"
                    className="flex items-center gap-3 mx-5 mb-4 px-4 py-3 border border-green-base/30 bg-green-base/5 rounded hover:bg-green-dim hover:border-green-base transition-colors group"
                >
                    <svg className="w-4 h-4 text-green-base group-hover:text-green-bright transition-colors flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <div className="flex-1 min-w-0">
                        <span className="block font-mono text-xs text-green-bright group-hover:text-green-neon tracking-wider uppercase">
                            {t('cta.getInInbox')}
                        </span>
                        <span className="block font-mono text-[10px] text-text-dim mt-0.5">
                            {t('cta.dailyAt7')}
                        </span>
                    </div>
                    <span className="font-mono text-xs text-green-base group-hover:text-green-bright transition-colors">
                        →
                    </span>
                </Link>

                {/* Footer */}
                <div className="flex items-center justify-between px-5 py-2.5 border-t border-border-mid bg-surface-0">
                    <button
                        onClick={onOpenArchive}
                        className="font-mono text-xs text-green-base hover:text-green-bright transition-colors"
                    >
                        {t('dashboard.briefing.archive', 'View archive')} →
                    </button>
                    <span className="font-mono text-[10px] text-text-dim">
                        {t('dashboard.briefing.generatedBy', 'Generated by')} {briefing.generated_by || 'AI'}
                    </span>
                </div>
            </div>
        </div>
    );
}
