<?php

namespace App\Services\Costs;

use App\Exceptions\PricesNotConfigured;

/** Reads prices from config/ai_prices.php (or PRICE_TABLE_PATH). No prices in code. */
class PriceTable
{
    private array $table;

    public function __construct(?array $table = null)
    {
        $this->table = $table ?? $this->load();
    }

    private function load(): array
    {
        $path = (string) config('dynoleads.price_table_path', 'config/ai_prices.php');

        if ($path === '' || $path === 'config/ai_prices.php') {
            return (array) config('ai_prices', []);
        }

        $file = str_starts_with($path, '/') ? $path : base_path($path);

        return is_file($file) ? (array) require $file : (array) config('ai_prices', []);
    }

    public function usdToMyr(): ?float
    {
        $rate = $this->table['usd_to_myr'] ?? null;

        return is_numeric($rate) && $rate > 0 ? (float) $rate : null;
    }

    /** USD per million tokens, or null when Bob has not filled them in yet. */
    public function modelPrices(string $model): ?array
    {
        $prices = $this->table['models'][$model] ?? null;

        if (! is_array($prices)) {
            return null;
        }

        foreach (['input', 'output', 'cache_write', 'cache_read'] as $key) {
            if (! is_numeric($prices[$key] ?? null)) {
                return null;
            }
        }

        return array_map('floatval', $prices);
    }

    public function isModelConfigured(string $model): bool
    {
        return $this->modelPrices($model) !== null && $this->usdToMyr() !== null;
    }

    /** @throws PricesNotConfigured */
    public function aiCostMyr(string $model, int $input, int $output, int $cacheRead = 0, int $cacheWrite = 0): float
    {
        $prices = $this->modelPrices($model) ?? throw PricesNotConfigured::forModel($model);
        $rate = $this->usdToMyr() ?? throw PricesNotConfigured::forCurrency();

        $usd = ($input * $prices['input']
            + $output * $prices['output']
            + $cacheRead * $prices['cache_read']
            + $cacheWrite * $prices['cache_write']) / 1_000_000;

        return round($usd * $rate, 6);
    }

    /** Cost of one Places call in MYR; 0 until prices are filled in. */
    public function placesCostMyr(string $sku): float
    {
        $usd = $this->table['places'][$sku] ?? null;
        $rate = $this->usdToMyr();

        return is_numeric($usd) && $rate !== null ? round((float) $usd * $rate, 6) : 0.0;
    }

    public function arePlacesPricesConfigured(): bool
    {
        return $this->usdToMyr() !== null
            && is_numeric($this->table['places']['text_search'] ?? null)
            && is_numeric($this->table['places']['details'] ?? null);
    }
}
