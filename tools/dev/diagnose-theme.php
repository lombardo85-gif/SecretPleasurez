<?php
/**
 * Diagnose why a theme will not enable: shop context, theme discovery, and
 * the validator's actual complaints.
 */

declare(strict_types=1);

require '/var/www/html/config/config.inc.php';

$themeName = $argv[1] ?? 'PRS935';

$kernel = new AppKernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();

echo '== context ==' . PHP_EOL;
$ctx = Context::getContext();
echo 'shop object: ' . (isset($ctx->shop) && $ctx->shop ? 'yes id=' . (int) $ctx->shop->id : 'NULL') . PHP_EOL;
echo 'Shop::getContextShopID: ' . var_export(Shop::getContextShopID(), true) . PHP_EOL;
echo 'PS_THEME cfg: "' . Configuration::get('PS_THEME') . '"' . PHP_EOL;
echo 'PS_SHOP_DEFAULT: ' . Configuration::get('PS_SHOP_DEFAULT') . PHP_EOL;

echo PHP_EOL . '== themes on disk ==' . PHP_EOL;
/** @var \PrestaShop\PrestaShop\Core\Addon\Theme\ThemeRepository $repo */
$repo = $container->get('prestashop.core.addon.theme.repository');
foreach ($repo->getList() as $t) {
    echo ' - ' . $t->getName() . '  (dir: ' . $t->getDirectory() . ')' . PHP_EOL;
}

echo PHP_EOL . '== validation ==' . PHP_EOL;
try {
    $theme = $repo->getInstanceByName($themeName);
    echo 'loaded theme object: ' . $theme->getName() . PHP_EOL;

    /** @var \PrestaShop\PrestaShop\Core\Addon\Theme\ThemeValidator $validator */
    $validator = $container->get('prestashop.core.addon.theme.theme_validator');
    $valid = $validator->isValid($theme);
    echo 'isValid: ' . var_export($valid, true) . PHP_EOL;

    if (!$valid) {
        foreach ((array) $validator->getErrors($themeName) as $k => $err) {
            echo "  [$k] " . (is_array($err) ? json_encode($err) : (string) $err) . PHP_EOL;
        }
    }
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . get_class($e) . ' — ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
