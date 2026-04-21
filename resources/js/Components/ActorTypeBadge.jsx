const TYPE_STYLES = {
    person: 'border-green-base text-green-bright',
    organization: 'border-blue-intel text-blue-bright',
};

const TYPE_LABELS = {
    person: 'PERSON',
    organization: 'ORG',
};

export default function ActorTypeBadge({ type }) {
    const style = TYPE_STYLES[type] || 'border-border-mid text-text-muted';
    const label = TYPE_LABELS[type] || String(type || '').toUpperCase();

    return (
        <span className={`inline-block font-mono text-xs tracking-widest uppercase px-2 py-0.5 border rounded ${style}`}>
            {label}
        </span>
    );
}
