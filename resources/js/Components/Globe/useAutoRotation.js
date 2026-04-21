import { useState, useCallback, useRef, useEffect } from 'react';
import { AUTO_ROTATE_MAX_ZOOM, AUTO_ROTATE_SPEED, AUTO_ROTATE_RESUME_DELAY } from './constants';

const INITIAL_VIEW_STATE = {
    latitude: 30,
    longitude: 30,
    zoom: 1.5,
    bearing: 0,
    pitch: 0,
};

/**
 * Custom hook for deck.gl GlobeView auto-rotation.
 *
 * - Increments longitude via requestAnimationFrame
 * - Only rotates at zoom <= AUTO_ROTATE_MAX_ZOOM
 * - Pauses on user interaction (onViewStateChange)
 * - Resumes after AUTO_ROTATE_RESUME_DELAY ms of idle
 */
export default function useAutoRotation() {
    const isDesktop = typeof window !== 'undefined' && window.innerWidth >= 1024;
    const [viewState, setViewState] = useState({
        ...INITIAL_VIEW_STATE,
        zoom: isDesktop ? 2.5 : 1.8,
    });
    const [isRotating, setIsRotating] = useState(true);
    const idleTimerRef = useRef(null);
    const lastFrameRef = useRef(null);
    const rafRef = useRef(null);

    // Animation loop
    useEffect(() => {
        const animate = (timestamp) => {
            if (lastFrameRef.current === null) {
                lastFrameRef.current = timestamp;
            }
            const delta = (timestamp - lastFrameRef.current) / 1000; // seconds
            lastFrameRef.current = timestamp;

            setViewState((prev) => {
                if (!isRotating || prev.zoom > AUTO_ROTATE_MAX_ZOOM) {
                    return prev;
                }
                return {
                    ...prev,
                    longitude: prev.longitude + AUTO_ROTATE_SPEED * delta * 10,
                };
            });

            rafRef.current = requestAnimationFrame(animate);
        };

        rafRef.current = requestAnimationFrame(animate);
        return () => {
            if (rafRef.current) cancelAnimationFrame(rafRef.current);
        };
    }, [isRotating]);

    // Handle user interaction — pause rotation
    const onViewStateChange = useCallback(({ viewState: newViewState }) => {
        setViewState({
            ...newViewState,
            bearing: 0, // GlobeView doesn't support bearing
            pitch: 0,   // GlobeView doesn't support pitch
        });

        // Pause rotation
        setIsRotating(false);
        lastFrameRef.current = null;

        // Schedule resume
        clearTimeout(idleTimerRef.current);
        idleTimerRef.current = setTimeout(() => {
            setIsRotating(true);
        }, AUTO_ROTATE_RESUME_DELAY);
    }, []);

    // Cleanup
    useEffect(() => {
        return () => clearTimeout(idleTimerRef.current);
    }, []);

    return { viewState, onViewStateChange, isRotating, setViewState };
}
