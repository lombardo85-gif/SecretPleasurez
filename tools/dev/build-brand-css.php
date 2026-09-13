<?php
/**
 * Generate assets/css/spz-brand.css — the Secret Pleasurez brand palette.
 *
 * Two parts:
 *
 *  1. A REMAP of every rule in the theme's own stylesheets that paints one of
 *     its stock accents, reassigned by role. Colours cannot be applied
 *     uniformly: the same hue cannot serve as a solid fill carrying white text
 *     and as accent text on a dark background, because those pull contrast in
 *     opposite directions.
 *
 *  2. A hand-written IDENTITY block. The remap alone only recolours what the
 *     theme already accented, and PRS935 barely used its blue, so cyan landed
 *     on three elements and the result read as "magenta theme". These rules
 *     place cyan where it is actually seen.
 *
 * Run in the container:
 *   docker exec spz-shop php /opt/spz/tools/dev/build-brand-css.php
 */

declare(strict_types=1);

$themeCss = '/var/www/html/themes/PRS935/assets/css';
$out = $themeCss . '/spz-brand.css';

// The theme's stock accents, in both stylesheets it actually serves.
// custom.css is the active skin and leans on !important, so the overrides
// generated here must too.
const SRC_ACCENTS = ['#ff55de', '#ff4c4c', '#ff9a52', '#265879'];

const FILL_PROPS = ['background-color', 'background', 'background-image'];
const TEXT_PROPS = ['color'];

$sources = [$themeCss . '/theme.css', $themeCss . '/custom.css'];

$rules = [];
$stats = ['fill' => 0, 'fill-hover' => 0, 'text' => 0, 'border' => 0];

foreach ($sources as $src) {
    if (!is_readable($src)) {
        fwrite(STDERR, "skipping unreadable source: {$src}\n");
        continue;
    }

    $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($src));

    if (!preg_match_all('/([^{}]+)\{([^{}]*)\}/', (string) $css, $blocks, PREG_SET_ORDER)) {
        continue;
    }

    foreach ($blocks as $block) {
        $selector = trim($block[1]);
        if ($selector === '' || str_starts_with($selector, '@')) {
            continue;
        }

        $interactive = (bool) preg_match('/:hover|:focus|:active|\.active/i', $selector);

        foreach (explode(';', $block[2]) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }

            [$prop, $value] = explode(':', $declaration, 2);
            $prop = strtolower(trim($prop));
            $value = trim($value);

            $matched = array_filter(SRC_ACCENTS, static fn ($a) => stripos($value, $a) !== false);
            if ($matched === []) {
                continue;
            }

            if (in_array($prop, FILL_PROPS, true)) {
                // Fills carry white text, so they need >= 4.5:1 against white.
                // Hover may use the brighter tone: it is transient and large.
                $token = $interactive ? 'var(--spz-fill-hover)' : 'var(--spz-fill)';
                $role = $interactive ? 'fill-hover' : 'fill';
            } elseif (in_array($prop, TEXT_PROPS, true)) {
                // Accent text sits on the dark body, needing >= 4.5:1 against
                // #181818 — which the fill magenta fails at 3.29:1.
                $token = 'var(--spz-accent)';
                $role = 'text';
            } else {
                $token = 'var(--spz-line)';
                $role = 'border';
            }

            $newValue = $value;
            foreach ($matched as $accent) {
                $newValue = str_ireplace($accent, $token, $newValue);
            }
            if (stripos($newValue, '!important') === false) {
                $newValue .= ' !important';
            }

            $rules[$selector] ??= [];
            $line = $prop . ': ' . $newValue . ';';
            if (!in_array($line, $rules[$selector], true)) {
                $rules[$selector][] = $line;
                ++$stats[$role];
            }
        }
    }
}

$header = <<<CSS
/*
 * Secret Pleasurez brand palette — GENERATED, do not hand-edit.
 * Rebuild: docker exec spz-shop php /opt/spz/tools/dev/build-brand-css.php
 *
 * Derived from brand/logo.png, whose mark runs cyan -> purple -> magenta.
 *
 * Colours are assigned by role, because one hue cannot be both a solid fill
 * and accent text. Measured WCAG contrast:
 *
 *   white on #ce0f69 ....... 5.39:1  AA    <- fills, which carry white text
 *   white on #e000c0 ....... 4.26:1  fail  (logo magenta: too light to fill)
 *   white on #ff55de ....... 2.76:1  fail  (the theme's own stock pink)
 *   #30a0f0 on #181818 ..... 6.27:1  AA    <- accent text and icons
 *   #ce0f69 on #181818 ..... 3.29:1  fail  (so never used as text)
 *
 * Hence: deep magenta fills, cyan accent text, the logo's bright magenta on
 * hover only, purple for rules. Information reads cyan, actions read magenta.
 */

:root {
  /* logo gradient stops */
  --spz-cyan: #30a0f0;
  --spz-purple: #8060e0;
  --spz-magenta: #e000c0;

  /* role tokens — what the rules below reference */
  --spz-fill: #ce0f69;
  --spz-fill-hover: #e000c0;
  --spz-accent: var(--spz-cyan);
  --spz-line: var(--spz-purple);

  --spz-gradient: linear-gradient(90deg, var(--spz-cyan) 0%, var(--spz-purple) 55%, var(--spz-magenta) 100%);
}

CSS;

$identity = <<<CSS

/* ------------------------------------------------------------------ *
 * Identity block (hand-written; preserved across regeneration)
 *
 * The remap above can only recolour what the theme already accented, and
 * PRS935 barely used its blue — cyan landed on three elements, so the page
 * read as a magenta theme. These rules put cyan where it is seen, giving
 * the pairing the logo implies: cyan carries information, magenta carries
 * actions.
 * ------------------------------------------------------------------ */

/* Links reveal cyan on interaction rather than shifting to another pink. */
a:hover,
a:focus,
.breadcrumb a:hover,
#_desktop_top_menu a:hover,
.header-nav a:hover,
.footer-container a:hover {
  color: var(--spz-cyan) !important;
}

/* Price is the page's key datum and cyan has the best contrast on this
   background (6.27:1), which also keeps it distinct from the CTA. */
.current-price span,
.product-price,
.product-price-and-shipping .price,
.price {
  color: var(--spz-cyan) !important;
}

/* Sale and discount flashes stay magenta: they are calls to act. */
.product-flags .discount,
.product-flags .on-sale,
.discount-percentage,
.discount-amount {
  background-color: var(--spz-fill) !important;
  color: #fff !important;
}

/* A visible keyboard focus ring. The theme ships none, which is a genuine
   accessibility gap, and cyan is the highest-contrast token available. */
a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
[tabindex]:focus-visible {
  outline: 2px solid var(--spz-cyan) !important;
  outline-offset: 2px !important;
}

/* One deliberate use of the full gradient, under section headings, so the
   logo's transition appears in the page rather than only in the logo. */
.featured-products .products-section-title::after,
.products-section-title::after,
.page-header h1::after {
  content: "";
  display: block;
  width: 96px;
  height: 3px;
  margin: 14px auto 0;
  background: var(--spz-gradient);
  border-radius: 3px;
}

CSS;

$body = '';
foreach ($rules as $selector => $decls) {
    $body .= $selector . " {\n  " . implode("\n  ", $decls) . "\n}\n\n";
}

file_put_contents($out, $header . "\n" . $body . $identity);

printf(
    "wrote %s\n  selectors remapped: %d\n  declarations: fill=%d fill-hover=%d text=%d border=%d\n",
    $out,
    count($rules),
    $stats['fill'],
    $stats['fill-hover'],
    $stats['text'],
    $stats['border']
);
