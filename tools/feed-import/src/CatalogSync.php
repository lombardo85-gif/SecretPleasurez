<?php
/**
 * Writes normalized feed rows into the PrestaShop catalog.
 *
 * Two rules shape everything here:
 *  1. Upserts are keyed on the supplier SKU stored in product.reference, so a
 *     re-run of the same feed changes nothing.
 *  2. Products that disappear from a feed are DISABLED, never deleted. A
 *     supplier omitting a line is routine; destroying the product (with its
 *     URL, reviews and order history references) is not recoverable.
 */

declare(strict_types=1);

final class CatalogSync
{
    private PriceRules $pricing;

    /** @var array<string,mixed> */
    private array $config;

    private int $idLang;
    private int $idShop;
    private bool $dryRun;
    private ImportLogger $log;

    /** @var array<string,int> supplier SKU => product id, for discontinued detection */
    private array $seen = [];

    /** @var array<string,int> */
    private array $stats = [
        'created' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'skipped' => 0,
        'failed' => 0,
        'disabled' => 0,
    ];

    /** @param array<string,mixed> $config */
    public function __construct(array $config, PriceRules $pricing, ImportLogger $log, bool $dryRun)
    {
        $this->config = $config;
        $this->pricing = $pricing;
        $this->log = $log;
        $this->dryRun = $dryRun;
        $this->idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->idShop = (int) Context::getContext()->shop->id;
    }

    /** @return array<string,int> */
    public function stats(): array
    {
        return $this->stats;
    }

    /**
     * @param array<string,string> $row raw feed row keyed by source column
     */
    public function upsert(array $row): void
    {
        $map = $this->config['mapping'];
        $line = $row['__line'] ?? '?';

        $sku = $this->value($row, $map['sku'] ?? null);
        if ($sku === null || $sku === '') {
            ++$this->stats['skipped'];
            $this->log->warn(sprintf('line %s: no SKU in column "%s" — skipped', $line, $map['sku'] ?? ''));

            return;
        }

        // reference is varchar(64); a truncated key would collide silently.
        if (mb_strlen($sku) > 64) {
            ++$this->stats['skipped'];
            $this->log->warn(sprintf('line %s: SKU "%s" exceeds 64 chars — skipped', $line, $sku));

            return;
        }

        $costRaw = $this->value($row, $map['cost'] ?? null);
        $cost = $this->toFloat($costRaw);
        if ($cost === null || $cost <= 0.0) {
            ++$this->stats['skipped'];
            $this->log->warn(sprintf('line %s: SKU %s has unusable cost "%s" — skipped', $line, $sku, (string) $costRaw));

            return;
        }

        try {
            $map_price = $this->toFloat($this->value($row, $map['map_price'] ?? null));
            $retail = $this->pricing->retailFrom($cost, $map_price);
        } catch (Throwable $e) {
            ++$this->stats['failed'];
            $this->log->error(sprintf('line %s: SKU %s pricing failed: %s', $line, $sku, $e->getMessage()));

            return;
        }

        $qty = (int) ($this->toFloat($this->value($row, $map['quantity'] ?? null)) ?? 0);
        $name = $this->value($row, $map['name'] ?? null) ?? '';

        $idProduct = $this->findByReference($sku);

        if ($idProduct === null && $name === '') {
            ++$this->stats['skipped'];
            $this->log->warn(sprintf('line %s: SKU %s is new but has no name — skipped', $line, $sku));

            return;
        }

        if ($this->dryRun) {
            $this->seen[$sku] = $idProduct ?? 0;
            $verb = $idProduct === null ? 'CREATE' : 'UPDATE';
            ++$this->stats[$idProduct === null ? 'created' : 'updated'];
            $this->log->info(sprintf(
                '[dry-run] %-6s %-20s cost %8.2f -> price %8.2f  qty %5d  %s',
                $verb, $sku, $cost, $retail, $qty, mb_substr($name, 0, 48)
            ));

            return;
        }

        try {
            $product = $idProduct === null ? new Product() : new Product($idProduct, false, $this->idLang);
            $isNew = $idProduct === null;
            $changed = $isNew;

            if ($isNew) {
                $product->reference = $sku;
                $product->id_category_default = $this->resolveCategory($row);
                $product->id_tax_rules_group = (int) ($this->config['catalog']['id_tax_rules_group'] ?? 0);
                $product->active = (bool) ($this->config['catalog']['activate_new'] ?? false);
                $product->visibility = 'both';
                $product->minimal_quantity = 1;
                $product->link_rewrite = [$this->idLang => Tools::link_rewrite($name !== '' ? $name : $sku)];
            }

            if ($name !== '' && $this->assign($product, 'name', $name, true)) {
                $changed = true;
            }

            $desc = $this->value($row, $map['description'] ?? null);
            if ($desc !== null && $this->assign($product, 'description_short', $desc, true)) {
                $changed = true;
            }

            $ean = $this->value($row, $map['ean13'] ?? null);
            if ($ean !== null && preg_match('/^\d{8}$|^\d{13}$/', $ean) && $this->assign($product, 'ean13', $ean)) {
                $changed = true;
            }

            $weight = $this->toFloat($this->value($row, $map['weight'] ?? null));
            if ($weight !== null && $this->assign($product, 'weight', $weight)) {
                $changed = true;
            }

            if ($this->assign($product, 'wholesale_price', round($cost, 6))) {
                $changed = true;
            }
            if ($this->assign($product, 'price', round($retail, 6))) {
                $changed = true;
            }

            if (!$changed) {
                ++$this->stats['unchanged'];
                $this->seen[$sku] = (int) $product->id;
                $this->syncStock((int) $product->id, $qty);

                return;
            }

            $ok = $isNew ? $product->add() : $product->update();
            if (!$ok) {
                ++$this->stats['failed'];
                $this->log->error(sprintf('line %s: SKU %s could not be saved', $line, $sku));

                return;
            }

            if ($isNew) {
                $categoryId = (int) $product->id_category_default;
                if ($categoryId > 0) {
                    $product->addToCategories([$categoryId]);
                }
                ++$this->stats['created'];
            } else {
                ++$this->stats['updated'];
            }

            $this->seen[$sku] = (int) $product->id;
            $this->syncStock((int) $product->id, $qty);
        } catch (Throwable $e) {
            ++$this->stats['failed'];
            $this->log->error(sprintf('line %s: SKU %s threw: %s', $line, $sku, $e->getMessage()));
        }
    }

