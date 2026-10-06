<?php

namespace App\Services\Billing;

/** One entry of config/plans.php. */
final class Plan
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly ?float $priceMyr,
        public readonly bool $purchasable,
        public readonly ?int $monthlyLeads,
        public readonly ?float $aiBudgetMyr,
        public readonly ?int $maxProducts,
        public readonly int $maxCandidates,
        public readonly array $features,
    ) {}

    public static function fromConfig(string $key, array $c): self
    {
        return new self(
            key: $key,
            name: (string) ($c['name'] ?? $key),
            priceMyr: is_numeric($c['price_myr'] ?? null) ? (float) $c['price_myr'] : null,
            purchasable: (bool) ($c['purchasable'] ?? false),
            monthlyLeads: isset($c['monthly_leads']) ? (int) $c['monthly_leads'] : null,
            aiBudgetMyr: is_numeric($c['ai_budget_myr'] ?? null) ? (float) $c['ai_budget_myr'] : null,
            maxProducts: isset($c['max_products']) ? (int) $c['max_products'] : null,
            maxCandidates: min((int) config('dynoleads.search.hard_max', 60), (int) ($c['max_candidates'] ?? 20)),
            features: (array) ($c['features'] ?? []),
        );
    }

    /** Can a customer pay for this plan right now? */
    public function isForSale(): bool
    {
        return $this->purchasable && $this->priceMyr !== null && $this->priceMyr > 0;
    }

    /** Price in sen for CHIP. */
    public function priceSen(): int
    {
        return (int) round(($this->priceMyr ?? 0) * 100);
    }
}
