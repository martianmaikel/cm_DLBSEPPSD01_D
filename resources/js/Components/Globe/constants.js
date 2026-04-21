/**
 * Shared constants for the conflict globe component.
 */

// Severity color mapping (RGBA for deck.gl)
export const SEVERITY_COLORS = {
    low: [82, 168, 68],       // #52A844 — green
    medium: [245, 158, 11],   // #F59E0B — amber
    high: [231, 76, 60],      // #E74C3C — red
};

// Country heat border colors by hotzone level
export const HEAT_BORDER_COLORS = {
    critical: [231, 76, 60],   // red
    high: [245, 158, 11],      // orange
    medium: [82, 168, 68],     // green
    low: [50, 80, 50],         // dim green
    none: [30, 58, 95],        // default border (#1e3a5f)
};

// Country heat fill colors (semi-transparent)
export const HEAT_FILL_COLORS = {
    critical: [231, 76, 60, 60],
    high: [245, 158, 11, 40],
    medium: [82, 168, 68, 30],
    low: [50, 80, 50, 20],
    none: [0, 0, 0, 0],
};

// Dark military theme
export const THEME = {
    ocean: [5, 8, 16, 255],        // #050810
    land: [13, 17, 23, 255],       // #0d1117
    border: [30, 58, 95, 255],     // #1e3a5f
    labelPrimary: [196, 212, 224], // #c4d4e0
    labelSecondary: [143, 168, 192], // #8fa8c0
};

// Zoom thresholds
export const ZOOM_CROSSFADE = { start: 5, end: 7 };
export const AUTO_ROTATE_MAX_ZOOM = 3;
export const AUTO_ROTATE_SPEED = 0.3;
export const AUTO_ROTATE_RESUME_DELAY = 30000; // 30s

// Pulse animation for recent events
export const PULSE_PERIOD_MS = 800;
export const RECENT_THRESHOLD_MS = 3600000; // 1 hour

// Tactical overlay threshold — events at or above this severity get rangefinder treatment
export const TACTICAL_SEVERITY_THRESHOLD = 8;

// Neon border severity color expression (MapLibre data-driven)
export const BORDER_SEVERITY_COLOR = [
    'case',
    ['>=', ['get', 'max_severity'], 7], '#E74C3C',  // red-bright
    ['>=', ['get', 'max_severity'], 4], '#F59E0B',  // amber-bright
    ['>', ['get', 'max_severity'], 0],  '#52A844',   // green-bright
    '#1A2618',  // border-subtle for non-conflict countries
];
