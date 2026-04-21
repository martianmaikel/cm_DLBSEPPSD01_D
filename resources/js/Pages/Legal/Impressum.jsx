import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

export default function Impressum() {
    const { t } = useTranslation();

    return (
        <AppLayout breadcrumbs={[{ label: t('legal.impressum', 'Impressum') }]}>

            <div className="max-w-3xl mx-auto space-y-8">
                <div>
                    <h1 className="font-display text-4xl tracking-wider text-green-bright mb-2">
                        {t('legal.impressum', 'IMPRESSUM')}
                    </h1>
                    <p className="font-mono text-xs text-text-muted tracking-wide">
                        {t('legal.impressumSubtitle', 'Legal notice pursuant to § 5 DDG')}
                    </p>
                </div>

                <div className="bg-surface-1 border border-border-mid rounded p-5 space-y-6 text-sm text-text-secondary leading-relaxed">
                    {/* Operator */}
                    <div>
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-3">
                            {t('legal.operator', 'Angaben gemäß § 5 DDG')}
                        </h2>
                        <p>Maikel Szymanski – Software &amp; Beratung</p>
                        <p>Bahnhofstraße 24</p>
                        <p>01796 Pirna</p>
                    </div>

                    {/* Owner */}
                    <div>
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-3">
                            {t('legal.owner', 'Inhaber')}
                        </h2>
                        <p>Maikel Szymanski</p>
                    </div>

                    {/* Contact */}
                    <div>
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-3">
                            {t('legal.contact', 'Kontakt')}
                        </h2>
                        <p>
                            E-Mail:{' '}
                            <a href="mailto:maikel.szy.developer@gmail.com" className="text-green-bright hover:text-green-neon transition-colors">
                                maikel.szy.developer@gmail.com
                            </a>
                        </p>
                    </div>

                    {/* Tax */}
                    <div>
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-3">
                            {t('legal.taxNumber', 'Steuernummer')}
                        </h2>
                        <p>210/280/02391</p>
                    </div>

                    {/* VAT ID */}
                    <div>
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-3">
                            {t('legal.vatId', 'Umsatzsteuer-Identifikationsnummer')}
                        </h2>
                        <p className="text-text-muted text-xs mb-1">
                            Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz:
                        </p>
                        <p>DE369296001</p>
                    </div>

                    {/* Dispute resolution */}
                    <div>
                        <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted mb-3">
                            {t('legal.disputeResolution', 'Streitschlichtung')}
                        </h2>
                        <p>
                            Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:{' '}
                            <a
                                href="https://ec.europa.eu/consumers/odr/"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-green-bright hover:text-green-neon transition-colors break-all"
                            >
                                https://ec.europa.eu/consumers/odr/
                            </a>
                        </p>
                        <p className="mt-2">
                            Unsere E-Mail-Adresse finden Sie oben im Impressum.
                        </p>
                        <p className="mt-2">
                            Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer
                            Verbraucherschlichtungsstelle teilzunehmen.
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
