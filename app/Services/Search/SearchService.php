<?php

namespace App\Services\Search;

use App\Exceptions\BudgetExceeded;
use App\Exceptions\PlanLimitReached;
use App\Exceptions\PricesNotConfigured;
use App\Models\Product;
use App\Models\Search;
use App\Services\Ai\AiBudget;
use App\Services\Billing\PlanService;
use App\Services\Costs\CostEstimate;
use App\Services\Costs\CostEstimator;
use App\Services\Costs\PriceTable;
use Illuminate\Support\Collection;

/** Search screen logic: estimate first, then start after the user confirms (spec §3.2). */
class SearchService
{
    public function __construct(
        private CostEstimator $estimator,
        private SearchPipeline $pipeline,
        private AiBudget $budget,
        private PriceTable $prices,
        private PlanService $plans,
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

    /** Why a search cannot start right now, or null. */
    public function blocker(): ?string
    {
        if ($reason = $this->plans->accessBlocker()) {
            return $reason;
        }

        if ($this->plans->leadsRemaining() === 0) {
            return 'Kuota lead bulan ini dah habis. Naik taraf pelan atau tunggu bulan depan.';
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

    public function start(Product $product, string $businessType, array $areas, int $max): Search
    {
        if ($reason = $this->blocker()) {
            throw new PlanLimitReached($reason);
        }

        $max = min($max, $this->plans->maxCandidates());
        $estimate = $this->estimate($max, $areas);

        return $this->pipeline->start($product, $businessType, $areas, $max, $estimate->total());
    }

    /** @return Collection<int, Search> */
    public function recent(int $limit = 10): Collection
    {
        return Search::query()->with('product')->latest('id')->limit($limit)->get();
    }
}
