import { useState } from 'react';
import MapGL, { Marker, NavigationControl } from 'react-map-gl/maplibre';
import 'maplibre-gl/dist/maplibre-gl.css';

const LOCATIONIQ_KEY = import.meta.env.VITE_LOCATIONIQ_KEY;
const STYLE_URL = `https://tiles.locationiq.com/v3/streets/vector.json?key=${LOCATIONIQ_KEY}`;

function DiamondMarker() {
    return (
        <div className="relative">
            <div
                className="w-4 h-4 bg-red-bright border-2 border-red-alert rotate-45 shadow-lg shadow-red-bright/30"
            />
            {/* Pulse ring */}
            <div
                className="absolute inset-0 w-4 h-4 border border-red-bright rotate-45 animate-ping opacity-40"
            />
        </div>
    );
}

export default function MiniMapInner({ lat, lng, className = '' }) {
    const [viewState] = useState({
        latitude: lat,
        longitude: lng,
        zoom: 8,
    });

    return (
        <div className={`relative overflow-hidden rounded border border-border-mid ${className}`}>
            <MapGL
                {...viewState}
                style={{ width: '100%', height: '100%' }}
                mapStyle={STYLE_URL}
                interactive={false}
                attributionControl={false}
                reuseMaps
            >
                <Marker latitude={lat} longitude={lng} anchor="center">
                    <DiamondMarker />
                </Marker>
            </MapGL>
            {/* Coordinates overlay */}
            <div className="absolute bottom-1.5 right-1.5 font-mono text-[10px] text-text-dim bg-surface-0/80 px-2 py-0.5 rounded backdrop-blur-sm">
                {lat.toFixed(4)}, {lng.toFixed(4)}
            </div>
        </div>
    );
}
