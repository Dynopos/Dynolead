<?php

namespace App\Livewire;

use App\Enums\LeadStatus;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\ContactRuleViolation;
use App\Models\Lead;
use App\Services\Leads\FollowupService;
use App\Services\Leads\LeadService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Follow-up')]
class FollowupsPage extends Component
{
    /** @var array<int, string> */
    public array $feedback = [];

    /** @var array<int, bool> leads waiting for an AI follow-up */
    public array $pending = [];

    public function generate(int $leadId, FollowupService $service): void
    {
        try {
            $service->requestMessage($this->findLead($leadId));
            $this->pending[$leadId] = true;
            $this->feedback[$leadId] = 'Sedang tulis mesej follow-up...';
        } catch (BudgetExceeded|ContactRuleViolation $e) {
            $this->feedback[$leadId] = $e->getMessage();
        }
    }

    public function markDone(int $leadId, FollowupService $service): void
    {
        $service->markFollowedUp($this->findLead($leadId));
        unset($this->feedback[$leadId], $this->pending[$leadId]);
    }

    public function setStatus(int $leadId, string $status, LeadService $leads): void
    {
        $new = LeadStatus::tryFrom($status);
        if ($new === null || ! in_array($new, LeadStatus::selectable(), true)) {
            return;
        }

        try {
            $leads->changeStatus($this->findLead($leadId), $new);
        } catch (ContactRuleViolation $e) {
            $this->feedback[$leadId] = $e->getMessage();
        }
    }

    private function findLead(int $id): Lead
    {
        return app(LeadService::class)->visibleQuery()->with('product')->findOrFail($id);
    }

    public function render(FollowupService $service, LeadService $leads)
    {
        $due = $service->due();

        foreach ($due as $lead) {
            if ($lead->followup_message !== null || $lead->review_note !== null) {
                unset($this->pending[$lead->id]);
            }
        }

        return view('livewire.followups-page', [
            'cards' => $leads->cards($due),
            'service' => $service,
            'polling' => $this->pending !== [],
        ]);
    }
}
