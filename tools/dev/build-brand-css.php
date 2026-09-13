<?php
/**
 * Generate assets/css/spz-brand.css — the Secret Pleasurez palette layer.
 *
 * Three parts:
 *
 *  1. TOKENS. The single source of truth for every colour on the storefront.
 *     spz-modern.css (the hand-written design layer) consumes these and
 *     defines no palette colours of its own.
 *
 *  2. A REMAP of the theme's own stylesheets:
 *     - stock ACCENTS, reassigned by role. One hue cannot serve as both a
 *       solid fill carrying white text and as accent text on a dark
 *       background: those pull contrast in opposite directions.
 *     - the NEUTRAL grey scale, mapped by luminance onto tinted surface and
 *       text tokens, which catches every grey the theme uses — including on
 *       pages not inspected by hand, such as checkout and account.
 *
 *  3. A small IDENTITY block placing cyan where it is seen.
 *
 * The stylesheet is walked with a quote- and parenthesis-aware parser that
 * keeps each rule's @media context and never rewrites inside url(...). An
 * earlier version split declarations on every ";", cutting
 * url("data:image/svg+xml;charset=...") in half; the unterminated string made
 * browsers discard every rule after it (84 of 552 survived). Output is now
 * validated before it replaces the live file.
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
const HEX = '/#(?:[0-9a-f]{6}|[0-9a-f]{3})(?![0-9a-f])/i';
const URL_TOKEN = '/url\((?:[^()"\']|"[^"]*"|\'[^\']*\')*\)/i';

/**
 * Walk a stylesheet into leaf rules, keeping the @media/@supports context
 * each lives in. Blocks that hold no restylable rules (@font-face, @keyframes,
 * @page) are skipped whole.
 *
 * @return array<int, array{context: string[], selector: string, body: string}>
 */
