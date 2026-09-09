<?php
/**
 * Attach product images to an already-imported catalog.
 *
 * Kept separate from import.php on purpose: image work is slow and IO-bound,
 * and a failed thumbnail should never hold up a price or stock sync. Run the
 * feed import first, then this.
 *
 * Matches feed rows to files by the feed's own image filename, looking in each
 * --images-dir. WordPress size variants (name-300x300.jpg) are ignored so the
 * full-size original is always the one imported.
 *
 * Usage:
 *   php import-images.php --config=config/supplier.json \
 *       --images-dir=/mnt/wp-uploads --dry-run
 *
 * Options:
 *   --config=PATH     job config, for the feed and its mapping (required)
 *   --images-dir=DIR  directory to search, repeatable via comma separation
 *   --dry-run         report matches, write nothing
 *   --limit=N         stop after N products
 *   --only-missing    skip products that already have an image (default on)
 *   --overwrite       replace existing images instead of skipping
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require_once __DIR__ . '/src/FeedReader.php';
require_once __DIR__ . '/src/ImportLogger.php';

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        continue;
    }
    $arg = substr($arg, 2);
    $eq = strpos($arg, '=');
    $opts[$eq === false ? $arg : substr($arg, 0, $eq)] = $eq === false ? '1' : substr($arg, $eq + 1);
}

if (!isset($opts['config'], $opts['images-dir'])) {
    fwrite(STDERR, "error: --config=PATH and --images-dir=DIR are required\n");
    exit(2);
}

$configPath = is_file($opts['config']) ? $opts['config'] : __DIR__ . '/' . ltrim($opts['config'], '/');
$jobConfig = json_decode((string) @file_get_contents($configPath), true);
if (!is_array($jobConfig)) {
    fwrite(STDERR, sprintf("error: unreadable config: %s\n", $configPath));
    exit(2);
}

$imageColumn = $jobConfig['mapping']['image'] ?? 'PRODUCTS_IMAGE';
$skuColumn = $jobConfig['mapping']['sku'] ?? null;
if ($skuColumn === null) {
    fwrite(STDERR, "error: config mapping has no \"sku\"\n");
    exit(2);
}

$dryRun = isset($opts['dry-run']);
$overwrite = isset($opts['overwrite']);
$limit = isset($opts['limit']) ? max(0, (int) $opts['limit']) : 0;

require_once (getenv('PS_ROOT_DIR') ?: '/var/www/html') . '/config/config.inc.php';

$kernel = new AppKernel('prod', false);
$kernel->boot();

set_time_limit(0);
ini_set('memory_limit', '1024M');

$log = new ImportLogger($jobConfig['runtime']['log_dir'] ?? __DIR__ . '/var/log', isset($opts['quiet']));

// Index every candidate file once. Walking 35k files per product would be
// quadratic; one pass into a basename map keeps the run linear.
$index = [];
foreach (explode(',', $opts['images-dir']) as $dir) {
    $dir = trim($dir);
    if ($dir === '' || !is_dir($dir)) {
        $log->warn(sprintf('images-dir not found, skipping: %s', $dir));
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $name = $file->getFilename();
        if (!preg_match('/\.(jpe?g|png|webp)$/i', $name)) {
            continue;
        }

        // "1019-300x300.jpg" is a WordPress derivative of "1019.jpg".
        if (preg_match('/-\d+x\d+\.[a-z]+$/i', $name)) {
            continue;
        }

        $key = strtolower($name);
        // Keep the largest candidate when a name appears more than once.
        if (!isset($index[$key]) || $file->getSize() > filesize($index[$key])) {
            $index[$key] = $file->getPathname();
        }
    }
}

$log->info(sprintf('indexed %d source images', count($index)));

$idLang = (int) Configuration::get('PS_LANG_DEFAULT');
$types = ImageType::getImagesTypes('products');

$matched = $attached = $noFile = $noProduct = $hasImage = $failed = 0;
$seen = 0;

try {
    foreach ((new FeedReader($jobConfig['feed']))->rows() as $row) {
        $sku = trim((string) ($row[$skuColumn] ?? ''));
        $file = trim((string) ($row[$imageColumn] ?? ''));

        if ($sku === '' || $file === '') {
            continue;
        }

        $source = $index[strtolower($file)] ?? null;
        if ($source === null) {
            ++$noFile;
            continue;
        }
        ++$matched;

        $idProduct = (int) Db::getInstance()->getValue(
            'SELECT id_product FROM ' . _DB_PREFIX_ . 'product WHERE reference = "' . pSQL($sku) . '"'
        );

        if ($idProduct <= 0) {
            ++$noProduct;
            continue;
        }

        $existing = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'image WHERE id_product = ' . $idProduct
        );

        if ($existing > 0 && !$overwrite) {
            ++$hasImage;
            continue;
        }

        ++$seen;

        if ($dryRun) {
            ++$attached;
            $log->info(sprintf('[dry-run] %-16s -> product %d  %s', $sku, $idProduct, basename($source)));
        } else {
            if (attachImage($idProduct, $source, $idLang, $types)) {
                ++$attached;
            } else {
                ++$failed;
                $log->warn(sprintf('%s: could not attach %s', $sku, basename($source)));
            }
        }

        if ($limit > 0 && $seen >= $limit) {
            $log->info(sprintf('stopping at --limit=%d', $limit));
            break;
        }

        if ($attached > 0 && $attached % 250 === 0) {
            $log->info(sprintf('... %d attached', $attached));
        }
    }
} catch (Throwable $e) {
    $log->error(sprintf('FATAL %s: %s', get_class($e), $e->getMessage()));
    exit(2);
}

$log->summary(sprintf(
    'images done matched=%d attached=%d already_had=%d no_file=%d no_product=%d failed=%d',
    $matched, $attached, $hasImage, $noFile, $noProduct, $failed
));

exit($failed > 0 ? 1 : 0);

/**
 * @param array<int,array<string,mixed>> $types
 */
function attachImage(int $idProduct, string $source, int $idLang, array $types): bool
{
    $image = new Image();
    $image->id_product = $idProduct;
    $image->position = Image::getHighestPosition($idProduct) + 1;
    $image->cover = !Image::getCover($idProduct);
    $image->legend = [$idLang => ''];

    if (!$image->add()) {
        return false;
    }

    $target = $image->getPathForCreation();

    // Write the full-size original, then one file per registered image type.
    // Skipping the derivatives leaves broken thumbnails across the storefront.
    if (!ImageManager::resize($source, $target . '.jpg')) {
        $image->delete();

        return false;
    }

    foreach ($types as $type) {
        ImageManager::resize(
            $source,
            $target . '-' . stripslashes($type['name']) . '.jpg',
            (int) $type['width'],
            (int) $type['height']
        );
    }

    @chmod($target . '.jpg', 0644);

    return true;
}
