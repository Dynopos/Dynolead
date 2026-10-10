<?php

namespace App\Services\Search;

use App\Exceptions\AccountLimitReached;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\PricesNotConfigured;
use App\Models\Product;
use App\Models\Search;
use App\Services\Ai\AiBudget;
use App\Services\Billing\WalletService;
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
        private WalletService $wallet,
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
    public function blocker(int $candidates = 1, int $areas = 1): ?string
    {
        if ($reason = $this->wallet->accessBlocker()) {
            return $reason;
        }

        foreach ([config('dynoleads.ai.model_score'), config('dynoleads.ai.model_write')] as $model) {
            if (! $this->prices->isModelConfigured((string) $model)) {
                return auth()->user()?->isAdmin()
                    ? PricesNotConfigured::forModel((string) $model)->getMessage()
                    : 'Perkhidmatan AI belum sedia. Sila cuba sebentar lagi atau hubungi kami.';
            }
        }

        // A paid search bills Places cost too; without Places prices it would be billed at RM0.
        if (config('billing.include_places_cost', true)
            && ! $this->wallet->isUnlimited() && ! $this->wallet->inTrial()
            && ! $this->prices->arePlacesPricesConfigured()) {
            return auth()->user()?->isAdmin()
                ? 'Harga Google Places belum diisi dalam config/ai_prices.php (places.text_search, places.details, usd_to_myr).'
                : 'Perkhidmatan carian belum sedia. Sila cuba sebentar lagi atau hubungi kami.';
        }

        if ($reason = $this->wallet->searchBlocker($this->estimate($candidates, array_fill(0, max(1, $areas), ''))->total())) {
            return $reason;
        }

        if ($this->budget->isExhausted()) {
            return BudgetExceeded::MESSAGE;
        }

        return null;
    }

    /** Estimated customer charge in sen (0 in a trial or for the internal workspace). */
    public function chargeEstimateSen(int $max, int $areas = 1): int
    {
        if ($this->wallet->isUnlimited() || $this->wallet->inTrial()) {
            return 0;
        }

        return $this->wallet->priceSen($this->estimate($max, array_fill(0, max(1, $areas), ''))->total());
    }

    /**
     * Start the pipeline. Nothing is charged up front: a paid search is billed
     * for what it actually uses (WalletService::settle), and stops if the balance runs out.
     */
    public function start(Product $product, string $businessType, array $areas, int $max): Search
    {
        $max = SearchPipeline::clampMax($max);

        if ($reason = $this->blocker($max, count($areas))) {
            throw new AccountLimitReached($reason);
        }

        $estimate = $this->estimate($max, $areas);

        return $this->pipeline->start($product, $businessType, $areas, $max, $estimate->total(), $this->wallet->inTrial())->refresh();
    }

    /** @return Collection<int, Search> */
    public function recent(int $limit = 10): Collection
    {
        return Search::query()->with('product')->whereNull('hidden_at')->latest('id')->limit($limit)->get();
    }

    /**
     * Remove a finished search from the list. Only hidden, never deleted: costs, charges
     * and leads stay linked to it. An unfinished search must be cancelled first.
     */
    public function hide(Search $search): bool
    {
        if (! $search->status->isFinished()) {
            return false;
        }

        $search->forceFill(['hidden_at' => now()])->save();

        return true;
    }
}
