import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

function Section({ title, children }) {
    return (
        <div className="space-y-3">
            <h2 className="font-mono text-xs tracking-widest uppercase text-text-muted">
                {title}
            </h2>
            <div className="text-sm text-text-secondary leading-relaxed space-y-2">
                {children}
            </div>
        </div>
    );
}

export default function Privacy() {
    const { t } = useTranslation();

    return (
        <AppLayout breadcrumbs={[{ label: t('legal.privacy', 'Datenschutz') }]}>

            <div className="max-w-3xl mx-auto space-y-8">
                <div>
                    <h1 className="font-display text-4xl tracking-wider text-green-bright mb-2">
                        {t('legal.privacy', 'DATENSCHUTZ')}
                    </h1>
                    <p className="font-mono text-xs text-text-muted tracking-wide">
                        {t('legal.privacySubtitle', 'Datenschutzerklärung gemäß DSGVO')}
                    </p>
                </div>

                <div className="bg-surface-1 border border-border-mid rounded p-5 space-y-8">
                    <Section title="1. Verantwortlicher">
                        <p>
                            Maikel Szymanski – Software &amp; Beratung<br />
                            Bahnhofstraße 24, 01796 Pirna<br />
                            E-Mail:{' '}
                            <a href="mailto:maikel.szy.developer@gmail.com" className="text-green-bright hover:text-green-neon transition-colors">
                                maikel.szy.developer@gmail.com
                            </a>
                        </p>
                    </Section>

                    <Section title="2. Allgemeines zur Datenverarbeitung">
                        <p>
                            Wir verarbeiten personenbezogene Daten unserer Nutzer grundsätzlich nur, soweit dies zur
                            Bereitstellung einer funktionsfähigen Website sowie unserer Inhalte und Leistungen erforderlich
                            ist. Die Verarbeitung personenbezogener Daten erfolgt regelmäßig nur nach Einwilligung des
                            Nutzers. Eine Ausnahme gilt in solchen Fällen, in denen eine vorherige Einholung einer
                            Einwilligung aus tatsächlichen Gründen nicht möglich ist und die Verarbeitung der Daten durch
                            gesetzliche Vorschriften gestattet ist.
                        </p>
                    </Section>

                    <Section title="3. Hosting und technische Bereitstellung">
                        <p>
                            Diese Website wird bei einem externen Dienstleister gehostet. Die personenbezogenen Daten, die
                            auf dieser Website erfasst werden, werden auf den Servern des Hosters gespeichert. Hierbei kann
                            es sich insbesondere um IP-Adressen, Browsertyp, Betriebssystem, die aufgerufene Seite, Datum
                            und Uhrzeit des Zugriffs handeln.
                        </p>
                        <p>
                            Die Nutzung des Hosters erfolgt auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO. Wir haben ein
                            berechtigtes Interesse an einer zuverlässigen Darstellung unserer Website.
                        </p>
                    </Section>

                    <Section title="4. Server-Log-Dateien">
                        <p>
                            Der Provider der Seiten erhebt und speichert automatisch Informationen in sogenannten
                            Server-Log-Dateien, die Ihr Browser automatisch an uns übermittelt. Dies sind:
                        </p>
                        <ul className="list-disc list-inside space-y-1 text-text-muted">
                            <li>Browsertyp und Browserversion</li>
                            <li>Verwendetes Betriebssystem</li>
                            <li>Referrer URL</li>
                            <li>Hostname des zugreifenden Rechners</li>
                            <li>IP-Adresse</li>
                            <li>Uhrzeit der Serveranfrage</li>
                        </ul>
                        <p>
                            Eine Zusammenführung dieser Daten mit anderen Datenquellen wird nicht vorgenommen.
                            Grundlage für die Datenverarbeitung ist Art. 6 Abs. 1 lit. f DSGVO.
                        </p>
                    </Section>

                    <Section title="5. Cookies und lokale Speicherung">
                        <p>
                            Diese Website verwendet technisch notwendige Session-Cookies für den Betrieb der Website.
                            Darüber hinaus wird die gewählte Spracheinstellung im Local Storage Ihres Browsers gespeichert
                            (Schlüssel: <span className="font-mono text-text-muted">fw-lang</span>). Diese Speicherung dient
                            ausschließlich der Benutzerfreundlichkeit und enthält keine personenbezogenen Daten.
                        </p>
                        <p>
                            Es werden keine Tracking-Cookies, Analyse-Cookies oder Werbe-Cookies eingesetzt.
                        </p>
                    </Section>

                    <Section title="6. Newsletter">
                        <p>
                            Wenn Sie unseren Newsletter abonnieren, werden folgende Daten erhoben:
                        </p>
                        <ul className="list-disc list-inside space-y-1 text-text-muted">
                            <li>E-Mail-Adresse (Pflichtangabe)</li>
                            <li>Zeitzone (automatisch erkannt)</li>
                            <li>Sprachpräferenz</li>
                            <li>Gewählte Konflikt-Threads</li>
                        </ul>
                        <p>
                            Die Verarbeitung erfolgt auf Grundlage Ihrer Einwilligung (Art. 6 Abs. 1 lit. a DSGVO).
                            Sie können diese Einwilligung jederzeit widerrufen, indem Sie sich über den in jeder E-Mail
                            enthaltenen Link abmelden. Die Rechtmäßigkeit der bereits erfolgten Datenverarbeitungsvorgänge
                            bleibt vom Widerruf unberührt.
                        </p>
                        <p>
                            Zum Versand der Newsletter nutzen wir Amazon Simple Email Service (SES). Ihr E-Mail-Anbieter
                            kann dabei Metadaten wie Öffnungszeiten erfassen — dies liegt außerhalb unserer Kontrolle.
                        </p>
                    </Section>

                    <Section title="7. KI-gestützte Datenverarbeitung">
                        <p>
                            ClashMonitor verarbeitet ausschließlich öffentlich zugängliche Informationen aus
                            Nachrichtenquellen und OSINT-Kanälen mithilfe von KI-Modellen. Es werden dabei keine
                            personenbezogenen Daten der Websitebesucher an KI-Dienste übermittelt.
                        </p>
                        <p>
                            Die KI-generierten Zusammenfassungen und Klassifikationen beziehen sich auf öffentliche
                            Nachrichteninhalte und sind als solche gekennzeichnet.
                        </p>
                    </Section>

                    <Section title="8. Ihre Rechte">
                        <p>Sie haben gegenüber uns folgende Rechte hinsichtlich der Sie betreffenden personenbezogenen Daten:</p>
                        <ul className="list-disc list-inside space-y-1 text-text-muted">
                            <li>Recht auf Auskunft (Art. 15 DSGVO)</li>
                            <li>Recht auf Berichtigung (Art. 16 DSGVO)</li>
                            <li>Recht auf Löschung (Art. 17 DSGVO)</li>
                            <li>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
                            <li>Recht auf Datenübertragbarkeit (Art. 20 DSGVO)</li>
                            <li>Recht auf Widerspruch (Art. 21 DSGVO)</li>
                        </ul>
                        <p>
                            Zur Ausübung Ihrer Rechte wenden Sie sich bitte an die oben genannte E-Mail-Adresse.
                        </p>
                    </Section>

                    <Section title="9. Beschwerderecht bei einer Aufsichtsbehörde">
                        <p>
                            Unbeschadet eines anderweitigen verwaltungsrechtlichen oder gerichtlichen Rechtsbehelfs steht
                            Ihnen das Recht auf Beschwerde bei einer Aufsichtsbehörde zu, wenn Sie der Ansicht sind, dass
                            die Verarbeitung der Sie betreffenden personenbezogenen Daten gegen die DSGVO verstößt.
                        </p>
                        <p>
                            Zuständige Aufsichtsbehörde:<br />
                            Sächsischer Datenschutz- und Transparenzbeauftragter<br />
                            Devrientstraße 5, 01067 Dresden
                        </p>
                    </Section>

                    <Section title="10. Aktualität und Änderungen">
                        <p>
                            Diese Datenschutzerklärung ist aktuell gültig und hat den Stand April 2026.
                            Wir behalten uns vor, diese Datenschutzerklärung anzupassen, damit sie stets den aktuellen
                            rechtlichen Anforderungen entspricht oder um Änderungen unserer Leistungen umzusetzen.
                        </p>
                    </Section>
                </div>
            </div>
        </AppLayout>
    );
}
