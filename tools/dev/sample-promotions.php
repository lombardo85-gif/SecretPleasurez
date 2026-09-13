<?php
/**
 * Sample promotions, to preview the homepage offers carousel.
 *
 * Creates, all ending in 30 days:
 *   - 20% off 8 in-stock vibrators                     (specific prices)
 *   - WELCOME15: 15% off, once per customer,
 *     not on items already on sale                     (cart rule)
 *   - free shipping on orders over $75, no code needed (cart rule)
 * and writes four carousel slides (SPZ_PROMO_SLIDES) that feature them.
 *
 * These are real, working promotions in this database: a shopper would get
 * the discounts. So cart rules are named "[TEST] ...", every id is recorded
 * in SPZ_SAMPLE_PROMOS, and --remove deletes exactly those. Replace them with
 * real offers before launch. Re-running replaces the previous sample set.
 *
 *   docker exec spz-shop php /opt/spz/tools/dev/sample-promotions.php
 *   docker exec spz-shop php /opt/spz/tools/dev/sample-promotions.php --remove
 */

declare(strict_types=1);

require '/var/www/html/config/config.inc.php';

const SPZ_REGISTRY = 'SPZ_SAMPLE_PROMOS';
const SPZ_SLIDES = 'SPZ_PROMO_SLIDES';
const SPZ_DAYS = 30;
const SPZ_FREE_SHIPPING_OVER = 75;
const SPZ_SEED = 7; // fixed, so re-running features the same products

function spz_fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function spz_remove_samples(): void
{
    $registry = json_decode((string) Configuration::get(SPZ_REGISTRY), true);
    if (!is_array($registry)) {
        return;
    }
    foreach ($registry['specific_prices'] ?? [] as $id) {
        $price = new SpecificPrice((int) $id);
        if (Validate::isLoadedObject($price)) {
            $price->delete();
        }
    }
    foreach ($registry['cart_rules'] ?? [] as $id) {
        $rule = new CartRule((int) $id);
        if (Validate::isLoadedObject($rule)) {
            $rule->delete();
        }
    }
    if (!empty($registry['slides'])) {
        Configuration::deleteByName(SPZ_SLIDES);
    }
    Configuration::deleteByName(SPZ_REGISTRY);
    printf("removed %d sale prices and %d cart rules\n", count($registry['specific_prices'] ?? []), count($registry['cart_rules'] ?? []));
}

/** In-stock, photographed, visible products in a category and price band. */
function spz_pick(int $idCategory, float $min, float $max, int $limit, array $exclude = []): array
{
    $p = _DB_PREFIX_;
    $not = $exclude === [] ? '' : ' AND p.id_product NOT IN (' . implode(',', array_map('intval', $exclude)) . ')';
    $rows = Db::getInstance()->executeS(
        "SELECT p.id_product
         FROM {$p}product p
         JOIN {$p}product_shop ps ON ps.id_product = p.id_product AND ps.id_shop = 1
              AND ps.active = 1 AND ps.visibility IN ('both', 'catalog')
         JOIN {$p}category_product cp ON cp.id_product = p.id_product AND cp.id_category = {$idCategory}
         JOIN {$p}image_shop i ON i.id_product = p.id_product AND i.id_shop = 1 AND i.cover = 1
         WHERE ps.price BETWEEN " . (float) $min . ' AND ' . (float) $max . "
           AND (SELECT MAX(sa.quantity) FROM {$p}stock_available sa
                WHERE sa.id_product = p.id_product AND sa.id_product_attribute = 0) >= 3
           AND NOT EXISTS (SELECT 1 FROM {$p}specific_price sp WHERE sp.id_product = p.id_product)
           {$not}
         GROUP BY p.id_product
         ORDER BY RAND(" . SPZ_SEED . ")
         LIMIT {$limit}"
    );

    return array_map(static fn (array $row): int => (int) $row['id_product'], $rows ?: []);
}

function spz_add_sale(int $idProduct, float $reduction, string $until): int
{
    $price = new SpecificPrice();
    $price->id_product = $idProduct;
    $price->id_product_attribute = 0;
    $price->id_shop = 0;
    $price->id_shop_group = 0;
    $price->id_currency = 0;
    $price->id_country = 0;
    $price->id_group = 0;
    $price->id_customer = 0;
    $price->id_cart = 0;
    $price->id_specific_price_rule = 0;
    $price->price = -1; // keep the product's own price
    $price->from_quantity = 1;
    $price->reduction = $reduction;
    $price->reduction_tax = 1;
    $price->reduction_type = 'percentage';
    $price->from = date('Y-m-d H:i:s');
    $price->to = $until;
    if (!$price->add()) {
        spz_fail("could not create a sale price for product {$idProduct}");
    }

    return (int) $price->id;
}

