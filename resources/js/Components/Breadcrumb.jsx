import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Breadcrumb({ crumbs = [] }) {
    const { t } = useTranslation();

    return (
        <nav className="flex items-center gap-2 font-mono text-xs tracking-widest uppercase text-text-muted">
            <Link href="/" className="hover:text-green-bright transition-colors">
                {t('nav.world')}
            </Link>
            {crumbs.map((crumb, i) => (
                <span key={i} className="flex items-center gap-2">
                    <span className="text-border-mid">/</span>
                    {crumb.href ? (
                        <Link href={crumb.href} className="hover:text-green-bright transition-colors">
                            {crumb.label}
                        </Link>
                    ) : (
                        <span className="text-text-secondary">{crumb.label}</span>
                    )}
                </span>
            ))}
        </nav>
    );
}
