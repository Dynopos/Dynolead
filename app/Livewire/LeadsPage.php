<?php

namespace App\Livewire;

use App\Enums\LeadStatus;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\ContactRuleViolation;
use App\Models\Lead;
use App\Models\Product;
use App\Services\Leads\LeadService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Lead')]
class LeadsPage extends Component
{
    use WithPagination;

    #[Url(as: 'product')]
    public ?int $productId = null;

    #[Url(as: 'jenis')]
    public string $businessType = '';

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'kawasan')]
    public string $area = '';

    /** @var array<int, string> lead id => notes being edited */
    public array $notes = [];

    /** @var array<int, string> lead id => message being edited */
    public array $messages = [];

    public ?int $editingMessageId = null;

    /** Page-level notice, for actions that remove the card (e.g. STOP). */
    public ?string $notice = null;

    /** @var array<int, string> lead id => feedback line */
    public array $feedback = [];

    public function updated(string $field): void
    {
        if (in_array($field, ['productId', 'businessType', 'status', 'area'], true)) {
            $this->resetPage();
        }
    }

    public function setStatus(int $leadId, string $status, LeadService $service): void
    {
        $lead = $this->findLead($leadId);
        $new = LeadStatus::tryFrom($status);

        if ($new === null || ! in_array($new, LeadStatus::selectable(), true)) {
            return;
        }

        try {
            $service->changeStatus($lead, $new);
            $this->feedback[$leadId] = 'Status: '.$new->label();

            if ($new === LeadStatus::Tolak) {
                $this->notice = 'Ditanda STOP. Kedai ni takkan muncul lagi untuk semua produk.';
            }
        } catch (ContactRuleViolation $e) {
            $this->feedback[$leadId] = $e->getMessage();
        }
    }

    public function saveNotes(int $leadId, LeadService $service): void
    {
        $service->saveNotes($this->findLead($leadId), $this->notes[$leadId] ?? null);
        $this->feedback[$leadId] = 'Nota disimpan.';
    }

    public function editMessage(int $leadId): void
    {
        $this->editingMessageId = $leadId;
        $this->messages[$leadId] = (string) $this->findLead($leadId)->message;
    }

    public function saveMessage(int $leadId, LeadService $service): void
    {
        $problems = $service->saveMessage($this->findLead($leadId), (string) ($this->messages[$leadId] ?? ''));

        if ($problems === []) {
            $this->editingMessageId = null;
            $this->feedback[$leadId] = 'Mesej disimpan.';
        } else {
            $this->feedback[$leadId] = 'Belum boleh simpan: '.implode('; ', $problems);
        }
    }

    public function regenerate(int $leadId, LeadService $service): void
    {
        try {
            $service->requestRegenerate($this->findLead($leadId));
            $this->feedback[$leadId] = 'Sedang jana semula. Tunggu sekejap...';
        } catch (BudgetExceeded|ContactRuleViolation $e) {
            $this->feedback[$leadId] = $e->getMessage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('productId', 'businessType', 'status', 'area');
        $this->resetPage();
    }

    private function findLead(int $id): Lead
    {
        // Only leads that may be shown can be acted on.
        return app(LeadService::class)->visibleQuery()->with('product')->findOrFail($id);
    }

    public function render(LeadService $service)
    {
        $leads = $service->list([
            'product_id' => $this->productId,
            'business_type' => $this->businessType ?: null,
            'status' => $this->status ?: null,
            'area' => $this->area ?: null,
        ]);

        $cards = $service->cards($leads->items());

        foreach ($cards as $card) {
            $this->notes[$card->lead->id] ??= (string) $card->lead->notes;
        }

        $regenerating = $cards->contains(fn ($c) => $c->lead->review_note === LeadService::REGENERATING);

        return view('livewire.leads-page', [
            'leads' => $leads,
            'cards' => $cards,
            'summary' => $service->summary($this->productId),
            'options' => $service->filterOptions(),
            'products' => Product::query()->orderBy('name')->get(),
            'statuses' => LeadStatus::cases(),
            'selectable' => LeadStatus::selectable(),
            'sentToday' => $service->sentToday(),
            'regenerating' => $regenerating,
            'service' => $service,
        ]);
    }
}
