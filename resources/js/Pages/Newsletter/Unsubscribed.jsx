import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

export default function Unsubscribed({ email }) {
    const { t } = useTranslation();

    return (
        <AppLayout breadcrumbs={[{ label: t('newsletter.pageTitle') }]}>
            <Head title={t('newsletter.unsubscribedTitle')} />

            <div className="max-w-xl mx-auto">
                <div className="bg-surface-1 border border-border-mid rounded p-8 text-center">
                    <div className="font-mono text-xs tracking-widest uppercase text-text-secondary mb-4">
                        {t('newsletter.unsubscribedTitle').toUpperCase()}
                    </div>
                    {email && (
                        <p className="font-mono text-xs text-text-dim mb-3">{email}</p>
                    )}
                    <p className="font-sans text-base text-text-primary leading-relaxed mb-6">
                        {t('newsletter.unsubscribedBody')}
                    </p>
                    <Link
                        href="/"
                        className="inline-block font-mono text-xs tracking-widest uppercase px-6 py-2.5 border border-border-mid text-text-secondary hover:border-green-base hover:text-green-bright transition-colors rounded"
                    >
                        {t('common.back')}
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
