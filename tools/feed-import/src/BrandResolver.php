<?php
/**
 * Turns a feed's brand text into a PrestaShop manufacturer id, creating the
 * manufacturer the first time a brand is seen.
 *
 * Brands are matched on a folded key (case, punctuation and spacing ignored),
 * so "OMG!" and "OMG" share one brand page instead of splitting its products.
 * Placeholder values such as "No Brand" resolve to 0: an unbranded product is
 * shown without a brand rather than under an invented one.
 */

declare(strict_types=1);

final class BrandResolver
{
    /** @var array<string,int> folded key => id_manufacturer */
    private array $ids = [];

    /** @var array<string,true> folded placeholder values */
    private array $ignored = [];

    private bool $dryRun;

    /** @param string[] $ignore brand values that mean "no brand" */
    public function __construct(array $ignore, bool $dryRun)
    {
        $this->dryRun = $dryRun;
        foreach ($ignore as $value) {
            $this->ignored[self::key(self::clean((string) $value))] = true;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT id_manufacturer, name FROM ' . _DB_PREFIX_ . 'manufacturer ORDER BY id_manufacturer'
        ) ?: [];
        foreach ($rows as $row) {
            $key = self::key((string) $row['name']);
            if ($key !== '' && !isset($this->ids[$key])) {
                $this->ids[$key] = (int) $row['id_manufacturer'];
            }
        }
    }

    /**
     * Manufacturer id for a brand, or 0 when the value is empty or a placeholder.
     * In dry-run mode an unknown brand also resolves to 0 and nothing is written.
     */
    public function resolve(?string $raw): int
    {
        $name = self::clean((string) $raw);
        $key = self::key($name);
        if ($key === '' || isset($this->ignored[$key])) {
            return 0;
        }
        if (isset($this->ids[$key])) {
            return $this->ids[$key];
        }
        if ($this->dryRun) {
            return 0;
        }

        $manufacturer = new Manufacturer();
        $manufacturer->name = $name;
        $manufacturer->active = true;
        if (!$manufacturer->add()) {
            throw new RuntimeException(sprintf('brand "%s" could not be created', $name));
        }

        return $this->ids[$key] = (int) $manufacturer->id;
    }

    /** Whether a brand already exists, or needs no manufacturer at all. */
    public function exists(?string $raw): bool
    {
        $key = self::key(self::clean((string) $raw));

        return $key === '' || isset($this->ignored[$key]) || isset($this->ids[$key]);
    }

    /**
     * Make feed brand text displayable and valid for Manufacturer::$name.
     *
     * The 2022 feed stores apostrophes as "and#039;" (its & was spelled out),
     * so "Buck'd" arrives as "Buckand#039;d". Validate::isCatalogName() rejects
     * < > ; = # { }, and the column is varchar(64).
     */
    public static function clean(string $name): string
    {
        $name = preg_replace('/and(#\d+;)/', '&$1', $name) ?? $name;
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = str_replace(['<', '>', ';', '=', '#', '{', '}'], ' ', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return mb_substr($name, 0, 64);
    }

    public static function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($name)) ?? '';
    }
}
