<?php

namespace App\Services\Costs;

use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\Search;

/**
 * Cost estimate shown before a search runs (spec §9.3).
 * Rates and average costs come from the last 30 days, with defaults when empty.
 */
class CostEstimator
{
    public const MIN_CANDIDATES = 20;

    public const MIN_SCORED = 10;

    public function __construct(private PriceTable $prices) {}

    public function forSearch(int $candidates, int $areas = 1): CostEstimate
    {
        $since = now()->subDays(30);
        $defaults = config('dynoleads.estimate_defaults');
        $usedDefaults = false;

        $found = (int) Search::query()->where('created_at', '>=', $since)->sum('found_count');
        $leads = (int) Search::query()->where('created_at', '>=', $since)->sum('lead_count');
        // Small samples are noisy: use real rates only with enough data.
        if ($found >= self::MIN_CANDIDATES) {
            $passRate = min(1, $leads / $found);
        } else {
            $passRate = (float) $defaults['pass_rate'];
            $usedDefaults = true;
        }

        $scored = Lead::query()->where('created_at', '>=', $since)->whereNotNull('fit')->count();
        if ($scored >= self::MIN_SCORED) {
            $fitRate = Lead::query()->where('created_at', '>=', $since)->where('fit', '>=', (int) config('dynoleads.ai.fit_threshold'))->count() / $scored;
        } else {
            $fitRate = (float) $defaults['fit_rate'];
            $usedDefaults = true;
        }

        $scoreModel = (string) config('dynoleads.ai.model_score');
        $writeModel = (string) config('dynoleads.ai.model_write');
        $aiConfigured = $this->prices->isModelConfigured($scoreModel) && $this->prices->isModelConfigured($writeModel);

        $scoreCost = $this->averagePerLead('score', $since);
        $writeCost = $this->averagePerLead('write', $since);

        if ($scoreCost === null || $writeCost === null) {
            $usedDefaults = true;
        }

        if ($aiConfigured) {
            $scoreCost ??= $this->prices->aiCostMyr($scoreModel, $defaults['score_input_tokens'], $defaults['score_output_tokens']);
            $writeCost ??= $this->prices->aiCostMyr($writeModel, $defaults['write_input_tokens'], $defaults['write_output_tokens']);
        }

        $textSearchCalls = $areas * min(3, (int) ceil($candidates / 20));
        $detailsCalls = (int) ceil($candidates * $passRate);
        $placesCost = $textSearchCalls * $this->prices->placesCostMyr('text_search')
            + $detailsCalls * $this->prices->placesCostMyr('details');

        return new CostEstimate(
            candidates: $candidates,
            passRate: round($passRate, 3),
            fitRate: round($fitRate, 3),
            scoreCost: round((float) $scoreCost, 6),
            writeCost: round((float) $writeCost, 6),
            placesCost: round($placesCost, 4),
            textSearchCalls: $textSearchCalls,
            detailsCalls: $detailsCalls,
            aiPricesConfigured: $aiConfigured,
            placesPricesConfigured: $this->prices->arePlacesPricesConfigured(),
            usedDefaults: $usedDefaults,
        );
    }

    /** Average cost per lead for a purpose (includes retries), or null with no data. */
    private function averagePerLead(string $purpose, $since): ?float
    {
        $row = AiUsage::query()
            ->where('purpose', $purpose)
            ->where('created_at', '>=', $since)
            ->whereNotNull('lead_id')
            ->selectRaw('SUM(cost_estimate) as total, COUNT(DISTINCT lead_id) as leads')
            ->first();

        return $row && (int) $row->leads > 0 ? (float) $row->total / (int) $row->leads : null;
    }
}
