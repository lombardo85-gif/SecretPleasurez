<?php
/**
 * Enable the PRS935 theme through PrestaShop's own ThemeManager, so the
 * theme's modules, hooks and image types are installed the same way the
 * back office would install them.
 *
 * Run inside the container:
 *   docker exec spz-shop php /opt/spz/tools/dev/enable-theme.php
 */

declare(strict_types=1);

require '/var/www/html/config/config.inc.php';

$themeName = $argv[1] ?? 'PRS935';

$kernel = new AppKernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();

/** @var \PrestaShop\PrestaShop\Core\Addon\Theme\ThemeManager $manager */
$manager = $container->get('prestashop.core.addon.theme.theme_manager');

echo 'Current theme: ' . Configuration::get('PS_THEME') . PHP_EOL;
echo 'Enabling: ' . $themeName . PHP_EOL;

// force = true: ThemeManager::enable() first checks the *logged-in employee's*
// AdminThemes permission, which no CLI process has, so without this it returns
// false immediately and reports no error. Forcing also skips the validator, so
// validate explicitly first rather than losing that check.
/** @var \PrestaShop\PrestaShop\Core\Addon\Theme\ThemeValidator $validator */
$validator = $container->get('prestashop.core.addon.theme.theme_validator');
$repository = $container->get('prestashop.core.addon.theme.repository');

if (!$validator->isValid($repository->getInstanceByName($themeName))) {
    echo 'Result: theme failed validation, refusing to force-enable' . PHP_EOL;
    foreach ((array) $validator->getErrors($themeName) as $error) {
        echo '  ' . (is_array($error) ? json_encode($error) : (string) $error) . PHP_EOL;
    }
    exit(1);
}

try {
    $ok = $manager->enable($themeName, true);
    echo $ok ? "Result: ENABLED" . PHP_EOL : "Result: enable() returned false" . PHP_EOL;
} catch (Throwable $e) {
    echo 'Result: ' . get_class($e) . ' — ' . $e->getMessage() . PHP_EOL;
}

foreach ((array) $manager->getErrors($themeName) as $error) {
    echo '  error: ' . (is_array($error) ? json_encode($error) : (string) $error) . PHP_EOL;
}

echo 'Active theme now: ' . Configuration::get('PS_THEME') . PHP_EOL;
