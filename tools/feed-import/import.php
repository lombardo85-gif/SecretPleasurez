<?php
/**
 * Supplier feed importer — CLI entry point.
 *
 * Reads a wholesaler CSV/XML feed and syncs it into the PrestaShop catalog:
 * creates new products, updates prices and stock on existing ones, and
 * disables anything the feed has dropped.
 *
 * Usage (inside the container):
 *   php import.php --config=config/acme.json --dry-run
 *   php import.php --config=config/acme.json
 *
 * Options:
 *   --config=PATH   job config (required)
 *   --dry-run       report what would change, write nothing
 *   --limit=N       stop after N rows (for testing a new feed)
 *   --quiet         only warnings and errors on stdout
 *   --no-disable    skip the discontinued sweep this run
 *
 * Exit codes: 0 ok, 1 rows failed, 2 fatal (config/feed unreadable).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

$root = getenv('PS_ROOT_DIR') ?: '/var/www/html';

require_once __DIR__ . '/src/FeedReader.php';
require_once __DIR__ . '/src/PriceRules.php';
require_once __DIR__ . '/src/ImportLogger.php';
require_once __DIR__ . '/src/BrandResolver.php';
require_once __DIR__ . '/src/CatalogSync.php';

$opts = parseArgs($argv);

if (isset($opts['help'])) {
    echo file_get_contents(__FILE__, false, null, 0, 1200);
    exit(0);
}

if (!isset($opts['config'])) {
    fwrite(STDERR, "error: --config=PATH is required\n");
    exit(2);
}

$configPath = $opts['config'];
if (!is_file($configPath)) {
    $relative = __DIR__ . '/' . ltrim($configPath, '/');
    if (!is_file($relative)) {
        fwrite(STDERR, sprintf("error: config not found: %s\n", $configPath));
        exit(2);
    }
    $configPath = $relative;
}

$jobConfig = json_decode((string) file_get_contents($configPath), true);
if (!is_array($jobConfig)) {
    fwrite(STDERR, sprintf("error: config is not valid JSON: %s (%s)\n", $configPath, json_last_error_msg()));
    exit(2);
}

foreach (['feed', 'mapping', 'pricing'] as $section) {
    if (!isset($jobConfig[$section]) || !is_array($jobConfig[$section])) {
        fwrite(STDERR, sprintf("error: config is missing the \"%s\" section\n", $section));
        exit(2);
    }
}

$dryRun = isset($opts['dry-run']);
$limit = isset($opts['limit']) ? max(0, (int) $opts['limit']) : 0;

// Bootstrap PrestaShop. The Symfony kernel is required because StockAvailable
// and Product writes dispatch hooks that reach into the container.
$configFile = rtrim($root, '/') . '/config/config.inc.php';
if (!is_file($configFile)) {
    fwrite(STDERR, sprintf("error: PrestaShop not found at %s (set PS_ROOT_DIR)\n", $root));
    exit(2);
}

// NOTE: PrestaShop's bootstrap defines its own global $config, so the job
// config is held in $jobConfig. Naming it $config here silently nulls it.
require_once $configFile;

$kernel = new AppKernel('prod', false);
$kernel->boot();

// StockAvailable::setQuantity() records a stock movement, and ps_stock_mvt
// requires a non-null id_employee. A CLI process has no logged-in employee, so
// without this every stock write dies on an integrity constraint.
$idEmployee = (int) Db::getInstance()->getValue(
    'SELECT id_employee FROM ' . _DB_PREFIX_ . 'employee WHERE active = 1 ORDER BY id_employee'
);
if ($idEmployee > 0) {
    Context::getContext()->employee = new Employee($idEmployee);
} else {
    fwrite(STDERR, "warning: no active employee found; stock movements may fail\n");
}

// Long feeds outlive the default limits.
set_time_limit(0);
ini_set('memory_limit', $jobConfig['runtime']['memory_limit'] ?? '512M');

$logger = new ImportLogger(
    $jobConfig['runtime']['log_dir'] ?? __DIR__ . '/var/log',
    isset($opts['quiet'])
);

$started = microtime(true);
$logger->info(sprintf(
    'start job=%s source=%s%s',
    basename($configPath),
    $jobConfig['feed']['source'] ?? '?',
    $dryRun ? ' MODE=dry-run' : ''
));

try {
    $reader = new FeedReader($jobConfig['feed']);
    $pricing = new PriceRules($jobConfig['pricing']);
    $sync = new CatalogSync($jobConfig, $pricing, $logger, $dryRun);

    $rows = 0;
    foreach ($reader->rows() as $row) {
        $sync->upsert($row);
        ++$rows;

        if ($limit > 0 && $rows >= $limit) {
            $logger->info(sprintf('stopping at --limit=%d', $limit));
            break;
        }

        if ($rows % 500 === 0) {
            $logger->info(sprintf('... %d rows', $rows));
        }
    }

    if ($rows === 0) {
        // An empty feed with the discontinued sweep armed would disable the
        // whole catalog. Treat it as a supplier-side failure, not a signal.
        $logger->error('feed contained no rows — aborting before the discontinued sweep');
        exit(2);
    }

    if (!isset($opts['no-disable']) && $limit === 0) {
        $sync->disableMissing();
    } elseif ($limit > 0) {
        $logger->info('skipping discontinued sweep (--limit makes the feed a partial view)');
    }

    $stats = $sync->stats();
    $logger->summary(sprintf(
        'done rows=%d created=%d updated=%d unchanged=%d skipped=%d failed=%d disabled=%d in %.1fs',
        $rows,
        $stats['created'], $stats['updated'], $stats['unchanged'],
        $stats['skipped'], $stats['failed'], $stats['disabled'],
        microtime(true) - $started
    ));

    exit($stats['failed'] > 0 ? 1 : 0);
} catch (Throwable $e) {
    $logger->error(sprintf('FATAL %s: %s', get_class($e), $e->getMessage()));
    $logger->error($e->getTraceAsString());
    exit(2);
}

/**
 * @param string[] $argv
 *
 * @return array<string,string>
 */
function parseArgs(array $argv): array
{
    $out = [];

    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }

        $arg = substr($arg, 2);
        $eq = strpos($arg, '=');

        if ($eq === false) {
            $out[$arg] = '1';
            continue;
        }

        $out[substr($arg, 0, $eq)] = substr($arg, $eq + 1);
    }

    return $out;
}
