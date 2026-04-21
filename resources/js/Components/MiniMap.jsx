import { lazy, Suspense } from 'react';

const MiniMapInner = lazy(() => import('./MiniMapInner'));

export default function MiniMap({ lat, lng, className = '' }) {
    return (
        <Suspense fallback={
            <div className={`bg-surface-2 border border-border-mid rounded flex items-center justify-center ${className}`}>
                <span className="font-mono text-[10px] text-text-dim tracking-wider uppercase">Loading map...</span>
            </div>
        }>
            <MiniMapInner lat={lat} lng={lng} className={className} />
        </Suspense>
    );
}
