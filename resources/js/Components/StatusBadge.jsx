import { useTranslation } from 'react-i18next';

const STATUS_STYLES = {
    unverified: 'border border-dashed border-text-muted text-text-muted',
    corroborated: 'border border-amber text-amber',
    confirmed: 'border border-green-base text-green-bright',
    disputed: 'border border-red-alert text-red-bright',
    retracted: 'border border-text-dim text-text-dim line-through',
    pending_classification: 'border border-dashed border-blue-intel text-blue-bright',
};

export default function StatusBadge({ status }) {
    const { t } = useTranslation();
    const style = STATUS_STYLES[status] || STATUS_STYLES.unverified;

    return (
        <span className={`inline-block font-mono text-xs tracking-widest uppercase px-2 py-0.5 rounded ${style}`}>
            {t(`status.${status}`, status)}
        </span>
    );
}
