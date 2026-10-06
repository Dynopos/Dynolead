<?php

namespace App\Livewire;

use App\Services\Ai\AiBudget;
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
        $this->validate(
            ['limit' => 'required|numeric|min:0|max:100000'],
            ['required' => 'Isi had bulanan.', 'numeric' => 'Mesti nombor.', 'min' => 'Tak boleh negatif.'],
        );

        $budget->setLimit((float) $this->limit);
        $this->limit = (string) $budget->limit();
        $this->saved = 'Had bulanan dikemas kini.';
    }

    public function render(CostReport $report, PriceTable $prices)
    {
        $models = array_filter([config('dynoleads.ai.model_score'), config('dynoleads.ai.model_write')]);

        return view('livewire.costs-page', [
            'month' => $report->thisMonth(),
            'calls' => $report->lastCalls(50),
            'missingPrices' => array_values(array_filter($models, fn ($m) => ! $prices->isModelConfigured((string) $m))),
        ]);
    }
}
