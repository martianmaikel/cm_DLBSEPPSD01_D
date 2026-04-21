import { useTranslation } from 'react-i18next';

export default function CategoryBadge({ category }) {
    const { t } = useTranslation();

    return (
        <span className="inline-block font-mono text-xs tracking-widest uppercase px-2 py-0.5 bg-surface-2 border border-border-mid text-text-secondary rounded">
            {t(`category.${category}`, category)}
        </span>
    );
}
