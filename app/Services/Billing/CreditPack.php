<?php

namespace App\Services\Billing;

/** One entry of config/credits.php packs. */
final class CreditPack
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly int $credits,
        public readonly ?float $priceMyr,
        public readonly bool $popular,
    ) {}

    public static function fromConfig(string $key, array $c): self
    {
        return new self(
            key: $key,
            name: (string) ($c['name'] ?? $key),
            credits: (int) ($c['credits'] ?? 0),
            priceMyr: is_numeric($c['price_myr'] ?? null) ? (float) $c['price_myr'] : null,
            popular: (bool) ($c['popular'] ?? false),
        );
    }

    public function isForSale(): bool
    {
        return $this->credits > 0 && $this->priceMyr !== null && $this->priceMyr > 0;
    }

    public function priceSen(): int
    {
        return (int) round(($this->priceMyr ?? 0) * 100);
    }

    public function pricePerCredit(): ?float
    {
        return $this->isForSale() ? $this->priceMyr / $this->credits : null;
    }
}