function walkRules(string $css): array
{
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
    $len = strlen($css);
    $rules = [];
    $groups = [];
    $buf = '';
    $quote = null;
    $paren = 0;

    for ($i = 0; $i < $len; $i++) {
        $c = $css[$i];

        if ($quote !== null) {
            $buf .= $c;
            if ($c === '\\' && $i + 1 < $len) {
                $buf .= $css[++$i];
            } elseif ($c === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($c === '"' || $c === "'") {
            $quote = $c;
            $buf .= $c;
            continue;
        }
        if ($c === '(') {
            ++$paren;
            $buf .= $c;
            continue;
        }
        if ($c === ')') {
            $paren = max(0, $paren - 1);
            $buf .= $c;
            continue;
        }

        // Statement at-rules (@import, @charset) end at ";".
        if ($c === ';' && $paren === 0 && str_starts_with(ltrim($buf), '@')) {
            $buf = '';
            continue;
        }

        if ($c === '}') {
            array_pop($groups);
            $buf = '';
            continue;
        }

        if ($c !== '{' || $paren !== 0) {
            $buf .= $c;
            continue;
        }

        $prelude = trim($buf);
        $buf = '';

        if (str_starts_with($prelude, '@')) {
            if (preg_match('/^@(media|supports)\b/i', $prelude)) {
                $groups[] = $prelude;
                continue;
            }
            // Skip the whole block, whatever it nests.
            $depth = 1;
            $q = null;
            for ($i++; $i < $len && $depth > 0; $i++) {
                $d = $css[$i];
                if ($q !== null) {
                    if ($d === '\\') {
                        ++$i;
                    } elseif ($d === $q) {
                        $q = null;
                    }
                    continue;
                }
                if ($d === '"' || $d === "'") {
                    $q = $d;
                } elseif ($d === '{') {
                    ++$depth;
                } elseif ($d === '}') {
                    --$depth;
                }
            }
            --$i;
            continue;
        }

        // A leaf rule: read its body up to the matching "}".
        $body = '';
        $q = null;
        $p = 0;
        for ($i++; $i < $len; $i++) {
            $d = $css[$i];
            if ($q !== null) {
                $body .= $d;
                if ($d === '\\' && $i + 1 < $len) {
                    $body .= $css[++$i];
                } elseif ($d === $q) {
                    $q = null;
                }
                continue;
            }
            if ($d === '"' || $d === "'") {
                $q = $d;
            } elseif ($d === '(') {
                ++$p;
            } elseif ($d === ')') {
                $p = max(0, $p - 1);
            } elseif ($d === '}' && $p === 0) {
                break;
            }
            $body .= $d;
        }

        if ($prelude !== '') {
            $rules[] = ['context' => $groups, 'selector' => $prelude, 'body' => $body];
        }
    }

    return $rules;
}

/**
 * Split a declaration block on ";" — but not inside strings or parentheses.
 *
 * @return string[]
 */
function splitDeclarations(string $body): array
{
    $out = [];
    $cur = '';
    $quote = null;
    $paren = 0;
    $len = strlen($body);

    for ($i = 0; $i < $len; $i++) {
        $c = $body[$i];
        if ($quote !== null) {
            $cur .= $c;
            if ($c === '\\' && $i + 1 < $len) {
                $cur .= $body[++$i];
            } elseif ($c === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($c === '"' || $c === "'") {
            $quote = $c;
        } elseif ($c === '(') {
            ++$paren;
        } elseif ($c === ')') {
            $paren = max(0, $paren - 1);
        } elseif ($c === ';' && $paren === 0) {
            if (trim($cur) !== '') {
                $out[] = $cur;
            }
            $cur = '';
            continue;
        }
        $cur .= $c;
    }
    if (trim($cur) !== '') {
        $out[] = $cur;
    }

    return $out;
}

/**
 * Return a description of the first structural fault, or null if balanced.
 */
function structuralFault(string $css): ?string
{
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
    $brace = 0;
    $paren = 0;
    $quote = null;
    $line = 1;
    $len = strlen($css);

    for ($i = 0; $i < $len; $i++) {
        $c = $css[$i];
        if ($c === "\n") {
            if ($quote !== null) {
                return "unterminated string before line {$line}";
            }
            ++$line;
        }
        if ($quote !== null) {
            if ($c === '\\') {
                ++$i;
            } elseif ($c === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($c === '"' || $c === "'") {
            $quote = $c;
        } elseif ($c === '(') {
            ++$paren;
        } elseif ($c === ')') {
            --$paren;
        } elseif ($c === '{') {
            ++$brace;
        } elseif ($c === '}') {
            if ($paren !== 0) {
                return "unclosed parenthesis in the rule ending on line {$line}";
            }
            if (--$brace < 0) {
                return "unexpected '}' on line {$line}";
            }
        }
    }

    return $brace === 0 && $quote === null ? null : 'unbalanced braces or string at end of file';
}

/**
 * Map a neutral grey to a surface or text token by its relative luminance.
 * Returns null for anything that is not a grey, or that should stay as is.
 */
function neutralToken(string $hex, string $kind): ?string
{
    $h = ltrim($hex, '#');
    if (strlen($h) === 3) {
        $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    [$r, $g, $b] = array_map('hexdec', str_split($h, 2));

    if (max($r, $g, $b) - min($r, $g, $b) > 10) {
        return null;
    }

    $lin = static function (int $c): float {
        $c /= 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };
    $l = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

    switch ($kind) {
        case 'bg':
            if ($l < 0.005) {
                return 'var(--spz-bg-deep)';
            }
            if ($l < 0.0105) {
                return 'var(--spz-bg)';
            }
            if ($l < 0.019) {
                return 'var(--spz-surface)';
            }
            if ($l > 0.97) {
                return 'var(--spz-surface)';
            }

            return 'var(--spz-surface-2)';

        case 'text':
            if ($l < 0.12) {
                return 'var(--spz-text)';
            }
            if ($l < 0.45) {
                return 'var(--spz-text-muted)';
            }

            return null;

        case 'border':
            return $l > 0.97 ? null : 'var(--spz-border)';
    }

    return null;
}

$sources = [$themeCss . '/theme.css', $themeCss . '/custom.css'];

/** @var array<string, array<string, string[]>> $byContext */
$byContext = [];
$contexts = [];
$stats = ['fill' => 0, 'fill-hover' => 0, 'text' => 0, 'border' => 0, 'surface' => 0, 'neutral-text' => 0, 'in-media' => 0, 'fill-text' => 0];

foreach ($sources as $src) {
    if (!is_readable($src)) {
        fwrite(STDERR, "skipping unreadable source: {$src}\n");
        continue;
    }

    foreach (walkRules((string) file_get_contents($src)) as $rule) {
        $selector = $rule['selector'];
        $interactive = (bool) preg_match('/:hover|:focus|:active|\.active/i', $selector);
        $contextKey = implode("\n", $rule['context']);
        $contexts[$contextKey] = $rule['context'];

        $emittedFill = false;

        foreach (splitDeclarations($rule['body']) as $declaration) {
            $colon = strpos($declaration, ':');
            if ($colon === false) {
                continue;
            }

            $prop = strtolower(trim(substr($declaration, 0, $colon)));
            $value = trim(substr($declaration, $colon + 1));

            $isFill = in_array($prop, FILL_PROPS, true);
            $isText = in_array($prop, TEXT_PROPS, true);
            $isBorder = !$isFill && !$isText && (str_contains($prop, 'border') || $prop === 'outline');
            if (!$isFill && !$isText && !$isBorder) {
                continue;
            }

            // Protect url(...) so colours inside embedded SVGs are never
            // rewritten into var() references, which would break the image.
            $urls = [];
            $work = (string) preg_replace_callback(URL_TOKEN, static function (array $m) use (&$urls): string {
                $urls[] = $m[0];

                return "\x00" . (count($urls) - 1) . "\x00";
            }, $value);

            $role = null;

            // 1. Neutrals: the theme's grey scale, re-based onto tinted tokens.
            $kind = $isFill ? 'bg' : ($isText ? 'text' : 'border');
            $work = (string) preg_replace_callback(
                HEX,
                static function (array $m) use ($kind, &$role): string {
                    $token = neutralToken($m[0], $kind);
                    if ($token === null) {
                        return $m[0];
                    }
                    $role ??= $kind === 'bg' ? 'surface' : ($kind === 'text' ? 'neutral-text' : 'border');

                    return $token;
                },
                $work
            );

            // 2. Accents, assigned by role.
            $matched = array_values(array_filter(SRC_ACCENTS, static fn ($a) => stripos($work, $a) !== false));
            if ($matched !== []) {
                if ($isFill) {
                    $token = $interactive ? 'var(--spz-fill-hover)' : 'var(--spz-fill)';
                    $role = $interactive ? 'fill-hover' : 'fill';
                    $emittedFill = true;
                } elseif ($isText) {
                    $token = 'var(--spz-accent)';
                    $role = 'text';
                } else {
                    $token = 'var(--spz-line)';
                    $role = 'border';
                }
                foreach ($matched as $accent) {
                    $work = str_ireplace($accent, $token, $work);
                }
            }

            if ($role === null) {
                continue;
            }

            $newValue = (string) preg_replace_callback('/\x00(\d+)\x00/', static fn (array $m): string => $urls[(int) $m[1]], $work);
            if (stripos($newValue, '!important') === false) {
                $newValue .= ' !important';
            }

            $line = $prop . ': ' . $newValue . ';';
            $byContext[$contextKey][$selector] ??= [];
            if (!in_array($line, $byContext[$contextKey][$selector], true)) {
                $byContext[$contextKey][$selector][] = $line;
                ++$stats[$role];
                if ($contextKey !== '') {
                    ++$stats['in-media'];
                }
            }
        }

        // Fills always carry ink text (5.55:1 on the pink; white would be
        // 3.55:1 and fail), even when the theme declared its own colour: that
        // declaration has no !important, so a generated neutral-text rule such
        // as `.label { color: muted !important }` would beat it. The cart's
        // "Continue shopping" label showed exactly that.
        if ($emittedFill) {
            $line = 'color: var(--spz-on-fill) !important;';
            if (!in_array($line, $byContext[$contextKey][$selector], true)) {
                $byContext[$contextKey][$selector][] = $line;
                ++$stats['fill-text'];
            }
        }
    }
}

$header = <<<'CSS'
/*
 * Secret Pleasurez palette layer — GENERATED, do not hand-edit.
 * Rebuild: docker exec spz-shop php /opt/spz/tools/dev/build-brand-css.php
 *
 * Palette supplied 2026-09-12 — the logo's own colours:
 *   #0B0B0D ink   #00E5FF neon cyan   #FF2A85 neon pink
 *   #E3C1AA champagne   #999999 grey
 * Surfaces, borders and a near-white are derived tints of the ink, because
 * the palette has no white and no mid-surfaces.
 *
 * Roles follow measured WCAG contrast (AA: >= 4.5:1 text, >= 3:1 controls):
 *   near-white #F2F2F4 on ink ........... 17.59   on surface-2 #1D1D23 15.00
 *   champagne  #E3C1AA on ink ........... 11.68   headings
 *   cyan       #00E5FF on ink ........... 12.78   prices, links, focus
 *   grey       #999999 on ink ...........  6.90   on surface-2          5.89
 *   ink on pink #FF2A85 .................  5.55   on hover #FF4D99      6.34
 *   WHITE on pink #FF2A85 ...............  3.55   FAILS: fills carry ink
 *   ink on cyan #00E5FF ................. 12.78
 *   input border #6B6B75 on surface-2 ...  3.18
 */

:root {
  /* supplied palette */
  --spz-ink: #0B0B0D;
  --spz-cyan: #00E5FF;
  --spz-pink: #FF2A85;
  --spz-champagne: #E3C1AA;
  --spz-grey: #999999;

  /* surfaces: tints of the ink */
  --spz-bg-deep: #070708;
  --spz-bg: #0B0B0D;
  --spz-surface: #141418;
  --spz-surface-2: #1D1D23;
  --spz-surface-3: #26262E;
  --spz-border: rgba(255, 255, 255, 0.08);
  --spz-border-strong: #6B6B75;
  --spz-well: #F4EFEA;          /* champagne-tinted stage for product photos */

  /* text */
  --spz-text: #F2F2F4;
  --spz-heading: var(--spz-champagne);
  --spz-text-muted: var(--spz-grey);
  --spz-text-faint: var(--spz-grey);

  /* roles */
  --spz-fill: var(--spz-pink);
  --spz-fill-hover: #FF4D99;
  --spz-on-fill: var(--spz-ink);  /* never white: 3.55:1 on the pink */
  --spz-accent: var(--spz-cyan);
  --spz-line: rgba(0, 229, 255, 0.45);

  --spz-gradient: linear-gradient(135deg, var(--spz-cyan) 0%, var(--spz-pink) 100%);
  --spz-gradient-cta: linear-gradient(135deg, var(--spz-pink) 0%, var(--spz-fill-hover) 100%);
}

CSS;

$identity = <<<'CSS'

/* ------------------------------------------------------------------ *
 * Identity block (hand-written; preserved across regeneration)
 *
 * The remap can only recolour what the theme already accented, and PRS935
 * barely used its blue. These rules put cyan where it is seen: information
 * reads cyan, actions read magenta.
 * ------------------------------------------------------------------ */

a:hover,
a:focus,
.breadcrumb a:hover,
#_desktop_top_menu a:hover,
.header-nav a:hover,
.footer-container a:hover {
  color: var(--spz-accent) !important;
}

.current-price span,
.product-price,
.product-price-and-shipping .price,
.price {
  color: var(--spz-accent) !important;
}

.product-flags .discount,
.product-flags .on-sale,
.discount-percentage,
.discount-amount {
  background-color: var(--spz-fill) !important;
  color: var(--spz-on-fill) !important;
}

/* The theme ships no visible keyboard focus ring. */
a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
[tabindex]:focus-visible {
  outline: 2px solid var(--spz-accent) !important;
  outline-offset: 2px !important;
}

CSS;

// Plain rules first, then each @media/@supports context, mirroring the
// source cascade where media overrides come after base rules.
$body = '';
ksort($byContext, SORT_STRING);
foreach ($byContext as $contextKey => $selectors) {
    $block = '';
    foreach ($selectors as $selector => $decls) {
        $block .= $selector . " {\n  " . implode("\n  ", $decls) . "\n}\n\n";
    }
    if ($contextKey === '') {
        $body .= $block;
        continue;
    }
    $open = '';
    $close = '';
    foreach ($contexts[$contextKey] as $prelude) {
        $open .= $prelude . " {\n";
        $close .= "}\n";
    }
    $body .= $open . $block . $close . "\n";
}

$css = $header . "\n" . $body . $identity;

$fault = structuralFault($css);
if ($fault !== null) {
    file_put_contents($out . '.rejected', $css);
    fwrite(STDERR, "REFUSED to replace {$out}: {$fault}\nCandidate kept at {$out}.rejected for inspection.\n");
    exit(1);
}

file_put_contents($out, $css);

$ruleCount = array_sum(array_map('count', $byContext));
printf(
    "wrote %s (validated)\n  rules: %d (%d contexts)\n  declarations: fill=%d fill-hover=%d accent-text=%d border=%d surface=%d neutral-text=%d fill-text=%d  [%d inside @media]\n",
    $out,
    $ruleCount,
    count($byContext),
    $stats['fill'],
    $stats['fill-hover'],
    $stats['text'],
    $stats['border'],
    $stats['surface'],
    $stats['neutral-text'],
    $stats['fill-text'],
    $stats['in-media']
);
