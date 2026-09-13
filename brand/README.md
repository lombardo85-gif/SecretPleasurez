# Brand assets

Recovered from `E:\Users\lomba\Desktop\CC\CC\SecretPleasurez` (Jan–May 2023).
Three different logo directions were tried; these are the survivors.

| File | Size | Alpha | Notes |
| --- | --- | --- | --- |
| `NEW-Logo.png` | 296x245 | no | **In use** as the storefront logo (`img/spz-logo-neon.png`, 2026-09-12). Neon cyan/magenta interlocked symbols, rose-gold script, tagline "Your secret. Our pleasure." Near-black ground; `spz-modern.css` blends it into the header with `mix-blend-mode: lighten`, so no transparent copy is needed on the dark site. |
| `NEW-Banner.png` | 601x191 | no | **In use** as the homepage hero (`assets/img/spz-banner.png`, 2026-09-12). Shown near its own size on a neon stage and blended with `mix-blend-mode: lighten`, not stretched across a full-width slider. |
| `NEW-Favicon.ico` | 117x121 | yes | Supplied favicon: the neon symbols in a round metallic badge. One 32-bit image, not the usual 16/32/48 set. |
| `NEW-Favicon.png` | 118x123 | no | Same artwork, but **actually a BMP** with a .png name and no transparency. Not used. |
| `NEW-Favicon-source.png` | 117x121 | yes | The image inside `NEW-Favicon.ico`, decoded to PNG. Source for `tools/dev/build-favicon.php`. |
| `NEW-Favico1.png` | 107x98 | no | Previous favicon source (square tile), replaced 2026-09-12 by the round badge. Neon symbols on a dark rounded tile. Not square, so `tools/dev/build-favicon.php` centres it on a transparent square before resampling. |
| `favicon-neon.ico` | 16/32/48 | yes | **In use** as `img/favicon.ico` (2026-09-12). Built from `NEW-Favicon-source.png`; rebuild rather than editing. |
| `logo.png` | 1008x325 | **no** | Previous storefront logo (`img/spz-logo.png`); still the **email and invoice** logo, because those render on white, where the neon logo's dark ground would show as a block. White brush script + gradient gender-symbol mark, tagline "Your best pleasure, our best secret." Landscape, so it suits the header. Its grey background is opaque, so it reads as a slightly lighter panel against the theme's near-black header. |
| `splogo.png` | 1256x456 | yes | Earlier "The Best Kept Secret" wordmark. Transparent, but **watermarked** — the neighbouring files are SmashingLogo / LogoMaker preview screenshots, so this was never purchased. Do not publish it. |
| `LOGOFINAL.png` | 849x612 | no | Different direction: chained-heart padlock, magenta on dark purple. Clean, but very low contrast and nearly square. |
| `splogoi.png` | 1665x1608 | yes | Square icon version, transparent. Used for `PS_STORES_ICON`. |
| `favicon.png` | 1665x1608 | yes | Same artwork as `splogoi.png`. |
| `favicon.ico` | 48x48 | — | Previous favicon, replaced 2026-09-12 by `favicon-neon.ico`. |
| `icon.ico` | 224x200 | — | Alternate icon. |

## Palette (supplied 2026-09-12)

| Colour | Role | Why |
| --- | --- | --- |
| `#0B0B0D` ink | page background; text on every fill | ink on pink 5.55:1 |
| `#00E5FF` neon cyan | prices, links, focus rings, outline buttons | 12.78:1 on ink |
| `#FF2A85` neon pink | primary buttons and badges | carries **ink** text: white on it is 3.55:1 and fails |
| `#E3C1AA` champagne | headings, from the logo lettering | 11.68:1 on ink |
| `#999999` grey | secondary text | 6.90:1 on ink |

Surfaces (`#141418`, `#1D1D23`), a near-white for body text (`#F2F2F4`) and the
input border (`#6B6B75`) are derived tints: the palette has no white and no
mid-surfaces. All tokens live in `tools/dev/build-brand-css.php`.

## Open issues

- **The banner is small** (601x191), so it is shown at roughly its own size.
  A 1920px-wide or 2x export would allow a full-width hero.

- **The neon logo is small** (296x245). Sharp at header size on high-density
  screens, but soft anywhere it is shown larger. Ask the designer for the
  original at 3x, ideally also as SVG.
- **No transparent version.** On the dark storefront the blend hides this. On
  white — email, invoices, print, marketplaces — it cannot, which is why those
  still use `logo.png`. A transparent PNG would let one logo serve everywhere.

- **The favicon source is too small for app icons** (107x98). The browser
  tab icon is fine, but a phone home-screen icon needs 180px and an installable
  web app 192px and 512px. Those were deliberately not generated, because
  upscaling would look soft. A square export at 512px or larger would cover
  all of them.

Do not substitute `splogo.png` for either: it is a watermarked preview and
using it on a live store is both visibly wrong and a licensing problem.
