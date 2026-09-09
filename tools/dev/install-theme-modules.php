<?php
/**
 * Install the theme's bundled modules into the shop.
 *
 * PRS935 ships its custom modules under themes/PRS935/dependencies/modules/,
 * but PrestaShop only loads modules from /modules/. The theme cannot be
 * enabled until they are copied there and installed, because ThemeManager
 * hooks them by name and throws FailedToEnableThemeModuleException otherwise.
 *
 * Safe to re-run: existing module directories are left alone unless --overwrite
 * is passed, and already-installed modules are skipped.
 *
 *   docker exec spz-shop php /var/www/html/themes/PRS935/tools/dev/install-theme-modules.php
 */

declare(strict_types=1);

require '/var/www/html/config/config.inc.php';

// Module::install() reaches for the Symfony container (translator, module
// repository). In a bare CLI process SymfonyContainer::getInstance() is null,
// and every install dies with "Call to a member function get() on null".
// Booting the kernel populates it.
$kernel = new AppKernel('prod', false);
$kernel->boot();

$overwrite = in_array('--overwrite', $argv, true);
$reinstall = in_array('--reinstall', $argv, true);

$source = _PS_THEME_DIR_ . 'dependencies/modules';
if (!is_dir($source)) {
    $source = '/var/www/html/themes/PRS935/dependencies/modules';
}
$target = _PS_MODULE_DIR_;

if (!is_dir($source)) {
    fwrite(STDERR, "Source not found: {$source}\n");
    exit(1);
}

echo "Source: {$source}" . PHP_EOL;
echo "Target: {$target}" . PHP_EOL . PHP_EOL;

$copied = $skipped = $installed = $already = $failed = 0;

foreach (new DirectoryIterator($source) as $entry) {
    if ($entry->isDot() || !$entry->isDir()) {
        continue;
    }

    $name = $entry->getFilename();
    $dest = rtrim($target, '/') . '/' . $name;

    if (is_dir($dest) && !$overwrite) {
        ++$skipped;
    } else {
        if (!copyTree($entry->getPathname(), $dest)) {
            echo "  COPY FAILED  {$name}" . PHP_EOL;
            ++$failed;
            continue;
        }
        ++$copied;
    }

    try {
        $module = Module::getInstanceByName($name);

        if (!$module instanceof Module) {
            echo "  INVALID      {$name} (main class did not load)" . PHP_EOL;
            ++$failed;
            continue;
        }

        // A module whose install() died partway (e.g. the container was null)
        // still has a ps_module row but never ran installDB, so the storefront
        // fatals on its missing tables. --reinstall tears the registration
        // down and installs from scratch.
        if ($reinstall && Module::isInstalled($name)) {
            try {
                $module->uninstall();
            } catch (Throwable $e) {
                // A half-installed module often cannot uninstall cleanly;
                // the row purge below is what actually matters.
            }
            purgeRegistration($name);
            Cache::clean('Module::isInstalled' . $name);
            $module = Module::getInstanceByName($name);
        }

        if (Module::isInstalled($name)) {
            if (!Module::isEnabled($name)) {
                $module->enable();
            }
            echo "  present      {$name}" . PHP_EOL;
            ++$already;
            continue;
        }

        if ($module->install()) {
            echo "  INSTALLED    {$name}" . PHP_EOL;
            ++$installed;
        } else {
            $errors = is_array($module->getErrors()) ? implode('; ', $module->getErrors()) : '';
            echo "  FAILED       {$name} {$errors}" . PHP_EOL;
            ++$failed;
        }
    } catch (Throwable $e) {
        echo "  EXCEPTION    {$name}: " . $e->getMessage() . PHP_EOL;
        ++$failed;
    }
}

echo PHP_EOL . sprintf(
    'copied=%d skipped=%d installed=%d already=%d failed=%d',
    $copied, $skipped, $installed, $already, $failed
) . PHP_EOL;

exit($failed > 0 ? 1 : 0);

/**
 * Remove every trace of a module's registration so install() starts clean.
 */
function purgeRegistration(string $name): void
{
    $db = Db::getInstance();
    $id = (int) $db->getValue('SELECT id_module FROM ' . _DB_PREFIX_ . 'module WHERE name = "' . pSQL($name) . '"');

    if ($id > 0) {
        foreach (['module_shop', 'hook_module', 'module_access', 'module_currency', 'module_country', 'module_group', 'module_carrier'] as $table) {
            $db->execute('DELETE FROM ' . _DB_PREFIX_ . $table . ' WHERE id_module = ' . $id);
        }
        $db->execute('DELETE FROM ' . _DB_PREFIX_ . 'module WHERE id_module = ' . $id);
    }
}

function copyTree(string $from, string $to): bool
{
    if (!is_dir($to) && !mkdir($to, 0755, true) && !is_dir($to)) {
        return false;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        $destPath = $to . DIRECTORY_SEPARATOR . $items->getSubPathName();

        if ($item->isDir()) {
            if (!is_dir($destPath) && !mkdir($destPath, 0755, true) && !is_dir($destPath)) {
                return false;
            }
            continue;
        }

        if (!copy($item->getPathname(), $destPath)) {
            return false;
        }
    }

    return true;
}
