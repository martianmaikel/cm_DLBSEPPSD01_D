import { useState, useEffect, useRef } from 'react';

const BOOT_LINES = [
    { text: 'CLASHMONITOR.COM // TACTICAL INTELLIGENCE SYSTEM', delay: 0, type: 'header' },
    { text: '─'.repeat(48), delay: 150, type: 'rule' },
    { text: 'SYS INIT .......... OK', delay: 300, type: 'check' },
    { text: 'MAPCORE v5.21 ...... LOADING', delay: 600, type: 'loading', doneText: 'MAPCORE v5.21 ...... ONLINE' },
    { text: 'SAT LAYER .......... LOADING', delay: 900, type: 'loading', doneText: 'SAT LAYER .......... LINKED' },
    { text: 'GEO MESH ........... LOADING', delay: 1200, type: 'loading', doneText: 'GEO MESH ........... READY' },
    { text: 'OSINT FEED ......... LOADING', delay: 1500, type: 'loading', doneText: 'OSINT FEED ......... ACTIVE' },
    { text: '─'.repeat(48), delay: 1800, type: 'rule' },
    { text: 'ESTABLISHING GLOBAL COVERAGE ...', delay: 2000, type: 'status' },
];

export default function GlobeLoadingSkeleton() {
    const [visibleCount, setVisibleCount] = useState(0);
    const [elapsed, setElapsed] = useState(0);
    const startRef = useRef(Date.now());

    // Reveal lines one by one based on their delay
    useEffect(() => {
        const timers = BOOT_LINES.map((line, i) =>
            setTimeout(() => setVisibleCount((c) => Math.max(c, i + 1)), line.delay),
        );
        return () => timers.forEach(clearTimeout);
    }, []);

    // Elapsed timer for bottom display
    useEffect(() => {
        const interval = setInterval(() => {
            setElapsed(Date.now() - startRef.current);
        }, 100);
        return () => clearInterval(interval);
    }, []);

    const elapsedSec = (elapsed / 1000).toFixed(1);

    return (
        <div className="globe-boot flex flex-col items-center justify-center w-full h-full min-h-[400px] bg-black relative overflow-hidden">
            {/* Scanline overlay */}
            <div className="absolute inset-0 pointer-events-none globe-boot-scanlines" />

            {/* Rotating ring */}
            <div className="globe-boot-ring" />

            {/* Terminal output */}
            <div className="relative z-10 w-full max-w-md px-6">
                <div className="font-mono text-[11px] leading-relaxed space-y-[2px]">
                    {BOOT_LINES.slice(0, visibleCount).map((line, i) => (
                        <div key={i} className={`globe-boot-line globe-boot-line--${line.type}`}>
                            {line.type === 'loading' && i < visibleCount - 1
                                ? line.doneText
                                : line.text}
                            {line.type === 'loading' && i === visibleCount - 1 && (
                                <span className="globe-boot-cursor" />
                            )}
                        </div>
                    ))}
                </div>

                {/* Progress bar */}
                <div className="mt-5 h-[2px] bg-surface-2 rounded-full overflow-hidden">
                    <div
                        className="h-full bg-green-bright transition-all duration-300 ease-out"
                        style={{ width: `${Math.min(100, (visibleCount / BOOT_LINES.length) * 100)}%` }}
                    />
                </div>

                {/* Footer */}
                <div className="flex justify-between mt-2 font-mono text-[9px] text-text-dim tracking-wider uppercase">
                    <span>T+{elapsedSec}s</span>
                    <span>{Math.min(100, Math.round((visibleCount / BOOT_LINES.length) * 100))}%</span>
                </div>
            </div>
        </div>
    );
}
