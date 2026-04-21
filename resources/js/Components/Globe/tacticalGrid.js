/**
 * Generates a GeoJSON FeatureCollection of grid lines
 * centered around a coordinate — used for tactical overlay
 * on severe events (severity >= 8).
 */
export function generateTacticalGrid(lng, lat, cellSizeDeg = 0.5, gridCount = 4) {
    const features = [];
    const half = (gridCount / 2) * cellSizeDeg;

    // Vertical lines
    for (let i = -gridCount / 2; i <= gridCount / 2; i++) {
        const x = lng + i * cellSizeDeg;
        features.push({
            type: 'Feature',
            geometry: {
                type: 'LineString',
                coordinates: [[x, lat - half], [x, lat + half]],
            },
            properties: {},
        });
    }

    // Horizontal lines
    for (let i = -gridCount / 2; i <= gridCount / 2; i++) {
        const y = lat + i * cellSizeDeg;
        features.push({
            type: 'Feature',
            geometry: {
                type: 'LineString',
                coordinates: [[lng - half, y], [lng + half, y]],
            },
            properties: {},
        });
    }

    return { type: 'FeatureCollection', features };
}