function spz_add_cart_rule(string $name, string $until, array $fields): int
{
    if (!empty($fields['code']) && CartRule::getIdByCode($fields['code'])) {
        spz_fail("a cart rule with code {$fields['code']} already exists and is not a sample; not touching it");
    }
    $currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
    $rule = new CartRule();
    foreach (Language::getIDs(false) as $idLang) {
        $rule->name[(int) $idLang] = $name;
    }
    $rule->description = 'Sample promotion from tools/dev/sample-promotions.php; remove with --remove.';
    $rule->date_from = date('Y-m-d H:i:s');
    $rule->date_to = $until;
    $rule->quantity = 100000;
    $rule->quantity_per_user = 100000;
    $rule->minimum_amount_currency = $currency;
    $rule->reduction_currency = $currency;
    $rule->partial_use = 0;
    $rule->highlight = 0;
    $rule->active = 1;
    foreach ($fields as $field => $value) {
        $rule->{$field} = $value;
    }
    if (!$rule->add()) {
        spz_fail("could not create cart rule {$name}");
    }

    return (int) $rule->id;
}

spz_remove_samples();
if (in_array('--remove', $argv, true)) {
    Tools::clearSmartyCache();
    echo "sample promotions removed\n";
    exit(0);
}

$endsDate = date('Y-m-d', strtotime('+' . SPZ_DAYS . ' days'));
$until = $endsDate . ' 23:59:59';
$registry = ['cart_rules' => [], 'specific_prices' => [], 'slides' => true, 'created' => date('c')];

// Record ids as they are created, so a failure part-way can still be undone
// with --remove.
$save = static function () use (&$registry): void {
    Configuration::updateValue(SPZ_REGISTRY, json_encode($registry));
};

$sale = spz_pick(31, 25, 120, 8);
if (count($sale) < 3) {
    spz_fail('not enough in-stock vibrators with photos to put on sale');
}
foreach ($sale as $id) {
    $registry['specific_prices'][] = spz_add_sale($id, 0.20, $until);
    $save();
}

$registry['cart_rules'][] = spz_add_cart_rule('[TEST] Welcome 15% off', $until, [
    'code' => 'WELCOME15',
    'quantity_per_user' => 1,
    'reduction_percent' => 15,
    'reduction_exclude_special' => 1,
]);
$save();

$registry['cart_rules'][] = spz_add_cart_rule('[TEST] Free shipping over $' . SPZ_FREE_SHIPPING_OVER, $until, [
    'code' => '',
    'free_shipping' => 1,
    'minimum_amount' => SPZ_FREE_SHIPPING_OVER,
    'minimum_amount_tax' => 1,
    'minimum_amount_shipping' => 0,
]);
$save();

$lingerie = spz_pick(36, 20, 60, 3, $sale);
$stimulators = spz_pick(35, 30, 90, 3, $sale);
$treats = spz_pick(25, 8, 25, 3, $sale);

$slides = [
    [
        'theme' => 'pink',
        'eyebrow' => 'Limited-time sale',
        'title' => '20% off selected vibrators',
        'text' => 'Marked down for a limited time. Prices already reduced, no code needed.',
        'cta_label' => 'Shop the sale',
        'link' => 'page:prices-drop',
        'ends' => $endsDate,
        'products' => array_slice($sale, 0, 3),
    ],
    [
        'theme' => 'cyan',
        'eyebrow' => 'New here?',
        'title' => '15% off your first order',
        'text' => 'Enter the code at checkout. One use per customer; not valid on sale items.',
        'code' => 'WELCOME15',
        'cta_label' => 'Shop lingerie',
        'link' => 'category:36',
        'ends' => $endsDate,
        'products' => $lingerie,
    ],
    [
        'theme' => 'champagne',
        'eyebrow' => 'Free shipping',
        'title' => 'Free shipping on orders over $' . SPZ_FREE_SHIPPING_OVER,
        'text' => 'Applied automatically at checkout. No code needed.',
        'cta_label' => 'Shop stimulators',
        'link' => 'category:35',
        'ends' => $endsDate,
        'products' => $stimulators,
    ],
    [
        'theme' => 'duo',
        'eyebrow' => 'Under $25',
        'title' => 'Little luxuries, big pleasure',
        'text' => 'Lubricants and essentials to go with everything else.',
        'cta_label' => 'Shop lubricants',
        'link' => 'category:25',
        'products' => $treats,
    ],
];

Configuration::updateValue(SPZ_SLIDES, json_encode($slides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$save();
Tools::clearSmartyCache();

printf(
    "created %d sale prices (products %s), cart rules %s, and %d slides; everything ends %s\n",
    count($registry['specific_prices']),
    implode(', ', $sale),
    implode(', ', $registry['cart_rules']),
    count($slides),
    $endsDate
);
printf("slide products: lingerie %s; stimulators %s; under \$25 %s\n", implode(', ', $lingerie), implode(', ', $stimulators), implode(', ', $treats));
