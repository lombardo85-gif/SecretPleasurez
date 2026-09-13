<?php
/**
 * Give every live category working shop filters (ps_facetedsearch).
 *
 * PrestaShop's install created one filter template, and it covers only the
 * demo categories, so the real catalogue showed no filters at all. This points
 * a single template at every active category and builds the facet list from
 * the data that actually exists:
 *
 *   - Price, always.
 *   - Brand, when products have brands.
 *   - Any feature or attribute group carried by at least MIN_LIVE_PRODUCTS
 *     live products. Features added later (material, length) appear when this
 *     is re-run; the few demo leftovers on a handful of products do not.
 *
 * Left out on purpose:
 *   - Availability: every live product is in stock and the feed's stock is
 *     years stale, so an "In stock" filter narrows nothing and reads as a
 *     promise.
 *   - Extras: it bundles "On sale" with "New product", and the whole catalogue
 *     was imported inside PS_NB_DAYS_NEW_PRODUCT, so old stock would be
 *     offered as new.
 *   - Subcategories, condition and weight: the tree is flat, everything is
 *     new, and weight is not how anyone shops here.
 *
 * Safe to re-run. Run it again after adding categories or features.
 *
 *   docker exec spz-shop php /opt/spz/tools/dev/setup-filters.php [--dry-run] [--full-price-index]
 *
 * --full-price-index rebuilds the price index from scratch instead of only
 * indexing products that are missing from it (use after bulk price changes).
 */

declare(strict_types=1);

require '/var/www/html/config/config.inc.php';

// Module methods reach into the Symfony container.
$kernel = new AppKernel('prod', false);
$kernel->boot();

const TEMPLATE_NAME = 'Live catalogue (tools/dev/setup-filters.php)';
const CONTROLLERS = ['category', 'manufacturer', 'supplier', 'new-products', 'best-sales', 'prices-drop'];
const MIN_LIVE_PRODUCTS = 20;

$dryRun = in_array('--dry-run', $argv, true);
$fullPriceIndex = in_array('--full-price-index', $argv, true);

$db = Db::getInstance();
$p = _DB_PREFIX_;
$idShop = (int) Context::getContext()->shop->id;
$idLang = (int) Configuration::get('PS_LANG_DEFAULT');

$module = Module::getInstanceByName('ps_facetedsearch');
if (!$module || !Module::isInstalled('ps_facetedsearch')) {
    fwrite(STDERR, "ps_facetedsearch is not installed\n");
    exit(1);
}

$categories = array_map('intval', array_column($db->executeS(
    "SELECT id_category FROM {$p}category
     WHERE active = 1 AND id_category <> " . (int) Configuration::get('PS_ROOT_CATEGORY') . '
     ORDER BY id_category'
) ?: [], 'id_category'));

$liveProduct = "JOIN {$p}product_shop ps ON ps.id_product = x.id_product AND ps.active = 1 AND ps.id_shop = {$idShop}";

$brandedProducts = (int) $db->getValue(
    "SELECT COUNT(*) FROM {$p}product x {$liveProduct} WHERE x.id_manufacturer > 0"
);

$features = $db->executeS(
    "SELECT fl.id_feature, fl.name, COUNT(DISTINCT x.id_product) AS products
     FROM {$p}feature_product x {$liveProduct}
     JOIN {$p}feature_lang fl ON fl.id_feature = x.id_feature AND fl.id_lang = {$idLang}
     GROUP BY fl.id_feature, fl.name
     HAVING products >= " . MIN_LIVE_PRODUCTS . '
     ORDER BY products DESC'
) ?: [];

$attributeGroups = $db->executeS(
    "SELECT agl.id_attribute_group, agl.name, COUNT(DISTINCT x.id_product) AS products
     FROM {$p}product_attribute x {$liveProduct}
     JOIN {$p}product_attribute_combination pac ON pac.id_product_attribute = x.id_product_attribute
     JOIN {$p}attribute a ON a.id_attribute = pac.id_attribute
     JOIN {$p}attribute_group_lang agl ON agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = {$idLang}
     GROUP BY agl.id_attribute_group, agl.name
     HAVING products >= " . MIN_LIVE_PRODUCTS . '
     ORDER BY products DESC'
) ?: [];

// filter_type 0 is the module default for each kind: a slider for price,
// checkboxes for lists. filter_show_limit 0 lists every value; long lists are
// kept compact by the theme's CSS instead of hiding values.
$facet = ['filter_type' => 0, 'filter_show_limit' => 0];

$filters = [
    'categories' => $categories,
    'controllers' => CONTROLLERS,
    'shop_list' => [$idShop => $idShop],
    'layered_selection_price_slider' => $facet,
];
$labels = ['Price'];

if ($brandedProducts > 0) {
    $filters['layered_selection_manufacturer'] = $facet;
    $labels[] = "Brand ({$brandedProducts} products)";
}
foreach ($features as $feature) {
    $filters['layered_selection_feat_' . (int) $feature['id_feature']] = $facet;
    $labels[] = "{$feature['name']} ({$feature['products']} products)";
}
foreach ($attributeGroups as $group) {
    $filters['layered_selection_ag_' . (int) $group['id_attribute_group']] = $facet;
    $labels[] = "{$group['name']} ({$group['products']} products)";
}

printf(
    "categories: %d\nfilters: %s%s\n",
    count($categories),
    implode(', ', $labels),
    $dryRun ? "\n[dry-run] nothing written" : ''
);

if ($dryRun) {
    exit(0);
}

// Reuse this script's template, or take over the install-time default (which
// only ever covered demo categories), so re-runs never pile up templates.
$idTemplate = (int) $db->getValue(
    "SELECT id_layered_filter FROM {$p}layered_filter WHERE name = '" . pSQL(TEMPLATE_NAME) . "'"
);
if ($idTemplate === 0) {
    $idTemplate = (int) $db->getValue(
        "SELECT id_layered_filter FROM {$p}layered_filter WHERE name LIKE 'My template %' ORDER BY id_layered_filter"
    );
}

// date_add is set to now because buildLayeredCategories() lets the newest
// template win when two claim the same category.
$row = [
    'name' => pSQL(TEMPLATE_NAME),
    'filters' => pSQL(serialize($filters)),
    'n_categories' => count($categories),
    'date_add' => date('Y-m-d H:i:s'),
];

if ($idTemplate > 0) {
    $db->update('layered_filter', $row, 'id_layered_filter = ' . $idTemplate);
} else {
    $db->insert('layered_filter', $row);
    $idTemplate = (int) $db->Insert_ID();
}
$db->execute("INSERT IGNORE INTO {$p}layered_filter_shop (id_layered_filter, id_shop) VALUES ({$idTemplate}, {$idShop})");

$module->buildLayeredCategories();
$module->indexAttributeGroup();
$module->indexFeatures();
$module->indexAttributes();

$before = (int) $db->getValue("SELECT COUNT(DISTINCT id_product) FROM {$p}layered_price_index");
$module->fullPricesIndexProcess(0, false, !$fullPriceIndex);
$after = (int) $db->getValue("SELECT COUNT(DISTINCT id_product) FROM {$p}layered_price_index");

Configuration::updateValue('PS_LAYERED_INDEXED', 1);
$module->invalidateLayeredFilterBlockCache();
Tools::clearSmartyCache();

printf(
    "template %d assigned (%d filter rows); price index covers %d products (was %d)\n",
    $idTemplate,
    (int) $db->getValue("SELECT COUNT(*) FROM {$p}layered_category"),
    $after,
    $before
);
