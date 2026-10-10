<?php

namespace App\Livewire;

use App\Exceptions\BudgetExceeded;
use App\Models\Product;
use App\Models\Search;
use App\Services\Billing\WalletService;
use App\Services\Search\SearchPipeline;
use App\Services\Search\SearchService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Cari')]
class SearchPage extends Component
{
    public ?int $product_id = null;

    public string $business_type = '';

    public string $areas = '';

    public int $max_candidates = 20;

    /** Estimate shown before running; null until "Kira anggaran". */
    public ?array $estimate = null;

    public ?int $lastSearchId = null;

    public function mount(): void
    {
        $this->product_id = Product::query()->where('active', true)->orderBy('id')->value('id');
        $this->max_candidates = (int) config('dynoleads.search.default_max', 20);
    }

    protected function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'business_type' => 'required|string|max:80',
            'areas' => 'required|string|max:300',
            'max_candidates' => 'required|integer|min:1|max:'.config('dynoleads.search.hard_max', 60),
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => 'Medan ini wajib diisi.',
            'max_candidates.max' => 'Had maksimum :max calon setiap carian.',
            'max_candidates.min' => 'Sekurang-kurangnya 1 calon.',
        ];
    }

    public function updated(string $field): void
    {
        // Any change invalidates the estimate the user saw.
        if (in_array($field, ['product_id', 'business_type', 'areas', 'max_candidates'], true)) {
            $this->estimate = null;
        }
    }

    public function pickType(string $type): void
    {
        $this->business_type = $type;
        $this->estimate = null;
    }

    public function calculate(SearchService $service): void
    {
        $this->validate();
        $areas = SearchService::parseAreas($this->areas);

        $e = $service->estimate($this->max_candidates, $areas);

        $this->estimate = [
            'candidates' => $e->candidates,
            'passed' => round($e->passed(), 1),
            'fit' => round($e->fit(), 1),
            'pass_rate' => $e->passRate,
            'fit_rate' => $e->fitRate,
            'ai' => $e->aiTotal(),
            'places' => $e->placesCost,
            'text_search_calls' => $e->textSearchCalls,
            'details_calls' => $e->detailsCalls,
            'total' => $e->total(),
            'ai_prices' => $e->aiPricesConfigured,
            'places_prices' => $e->placesPricesConfigured,
            'defaults' => $e->usedDefaults,
            'areas' => $areas,
            'charge_sen' => $service->chargeEstimateSen($this->max_candidates, count($areas)),
        ];
    }

    public function confirm(SearchService $service): void
    {
        $this->validate();

        if ($this->estimate === null) {
            $this->addError('estimate', 'Kira anggaran kos dahulu.');

            return;
        }

        try {
            $search = $service->start(
                Product::query()->findOrFail($this->product_id),
                $this->business_type,
                SearchService::parseAreas($this->areas),
                $this->max_candidates,
            );
        } catch (BudgetExceeded $e) {
            $this->addError('estimate', $e->getMessage());

            return;
        }

        $this->lastSearchId = $search->id;
        $this->estimate = null;
    }

    public function cancel(int $searchId, SearchPipeline $pipeline): void
    {
        // Workspace-scoped query: another workspace's search is simply not found.
        $search = Search::query()->find($searchId);

        if ($search !== null) {
            $pipeline->cancel($search);
        }
    }

    public function render(SearchService $service, WalletService $wallet)
    {
        $searches = $service->recent();
        $product = $this->product_id ? Product::query()->find($this->product_id) : null;

        return view('livewire.search-page', [
            'products' => Product::query()->where('active', true)->orderBy('name')->get(),
            'suggestedTypes' => $product?->default_place_types ?? [],
            'searches' => $searches,
            'running' => $searches->contains(fn ($s) => ! $s->status->isFinished()),
            'blocker' => $service->blocker($this->max_candidates, max(1, count(SearchService::parseAreas($this->areas)))),
            'maxCandidates' => (int) config('dynoleads.search.hard_max', 60),
            'chargeSen' => $service->chargeEstimateSen($this->max_candidates, max(1, count(SearchService::parseAreas($this->areas)))),
            'balance' => $wallet->balanceSen(),
            'unlimited' => $wallet->isUnlimited(),
            'inTrial' => $wallet->inTrial(),
            'trialLeadsLeft' => $wallet->trialLeadsRemaining(),
            'isAdmin' => (bool) auth()->user()?->isAdmin(),
        ]);
    }
}