    /**
     * Disable products carrying this supplier's prefix that the feed no longer lists.
     *
     * Guarded by a ratio check: if a feed suddenly drops most of the catalog it
     * is far more likely to be truncated than for the supplier to have
     * discontinued everything, so we refuse and let a human look.
     */
    public function disableMissing(): void
    {
        if (!($this->config['catalog']['disable_missing'] ?? false)) {
            return;
        }

        $prefix = (string) ($this->config['catalog']['sku_prefix'] ?? '');
        $where = $prefix !== ''
            ? 'p.reference LIKE "' . pSQL($prefix) . '%"'
            : '1';

        $rows = Db::getInstance()->executeS(
            'SELECT p.id_product, p.reference FROM ' . _DB_PREFIX_ . 'product p WHERE p.active = 1 AND ' . $where
        ) ?: [];

        $missing = [];
        foreach ($rows as $r) {
            if (!isset($this->seen[$r['reference']])) {
                $missing[] = (int) $r['id_product'];
            }
        }

        if ($missing === []) {
            return;
        }

        $liveCount = count($rows);
        $ratio = $liveCount > 0 ? count($missing) / $liveCount : 0.0;
        $maxRatio = (float) ($this->config['catalog']['max_disable_ratio'] ?? 0.25);

        if ($ratio > $maxRatio) {
            $this->log->error(sprintf(
                'ABORTED discontinued sweep: feed omits %d of %d active products (%.0f%%, limit %.0f%%). '
                . 'This usually means a truncated feed, not %d discontinued items. Nothing was disabled.',
                count($missing), $liveCount, $ratio * 100, $maxRatio * 100, count($missing)
            ));

            return;
        }

        if ($this->dryRun) {
            $this->log->info(sprintf('[dry-run] would disable %d product(s) missing from feed', count($missing)));
            $this->stats['disabled'] = count($missing);

            return;
        }

        foreach (array_chunk($missing, 500) as $chunk) {
            Db::getInstance()->execute(
                'UPDATE ' . _DB_PREFIX_ . 'product SET active = 0 WHERE id_product IN (' . implode(',', $chunk) . ')'
            );
            Db::getInstance()->execute(
                'UPDATE ' . _DB_PREFIX_ . 'product_shop SET active = 0 WHERE id_product IN (' . implode(',', $chunk) . ')'
            );
        }

        $this->stats['disabled'] = count($missing);
        $this->log->info(sprintf('disabled %d product(s) no longer in the feed', count($missing)));
    }

