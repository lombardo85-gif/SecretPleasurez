<?php
/**
 * Build img/favicon.ico from brand/NEW-Favicon-source.png.
 *
 * That source is the image inside the supplied brand/NEW-Favicon.ico, decoded
 * to PNG because it is the only copy with transparency (brand/NEW-Favicon.png
 * is really a BMP with no alpha).
 *
 * The artwork may not be square (the supplied one is 117x121). It is centred on a
 * transparent square canvas so it is not stretched, then resampled to 16, 32
 * and 48 px. The ICO container holds PNG-encoded images, supported by every
 * current browser and by Windows since Vista, which keeps alpha and size down.
 *
 * The theme's <head> already links PS_FAVICON as an ICO, so no template
 * change is needed. PS_IMG_UPDATE_TIME is bumped because the theme appends it
 * to the favicon URL as a cache-buster; without it browsers keep the old icon.
 *
 * Deliberately not produced: apple-touch-icon (180px) and PWA icons (192/512).
 * The 107px source would have to be upscaled and would look soft.
 *
 * Run in the container:
 *   docker exec spz-shop php /opt/spz/tools/dev/build-favicon.php [source.png]
 */

declare(strict_types=1);

$source = $argv[1] ?? '/opt/spz/brand/NEW-Favicon-source.png';
$target = '/var/www/html/img/favicon.ico';
$sizes = [16, 32, 48];

if (!is_file($source)) {
    fwrite(STDERR, "source not found: {$source}\n");
    exit(1);
}

$src = imagecreatefrompng($source);
if ($src === false) {
    fwrite(STDERR, "not a readable PNG: {$source}\n");
    exit(1);
}

function transparentCanvas(int $w, int $h)
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));

    return $im;
}

$sw = imagesx($src);
$sh = imagesy($src);
$side = max($sw, $sh);

$square = transparentCanvas($side, $side);
imagealphablending($square, true);
imagecopy($square, $src, intdiv($side - $sw, 2), intdiv($side - $sh, 2), 0, 0, $sw, $sh);

$entries = [];
foreach ($sizes as $size) {
    $img = transparentCanvas($size, $size);
    imagecopyresampled($img, $square, 0, 0, 0, 0, $size, $size, $side, $side);
    ob_start();
    imagepng($img, null, 9);
    $entries[] = [$size, (string) ob_get_clean()];
    imagedestroy($img);
}

// ICONDIR, then one 16-byte ICONDIRENTRY per image, then the image data.
$ico = pack('vvv', 0, 1, count($entries));
$offset = 6 + 16 * count($entries);
$data = '';
foreach ($entries as [$size, $png]) {
    $ico .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($png), $offset);
    $data .= $png;
    $offset += strlen($png);
}
$ico .= $data;

file_put_contents($target, $ico);
@chown($target, 'www-data');

require '/var/www/html/config/config.inc.php';
$kernel = new AppKernel('prod', false);
$kernel->boot();
Configuration::updateValue('PS_FAVICON', 'favicon.ico');
Configuration::updateValue('PS_IMG_UPDATE_TIME', time());

printf(
    "wrote %s (%d bytes): %s from %dx%d source\n",
    $target,
    strlen($ico),
    implode(', ', array_map(static fn ($e) => $e[0] . 'px', $entries)),
    $sw,
    $sh
);
