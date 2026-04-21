import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

function Section({ id, title, children }) {
    return (
        <section id={id} className="scroll-mt-8">
            <h2 className="font-display text-2xl tracking-wider text-green-bright mb-3">
                {title}
            </h2>
            <div className="text-sm text-text-secondary leading-relaxed space-y-3">
                {children}
            </div>
        </section>
    );
}

function PipelineStep({ number, label, active = false }) {
    return (
        <div className="flex items-center gap-3">
            <div className={`w-8 h-8 rounded flex items-center justify-center font-mono text-sm font-bold border ${
                active
                    ? 'border-green-bright text-green-bright bg-green-dim'
                    : 'border-border-mid text-text-muted'
            }`}>
                {number}
            </div>
            <span className={`font-mono text-xs tracking-wider uppercase ${active ? 'text-green-bright' : 'text-text-muted'}`}>
                {label}
            </span>
            <div className="flex-1 border-t border-border-subtle" />
        </div>
    );
}

export default function MethodologyIndex() {
    const { t } = useTranslation();

    const breadcrumbs = [
        { label: t('nav.methodology', 'Methodology') },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="max-w-3xl mx-auto space-y-10">
                {/* Page header */}
                <div>
                    <h1 className="font-display text-4xl tracking-wider text-green-bright mb-2">
                        {t('methodology.title', 'METHODOLOGY')}
                    </h1>
                    <p className="font-mono text-sm text-text-muted tracking-wide">
                        {t('methodology.subtitle', 'How ClashMonitor processes, verifies, and presents conflict data')}
                    </p>
                </div>

                {/* Pipeline overview */}
                <div className="bg-surface-1 border border-border-mid rounded p-5 space-y-3">
                    <h3 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-4">
                        Processing Pipeline
                    </h3>
                    <PipelineStep number="1" label="Ingestion" active />
                    <PipelineStep number="2" label="AI Classification" active />
                    <PipelineStep number="3" label="Geolocation" active />
                    <PipelineStep number="4" label="Corroboration" active />
                    <PipelineStep number="5" label="Conflict Threading" active />
                    <PipelineStep number="6" label="Reconciliation" active />
                </div>

                {/* Sections */}
                <Section id="ingestion" title={t('methodology.ingestion.title', 'INGESTION')}>
                    <p>{t('methodology.ingestion.description', 'ClashMonitor continuously monitors multiple data sources for conflict-related reports. RSS feeds are polled on configurable intervals (default: 10 minutes), while Telegram channels and high-priority OSINT sources are checked more frequently (default: 5 minutes). Each incoming item is hashed using a combination of title, source, and approximate timestamp, then checked against a deduplication cache. Duplicate reports are discarded silently to prevent noise.')}</p>
                    <p>{t('methodology.ingestion.sources', 'Supported source types include RSS/Atom feeds, Telegram channels, and manual submissions. Each source has a configurable polling interval and reliability score that feeds into the verification pipeline.')}</p>
                </Section>

                <Section id="classification" title={t('methodology.classification.title', 'AI CLASSIFICATION')}>
                    <p>{t('methodology.classification.description', 'Each new event is processed by a large language model in a single structured call. The model returns a JSON object containing: category (e.g. airstrike, artillery, troop movement, humanitarian), severity (1-10 scale of impact), confidence (1-10 model certainty), extracted entities (persons, military units, organizations, locations), geographic context (country, region, coordinates if specific), and a neutral summary.')}</p>
                    <p>{t('methodology.classification.note', 'Classification and entity extraction happen in one pass to minimize latency. All AI-generated content is clearly labeled as such. Classification failures are caught and the raw event is stored with status pending_classification for automatic retry.')}</p>
                </Section>

                <Section id="geolocation" title={t('methodology.geolocation.title', 'GEOLOCATION')}>
                    <p>{t('methodology.geolocation.description', 'When the classification step returns a specific location name, it is geocoded to coordinates using the Nominatim geocoding service. Country-level fallback is used when no specific location can be extracted. All coordinates are stored as PostGIS geometry for efficient spatial queries. Events without reliable coordinates are flagged as geo_approximate.')}</p>
                </Section>

                <Section id="corroboration" title={t('methodology.corroboration.title', 'CORROBORATION')}>
                    <p>{t('methodology.corroboration.description', 'When a new event is stored, it is immediately compared against all events from the past 24 hours using a combination of embedding cosine similarity (semantic match), entity overlap (shared named entities), and country plus category match (structural match). If a match is found from a different source family, a corroboration link is created.')}</p>
                    <p>{t('methodology.corroboration.families', 'Source family grouping is critical to prevent false corroboration. A Google News item linking to Reuters counts as Reuters, not as an independent source. Only organizationally independent sources increment the corroboration count.')}</p>
                </Section>

                <Section id="threading" title={t('methodology.threading.title', 'CONFLICT THREADING')}>
                    <p>{t('methodology.threading.description', 'After corroboration, each event is evaluated for assignment to a conflict thread — a named, evolving narrative that groups related events over time. The AI compares the event against open threads using semantic similarity on titles, summaries, and entity overlap. High-confidence matches are automatically assigned; low-confidence matches are queued for review. If no match exists and the event appears to start a new sustained situation, a new thread is created.')}</p>
                </Section>

                <Section id="reconciliation" title={t('methodology.reconciliation.title', 'RECONCILIATION')}>
                    <p>{t('methodology.reconciliation.description', 'A periodic background job re-examines events from the past 48 hours to catch late-arriving corroborations — sources that were slow to publish or were added to the system after the initial window. Reconciliation does not reclassify events; it only updates corroboration links and status upgrades.')}</p>
                </Section>

                <Section id="verification" title={t('methodology.verification.title', 'VERIFICATION STATUS')}>
                    <p>{t('methodology.verification.description', 'Every event has a verification status that reflects how well-corroborated it is:')}</p>
                    <div className="bg-surface-0 border border-border-mid rounded p-3 sm:p-4 space-y-2 font-mono text-xs">
                        <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                            <span className="w-24 flex-shrink-0 text-text-muted">UNVERIFIED</span>
                            <span className="text-text-secondary">Single source, no corroboration</span>
                        </div>
                        <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                            <span className="w-24 flex-shrink-0 text-amber">CORROBORATED</span>
                            <span className="text-text-secondary">2+ independent source families report the same event</span>
                        </div>
                        <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                            <span className="w-24 flex-shrink-0 text-green-bright">CONFIRMED</span>
                            <span className="text-text-secondary">3+ independent source families, or manual editor confirmation</span>
                        </div>
                        <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                            <span className="w-24 flex-shrink-0 text-red-bright">DISPUTED</span>
                            <span className="text-text-secondary">Active corroboration contradicted by a counter-report</span>
                        </div>
                        <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                            <span className="w-24 flex-shrink-0 text-text-dim">RETRACTED</span>
                            <span className="text-text-secondary">Source has issued a correction or deletion</span>
                        </div>
                    </div>
                    <p>{t('methodology.verification.automation', 'Status can only move forward automatically (unverified to corroborated to confirmed). Moving to disputed or retracted requires either AI detection of a contradicting report or manual editor action.')}</p>
                </Section>
            </div>
        </AppLayout>
    );
}
