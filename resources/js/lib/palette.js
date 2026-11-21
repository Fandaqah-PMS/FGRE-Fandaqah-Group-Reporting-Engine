/**
 * Chart colours: Fandaqah core palette, used the way the Fandaqah PMS dashboards use it.
 * Checked with the dataviz palette validator against the white card surface:
 *   navy ↔ grey (this period vs last year) ΔE 35.8; the 5-hue set passes colour-blind
 *   and normal-vision separation. Light hues (peach, light grey) always carry labels,
 *   a legend and a table view. Fixed roles, never cycled:
 *   this period = navy · last year = grey (dashed on lines, light grey on bars)
 *   averages / highlights = coral
 */
export const BRAND = {
    coral: '#F15F5A',
    navy: '#262E43',
    muted: '#889594',
    peach: '#FCC5A9',
    green: '#4C6B67',
    lightGrey: '#C3CBCA',
    neutral: '#EDEBE6',
    paper: '#F8F7F4',
};

export const THIS_YEAR = BRAND.navy;
export const LAST_YEAR = BRAND.muted;          // lines (dashed)
export const LAST_YEAR_BAR = BRAND.lightGrey;  // columns
export const AVERAGE = BRAND.coral;

/** Categorical order for parts / hotels (max 5; fold the rest into "Other"). */
export const SERIES = [BRAND.navy, BRAND.coral, BRAND.muted, BRAND.peach, BRAND.green];
export const MIX = { rooms: BRAND.navy, fnb: BRAND.coral, other: BRAND.peach };

export const GRID = '#ECEAE5';
export const AXIS_TEXT = '#6B7280';
export const SURFACE = '#FFFFFF';

/** A lighter tint of a hex colour (used for gradient segments). */
export function tint(hex, amount = 0.35) {
    const n = parseInt(hex.slice(1), 16);
    const c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map((v) => Math.round(v + (255 - v) * amount));
    return `rgb(${c.join(',')})`;
}

/** Sequential coral ramp for heatmaps: t = 0 → near-white tint, t = 1 → full coral. */
export function coralRamp(t) {
    const v = Math.max(0, Math.min(1, t));
    const from = [253, 235, 233];
    const to = [241, 95, 90];
    return `rgb(${from.map((c, i) => Math.round(c + (to[i] - c) * v)).join(',')})`;
}
