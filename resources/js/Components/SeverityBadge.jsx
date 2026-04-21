export default function SeverityBadge({ severity }) {
    const level = Number(severity) || 0;

    let colorClass;
    if (level >= 7) {
        colorClass = 'bg-red-alert text-text-primary border-red-bright';
    } else if (level >= 4) {
        colorClass = 'bg-amber text-black border-amber-bright';
    } else {
        colorClass = 'bg-green-mid text-green-neon border-green-base';
    }

    return (
        <span
            className={`inline-flex items-center justify-center w-7 h-7 rounded font-mono text-xs font-bold border ${colorClass}`}
            title={`Severity ${level}`}
        >
            {level}
        </span>
    );
}
