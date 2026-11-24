# Fandaqah brand: FGRE usage

Sources: *Fandaqah Identity Guide* (logo, pattern, fonts, five-colour palette) and the *Fandaqah Web
Identity* concept (layout language). FGRE follows them and nothing else.

## Assets (`public/brand/`, from the original artwork, unmodified)

| File | Use |
|---|---|
| `logo.png` | Original lockup on light surfaces (sidebar, login form) |
| `logo-inverse.png` | Inverse lockup (white wordmark, white dot) on navy |
| `mark.png` | The coral mark alone (favicon) |
| `pattern.png` | Original signature pattern (hero cards, login, sidebar footer), always faded toward content |

Clearspace: at least the width of the "f". Minimum height 20 px.

## Core palette (identity guide)

| Token | Hex | Use |
|---|---|---|
| coral | `#F15F5A` | primary accent, active navigation, buttons, this-year series |
| navy | `#262E43` | text, hero cards, rooms / expenses series |
| warm neutral | `#EDEBE6` | tracks, quiet surfaces |
| muted | `#889594` | secondary text, last-year series |
| peach | `#FCC5A9` | soft accent, avatar, "other" series |

Page background `#F8F7F4`; cards white with `#ECE9E3` borders, 18 px radius. Tints for tiles: coral
`#FDEBE9`, peach `#FEF1EA`. The pattern's own extra colours (teal, aqua, blush) stay inside the
pattern artwork.

## Charts

Fixed roles, never cycled: this year coral, last year muted grey; revenue mix navy / coral / peach;
heatmaps a single coral ramp. Validated with the dataviz palette checker (colour-blind and
normal-vision separation pass). Peach is low-contrast, so every chart carries a legend, labels and a
table view (Charts / Tables switch).

## Typography (Google Fonts)

| Use | Font |
|---|---|
| Titles | **El Messiri** |
| English body | **Montserrat** |
| Arabic | **Almarai** |

## Themes (Light / Dark / System)

Users choose Light, Dark or System (follows the device) in the header, on the sign-in pages or
in Settings → Profile; the choice is saved on the user (`users.theme`). The page template sets
`<html data-theme>` before first paint, so there is no flash.

- Use role tokens, never raw white or navy for text: `bg-surface` (cards, inputs), `text-ink`
  (text), `bg-paper` (page), `border-line`, `text-ink-2` / `text-muted`. Navy (`bg-navy`) is a
  brand surface (hero, selected states) in both themes. Pastel peach badges use `text-on-peach`.
- Dark values live in `resources/css/app.css` (`:root[data-theme='dark']`): deep navy canvas
  #0E1320, cards #161C2B, text #E8ECF3. The brand art is toned down there automatically.
- Charts: `resources/js/lib/palette.js` swaps to a dark set validated for the dark card
  (#6683C9 · #DF5E58 · #8E7CC3 · #BE8045 · #1E9E91). Read `BRAND`, `SERIES`, `MIX`, `CHART`
  inside templates or `computed()` so charts redraw when the theme changes.
- Logos: `<BrandLogo>` shows the original artwork in light and the inverse lockup in dark.
- Emails stay light.
