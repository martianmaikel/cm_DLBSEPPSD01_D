import { useState, useEffect, useRef } from 'react';

/**
 * Fetches per-country event aggregation for the heat layer.
 * Polls every 60s aligned with the dashboard's polling interval.
 */
export default function useCountryHeat(timeRange = '24h') {
    const [heatData, setHeatData] = useState(new Map());
    const abortRef = useRef(null);

    useEffect(() => {
        const fetchHeat = async () => {
            try {
                abortRef.current?.abort();
                abortRef.current = new AbortController();

                const params = new URLSearchParams();
                if (timeRange && timeRange !== '24h') params.set('time_range', timeRange);

                const res = await fetch(`/api/map/country-heat?${params}`, {
                    signal: abortRef.current.signal,
                });
                if (!res.ok) return;

                const data = await res.json();
                const map = new Map();
                for (const entry of data) {
                    map.set(entry.country, entry);
                }
                setHeatData(map);
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.warn('Country heat fetch failed:', err);
                }
            }
        };

        fetchHeat();
        const interval = setInterval(fetchHeat, 60000);
        return () => {
            clearInterval(interval);
            abortRef.current?.abort();
        };
    }, [timeRange]);

    return heatData;
}
