<?php
/**
 * Find which field the importer keeps rewriting on an otherwise unchanged feed.
 *
 * Re-reads each product the way CatalogSync does and reports, per field, how
 * many differ from the value the feed would assign.
 *
 *   docker exec -w .../tools/feed-import spz-shop \
 *     php ../dev/diagnose-churn.php --config=config/supplier.json --limit=400
 */

declare(strict_types=1);

require_once __DIR__ . '/../feed-import/src/FeedReader.php';

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        continue;
    }
    $arg = substr($arg, 2);
    $eq = strpos($arg, '=');
    $opts[$eq === false ? $arg : substr($arg, 0, $eq)] = $eq === false ? '1' : substr($arg, $eq + 1);
}

$configPath = $opts['config'] ?? 'config/supplier.json';
if (!is_file($configPath)) {
    $configPath = __DIR__ . '/../feed-import/' . ltrim($configPath, '/');
}
$job = json_decode((string) file_get_contents($configPath), true);
$limit = (int) ($opts['limit'] ?? 400);

require '/var/www/html/config/config.inc.php';
$kernel = new AppKernel('prod', false);
$kernel->boot();

$idLang = (int) Configuration::get('PS_LANG_DEFAULT');
$map = $job['mapping'];

$diff = ['name' => 0, 'description' => 0, 'description_short' => 0, 'ean13' => 0, 'weight' => 0, 'price' => 0, 'wholesale_price' => 0];
$samples = [];
$wsamples = [];
$esamples = [];
$nsamples = [];
$checked = 0;

foreach ((new FeedReader($job['feed']))->rows() as $row) {
    $sku = trim((string) ($row[$map['sku']] ?? ''));
    if ($sku === '') {
        continue;
    }

    $id = (int) Db::getInstance()->getValue(
        'SELECT id_product FROM ' . _DB_PREFIX_ . 'product WHERE reference = "' . pSQL($sku) . '"'
    );
    if ($id <= 0) {
        continue;
    }

    $p = new Product($id, false, $idLang);
    if ((int) $p->id !== $id) {
        continue;
    }
    ++$checked;

    $feedDesc = trim((string) ($row[$map['description']] ?? ''));
    $stored = is_array($p->description) ? ($p->description[$idLang] ?? '') : $p->description;

    if ((string) $stored !== $feedDesc && $feedDesc !== '') {
        ++$diff['description'];
        if (count($samples) < 3) {
            $samples[] = [
                'sku' => $sku,
                'feed' => mb_substr($feedDesc, 0, 110),
                'stored' => mb_substr((string) $stored, 0, 110),
            ];
        }
    }

    $feedWeight = (float) str_replace(',', '.', (string) ($row[$map['weight']] ?? '0'));
    if (abs((float) $p->weight - $feedWeight) >= 0.000001) {
        ++$diff['weight'];
        if (count($wsamples) < 5) {
            $wsamples[] = sprintf('%s weight stored=%s feed=%s', $sku, $p->weight, $feedWeight);
        }
    }

    $feedEan = trim((string) ($row[$map['ean13']] ?? ''));
    if ($feedEan !== '' && preg_match('/^\d{8}$|^\d{13}$/', $feedEan) && (string) $p->ean13 !== $feedEan) {
        ++$diff['ean13'];
        if (count($esamples) < 5) {
            $esamples[] = sprintf('%s ean stored="%s" feed="%s"', $sku, $p->ean13, $feedEan);
        }
    }

    $storedName = is_array($p->name) ? ($p->name[$idLang] ?? '') : $p->name;
    $feedName = trim((string) ($row[$map['name']] ?? ''));
    if ($feedName !== '' && html_entity_decode((string) $storedName, ENT_QUOTES | ENT_HTML5, 'UTF-8') !== html_entity_decode($feedName, ENT_QUOTES | ENT_HTML5, 'UTF-8')) {
        ++$diff['name'];
        if (count($nsamples) < 5) {
            $nsamples[] = sprintf('%s name stored="%s" feed="%s"', $sku, mb_substr((string) $storedName, 0, 60), mb_substr($feedName, 0, 60));
        }
    }

    if ($limit > 0 && $checked >= $limit) {
        break;
    }
}

echo "checked: {$checked}" . PHP_EOL;
foreach ($diff as $field => $n) {
    if ($n > 0) {
        printf("  %-18s differs on %d\n", $field, $n);
    }
}

foreach (array_merge($nsamples, $wsamples, $esamples) as $line) {
    echo '  ' . $line . PHP_EOL;
}

foreach ($samples as $s) {
    echo PHP_EOL . "SKU {$s['sku']}" . PHP_EOL;
    echo '  feed  : ' . $s['feed'] . PHP_EOL;
    echo '  stored: ' . $s['stored'] . PHP_EOL;
}
