const STATUS_STYLES = {
    pending: 'border-dashed border-text-muted text-text-muted',
    enriching: 'border-dashed border-blue-intel text-blue-bright',
    enriched: 'border-green-base text-green-bright',
    failed: 'border-red-alert text-red-bright',
};

export default function EnrichmentStatusBadge({ status }) {
    const style = STATUS_STYLES[status] || STATUS_STYLES.pending;
    return (
        <span className={`inline-block font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded ${style}`}>
            {status}
        </span>
    );
}
