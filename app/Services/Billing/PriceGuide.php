<?php

namespace App\Services\Billing;

use App\Services\Costs\CostEstimate;
use App\Services\Costs\PriceTable;

/**
 * Public price guide: an estimated customer price per lead, and how many leads a
 * top-up buys. Built from the price table and the default rates only (no customer
 * data), so the sales page shows the same figure to everyone.
 */
class PriceGuide
{
    /** Leads per search used for the estimate (one Text Search page). */
    private const CANDIDATES = 20;

    public function __construct(private PriceTable $prices, private WalletService $wallet) {}

    /** @return array{per_lead_sen: int, topups: array<int, int>}|null  null until prices are filled in */
    public function get(): ?array
    {
        $estimate = $this->estimate();

        if ($estimate === null || $estimate->fit() <= 0) {
            return null;
        }

        // Customer price per lead, rounded up to the sen.
        $perLeadSen = (int) ceil($this->wallet->priceSen($estimate->total()) / $estimate->fit());

        $topups = [];
        foreach ((array) config('billing.topup_options') as $myr) {
            // Rounded down to a multiple of 5 so the example never over-promises.
            $topups[(int) $myr] = (int) (floor((int) $myr * 100 / $perLeadSen / 5) * 5);
        }

        return ['per_lead_sen' => $perLeadSen, 'topups' => $topups];
    }

    private function estimate(): ?CostEstimate
    {
        $defaults = config('dynoleads.estimate_defaults');
        $scoreModel = (string) config('dynoleads.ai.model_score');
        $writeModel = (string) config('dynoleads.ai.model_write');

        if (! $this->prices->isModelConfigured($scoreModel) || ! $this->prices->isModelConfigured($writeModel)) {
            return null;
        }

        $includePlaces = (bool) config('billing.include_places_cost', true);
        if ($includePlaces && ! $this->prices->arePlacesPricesConfigured()) {
            return null;
        }

        $passRate = (float) $defaults['pass_rate'];
        $detailsCalls = (int) ceil(self::CANDIDATES * $passRate);
        $placesCost = $includePlaces
            ? $this->prices->placesCostMyr('text_search') + $detailsCalls * $this->prices->placesCostMyr('details')
            : 0.0;

        return new CostEstimate(
            candidates: self::CANDIDATES,
            passRate: $passRate,
            fitRate: (float) $defaults['fit_rate'],
            scoreCost: $this->prices->aiCostMyr($scoreModel, $defaults['score_input_tokens'], $defaults['score_output_tokens']),
            writeCost: $this->prices->aiCostMyr($writeModel, $defaults['write_input_tokens'], $defaults['write_output_tokens']),
            placesCost: $placesCost,
            textSearchCalls: 1,
            detailsCalls: $detailsCalls,
            aiPricesConfigured: true,
            placesPricesConfigured: $this->prices->arePlacesPricesConfigured(),
            usedDefaults: true,
        );
    }
}
