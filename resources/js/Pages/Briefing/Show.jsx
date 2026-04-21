import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';
import ShareButtons from '../../Components/ShareButtons';

function formatDate(dateStr, locale) {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(locale === 'de' ? 'de-DE' : 'en-US', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
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
        <div className="relative pl-4 border-l-2 border-border-mid">
            <div className={`absolute left-[-1px] top-0 w-0.5 h-full ${getSeverityBar(section.max_severity)}`} />
            <div className="flex items-start justify-between gap-3 mb-1.5">
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
            <p className="text-sm text-text-secondary leading-relaxed">
                {section.summary}
            </p>
        </div>
    );
}

function KeyDevelopment({ dev, index }) {
    return (
        <div className="flex items-start gap-3 text-sm">
            <span className={`font-mono text-xs w-5 text-center flex-shrink-0 mt-0.5 ${getSeverityColor(dev.severity)}`}>
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

export default function Show({ briefing, previousDate, nextDate }) {
    const { t, i18n } = useTranslation();
    const isDe = i18n.language === 'de';

    const summary = isDe ? (briefing.summary_de || briefing.summary_en) : briefing.summary_en;
    const conflictSections = briefing.conflict_sections?.[i18n.language] || briefing.conflict_sections?.en || [];
    const developments = briefing.key_developments || [];
    const stats = briefing.statistics || {};

    const breadcrumbs = [
        { label: t('nav.briefing', 'Briefing'), href: '/briefing' },
        { label: briefing.briefing_date },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="max-w-3xl mx-auto space-y-6">
                {/* Header */}
                <div className="bg-surface-1 border border-border-mid rounded p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h1 className="font-display text-xl tracking-wider text-green-bright">
                            {isDe ? 'TAGESBRIEFING' : 'DAILY INTEL BRIEFING'}
                        </h1>
                        <div className="flex items-center gap-3">
                            <span className="font-mono text-xs text-text-dim">
                                {formatDate(briefing.briefing_date, i18n.language)}
                            </span>
                            <ShareButtons title={`Daily Intel Briefing — ${briefing.briefing_date}`} />
                        </div>
                    </div>

                    <h2 className="font-ui text-lg font-semibold text-text-primary mb-3">
                        {briefing.title}
                    </h2>

                    <p className="text-sm text-text-secondary leading-relaxed">
                        {summary}
                    </p>
                </div>

                {/* Statistics */}
                {stats.total_events && (
                    <div className="flex flex-wrap gap-x-5 gap-y-1.5 py-3 px-4 bg-surface-1 rounded border border-border-mid font-mono text-xs text-text-muted tracking-wider uppercase">
                        <span>{stats.total_events} {t('dashboard.briefing.events', 'events')}</span>
                        <span>{t('dashboard.briefing.avgSeverity', 'avg severity')} {stats.avg_severity}</span>
                        {stats.new_threads > 0 && (
                            <span>{stats.new_threads} {isDe ? 'neue Konflikte' : 'new conflicts'}</span>
                        )}
                        {stats.top_categories?.slice(0, 3).map((cat) => (
                            <span key={cat}>{cat}</span>
                        ))}
                    </div>
                )}

                {/* Conflict Breakdown */}
                {conflictSections.length > 0 && (
                    <div className="bg-surface-1 border border-border-mid rounded p-6">
                        <h3 className="font-mono text-xs text-text-muted tracking-widest uppercase mb-4">
                            {t('dashboard.briefing.conflictBreakdown', 'Conflict Breakdown')}
                        </h3>
                        <div className="space-y-5">
                            {conflictSections.map((section, i) => (
                                <ConflictSection key={i} section={section} />
                            ))}
                        </div>
                    </div>
                )}

                {/* Key Developments */}
                {developments.length > 0 && (
                    <div className="bg-surface-1 border border-border-mid rounded p-6">
                        <h3 className="font-mono text-xs text-text-muted tracking-widest uppercase mb-3">
                            {t('dashboard.briefing.keyDevelopments', 'Key Developments')}
                        </h3>
                        <div className="space-y-2">
                            {developments.map((dev, i) => (
                                <KeyDevelopment key={i} dev={dev} index={i} />
                            ))}
                        </div>
                    </div>
                )}

                {/* Subscribe CTA */}
                <div className="bg-surface-2 border border-green-base/30 rounded p-5 text-center">
                    <p className="font-mono text-xs text-text-muted tracking-wider uppercase mb-2">
                        {isDe ? 'Tägliches Briefing per E-Mail erhalten' : 'Get this briefing delivered to your inbox daily'}
                    </p>
                    <Link
                        href="/newsletter"
                        className="inline-block font-mono text-sm text-green-bright hover:text-green-base transition-colors border border-green-bright/40 hover:border-green-bright rounded px-4 py-2"
                    >
                        {isDe ? 'Jetzt abonnieren' : 'Subscribe now'} →
                    </Link>
                </div>

                {/* Navigation */}
                <div className="flex items-center justify-between">
                    {previousDate ? (
                        <Link
                            href={`/briefing/${previousDate}`}
                            className="font-mono text-xs text-green-base hover:text-green-bright transition-colors"
                        >
                            ← {previousDate}
                        </Link>
                    ) : <span />}
                    {nextDate ? (
                        <Link
                            href={`/briefing/${nextDate}`}
                            className="font-mono text-xs text-green-base hover:text-green-bright transition-colors"
                        >
                            {nextDate} →
                        </Link>
                    ) : <span />}
                </div>
            </div>
        </AppLayout>
    );
}
