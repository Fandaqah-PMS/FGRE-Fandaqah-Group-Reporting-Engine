/**
 * Chart colours: Fandaqah core palette only.
 * Checked with the dataviz palette validator against the white card surface:
 * coral↔grey and navy↔coral↔peach pass colour-blind and normal-vision separation.
 * Peach is light (contrast 1.5:1), so every chart with it carries a legend, direct
 * labels and a table view. Fixed roles, never cycled:
 *   this year = coral · last year = muted grey
 *   revenue mix = rooms navy · F&B coral · other peach
 */
export const BRAND = {
    coral: '#F15F5A',
    navy: '#262E43',
    muted: '#889594',
    peach: '#FCC5A9',
    neutral: '#EDEBE6',
    paper: '#F8F7F4',
};

export const THIS_YEAR = BRAND.coral;
export const LAST_YEAR = BRAND.muted;
export const MIX = { rooms: BRAND.navy, fnb: BRAND.coral, other: BRAND.peach };

export const GRID = '#EFECE6';
export const AXIS_TEXT = '#5B6374';
export const SURFACE = '#FFFFFF';

/** Sequential coral ramp for heatmaps: t = 0 → near-white tint, t = 1 → full coral. */
export function coralRamp(t) {
    const v = Math.max(0, Math.min(1, t));
    const from = [253, 235, 233];
    const to = [241, 95, 90];
    const mix = from.map((c, i) => Math.round(c + (to[i] - c) * v));
    return `rgb(${mix.join(',')})`;
}
