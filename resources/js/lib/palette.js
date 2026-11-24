import { reactive } from 'vue';

/**
 * Chart colours: Fandaqah core palette, used the way the Fandaqah PMS dashboards use it.
 * Fixed roles, never cycled:
 *   this period = navy · last year = grey (dashed on lines, light grey on bars)
 *   averages / highlights = coral
 *
 * Two validated sets (dataviz palette validator, `validate_palette.js`):
 *  - light, on the white card: navy ↔ grey ΔE 35.8; the 5-hue set passes colour-blind and
 *    normal-vision separation. Light hues (peach, light grey) always carry labels, a legend
 *    and a table view.
 *  - dark, on the #161C2B card: series #6683C9 · #DF5E58 · #8E7CC3 · #BE8045 · #1E9E91 pass
 *    lightness band, chroma floor, CVD (worst ΔE 10.1), normal vision (18.7) and 3:1 contrast;
 *    this year ↔ last year bars ΔE 18.2 (last-year grey is recessive: legend + table view).
 *
 * The exported objects are reactive: applyChartTheme() swaps the values and every chart
 * re-renders. Read them in templates or computed(), not into plain constants.
 */
const LIGHT = {
    brand: {
        coral: '#F15F5A', navy: '#262E43', muted: '#889594', peach: '#FCC5A9', green: '#4C6B67',
        lightGrey: '#C3CBCA', neutral: '#EDEBE6', paper: '#F8F7F4',
    },
    series: ['#262E43', '#F15F5A', '#889594', '#FCC5A9', '#4C6B67'],
    chart: { grid: '#ECEAE5', axisText: '#6B7280', surface: '#FFFFFF', ink: '#262E43', shadow: '#262E43' },
    rampFrom: [253, 235, 233],
};

const DARK = {
    brand: {
        coral: '#DF5E58', navy: '#6683C9', muted: '#7F8B9B', peach: '#BE8045', green: '#1E9E91',
        lightGrey: '#4A5568', neutral: '#222A3B', paper: '#0E1320',
    },
    series: ['#6683C9', '#DF5E58', '#8E7CC3', '#BE8045', '#1E9E91'],
    chart: { grid: '#263046', axisText: '#8F9AAD', surface: '#161C2B', ink: '#E8ECF3', shadow: '#000000' },
    rampFrom: [58, 33, 39],
};

export const BRAND = reactive({ ...LIGHT.brand });

/** Categorical order for parts / hotels (max 5; fold the rest into "Other"). */
export const SERIES = reactive([...LIGHT.series]);
export const MIX = reactive({ rooms: LIGHT.brand.navy, fnb: LIGHT.brand.coral, other: LIGHT.brand.peach });

/** Roles used by the chart components. */
export const CHART = reactive({
    thisYear: LIGHT.brand.navy,
    lastYear: LIGHT.brand.muted,          // lines (dashed)
    lastYearBar: LIGHT.brand.lightGrey,   // columns
    average: LIGHT.brand.coral,
    ...LIGHT.chart,
    rampFrom: LIGHT.rampFrom,
});

export function applyChartTheme(dark) {
    const set = dark ? DARK : LIGHT;
    Object.assign(BRAND, set.brand);
    SERIES.splice(0, SERIES.length, ...set.series);
    Object.assign(MIX, { rooms: set.brand.navy, fnb: set.brand.coral, other: set.brand.peach });
    Object.assign(CHART, {
        thisYear: set.brand.navy, lastYear: set.brand.muted, lastYearBar: set.brand.lightGrey, average: set.brand.coral, ...set.chart,
        rampFrom: set.rampFrom,
    });
}

/** A lighter tint of a hex colour (used for gradient segments). */
export function tint(hex, amount = 0.35) {
    const n = parseInt(hex.slice(1), 16);
    const c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map((v) => Math.round(v + (255 - v) * amount));
    return `rgb(${c.join(',')})`;
}

/** Sequential coral ramp for heatmaps: t = 0 → faint tint of the surface, t = 1 → full coral. */
export function coralRamp(t) {
    const v = Math.max(0, Math.min(1, t));
    const to = [241, 95, 90];
    return `rgb(${CHART.rampFrom.map((c, i) => Math.round(c + (to[i] - c) * v)).join(',')})`;
}