    private function syncStock(int $idProduct, int $qty): void
    {
        if ($idProduct <= 0 || $this->dryRun) {
            return;
        }

        $buffer = (int) ($this->config['catalog']['stock_buffer'] ?? 0);
        $qty = max(0, $qty - $buffer);

        StockAvailable::setQuantity($idProduct, 0, $qty, $this->idShop);
    }

    private function findByReference(string $sku): ?int
    {
        $id = Db::getInstance()->getValue(
            'SELECT id_product FROM ' . _DB_PREFIX_ . 'product WHERE reference = "' . pSQL($sku) . '" LIMIT 1'
        );

        return $id ? (int) $id : null;
    }

    private function resolveCategory(array $row): int
    {
        $fallback = (int) ($this->config['catalog']['default_category_id'] ?? 2);
        $col = $this->config['mapping']['category'] ?? null;
        $name = $this->value($row, $col);

        if ($name === null || $name === '' || !($this->config['catalog']['create_categories'] ?? false)) {
            return $fallback;
        }

        $existing = Db::getInstance()->getValue(
            'SELECT c.id_category FROM ' . _DB_PREFIX_ . 'category c
             JOIN ' . _DB_PREFIX_ . 'category_lang cl ON cl.id_category = c.id_category
             WHERE cl.name = "' . pSQL($name) . '" AND cl.id_lang = ' . $this->idLang . ' LIMIT 1'
        );

        if ($existing) {
            return (int) $existing;
        }

        if ($this->dryRun) {
            return $fallback;
        }

        $category = new Category();
        $category->name = [$this->idLang => $name];
        $category->link_rewrite = [$this->idLang => Tools::link_rewrite($name)];
        $category->id_parent = $fallback;
        $category->active = true;

        return $category->add() ? (int) $category->id : $fallback;
    }

    /**
     * Assign a field only if it actually differs, so unchanged rows do not
     * bump date_upd and invalidate caches on every run.
     */
    private function assign(Product $product, string $field, $value, bool $multilang = false): bool
    {
        if ($multilang) {
            $current = is_array($product->{$field})
                ? ($product->{$field}[$this->idLang] ?? null)
                : $product->{$field};

            if ((string) $current === (string) $value) {
                return false;
            }

            $product->{$field} = is_array($product->{$field})
                ? array_replace($product->{$field}, [$this->idLang => $value])
                : [$this->idLang => $value];

            return true;
        }

        if (is_float($value)) {
            if (abs((float) $product->{$field} - $value) < 0.000001) {
                return false;
            }
        } elseif ((string) $product->{$field} === (string) $value) {
            return false;
        }

        $product->{$field} = $value;

        return true;
    }

    /** @param array<string,string> $row */
    private function value(array $row, ?string $column)
    {
        if ($column === null || $column === '') {
            return null;
        }

        return isset($row[$column]) ? trim((string) $row[$column]) : null;
    }

    /**
     * Parse a supplier price. Feeds arrive with currency symbols, thousands
     * separators and comma decimals; "1.234,56 EUR" must not become 1.234.
     */
    private function toFloat($raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $s = preg_replace('/[^\d,.\-]/', '', (string) $raw);
        if ($s === '' || $s === '-') {
            return null;
        }

        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Whichever separator is rightmost is the decimal point.
            if ($lastComma > $lastDot) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($lastComma !== false) {
            // A lone comma with exactly 3 trailing digits is a thousands separator.
            $s = (strlen($s) - $lastComma - 1) === 3 && substr_count($s, ',') === 1
                ? str_replace(',', '', $s)
                : str_replace(',', '.', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
