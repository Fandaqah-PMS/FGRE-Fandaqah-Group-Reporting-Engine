# FGRE design system

One definition for every building block. Screens are assembled from the components in
`resources/js/Components/ui/` and the charts in `resources/js/Components/Charts/`; they never
restyle them. The living style guide shows every component in the current theme:
**`/design-system`** (local development and Fandaqah platform admins).

Brand basics (colours, fonts, logo, art) are in [BRAND.md](BRAND.md).

## The rules

1. **One component per job.** If a screen needs a pattern below, it uses the component. If a
   screen needs something new, add it to `Components/ui/` and to the style guide first, then use it.
2. **Variants, not new designs.** Components take options (tone, size, link, selected); a screen
   never adds its own colours, sizes or shadows to them.
3. **Theme tokens only.** Text `text-ink` / `text-ink-2` / `text-muted`, surfaces `bg-surface` /
   `bg-paper`, borders `border-line`. Never `text-navy`, `bg-white` or a hex value in a screen.
4. **Tone scale for meaning.** Status colours come from the five tones, with an icon or a word,
   never colour alone.
5. **Charts:** colours only from `lib/palette.js` (validated for both themes), fixed roles: this
   period, last year, average.

`tests/Unit/DesignRulesTest.php` enforces rules 1, 3 and 4 for the most common cases: a
hand-made KPI figure, a hand-styled button, raw white / navy text, hex colours in a screen and
tints with ad-hoc text colours all fail `php artisan test`.

## Components

| Need | Component | Notes |
|---|---|---|
| A key figure (KPI) | `ui/KpiCard` | The only KPI card. Anatomy: dot + label · change vs last year · value + unit · note · sparkline or progress. Options: `href` (link card), `selectable` + `active` (filter), `icon` (replaces the dot), `badge`, `highlight` (the one card needing attention), `valueTone` (a status word as the value). |
| A small figure inside a card | `ui/StatTile` | Inset tile: icon, value, label, change. |
| Any action | `ui/UiButton` | `primary` (one main action per view) · `secondary` · `ghost` (row / card actions) · `danger` · `on-dark`; sizes `sm` · `md` · `lg`; `href` makes it a link; `block` for full width. |
| A status or label pill | `ui/Badge` | `tone` from the tone scale, optional `icon`, `size="sm"` in tables. |
| An icon next to a title, stat or row | `ui/IconTile` | `tone`, sizes `sm` 32 px · `md` 40 px · `lg` 48 px. |
| A card title row | `ui/CardHeader` | Title, subtitle, meta text, actions slot, optional icon. |
| Nothing to show yet | `ui/EmptyState` | Icon, one line, what to do next (slot for a button). |
| A form field | `ui/Field` + `.field` input | Label above, error or hint below. |
| Choice between views / periods | `.seg` with `aria-pressed` buttons | Selected = navy. |
| Page title | `PageHeader` | Eyebrow, title (El Messiri), subtitle, actions. |
| Dialog | `Modal` | Navy header with icon, scrolling body, footer actions. |
| Settings pages | `Settings/SettingsShell` | Section menu + page. |

## Tone scale (`ui/tones.js`)

| Tone | Means | Example |
|---|---|---|
| `coral` / `coral-solid` | attention, action needed | Needs review, Action needed |
| `peach` / `peach-solid` | watch, pending | Invitation pending, Watch |
| `success` | good, balanced, on track | Processed, Balanced |
| `navy` | strong neutral, selected | Owner, All reports |
| `neutral` | information, inactive | Failed (no action), Fixed |

## Spacing and type

- Cards: `.card` (radius 18 px, border, soft shadow); padding `p-6` for content cards, KPI
  cards use their own compact padding.
- Grids: `gap-4` between KPI cards, `gap-6` between content cards.
- Type: titles El Messiri; body Montserrat (English) / Almarai (Arabic); figures always
  `.tabular` (Montserrat, aligned digits).
