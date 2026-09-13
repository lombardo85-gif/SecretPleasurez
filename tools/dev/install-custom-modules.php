<?php
/**
 * Install the store's own modules (custom-modules/ in the repo).
 *
 * Each is mounted into /var/www/html/modules/<name> by docker-compose.yml, so
 * there is nothing to copy; this only registers and installs them. Safe to
 * re-run: installed modules are not reinstalled unless --reinstall is passed
 * (a reinstall deletes their settings), but any hook a module has gained since
 * it was installed is registered.
 *
 * Theme demo modules whose spot one of these modules now fills are unhooked
 * from that spot (see SPZ_REPLACED_HOOKS); they stay installed.
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

/**
 * module => hook it no longer renders in. otbrandlist's "Clients" strip showed
 * placeholder logos under a heading implying client relationships; spzpromos
 * lists real brands there instead.
 */
const SPZ_REPLACED_HOOKS = ['otbrandlist' => 'displayHomeBottom'];

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
            $hooks = defined(get_class($module) . '::HOOKS') ? constant(get_class($module) . '::HOOKS') : [];
            $missing = array_values(array_filter($hooks, static fn ($hook) => !$module->isRegisteredInHook($hook)));
            if ($missing !== [] && !$module->registerHook($missing)) {
                fwrite(STDERR, "{$name}: could not register " . implode(', ', $missing) . "\n");
                $failed = true;
                continue;
            }
            echo "{$name}: already installed" . ($missing !== [] ? '; registered ' . implode(', ', $missing) : '') . "\n";
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

foreach (SPZ_REPLACED_HOOKS as $name => $hook) {
    $module = Module::isInstalled($name) ? Module::getInstanceByName($name) : false;
    if (!$module || !$module->isRegisteredInHook($hook)) {
        continue;
    }
    $idHook = (int) Hook::getIdByName($hook);
    if ($idHook > 0 && $module->unregisterHook($idHook)) {
        echo "{$name}: removed from {$hook}\n";
    } else {
        fwrite(STDERR, "{$name}: could not be removed from {$hook}\n");
        $failed = true;
    }
}

if (!$failed) {
    Tools::clearSmartyCache();
}

exit($failed ? 1 : 0);
