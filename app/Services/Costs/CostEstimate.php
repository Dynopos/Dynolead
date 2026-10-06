<?php

namespace App\Services\Costs;

final class CostEstimate
{
    public function __construct(
        public readonly int $candidates,
        public readonly float $passRate,
        public readonly float $fitRate,
        public readonly float $scoreCost,
        public readonly float $writeCost,
        public readonly float $placesCost,
        public readonly int $textSearchCalls,
        public readonly int $detailsCalls,
        public readonly bool $aiPricesConfigured,
        public readonly bool $placesPricesConfigured,
        public readonly bool $usedDefaults,
    ) {}

    public function passed(): float
    {
        return $this->candidates * $this->passRate;
    }

    public function fit(): float
    {
        return $this->passed() * $this->fitRate;
    }

    public function aiTotal(): float
    {
        return round($this->passed() * $this->scoreCost + $this->fit() * $this->writeCost, 4);
    }

    /** Spec §9.3: (calon × lulus × kos_nilai) + (calon_sesuai × kos_tulis) + kos_places */
    public function total(): float
    {
        return round($this->aiTotal() + $this->placesCost, 4);
    }
}
