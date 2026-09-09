<?php
/**
 * Reproduce a single failing product insert with full error reporting, to see
 * why Product::add() reports success while leaving the id at 0.
 *
 *   docker exec spz-shop php .../tools/dev/debug-product-add.php "Cock Cage U-Ring No. 3 - Black"
 */

declare(strict_types=1);

require '/var/www/html/config/config.inc.php';

$kernel = new AppKernel('prod', false);
$kernel->boot();

$name = $argv[1] ?? 'Cock Cage U-Ring No. 3 - Black';
$reference = $argv[2] ?? 'DEBUG-TEST-1';
$idLang = (int) Configuration::get('PS_LANG_DEFAULT');

echo "name      : {$name}" . PHP_EOL;
echo 'isCatalogName : ' . var_export(Validate::isCatalogName($name), true) . PHP_EOL;

$rewrite = Tools::link_rewrite($name);
echo "link_rewrite  : '{$rewrite}'" . PHP_EOL;
echo 'isLinkRewrite : ' . var_export(Validate::isLinkRewrite($rewrite), true) . PHP_EOL;

$p = new Product();
$p->reference = $reference;
$p->name = [$idLang => $name];
$p->link_rewrite = [$idLang => $rewrite];
$p->id_category_default = 2;
$p->price = 24.99;
$p->wholesale_price = 12.00;
$p->active = false;
$p->visibility = 'both';
$p->minimal_quantity = 1;

$errors = $p->validateFields(false, true);
echo 'validateFields: ' . (is_string($errors) ? $errors : 'ok') . PHP_EOL;

$langErrors = $p->validateFieldsLang(false, true);
echo 'validateFieldsLang: ' . (is_string($langErrors) ? $langErrors : 'ok') . PHP_EOL;

try {
    $ok = $p->add();
    echo 'add() returned: ' . var_export($ok, true) . PHP_EOL;
    echo 'resulting id  : ' . var_export($p->id, true) . PHP_EOL;
    echo 'db error      : ' . Db::getInstance()->getMsgError() . PHP_EOL;
    echo 'Insert_ID     : ' . var_export(Db::getInstance()->Insert_ID(), true) . PHP_EOL;

    if ((int) $p->id > 0) {
        $p->delete();
        echo "cleaned up test product" . PHP_EOL;
    } else {
        foreach (['product_lang', 'product_shop', 'stock_available'] as $t) {
            Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . $t . ' WHERE id_product = 0');
        }
        echo "purged id-0 rows" . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'THREW ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . 'ps_product AUTO_INCREMENT: ' . Db::getInstance()->getValue(
    "SELECT AUTO_INCREMENT FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . _DB_PREFIX_ . "product'"
) . PHP_EOL;
echo 'max id_product           : ' . Db::getInstance()->getValue('SELECT MAX(id_product) FROM ' . _DB_PREFIX_ . 'product') . PHP_EOL;
