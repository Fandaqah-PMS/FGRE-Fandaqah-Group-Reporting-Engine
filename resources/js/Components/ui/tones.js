/**
 * The FGRE tone scale (docs/DESIGN-SYSTEM.md). Every coloured badge, icon tile and status uses
 * one of these names, never its own colour classes, so meaning reads the same everywhere and
 * both themes work:
 *   coral    attention, action needed, the brand accent
 *   peach    watch, pending, soft warning
 *   success  good, balanced, on track
 *   navy     strong neutral, selected, owner
 *   neutral  information, inactive
 * "-solid" variants are for the one element that must stand out (e.g. "Action needed").
 */
export const TONES = {
    coral: 'bg-coral-tint text-coral-dark',
    'coral-solid': 'bg-coral text-white',
    peach: 'bg-peach-tint text-peach-ink',
    'peach-solid': 'bg-peach text-on-peach',
    success: 'bg-success-tint text-success',
    navy: 'bg-navy text-white',
    neutral: 'bg-paper text-ink-2',
};

export const toneClass = (tone) => TONES[tone] ?? TONES.neutral;
