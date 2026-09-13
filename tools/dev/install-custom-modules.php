<?php
/**
 * Install the store's own modules (custom-modules/ in the repo).
 *
 * Each is mounted into /var/www/html/modules/<name> by docker-compose.yml, so
 * there is nothing to copy; this only registers and installs them. Safe to
 * re-run: installed modules are skipped unless --reinstall is passed.
 *
 *   docker exec spz-shop php /opt/spz/tools/dev/install-custom-modules.php [--reinstall]
 */

declare(strict_types=1);

require '/var/www/html/config/config.inc.php';

// Module::install() needs the Symfony container, which a bare CLI process
// does not have until the kernel is booted.
$kernel = new AppKernel('prod', false);
$kernel->boot();

const SPZ_CUSTOM_MODULES = ['spzpromos'];

$reinstall = in_array('--reinstall', $argv, true);
$failed = false;

foreach (SPZ_CUSTOM_MODULES as $name) {
    if (!is_file(_PS_MODULE_DIR_ . "{$name}/{$name}.php")) {
        fwrite(STDERR, "{$name}: not mounted at " . _PS_MODULE_DIR_ . "{$name} (check docker-compose.yml)\n");
        $failed = true;
        continue;
    }
    $module = Module::getInstanceByName($name);
    if (!$module) {
        fwrite(STDERR, "{$name}: could not be loaded\n");
        $failed = true;
        continue;
    }
    if (Module::isInstalled($name)) {
        if (!$reinstall) {
            echo "{$name}: already installed\n";
            continue;
        }
        $module->uninstall();
    }
    if ($module->install()) {
        echo "{$name}: installed\n";
    } else {
        fwrite(STDERR, "{$name}: install failed: " . implode('; ', $module->getErrors()) . "\n");
        $failed = true;
    }
}

exit($failed ? 1 : 0);
