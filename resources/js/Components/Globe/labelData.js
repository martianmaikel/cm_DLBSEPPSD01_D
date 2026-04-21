/**
 * Static label dataset for zoom-dependent globe labels.
 * Each entry: { name, coordinates: [lng, lat], minZoom, maxZoom, size, type }
 *
 * type: 'continent' | 'capital' | 'major' | 'city'
 */

// Continent labels (zoom 3.5–5.5)
const continents = [
    { name: 'Africa', coordinates: [17.0, 3.0], minZoom: 3.5, maxZoom: 5.5, size: 18, type: 'continent' },
    { name: 'Asia', coordinates: [85.0, 40.0], minZoom: 3.5, maxZoom: 5.5, size: 18, type: 'continent' },
    { name: 'Europe', coordinates: [15.0, 52.0], minZoom: 3.5, maxZoom: 5.5, size: 18, type: 'continent' },
    { name: 'Middle East', coordinates: [45.0, 28.0], minZoom: 3.5, maxZoom: 5.5, size: 14, type: 'continent' },
    { name: 'North America', coordinates: [-100.0, 45.0], minZoom: 3.5, maxZoom: 5.5, size: 18, type: 'continent' },
    { name: 'South America', coordinates: [-58.0, -15.0], minZoom: 3.5, maxZoom: 5.5, size: 18, type: 'continent' },
    { name: 'Oceania', coordinates: [135.0, -25.0], minZoom: 3.5, maxZoom: 5.5, size: 14, type: 'continent' },
];

// Capital cities (zoom 5-6) — conflict-relevant regions prioritized
const capitals = [
    { name: 'Kyiv', coordinates: [30.52, 50.45], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Moscow', coordinates: [37.62, 55.75], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Damascus', coordinates: [36.29, 33.51], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Baghdad', coordinates: [44.37, 33.31], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Tehran', coordinates: [51.39, 35.69], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Ankara', coordinates: [32.86, 39.93], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Cairo', coordinates: [31.24, 30.04], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Riyadh', coordinates: [46.72, 24.71], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Kabul', coordinates: [69.17, 34.53], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Islamabad', coordinates: [73.05, 33.69], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'New Delhi', coordinates: [77.21, 28.61], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Beijing', coordinates: [116.41, 39.90], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Tokyo', coordinates: [139.69, 35.69], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'London', coordinates: [-0.12, 51.51], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Paris', coordinates: [2.35, 48.86], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Berlin', coordinates: [13.41, 52.52], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Washington DC', coordinates: [-77.04, 38.90], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Brasília', coordinates: [-47.88, -15.79], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Nairobi', coordinates: [36.82, -1.29], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Addis Ababa', coordinates: [38.75, 9.02], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Khartoum', coordinates: [32.53, 15.59], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Tripoli', coordinates: [13.18, 32.90], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Mogadishu', coordinates: [45.34, 2.05], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Sanaa', coordinates: [44.21, 15.35], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Minsk', coordinates: [27.57, 53.90], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Warsaw', coordinates: [21.01, 52.23], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Bucharest', coordinates: [26.10, 44.43], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Tbilisi', coordinates: [44.79, 41.72], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Jerusalem', coordinates: [35.21, 31.77], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Beirut', coordinates: [35.50, 33.89], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
    { name: 'Amman', coordinates: [35.93, 31.95], minZoom: 5.5, maxZoom: 8, size: 13, type: 'capital' },
];

// Major cities (zoom 7-9) — key conflict and regional cities
const majorCities = [
    { name: 'Kharkiv', coordinates: [36.23, 49.99], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Odesa', coordinates: [30.73, 46.48], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Dnipro', coordinates: [35.05, 48.46], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Lviv', coordinates: [24.03, 49.84], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Zaporizhzhia', coordinates: [35.14, 47.84], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Kherson', coordinates: [32.62, 46.64], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Donetsk', coordinates: [37.80, 48.00], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Mariupol', coordinates: [37.54, 47.10], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'St. Petersburg', coordinates: [30.32, 59.93], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Aleppo', coordinates: [37.16, 36.20], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Homs', coordinates: [36.72, 34.73], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Mosul', coordinates: [43.12, 36.34], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Basra', coordinates: [47.78, 30.51], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Erbil', coordinates: [44.01, 36.19], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Isfahan', coordinates: [51.68, 32.65], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Tabriz', coordinates: [46.30, 38.08], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Benghazi', coordinates: [20.07, 32.12], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Aden', coordinates: [45.04, 12.79], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Gaza', coordinates: [34.47, 31.50], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Tel Aviv', coordinates: [34.78, 32.09], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Istanbul', coordinates: [28.98, 41.01], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Alexandria', coordinates: [29.92, 31.20], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Juba', coordinates: [31.58, 4.85], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Bangui', coordinates: [18.56, 4.36], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'N\'Djamena', coordinates: [15.04, 12.13], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Goma', coordinates: [29.23, -1.68], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Maiduguri', coordinates: [13.16, 11.85], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Mumbai', coordinates: [72.88, 19.08], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Karachi', coordinates: [67.01, 24.86], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Shanghai', coordinates: [121.47, 31.23], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Lagos', coordinates: [3.38, 6.52], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Taipei', coordinates: [121.57, 25.03], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Seoul', coordinates: [126.98, 37.57], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Pyongyang', coordinates: [125.75, 39.02], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
    { name: 'Yangon', coordinates: [96.20, 16.87], minZoom: 7.5, maxZoom: 15, size: 11, type: 'major' },
];

export const ALL_LABELS = [...continents, ...capitals, ...majorCities];

/**
 * Filter labels by current zoom level.
 */
export function getVisibleLabels(zoom) {
    return ALL_LABELS.filter((l) => zoom >= l.minZoom && zoom <= l.maxZoom);
}
