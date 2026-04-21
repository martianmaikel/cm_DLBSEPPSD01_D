import { memo } from 'react';

function CursorCoordinates({ lat, lng }) {
    if (lat == null || lng == null) return null;

    const latDir = lat >= 0 ? 'N' : 'S';
    const lngDir = lng >= 0 ? 'E' : 'W';

    return (
        <div className="cursor-coords">
            <span className="cursor-coords-value">
                {Math.abs(lat).toFixed(4)}&deg;{latDir}
            </span>
            <span className="cursor-coords-sep">/</span>
            <span className="cursor-coords-value">
                {Math.abs(lng).toFixed(4)}&deg;{lngDir}
            </span>
        </div>
    );
}

export default memo(CursorCoordinates);
