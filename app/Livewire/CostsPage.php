<?php

namespace App\Livewire;

use App\Models\CreditTransaction;
use App\Models\Search;
use App\Services\Ai\AiBudget;
use App\Services\Billing\CreditService;
use App\Services\Costs\CostReport;
use App\Services\Costs\PriceTable;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kos')]
class CostsPage extends Component
{
    public string $limit = '';

    public ?string $saved = null;

    public function mount(AiBudget $budget): void
    {
        $this->limit = (string) $budget->limit();
    }

    public function saveLimit(AiBudget $budget): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->validate(
            ['limit' => 'required|numeric|min:0|max:100000'],
            ['required' => 'Isi had bulanan.', 'numeric' => 'Mesti nombor.', 'min' => 'Tak boleh negatif.'],
        );

        $budget->setLimit((float) $this->limit);
        $this->limit = (string) $budget->limit();
        $this->saved = 'Had bulanan dikemas kini.';
    }

    public function render(CostReport $report, PriceTable $prices, CreditService $credits, AiBudget $budget)
    {
        $models = array_filter([config('dynoleads.ai.model_score'), config('dynoleads.ai.model_write')]);

        return view('livewire.costs-page', [
            'month' => $report->thisMonth(),
            'calls' => $report->lastCalls(50),
            'isAdmin' => (bool) auth()->user()?->isAdmin(),
            'balance' => $credits->balance(),
            'unlimited' => $credits->isUnlimited(),
            'searchesThisMonth' => Search::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'creditsUsedThisMonth' => (int) -CreditTransaction::query()->where('created_at', '>=', now()->startOfMonth())->whereIn('reason', ['search', 'refund'])->sum('amount'),
            'ledger' => CreditTransaction::query()->with('search')->latest('id')->limit(30)->get(),
            'missingPrices' => array_values(array_filter($models, fn ($m) => ! $prices->isModelConfigured((string) $m))),
        ]);
    }
}
