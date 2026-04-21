import { memo } from 'react';
import { Marker } from 'react-map-gl/maplibre';

/**
 * Tactical info card that appears near hotzone countries on the globe
 * when zoomed out. Shows country name, event count, and severity.
 * Clicking zooms to the country and filters the event feed.
 */

function getSeverityLabel(sev) {
    if (sev >= 8) return 'CRITICAL';
    if (sev >= 6) return 'HIGH';
    if (sev >= 4) return 'ELEVATED';
    return 'ACTIVE';
}

function getSeverityClass(sev) {
    if (sev >= 7) return 'hotzone-sev--critical';
    if (sev >= 4) return 'hotzone-sev--high';
    return 'hotzone-sev--active';
}

function HotzoneCard({ country, lat, lng, eventCount, maxSeverity, zoom, onClick }) {
    if (zoom > 5) return null;

    const opacity = zoom > 4 ? Math.max(0, 1 - (zoom - 4)) : 1;
    const sevLabel = getSeverityLabel(maxSeverity);
    const sevClass = getSeverityClass(maxSeverity);

    return (
        <Marker longitude={lng} latitude={lat} anchor="bottom-left" subpixelPositioning>
            <div
                className="hotzone-card"
                style={{ opacity }}
                onClick={(e) => { e.stopPropagation(); onClick(); }}
            >
                {/* Connector dot */}
                <div className={`hotzone-dot ${sevClass}`} />

                {/* Info panel */}
                <div className="hotzone-panel">
                    <div className="hotzone-header">
                        <span className={`hotzone-status ${sevClass}`}>{sevLabel}</span>
                    </div>
                    <div className="hotzone-country">{country}</div>
                    <div className="hotzone-meta">
                        <span>{eventCount} EVENT{eventCount !== 1 ? 'S' : ''}</span>
                        <span className="hotzone-sep">/</span>
                        <span>SEV {maxSeverity}</span>
                    </div>
                </div>
            </div>
        </Marker>
    );
}

// Skip onClick comparison — reference changes but behavior doesn't
export default memo(HotzoneCard, (prev, next) =>
    prev.country === next.country &&
    prev.zoom === next.zoom &&
    prev.eventCount === next.eventCount &&
    prev.maxSeverity === next.maxSeverity
);
