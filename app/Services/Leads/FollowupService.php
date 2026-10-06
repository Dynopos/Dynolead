<?php

namespace App\Services\Leads;

use App\Enums\LeadStatus;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\ContactRuleViolation;
use App\Jobs\GenerateFollowupJob;
use App\Models\Lead;
use App\Services\Ai\AiBudget;
use App\Services\Ai\MessageValidator;
use App\Support\MalaysianPhone;
use Illuminate\Support\Collection;

/** "Dah hantar" leads with no change for 3+ days (spec §3.4). */
class FollowupService
{
    public function __construct(
        private LeadService $leads,
        private ContactRules $rules,
        private AiBudget $budget,
        private MessageValidator $validator,
    ) {}

    /** @return Collection<int, Lead> */
    public function due(): Collection
    {
        return $this->leads->visibleQuery()
            ->with('product')
            ->where('status', LeadStatus::Dihantar->value)
            ->whereNotNull('next_followup_at')
            ->where('next_followup_at', '<=', now())
            ->orderBy('next_followup_at')
            ->limit(50)
            ->get();
    }

    public function requestMessage(Lead $lead): void
    {
        if ($this->rules->isSuppressed($lead->place_id)) {
            throw new ContactRuleViolation('Kedai ni dalam senarai STOP.');
        }

        if ($this->budget->isExhausted()) {
            throw new BudgetExceeded;
        }

        GenerateFollowupJob::dispatch($lead->id);
    }

    /** wa.me link for the follow-up, only when it passes the same checks as first messages. */
    public function whatsappLink(Lead $lead, ?string $phone): ?string
    {
        if ($lead->followup_message === null || ! $this->validator->passes($lead->product, $lead->followup_message)) {
            return null;
        }

        return MalaysianPhone::waLink($phone, $lead->followup_message);
    }

    /** Bob sent the follow-up: wait another 3 days before reminding again. */
    public function markFollowedUp(Lead $lead): void
    {
        $lead->forceFill([
            'next_followup_at' => now()->addDays((int) config('dynoleads.rules.followup_after_days', 3)),
            'followup_message' => null,
            'review_note' => null,
        ])->save();
    }
}
