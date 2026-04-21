import { memo } from 'react';
import { Marker } from 'react-map-gl/maplibre';
import { TACTICAL_SEVERITY_THRESHOLD } from './constants';

/**
 * Tactical rangefinder overlay for critical events (severity >= 8).
 * Renders as an HTML overlay positioned at the event's coordinates.
 */
function TacticalReticle({ event, zoom }) {
    if (event.severity < TACTICAL_SEVERITY_THRESHOLD || zoom < 4) return null;

    const scale = Math.min(1, (zoom - 4) / 2);
    const lat = event.coordinates[1];
    const lng = event.coordinates[0];
    const latDir = lat >= 0 ? 'N' : 'S';
    const lngDir = lng >= 0 ? 'E' : 'W';

    return (
        <Marker longitude={lng} latitude={lat} anchor="center" subpixelPositioning>
            <div className="tactical-reticle" style={{ opacity: scale }}>
                {/* Concentric rings */}
                <div className="reticle-ring reticle-ring--outer" />
                <div className="reticle-ring reticle-ring--inner" />

                {/* Crosshair */}
                <div className="reticle-crosshair" />

                {/* Corner brackets */}
                <div className="reticle-bracket reticle-bracket--tl" />
                <div className="reticle-bracket reticle-bracket--tr" />
                <div className="reticle-bracket reticle-bracket--bl" />
                <div className="reticle-bracket reticle-bracket--br" />

                {/* Labels */}
                <div className="reticle-label">
                    <span className="reticle-sev">SEV {event.severity}</span>
                    <span className="reticle-coords">
                        {Math.abs(lat).toFixed(4)}{latDir} {Math.abs(lng).toFixed(4)}{lngDir}
                    </span>
                </div>
            </div>
        </Marker>
    );
}

export default memo(TacticalReticle);
