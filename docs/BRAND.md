# Fandaqah brand: FGRE usage

Source of truth: the Fandaqah identity guide and the colour tokens the Fandaqah PMS uses for its new
dashboards and the Owner App (`docs/fandaqah-pms-reference.md` §8.2). FGRE uses the navy / coral
identity, not the older blue PMS theme.

## Colors

| Token | Hex | Use |
|---|---|---|
| `navy` | `#262E43` | primary dark, text, headline cards, top band |
| `navy-light` | `#3A4360` | |
| `navy-deep` | `#1C2233` | |
| `coral` | `#F15F5A` | brand accent, highlights |
| `coral-text` | `#D2443F` | coral text on light backgrounds (contrast) |
| `coral-tint` | `#FDE8E6` | soft coral background |
| `off-white` | `#F8F7F4` | page background |
| `warm` | `#EDEBE6` | tracks, surfaces |
| `border` | `#ECE9E3` | |
| `grey` | `#889594` | muted (official swatch) |
| `secondary` | `#5B6374` | secondary text |
| `peach` | `#FCC5A9` | soft accent |
| `salmon` | `#F58985` | pattern / chart |
| `teal` | `#214F5F` | pattern / chart |
| `ice` | `#B8D7D9` | pattern / chart |
| `success` / soft | `#4C6B67` / `#E4ECEA` | |
| `danger` / soft | `#B73530` / `#FBE1DF` | |

Chart series, in order: `#262E43`, `#F15F5A`, `#889594`, `#FCC5A9`, `#5E6886`, `#F58985`, `#C3CBCA`, `#D8D4CC`.

Top band gradient: `linear-gradient(160deg, #2C354D 0%, #262E43 55%, #222A3D 100%)`.

## Typography (Google Fonts)

| Use | Font | Weights |
|---|---|---|
| Titles | **El Messiri** | 500, 600, 700 |
| Body (EN) | **Montserrat** | 400, 500, 600, 700 |
| Arabic | **Almarai** | 300, 400, 700, 800 |

Body stack: `'Montserrat', 'Almarai', system-ui`. Title stack: `'El Messiri', 'Almarai', Georgia, serif`.

## Logo

- Bilingual lockup (Arabic + "fandaqah" + coral mark). Light backgrounds: navy wordmark; navy
  backgrounds: white wordmark; coral backgrounds: all white.
- Clearspace: at least the width of the "f" on every side. Minimum size: 20 px tall.
- Official file: `public/images/logo.svg` in the Fandaqah PMS repo → copy to `public/brand/logo.svg`.
