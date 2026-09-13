<?php
/**
 * Homepage offers carousel for Secret Pleasurez.
 *
 * Slides are stored as JSON in SPZ_PROMO_SLIDES. Each names its products by
 * id; names, photos and prices (including any sale price) are read live, so a
 * slide never shows a stale price. Products that are inactive, out of stock
 * or have no photo are skipped. With no slides configured nothing renders.
 *
 * Slide fields: eyebrow, title, text, code, cta_label, link, theme, ends,
 * products. link is "category:<id>", "page:<controller>" or a relative path;
 * theme is pink, cyan, champagne or duo; ends is Y-m-d.
 *
 * tools/dev/sample-promotions.php writes a sample set.
 *
 * It also renders "Shop by brand" near the foot of the homepage: the brands
 * with the most live products, as text links. The feed carries no brand
 * logos, and a strip of placeholder images would suggest partnerships that do
 * not exist.
 */

use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SpzPromos extends Module
{
    public const CONFIG_SLIDES = 'SPZ_PROMO_SLIDES';

    /**
     * Kept in one list so tools/dev/install-custom-modules.php can register a
     * newly added hook without a reinstall, which would delete the slides.
     */
    public const HOOKS = ['displayTopColumn', 'displayHomeBottom', 'actionFrontControllerSetMedia'];

    private const THEMES = ['pink', 'cyan', 'champagne', 'duo'];
    private const PRODUCTS_PER_SLIDE = 3;

    /** How many brands "Shop by brand" lists, and the fewest worth a section. */
    private const BRAND_LINKS = 18;
    private const BRAND_LINKS_MIN = 6;

    /** Category shortcuts under the carousel: the largest live categories. */
    private const CHIPS = [
        31 => 'Vibrators',
        35 => 'Stimulators',
        23 => 'Dildos',
        38 => 'Anal',
        36 => 'Lingerie',
        15 => 'Bondage',
        25 => 'Lubricants',
        40 => 'Masturbators',
    ];

    public function __construct()
    {
        $this->name = 'spzpromos';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Secret Pleasurez';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = 'Homepage offers carousel';
        $this->description = 'Promotional slides with live product prices above the homepage products, and a Shop by brand list below them.';
    }

    public function install()
    {
        return parent::install() && $this->registerHook(self::HOOKS);
    }

    public function uninstall()
    {
        return Configuration::deleteByName(self::CONFIG_SLIDES) && parent::uninstall();
    }

    public function hookActionFrontControllerSetMedia()
    {
        if (($this->context->controller->php_self ?? '') !== 'index') {
            return;
        }
        // After the theme's spz-modern layer (1010), whose button styles the
        // slides reuse.
        $this->context->controller->registerStylesheet(
            'module-spzpromos',
            'modules/' . $this->name . '/views/css/spzpromos.css',
            ['media' => 'all', 'priority' => 1020]
        );
        $this->context->controller->registerJavascript(
            'module-spzpromos',
            'modules/' . $this->name . '/views/js/spzpromos.js',
            ['position' => 'bottom', 'priority' => 1020]
        );
    }

    public function hookDisplayTopColumn()
    {
        $slides = $this->getSlides();
        if ($slides === []) {
            return '';
        }

        $chips = [];
        foreach (self::CHIPS as $idCategory => $label) {
            $chips[] = ['label' => $label, 'url' => $this->context->link->getCategoryLink($idCategory)];
        }

        $this->context->smarty->assign([
            'spz_slides' => $slides,
            'spz_chips' => $chips,
        ]);

        return $this->fetch('module:spzpromos/views/templates/hook/spzpromos.tpl');
    }

    public function hookDisplayHomeBottom()
    {
        $brands = $this->getTopBrands();
        if (count($brands) < self::BRAND_LINKS_MIN) {
            return '';
        }

        $this->context->smarty->assign([
            'spz_brands' => $brands,
            'spz_brands_url' => $this->context->link->getPageLink('manufacturer'),
        ]);

        return $this->fetch('module:spzpromos/views/templates/hook/spzbrands.tpl');
    }

    /**
     * The brands with the most products a shopper can actually open, listed
     * alphabetically so the list reads as a directory rather than a ranking.
     */
    private function getTopBrands(): array
    {
        $p = _DB_PREFIX_;
        $idShop = (int) $this->context->shop->id;

        $rows = Db::getInstance()->executeS(
            "SELECT m.id_manufacturer, m.name, COUNT(*) AS products
             FROM {$p}manufacturer m
             JOIN {$p}product pr ON pr.id_manufacturer = m.id_manufacturer
             JOIN {$p}product_shop ps ON ps.id_product = pr.id_product AND ps.id_shop = {$idShop}
             WHERE m.active = 1 AND ps.active = 1 AND ps.visibility IN ('both', 'catalog')
             GROUP BY m.id_manufacturer, m.name
             ORDER BY products DESC, m.name
             LIMIT " . self::BRAND_LINKS
        ) ?: [];

        usort($rows, static fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        $brands = [];
        foreach ($rows as $row) {
            $brands[] = [
                'name' => $row['name'],
                'products' => (int) $row['products'],
                'url' => $this->context->link->getManufacturerLink(
                    (int) $row['id_manufacturer'],
                    Tools::link_rewrite($row['name'])
                ),
            ];
        }

        return $brands;
    }

    private function getSlides(): array
    {
        $raw = json_decode((string) Configuration::get(self::CONFIG_SLIDES), true);
        if (!is_array($raw)) {
            return [];
        }

        $slides = [];
        foreach ($raw as $slide) {
            if (!is_array($slide) || empty($slide['title'])) {
                continue;
            }
            $ends = !empty($slide['ends']) ? strtotime((string) $slide['ends']) : false;
            if ($ends !== false && $ends < strtotime('today')) {
                continue; // the offer is over: do not advertise it
            }
            $slides[] = [
                'eyebrow' => (string) ($slide['eyebrow'] ?? ''),
                'title' => (string) $slide['title'],
                'text' => (string) ($slide['text'] ?? ''),
                'code' => (string) ($slide['code'] ?? ''),
                'cta_label' => (string) ($slide['cta_label'] ?? 'Shop now'),
                'cta_url' => $this->resolveLink((string) ($slide['link'] ?? '')),
                'theme' => in_array($slide['theme'] ?? '', self::THEMES, true) ? $slide['theme'] : 'pink',
                'ends' => $ends !== false ? date('M j', $ends) : '',
                'products' => $this->presentProducts((array) ($slide['products'] ?? [])),
            ];
        }

        return $slides;
    }

    private function resolveLink(string $link): string
    {
        $link = trim($link);
        if (strpos($link, 'category:') === 0) {
            return $this->context->link->getCategoryLink((int) substr($link, 9));
        }
        if (strpos($link, 'page:') === 0) {
            return $this->context->link->getPageLink(substr($link, 5));
        }
        // Relative paths only: slide data must not be able to send shoppers
        // to another site.
        if ($link !== '' && $link[0] === '/' && (strlen($link) < 2 || $link[1] !== '/')) {
            return rtrim($this->context->shop->getBaseURL(true), '/') . $link;
        }

        return $this->context->link->getPageLink('index');
    }

    private function presentProducts(array $ids): array
    {
        $idLang = (int) $this->context->language->id;
        $assembler = new ProductAssembler($this->context);
        $settings = (new ProductPresenterFactory($this->context))->getPresentationSettings();
        $presenter = new ProductListingPresenter(
            new ImageRetriever($this->context->link),
            $this->context->link,
            new PriceFormatter(),
            new ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        $products = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $product = new Product($id, false, $idLang);
            if (!Validate::isLoadedObject($product)
                || !$product->active
                || !in_array($product->visibility, ['both', 'catalog'], true)
                || StockAvailable::getQuantityAvailableByProduct($id) <= 0
                || !Product::getCover($id)
            ) {
                continue;
            }
            $products[] = $presenter->present(
                $settings,
                $assembler->assembleProduct(['id_product' => $id]),
                $this->context->language
            );
            if (count($products) === self::PRODUCTS_PER_SLIDE) {
                break;
            }
        }

        return $products;
    }
}
