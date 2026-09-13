<?php
/**
 * Give products already in the catalogue the brand their feed row names.
 *
 * import.php sets brands on every product it writes, but running it over the
 * whole feed also re-syncs prices and stock. This only touches brands: it reads
 * the feed, creates any missing manufacturers, and sets id_manufacturer on
 * products (matched by reference) that have no brand yet. Products that already
 * have a brand are left alone, so a brand corrected by hand survives and a
 * second run changes nothing.
 *
 * Where a brand is spelled several ways ("OMG!" / "OMG") the most common
 * spelling names the brand page.
 *
 *   docker exec -w /opt/spz/tools/feed-import spz-shop \
 *     php backfill-brands.php --config=config/supplier.json [--dry-run]
 *
 * Then run tools/dev/activate-ready-products.php, which shows brands that have
 * live products and hides the rest.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require_once __DIR__ . '/src/FeedReader.php';
require_once __DIR__ . '/src/BrandResolver.php';

$configPath = null;
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--config=') === 0) {
        $configPath = substr($arg, 9);
    }
}
$dryRun = in_array('--dry-run', $argv, true);

if ($configPath === null) {
    fwrite(STDERR, "error: --config=PATH is required\n");
    exit(2);
}
if (!is_file($configPath)) {
    $configPath = __DIR__ . '/' . ltrim($configPath, '/');
}
$jobConfig = json_decode((string) @file_get_contents($configPath), true);
if (!is_array($jobConfig)) {
    fwrite(STDERR, "error: config missing or not valid JSON: {$configPath}\n");
    exit(2);
}

$skuColumn = (string) ($jobConfig['mapping']['sku'] ?? '');
$brandColumn = (string) ($jobConfig['mapping']['brand'] ?? '');
if ($skuColumn === '' || $brandColumn === '') {
    fwrite(STDERR, "error: config mapping needs both \"sku\" and \"brand\"\n");
    exit(2);
}

// NOTE: PrestaShop's bootstrap defines its own global $config.
require_once '/var/www/html/config/config.inc.php';
$kernel = new AppKernel('prod', false);
$kernel->boot();
ini_set('memory_limit', $jobConfig['runtime']['memory_limit'] ?? '512M');

// Feed paths in the config are relative to this directory.
chdir(__DIR__);

$db = Db::getInstance();
$p = _DB_PREFIX_;

/** @var array<string,int> products with no brand yet: reference => id */
$unbranded = [];
foreach ($db->executeS("SELECT id_product, reference FROM {$p}product WHERE id_manufacturer = 0 AND reference <> ''") ?: [] as $r) {
    $unbranded[(string) $r['reference']] = (int) $r['id_product'];
}

$resolver = new BrandResolver((array) ($jobConfig['catalog']['ignore_brands'] ?? []), $dryRun);

/** @var array<string,array<string,int>> brand key => spelling => count */
$spellings = [];
/** @var array<string,int[]> brand key => unbranded product ids */
$products = [];
$rows = 0;

foreach ((new FeedReader($jobConfig['feed']))->rows() as $row) {
    ++$rows;
    $name = BrandResolver::clean((string) ($row[$brandColumn] ?? ''));
    $key = BrandResolver::key($name);
    if ($key === '') {
        continue;
    }
    $spellings[$key][$name] = ($spellings[$key][$name] ?? 0) + 1;

    $id = $unbranded[trim((string) ($row[$skuColumn] ?? ''))] ?? 0;
    if ($id > 0) {
        $products[$key][] = $id;
    }
}

$brands = 0;
$created = 0;
$assigned = 0;

foreach ($products as $key => $ids) {
    arsort($spellings[$key]);
    $name = (string) array_key_first($spellings[$key]);
    $isNew = !$resolver->exists($name);

    if ($dryRun) {
        if (!$isNew && $resolver->resolve($name) === 0) {
            continue; // a placeholder such as "No Brand"
        }
        ++$brands;
        $created += (int) $isNew;
        $assigned += count($ids);
        continue;
    }

    $idManufacturer = $resolver->resolve($name);
    if ($idManufacturer === 0) {
        continue;
    }
    ++$brands;
    $created += (int) $isNew;

    foreach (array_chunk(array_values(array_unique($ids)), 1000) as $chunk) {
        $db->execute(
            "UPDATE {$p}product SET id_manufacturer = {$idManufacturer}
             WHERE id_manufacturer = 0 AND id_product IN (" . implode(',', $chunk) . ')'
        );
        $assigned += (int) $db->Affected_Rows();
    }
}

if (!$dryRun && $assigned > 0) {
    Tools::clearSmartyCache();
    Media::clearCache();
}

printf(
    "feed rows: %d   brands in feed: %d   products without a brand: %d\n%s %d product(s) under %d brand(s), %d of them new%s\n",
    $rows,
    count($spellings),
    count($unbranded),
    $dryRun ? 'would brand' : 'branded',
    $assigned,
    $brands,
    $created,
    $dryRun ? '   [dry-run]' : ''
);
