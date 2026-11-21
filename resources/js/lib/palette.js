/**
 * Chart colours. Navy / coral are the brand chrome; data series use this
 * categorical order, validated with the dataviz palette checker against the white
 * card surface (lightness band, chroma, colour-blind and normal-vision separation,
 * contrast: all pass, all pairs). Fixed order, never cycled:
 *   1 teal (this year / rooms) · 2 coral (last year / F&B) · 3 violet (other)
 */
export const SERIES = ['#00849B', '#F15F5A', '#7468D0'];

export const THIS_YEAR = SERIES[0];
export const LAST_YEAR = SERIES[1];

export const GRID = '#ECE9E3';
export const AXIS_TEXT = '#5B6374';
export const SURFACE = '#FFFFFF';
