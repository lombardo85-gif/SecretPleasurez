<?php
/**
 * Put sellable products on the storefront: active only when a shopper would
 * see something worth seeing.
 *
 * The importer creates products inactive on purpose, so a half-finished
 * import never shows a storefront full of empty image boxes. This is the
 * other half of that decision. A product is activated when it is
 *   - in stock (quantity > 0), and
 *   - has at least one image,
 * and is not PrestaShop demo data or importer test data.
 *
 * Every live product is also placed in the Home category, because that is
 * what the homepage product block reads. Pointing the block at a single real
 * category instead leaves the homepage empty until that category happens to
 * get photos, which can be hours into an image import.
 *
 * Idempotent and cheap, so it is safe to run repeatedly while images are
 * still being attached — the storefront fills in as photos land.
 *
 * It only ever activates. Products that later go out of stock are left to the
 * feed sync, which owns that decision.
 *
 *   docker exec spz-shop php /opt/spz/tools/dev/activate-ready-products.php [--dry-run]
 */

declare(strict_types=1);

require '/var/www/html/config/config.inc.php';

const HOME_CATEGORY = 2;

$dryRun = in_array('--dry-run', $argv, true);
$db = Db::getInstance();
$p = _DB_PREFIX_;

$notTestData = "pr.reference NOT LIKE 'demo\\_%' AND pr.reference NOT LIKE 'ACME-%'";

// Shared by the count and the update, so both always agree on "ready".
$ready = "
    FROM {$p}product pr
    JOIN {$p}product_shop ps ON ps.id_product = pr.id_product
    JOIN {$p}stock_available sa ON sa.id_product = pr.id_product AND sa.id_product_attribute = 0
    WHERE ps.active = 0
      AND sa.quantity > 0
      AND EXISTS (SELECT 1 FROM {$p}image im WHERE im.id_product = pr.id_product)
      AND {$notTestData}
";

// Live products the homepage block cannot see yet.
$missingFromHome = "
    FROM {$p}product pr
    JOIN {$p}product_shop ps ON ps.id_product = pr.id_product AND ps.active = 1
    WHERE {$notTestData}
      AND NOT EXISTS (
        SELECT 1 FROM {$p}category_product cp
        WHERE cp.id_product = pr.id_product AND cp.id_category = " . HOME_CATEGORY . "
      )
";

$pending = (int) $db->getValue('SELECT COUNT(*) ' . $ready);
$homeGap = (int) $db->getValue('SELECT COUNT(*) ' . $missingFromHome);
$totals = $db->getRow("SELECT SUM(active = 1) AS active, SUM(active = 0) AS inactive FROM {$p}product_shop");

printf(
    "ready to activate: %d   live but not on homepage: %d   (active: %d, inactive: %d)%s\n",
    $pending,
    $homeGap,
    (int) $totals['active'],
    (int) $totals['inactive'],
    $dryRun ? '   [dry-run]' : ''
);

if ($dryRun) {
    exit(0);
}

$activated = 0;
if ($pending > 0) {
    // MariaDB will not let a multi-table UPDATE reference its own target in a
    // subquery, so resolve the ids first.
    $ids = array_map('intval', array_column($db->executeS('SELECT pr.id_product ' . $ready) ?: [], 'id_product'));

    foreach (array_chunk($ids, 1000) as $chunk) {
        $in = implode(',', $chunk);
        $db->execute("UPDATE {$p}product SET active = 1, date_upd = NOW() WHERE id_product IN ({$in})");
        $db->execute("UPDATE {$p}product_shop SET active = 1, date_upd = NOW() WHERE id_product IN ({$in})");
    }
    $activated = count($ids);
}

// Re-query after activation so this run's products are included.
$homeIds = array_map('intval', array_column($db->executeS('SELECT pr.id_product ' . $missingFromHome) ?: [], 'id_product'));
if ($homeIds !== []) {
    $position = (int) $db->getValue("SELECT COALESCE(MAX(position), -1) FROM {$p}category_product WHERE id_category = " . HOME_CATEGORY);

    foreach (array_chunk($homeIds, 1000) as $chunk) {
        $values = [];
        foreach ($chunk as $idProduct) {
            $values[] = sprintf('(%d, %d, %d)', HOME_CATEGORY, $idProduct, ++$position);
        }
        $db->execute("INSERT IGNORE INTO {$p}category_product (id_category, id_product, position) VALUES " . implode(',', $values));
    }
}

if ($activated > 0 || $homeIds !== []) {
    // Rendered pages and listings are cached; without this the storefront
    // keeps showing the old, empty state.
    Tools::clearSmartyCache();
    Tools::clearXMLCache();
    Media::clearCache();
}

printf("activated %d product(s); added %d to the homepage pool\n", $activated, count($homeIds));
