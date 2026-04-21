import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

export default function Subscribed({ email }) {
    const { t } = useTranslation();

    return (
        <AppLayout breadcrumbs={[{ label: t('newsletter.pageTitle') }]}>
            <Head title={t('newsletter.successTitle')} />

            <div className="max-w-xl mx-auto">
                <div className="bg-surface-1 border border-green-base rounded p-8 text-center">
                    <div className="font-mono text-xs tracking-widest uppercase text-green-bright mb-4">
                        ✓ {t('newsletter.successTitle').toUpperCase()}
                    </div>
                    <p className="font-sans text-base text-text-primary leading-relaxed">
                        {t('newsletter.successBody', { email: email || 'your inbox' })}
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
