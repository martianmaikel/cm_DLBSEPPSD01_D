/**
 * Converts a GeoJSON FeatureCollection of Polygons/MultiPolygons
 * to a FeatureCollection of MultiLineStrings (border outlines).
 * Preserves feature id and properties for per-country styling.
 *
 * Applies Chaikin corner-cutting to smooth jagged low-resolution
 * polygon edges — critical for the wide neon glow lines at globe zoom.
 */

/**
 * Chaikin corner-cutting: replaces each segment with two points at 25%/75%,
 * rounding sharp corners while preserving overall shape.
 * Handles closed rings (first == last point) seamlessly.
 */
function chaikinSmooth(ring, iterations = 3) {
    let pts = ring;

    for (let iter = 0; iter < iterations; iter++) {
        const isClosed =
            pts.length > 2 &&
            pts[0][0] === pts[pts.length - 1][0] &&
            pts[0][1] === pts[pts.length - 1][1];

        const out = [];
        const len = isClosed ? pts.length - 1 : pts.length;

        for (let i = 0; i < len - 1; i++) {
            const a = pts[i];
            const b = pts[i + 1];
            out.push(
                [0.75 * a[0] + 0.25 * b[0], 0.75 * a[1] + 0.25 * b[1]],
                [0.25 * a[0] + 0.75 * b[0], 0.25 * a[1] + 0.75 * b[1]],
            );
        }

        if (isClosed) {
            // Smooth the wrap-around segment (last→first)
            const a = pts[len - 1];
            const b = pts[0];
            out.push(
                [0.75 * a[0] + 0.25 * b[0], 0.75 * a[1] + 0.25 * b[1]],
                [0.25 * a[0] + 0.75 * b[0], 0.25 * a[1] + 0.75 * b[1]],
            );
            // Close the ring
            out.push(out[0]);
        }

        pts = out;
    }

    return pts;
}

export function polygonsToLines(featureCollection) {
    return {
        type: 'FeatureCollection',
        features: featureCollection.features.map((f) => {
            const coords = [];
            const geom = f.geometry;

            if (geom.type === 'Polygon') {
                coords.push(...geom.coordinates);
            } else if (geom.type === 'MultiPolygon') {
                geom.coordinates.forEach((poly) => coords.push(...poly));
            }

            return {
                type: 'Feature',
                id: f.id,
                geometry: {
                    type: 'MultiLineString',
                    coordinates: coords.map((ring) => chaikinSmooth(ring)),
                },
                properties: f.properties,
            };
        }),
    };
}
