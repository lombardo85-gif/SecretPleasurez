<?php
/**
 * Turns a supplier cost into a retail price.
 *
 * Dropship margins are the whole business, so this is deliberately explicit:
 * tiered markup by cost band, an optional MAP floor the markup may never
 * undercut, and psychological rounding applied last.
 */

declare(strict_types=1);

final class PriceRules
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config the "pricing" block of the job config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param float      $cost supplier cost, tax excluded
     * @param float|null $map  minimum advertised price, if the supplier sets one
     *
     * @return float retail price, tax excluded
     */
    public function retailFrom(float $cost, ?float $map = null): float
    {
        if ($cost <= 0.0) {
            throw new InvalidArgumentException(sprintf('Cost must be positive, got %.4f', $cost));
        }

        $price = $cost * (1.0 + ($this->markupFor($cost) / 100.0));
        $price += (float) ($this->config['handling_fee'] ?? 0.0);

        $minMargin = (float) ($this->config['min_margin_amount'] ?? 0.0);
        if ($minMargin > 0.0 && ($price - $cost) < $minMargin) {
            $price = $cost + $minMargin;
        }

        // MAP is a contractual floor: it wins over every rule above it.
        if ($map !== null && $map > 0.0 && $price < $map) {
            return $this->round($map);
        }

        $rounded = $this->round($price);

        // Rounding down must not push us back under MAP.
        if ($map !== null && $map > 0.0 && $rounded < $map) {
            return $map;
        }

        return $rounded;
    }

    /**
     * Markup percentage for a cost, from the first matching tier.
     * Tiers are [max_cost, markup_percent]; the last tier may use null as "and above".
     */
    private function markupFor(float $cost): float
    {
        $tiers = $this->config['tiers'] ?? [];

        foreach ($tiers as $tier) {
            $upTo = $tier['up_to'] ?? null;
            if ($upTo === null || $cost <= (float) $upTo) {
                return (float) $tier['markup_percent'];
            }
        }

        if (!isset($this->config['default_markup_percent'])) {
            throw new RuntimeException(sprintf(
                'No pricing tier matched cost %.2f and pricing.default_markup_percent is not set.',
                $cost
            ));
        }

        return (float) $this->config['default_markup_percent'];
    }

    /**
     * Apply the configured rounding style.
     */
    private function round(float $price): float
    {
        $mode = (string) ($this->config['rounding'] ?? 'none');

        switch ($mode) {
            case 'none':
                return round($price, 2);

            case 'charm':
                // 24.31 -> 24.99, 24.99 -> 24.99
                return max(0.99, floor($price) + 0.99);

            case 'charm_95':
                return max(0.95, floor($price) + 0.95);

            case 'nearest_50':
                return max(0.5, round($price * 2.0) / 2.0);

            case 'whole':
                return max(1.0, ceil($price));

            default:
                throw new RuntimeException(sprintf('Unknown pricing.rounding mode "%s".', $mode));
        }
    }
}
