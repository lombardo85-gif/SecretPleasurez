<?php
/**
 * Build the homepage hero crops from the brand banner art.
 *
 * Source is brand/Gemini_Generated_Image_96almx96almx96al.jpeg: 1376x768,
 * logo and tagline centred on a nebula. Two crops are cut around the logo:
 *
 *   spz-hero-desktop.jpg  1376x520  full width, trimmed top and bottom
 *   spz-hero-mobile.jpg    980x560  tighter, so the lettering stays readable
 *                                  on a 375px phone
 *
 * The logo is located rather than hard-coded. Its neon strokes and lettering
 * form a dense block of bright pixels; stars and bokeh are small or sparse.
 * So a column counts only if it holds several bright pixels, and the logo is
 * the longest run of such columns plus any wide runs close by (the symbol
 * sits apart from the lettering). Rows are found the same way within it.
 *
 * Output sizes are fixed because the template and CSS declare them. If the
 * logo does not fit a crop with a margin, the script fails instead of
 * writing an image with the lettering cut off.
 *
 * Run in the container:
 *   docker exec spz-shop php /opt/spz/tools/dev/build-hero.php [source.jpg]
 */

declare(strict_types=1);

$source = $argv[1] ?? '/opt/spz/brand/Gemini_Generated_Image_96almx96almx96al.jpeg';
$outDir = '/var/www/html/themes/PRS935/assets/img';
$crops = [
    'spz-hero-desktop.jpg' => [1376, 520],
    'spz-hero-mobile.jpg' => [980, 560],
];
// 0.65 rather than lower: at 0.5 the nebula glow at the edges joins the logo
// into one run spanning the whole image.
const BRIGHT = 0.65;     // relative luminance counted as "lit"
const MIN_HITS = 6;      // bright pixels a column/row needs to count
const MARGIN = 24;       // clear space required around the logo in a crop

$src = @imagecreatefromjpeg($source);
if ($src === false) {
    fwrite(STDERR, "not a readable JPEG: {$source}\n");
    exit(1);
}
$w = imagesx($src);
$h = imagesy($src);

$lit = static function (int $x, int $y) use ($src): bool {
    $rgb = imagecolorat($src, $x, $y);

    return (0.2126 * (($rgb >> 16) & 255) + 0.7152 * (($rgb >> 8) & 255) + 0.0722 * ($rgb & 255)) / 255 > BRIGHT;
};

/**
 * Span of the logo along one axis.
 *
 * Qualifying indices are grouped into runs (bridging gaps up to $gap). The
 * longest run is the anchor; neighbouring runs within $reach are absorbed if
 * they are at least $minWidth wide. That joins the symbol to the lettering,
 * which sit further apart than letters do, while stars and bokeh (narrow
 * runs) and distant nebula stay out.
 */
$logoSpan = static function (array $hits, int $gap, int $reach, int $minWidth): ?array {
    $runs = [];
    foreach (array_keys(array_filter($hits, static fn (int $n): bool => $n >= MIN_HITS)) as $i) {
        $last = count($runs) - 1;
        if ($last >= 0 && $i - $runs[$last][1] <= $gap) {
            $runs[$last][1] = $i;
        } else {
            $runs[] = [$i, $i];
        }
    }
    if ($runs === []) {
        return null;
    }

    $anchor = 0;
    foreach ($runs as $k => $run) {
        if ($run[1] - $run[0] > $runs[$anchor][1] - $runs[$anchor][0]) {
            $anchor = $k;
        }
    }
    // Reach is measured from the previous run, wide or not, so a narrow
    // flourish between the symbol and the lettering links them; only wide
    // runs move the logo's edge.
    [$start, $end] = $runs[$anchor];
    for ($k = $anchor - 1, $edge = $start; $k >= 0 && $edge - $runs[$k][1] <= $reach; --$k) {
        $edge = $runs[$k][0];
        if ($runs[$k][1] - $runs[$k][0] + 1 >= $minWidth) {
            $start = $runs[$k][0];
        }
    }
    for ($k = $anchor + 1, $edge = $end; $k < count($runs) && $runs[$k][0] - $edge <= $reach; ++$k) {
        $edge = $runs[$k][1];
        if ($runs[$k][1] - $runs[$k][0] + 1 >= $minWidth) {
            $end = $runs[$k][1];
        }
    }

    return [$start, $end];
};

$colHits = array_fill(0, $w, 0);
for ($x = 0; $x < $w; ++$x) {
    for ($y = 0; $y < $h; ++$y) {
        $colHits[$x] += (int) $lit($x, $y);
    }
}
$cols = $logoSpan($colHits, 24, 120, 80);

$rowHits = array_fill(0, $h, 0);
if ($cols !== null) {
    for ($y = 0; $y < $h; ++$y) {
        for ($x = $cols[0]; $x <= $cols[1]; ++$x) {
            $rowHits[$y] += (int) $lit($x, $y);
        }
    }
}
$rows = $logoSpan($rowHits, 20, 60, 12);

if ($cols === null || $rows === null) {
    fwrite(STDERR, "could not find the logo in {$source}\n");
    exit(1);
}
[$left, $right] = $cols;
[$top, $bottom] = $rows;
printf("source %dx%d, logo box x=%d..%d y=%d..%d (%dx%d)\n", $w, $h, $left, $right, $top, $bottom, $right - $left + 1, $bottom - $top + 1);

$clamp = static fn (int $v, int $lo, int $hi): int => max($lo, min($hi, $v));
$failed = false;

foreach ($crops as $name => [$cw, $ch]) {
    if ($cw > $w || $ch > $h) {
        fwrite(STDERR, "  {$name}: {$cw}x{$ch} is larger than the source\n");
        $failed = true;
        continue;
    }
    $x = $clamp(intdiv($left + $right - $cw, 2), 0, $w - $cw);
    $y = $clamp(intdiv($top + $bottom - $ch, 2), 0, $h - $ch);

    if ($left - $x < MARGIN || $x + $cw - 1 - $right < MARGIN || $top - $y < MARGIN || $y + $ch - 1 - $bottom < MARGIN) {
        fwrite(STDERR, "  {$name}: logo does not fit {$cw}x{$ch} with a " . MARGIN . "px margin\n");
        $failed = true;
        continue;
    }

    $dst = imagecreatetruecolor($cw, $ch);
    imagecopy($dst, $src, 0, 0, $x, $y, $cw, $ch);
    imageinterlace($dst, true);          // progressive: renders coarse-to-fine
    imagejpeg($dst, "{$outDir}/{$name}", 84);
    imagedestroy($dst);

    printf("  %-22s %dx%d from x=%d y=%d  %d KB\n", $name, $cw, $ch, $x, $y, filesize("{$outDir}/{$name}") / 1024);
}

exit($failed ? 1 : 0);
