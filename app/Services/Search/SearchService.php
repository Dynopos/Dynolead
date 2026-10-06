<?php

namespace App\Services\Search;

use App\Exceptions\AccountLimitReached;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\PricesNotConfigured;
use App\Models\Product;
use App\Models\Search;
use App\Services\Ai\AiBudget;
use App\Services\Billing\CreditService;
use App\Services\Costs\CostEstimate;
use App\Services\Costs\CostEstimator;
use App\Services\Costs\PriceTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Search screen logic: estimate first, then start after the user confirms (spec §3.2). */
class SearchService
{
    public function __construct(
        private CostEstimator $estimator,
        private SearchPipeline $pipeline,
        private AiBudget $budget,
        private PriceTable $prices,
        private CreditService $credits,
    ) {}

    /** One area per line (or separated by ";"). Commas stay: "Pasir Mas, Kelantan" is one area. */
    public static function parseAreas(string $text): array
    {
        return collect(preg_split('/[\n;]+/', $text))
            ->map(fn ($v) => trim($v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    public function estimate(int $max, array $areas): CostEstimate
    {
        return $this->estimator->forSearch(SearchPipeline::clampMax($max), max(1, count($areas)));
    }

    /** Why a search of $candidates cannot start right now, or null. */
    public function blocker(int $candidates = 1): ?string
    {
        if ($reason = $this->credits->searchBlocker($candidates)) {
            return $reason;
        }

        foreach ([config('dynoleads.ai.model_score'), config('dynoleads.ai.model_write')] as $model) {
            if (! $this->prices->isModelConfigured((string) $model)) {
                return auth()->user()?->isAdmin()
                    ? PricesNotConfigured::forModel((string) $model)->getMessage()
                    : 'Perkhidmatan AI belum sedia. Sila cuba sebentar lagi atau hubungi kami.';
            }
        }

        if ($this->budget->isExhausted()) {
            return BudgetExceeded::MESSAGE;
        }

        return null;
    }

    /** Credits for a search of $max candidates (0 for the internal workspace). */
    public function creditCost(int $max): int
    {
        return $this->credits->isUnlimited() ? 0 : $this->credits->costFor(SearchPipeline::clampMax($max));
    }

    /**
     * Charge credits, then start the pipeline. Credits come back automatically
     * if the search ends with no leads (CreditService::settleSearch).
     */
    public function start(Product $product, string $businessType, array $areas, int $max): Search
    {
        $max = SearchPipeline::clampMax($max);

        if ($reason = $this->blocker($max)) {
            throw new AccountLimitReached($reason);
        }

        $workspace = $this->credits->workspace();
        $estimate = $this->estimate($max, $areas);

        return DB::transaction(function () use ($workspace, $product, $businessType, $areas, $max, $estimate) {
            $charged = $this->credits->chargeForSearch($workspace, $max, auth()->id());

            $search = $this->pipeline->start($product, $businessType, $areas, $max, $estimate->total(), $charged);
            $this->credits->attachSearch($workspace, $search);

            return $search->refresh();
        });
    }

    /** @return Collection<int, Search> */
    public function recent(int $limit = 10): Collection
    {
        return Search::query()->with('product')->latest('id')->limit($limit)->get();
    }
}
