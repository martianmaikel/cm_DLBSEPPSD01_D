import { useMemo, memo } from 'react';

function computeSparkline(events) {
    const now = Date.now();
    const hours = new Array(24).fill(0);
    for (const e of events) {
        if (!e.occurred_at) continue;
        const hoursAgo = (now - new Date(e.occurred_at).getTime()) / 3600000;
        const idx = Math.max(0, Math.min(23, 23 - Math.floor(hoursAgo)));
        hours[idx]++;
    }
    return hours;
}

function TimelineScrubber({ events, value, onChange }) {
    const sparkline = useMemo(() => computeSparkline(events), [events]);
    const maxCount = Math.max(1, ...sparkline);

    // Hour labels: show every 6h
    const labels = ['-24h', '-18h', '-12h', '-6h', 'NOW'];

    return (
        <div className="timeline-scrubber" onClick={(e) => e.stopPropagation()}>
            {/* Sparkline bars */}
            <div className="timeline-sparkline">
                {sparkline.map((count, i) => {
                    const height = (count / maxCount) * 100;
                    const dimmed = i > value;
                    return (
                        <div
                            key={i}
                            className={`timeline-bar${dimmed ? ' timeline-bar--dimmed' : ''}`}
                            style={{ height: `${Math.max(2, height)}%` }}
                            title={`${count} events`}
                        />
                    );
                })}
                {/* Cursor line */}
                <div
                    className="timeline-cursor-line"
                    style={{ left: `${((value + 0.5) / 24) * 100}%` }}
                />
            </div>

            {/* Slider */}
            <input
                type="range"
                className="timeline-range"
                min={0}
                max={23}
                step={1}
                value={value}
                onChange={(e) => onChange(parseInt(e.target.value, 10))}
            />

            {/* Labels */}
            <div className="timeline-labels">
                {labels.map((l, i) => (
                    <span key={i} className="timeline-label">{l}</span>
                ))}
            </div>
        </div>
    );
}

export default memo(TimelineScrubber);
