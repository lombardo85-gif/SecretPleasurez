# Brand assets

Recovered from `E:\Users\lomba\Desktop\CC\CC\SecretPleasurez` (Jan–May 2023).
Three different logo directions were tried; these are the survivors.

| File | Size | Alpha | Notes |
| --- | --- | --- | --- |
| `NEW-Logo.png` | 296x245 | no | **In use** as the storefront logo (`img/spz-logo-neon.png`, 2026-09-12). Neon cyan/magenta interlocked symbols, rose-gold script, tagline "Your secret. Our pleasure." Near-black ground; `spz-modern.css` blends it into the header with `mix-blend-mode: lighten`, so no transparent copy is needed on the dark site. |
| `NEW-Favico1.png` | 107x98 | no | Source for the **current favicon**. Neon symbols on a dark rounded tile. Not square, so `tools/dev/build-favicon.php` centres it on a transparent square before resampling. |
| `favicon-neon.ico` | 16/32/48 | yes | **In use** as `img/favicon.ico` (2026-09-12). Built from `NEW-Favico1.png`; rebuild rather than editing. |
| `logo.png` | 1008x325 | **no** | Previous storefront logo (`img/spz-logo.png`); still the **email and invoice** logo, because those render on white, where the neon logo's dark ground would show as a block. White brush script + gradient gender-symbol mark, tagline "Your best pleasure, our best secret." Landscape, so it suits the header. Its grey background is opaque, so it reads as a slightly lighter panel against the theme's near-black header. |
| `splogo.png` | 1256x456 | yes | Earlier "The Best Kept Secret" wordmark. Transparent, but **watermarked** — the neighbouring files are SmashingLogo / LogoMaker preview screenshots, so this was never purchased. Do not publish it. |
| `LOGOFINAL.png` | 849x612 | no | Different direction: chained-heart padlock, magenta on dark purple. Clean, but very low contrast and nearly square. |
| `splogoi.png` | 1665x1608 | yes | Square icon version, transparent. Used for `PS_STORES_ICON`. |
| `favicon.png` | 1665x1608 | yes | Same artwork as `splogoi.png`. |
| `favicon.ico` | 48x48 | — | Previous favicon, replaced 2026-09-12 by `favicon-neon.ico`. |
| `icon.ico` | 224x200 | — | Alternate icon. |

## Open issues

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
