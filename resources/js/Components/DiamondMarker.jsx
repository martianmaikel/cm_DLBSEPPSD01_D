const SEVERITY_COLORS = {
    high: '#C0392B',
    medium: '#C97B1A',
    low: '#3D7A32',
    none: '#1F261C',
};

function getSeverityLevel(severity) {
    const s = Number(severity) || 0;
    if (s >= 7) return 'high';
    if (s >= 4) return 'medium';
    if (s >= 1) return 'low';
    return 'none';
}

export default function DiamondMarker({ x, y, severity, status, size = 10, onClick }) {
    const level = getSeverityLevel(severity);
    const color = SEVERITY_COLORS[level];
    const isUnverified = status === 'unverified';
    const half = size / 2;

    const points = `${x},${y - half} ${x + half},${y} ${x},${y + half} ${x - half},${y}`;

    return (
        <polygon
            points={points}
            fill={color}
            fillOpacity={isUnverified ? 0.4 : 0.85}
            stroke={isUnverified ? color : '#52A844'}
            strokeWidth={isUnverified ? 1 : 1.5}
            strokeDasharray={isUnverified ? '3,2' : 'none'}
            style={{ cursor: onClick ? 'pointer' : 'default' }}
            onClick={onClick}
        />
    );
}
