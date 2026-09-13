<?php
/**
 * Build the header logo from the brand banner art.
 *
 * brand/NEW-Logo.png stacks the symbol above the wordmark with wide dark
 * margins, so in a header row about 96px tall the wordmark was only ~10px
 * high. The banner (brand/Gemini_Generated_Image_96almx96almx96al.jpeg) has
 * the same artwork as a horizontal lockup, symbol beside wordmark; cropped
 * tight, the wordmark is several times larger at the same header height.
 *
 * Output: assets/img/spz-logo-lockup.png, a transparent PNG 200px tall (2x
 * for a ~100px header).
 *
 * Transparency: the art is light on black, so it is converted screen-to-alpha
 * (alpha = brightest channel, colour un-premultiplied). Over any dark ground
 * that reproduces the art, with no rectangle and no blend mode needed. A
 * black point first drops faint haze and stars, and the edges fade out so a
 * wisp of nebula cannot end in a hard line.
 *
 * The logo is located, not hard-coded: see logo_box(). Its neon strokes and
 * lettering form a dense block of bright pixels; stars and bokeh are small.
 *
 * Run in the container:
 *   docker exec spz-shop php /opt/spz/tools/dev/build-logo-lockup.php [source.jpg]
 */

declare(strict_types=1);

$source = $argv[1] ?? '/opt/spz/brand/Gemini_Generated_Image_96almx96almx96al.jpeg';
$output = '/var/www/html/themes/PRS935/assets/img/spz-logo-lockup.png';

const BRIGHT = 0.65;      // relative luminance counted as "lit" when locating
const MIN_HITS = 6;       // lit pixels a column/row needs to count
// The dense-pixel box stops short of the thin arrow tip, swirls and the "z"
// tail, which are dimmer; pad it to take them in.
const PAD_TOP = 60;
const PAD_RIGHT = 80;
const PAD_BOTTOM = 60;
const PAD_LEFT = 40;
// 56 rather than lower: at 36, faint banner stars around the wordmark
// survived as speckles once the logo sat on the header.
const BLACK_POINT = 56;   // 0-255: channel values at or below become black
const FADE = 32;          // px of edge fade
const OUT_HEIGHT = 200;

$src = @imagecreatefromjpeg($source);
if ($src === false) {
    fwrite(STDERR, "not a readable JPEG: {$source}\n");
    exit(1);
}
$w = imagesx($src);
$h = imagesy($src);

/**
 * Span of the logo along one axis: the longest run of qualifying indices
 * (gaps up to $gap bridged), extended by neighbouring runs at least
 * $minWidth wide within $reach. Reach is measured from the previous run, so
 * a narrow flourish between symbol and lettering links them.
 */
function logo_span(array $hits, int $gap, int $reach, int $minWidth): ?array
{
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
}

function logo_box($img, int $w, int $h): ?array
{
    $lit = static function (int $x, int $y) use ($img): bool {
        $rgb = imagecolorat($img, $x, $y);

        return (0.2126 * (($rgb >> 16) & 255) + 0.7152 * (($rgb >> 8) & 255) + 0.0722 * ($rgb & 255)) / 255 > BRIGHT;
    };
    $colHits = array_fill(0, $w, 0);
    for ($x = 0; $x < $w; ++$x) {
        for ($y = 0; $y < $h; ++$y) {
            $colHits[$x] += (int) $lit($x, $y);
        }
    }
    $cols = logo_span($colHits, 24, 120, 80);
    if ($cols === null) {
        return null;
    }
    $rowHits = array_fill(0, $h, 0);
    for ($y = 0; $y < $h; ++$y) {
        for ($x = $cols[0]; $x <= $cols[1]; ++$x) {
            $rowHits[$y] += (int) $lit($x, $y);
        }
    }
    $rows = logo_span($rowHits, 20, 60, 12);

    return $rows === null ? null : [$cols[0], $rows[0], $cols[1], $rows[1]];
}

$box = logo_box($src, $w, $h);
if ($box === null) {
    fwrite(STDERR, "could not find the logo in {$source}\n");
    exit(1);
}
$x0 = max(0, $box[0] - PAD_LEFT);
$y0 = max(0, $box[1] - PAD_TOP);
$x1 = min($w - 1, $box[2] + PAD_RIGHT);
$y1 = min($h - 1, $box[3] + PAD_BOTTOM);
$cw = $x1 - $x0 + 1;
$ch = $y1 - $y0 + 1;
printf("source %dx%d, logo box x=%d..%d y=%d..%d, crop %dx%d at (%d, %d)\n", $w, $h, $box[0], $box[2], $box[1], $box[3], $cw, $ch, $x0, $y0);

$smooth = static fn (float $t): float => $t <= 0 ? 0.0 : ($t >= 1 ? 1.0 : $t * $t * (3 - 2 * $t));
$scale = 255 / (255 - BLACK_POINT);

$cut = imagecreatetruecolor($cw, $ch);
imagealphablending($cut, false);
imagesavealpha($cut, true);
for ($y = 0; $y < $ch; ++$y) {
    $fy = $smooth(min($y, $ch - 1 - $y) / FADE);
    for ($x = 0; $x < $cw; ++$x) {
        $rgb = imagecolorat($src, $x0 + $x, $y0 + $y);
        $r = max(0, (($rgb >> 16) & 255) - BLACK_POINT) * $scale;
        $g = max(0, (($rgb >> 8) & 255) - BLACK_POINT) * $scale;
        $b = max(0, ($rgb & 255) - BLACK_POINT) * $scale;
        $alpha = max($r, $g, $b) / 255 * $fy * $smooth(min($x, $cw - 1 - $x) / FADE);
        if ($alpha <= 0.004) {
            imagesetpixel($cut, $x, $y, imagecolorallocatealpha($cut, 0, 0, 0, 127));
            continue;
        }
        // Un-premultiply against the original brightness, so fading the edge
        // lowers opacity instead of darkening the colour.
        $max = max($r, $g, $b);
        $k = 255 / $max;
        imagesetpixel($cut, $x, $y, imagecolorallocatealpha(
            $cut,
            (int) min(255, round($r * $k)),
            (int) min(255, round($g * $k)),
            (int) min(255, round($b * $k)),
            127 - (int) round(min(1, $alpha) * 127)
        ));
    }
}

$outW = (int) round($cw * OUT_HEIGHT / $ch);
$out = imagecreatetruecolor($outW, OUT_HEIGHT);
imagealphablending($out, false);
imagesavealpha($out, true);
imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
imagecopyresampled($out, $cut, 0, 0, 0, 0, $outW, OUT_HEIGHT, $cw, $ch);
imagepng($out, $output, 9);

printf("wrote %s %dx%d, %d KB\n", basename($output), $outW, OUT_HEIGHT, filesize($output) / 1024);
