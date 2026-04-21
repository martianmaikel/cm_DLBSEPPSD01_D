const LOCATIONIQ_KEY = import.meta.env.VITE_LOCATIONIQ_KEY;

export const STYLE_URL = `https://tiles.locationiq.com/v3/streets/vector.json?key=${LOCATIONIQ_KEY}`;

export function darkenStyle(style) {
    style.projection = { type: 'globe' };
    style.sky = {
        'atmosphere-blend': ['interpolate', ['linear'], ['zoom'], 0, 1, 5, 1, 7, 0],
    };

    style.sources['satellite'] = {
        type: 'raster',
        tiles: [
            'https://tiles.maps.eox.at/wmts/1.0.0/s2cloudless-2021_3857/default/GoogleMapsCompatible/{z}/{y}/{x}.jpg',
        ],
        tileSize: 256,
        attribution: '&copy; <a href="https://s2maps.eu">Sentinel-2 cloudless</a> by EOX',
        maxzoom: 10,
    };

    const bgIdx = style.layers.findIndex((l) => l.type === 'background');
    style.layers.splice(bgIdx + 1, 0, {
        id: 'satellite-base',
        type: 'raster',
        source: 'satellite',
        paint: {
            'raster-brightness-max': 0.3,
            'raster-brightness-min': 0.0,
            'raster-saturation': -0.6,
            'raster-contrast': 0.2,
            'raster-opacity': 0.85,
            'raster-fade-duration': 300,
        },
    });

    for (const layer of style.layers) {
        if (layer.id === 'satellite-base') continue;
        if (!layer.paint) layer.paint = {};
        const sl = layer['source-layer'] || '';
        const id = layer.id || '';

        switch (layer.type) {
            case 'background':
                layer.paint['background-color'] = '#080A07';
                break;
            case 'fill':
                if (sl === 'water' || id.includes('water') || id.includes('ocean')) {
                    layer.paint['fill-color'] = '#0A1210';
                    layer.paint['fill-opacity'] = 0.4;
                } else if (sl === 'landcover' || sl === 'landuse') {
                    layer.paint['fill-opacity'] = 0;
                } else if (sl === 'building' || id.includes('building')) {
                    layer.paint['fill-color'] = '#1F261C';
                    layer.paint['fill-opacity'] = 0.7;
                } else {
                    layer.paint['fill-opacity'] = 0;
                }
                break;
            case 'line':
                if (sl === 'boundary' || id.includes('boundary') || id.includes('admin')) {
                    layer.paint['line-color'] = '#2D5426';
                } else if (sl === 'waterway' || id.includes('waterway')) {
                    layer.paint['line-color'] = '#1A3018';
                } else if (sl === 'transportation' || id.includes('road') || id.includes('tunnel') || id.includes('bridge')) {
                    layer.paint['line-color'] = '#1F261C';
                    layer.paint['line-opacity'] = 0.6;
                } else {
                    layer.paint['line-color'] = '#1A2618';
                    layer.paint['line-opacity'] = 0.5;
                }
                break;
            case 'symbol': {
                layer.paint['text-color'] = '#8AAD83';
                layer.paint['text-halo-color'] = '#080A07';
                layer.paint['text-halo-width'] = 1.5;

                const isPlace = sl === 'place' || id.includes('place');
                const isCountry = id.includes('country') || id.includes('continent');
                if (isPlace && !isCountry) {
                    layer.minzoom = Math.max(layer.minzoom || 0, 4.5);
                }
                break;
            }
        }
    }

    return style;
}

export function highContrastStyle(style) {
    style.projection = { type: 'globe' };
    style.sky = {
        'atmosphere-blend': ['interpolate', ['linear'], ['zoom'], 0, 1, 5, 1, 7, 0],
    };

    style.sources['satellite'] = {
        type: 'raster',
        tiles: [
            'https://tiles.maps.eox.at/wmts/1.0.0/s2cloudless-2021_3857/default/GoogleMapsCompatible/{z}/{y}/{x}.jpg',
        ],
        tileSize: 256,
        attribution: '&copy; <a href="https://s2maps.eu">Sentinel-2 cloudless</a> by EOX',
        maxzoom: 10,
    };

    const bgIdx = style.layers.findIndex((l) => l.type === 'background');
    style.layers.splice(bgIdx + 1, 0, {
        id: 'satellite-base',
        type: 'raster',
        source: 'satellite',
        paint: {
            'raster-brightness-max': 0.45,
            'raster-brightness-min': 0.05,
            'raster-saturation': -0.4,
            'raster-contrast': 0.35,
            'raster-opacity': 0.9,
            'raster-fade-duration': 300,
        },
    });

    for (const layer of style.layers) {
        if (layer.id === 'satellite-base') continue;
        if (!layer.paint) layer.paint = {};
        const sl = layer['source-layer'] || '';
        const id = layer.id || '';

        switch (layer.type) {
            case 'background':
                layer.paint['background-color'] = '#020302';
                break;
            case 'fill':
                if (sl === 'water' || id.includes('water') || id.includes('ocean')) {
                    layer.paint['fill-color'] = '#0E1A1E';
                    layer.paint['fill-opacity'] = 0.45;
                } else if (sl === 'landcover' || sl === 'landuse') {
                    layer.paint['fill-opacity'] = 0;
                } else if (sl === 'building' || id.includes('building')) {
                    layer.paint['fill-color'] = '#1C221A';
                    layer.paint['fill-opacity'] = 0.7;
                } else {
                    layer.paint['fill-opacity'] = 0;
                }
                break;
            case 'line':
                if (sl === 'boundary' || id.includes('boundary') || id.includes('admin')) {
                    layer.paint['line-color'] = '#3A6E30';
                    layer.paint['line-opacity'] = 0.8;
                } else if (sl === 'waterway' || id.includes('waterway')) {
                    layer.paint['line-color'] = '#1E3A1A';
                } else if (sl === 'transportation' || id.includes('road') || id.includes('tunnel') || id.includes('bridge')) {
                    layer.paint['line-color'] = '#1C221A';
                    layer.paint['line-opacity'] = 0.7;
                } else {
                    layer.paint['line-color'] = '#1E3018';
                    layer.paint['line-opacity'] = 0.6;
                }
                break;
            case 'symbol': {
                layer.paint['text-color'] = '#C0DEB8';
                layer.paint['text-halo-color'] = '#020302';
                layer.paint['text-halo-width'] = 2;

                const isPlace = sl === 'place' || id.includes('place');
                const isCountry = id.includes('country') || id.includes('continent');
                if (isPlace && !isCountry) {
                    layer.minzoom = Math.max(layer.minzoom || 0, 4.5);
                }
                break;
            }
        }
    }

    return style;
}
