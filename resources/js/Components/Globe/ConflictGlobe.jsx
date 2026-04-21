import { lazy, Suspense } from 'react';
import GlobeLoadingSkeleton from './GlobeLoadingSkeleton';

const ConflictGlobeInner = lazy(() => import('./ConflictGlobeInner'));

export default function ConflictGlobe(props) {
    // SSR guard: do not render WebGL on server
    if (typeof window === 'undefined') {
        return <GlobeLoadingSkeleton />;
    }

    return (
        <Suspense fallback={<GlobeLoadingSkeleton />}>
            <ConflictGlobeInner {...props} />
        </Suspense>
    );
}
