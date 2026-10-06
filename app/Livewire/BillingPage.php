<?php

namespace App\Livewire;

use App\Services\Ai\AiBudget;
use App\Services\Billing\PlanService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Langganan')]
class BillingPage extends Component
{
    public function render(PlanService $plans, AiBudget $budget)
    {
        $plan = $plans->planOf();

        return view('livewire.billing-page', [
            'plan' => $plan,
            'plans' => $plans->forSale(),
            'active' => $plans->isActive(),
            'blocker' => $plans->accessBlocker(),
            'endsAt' => $plans->accessEndsAt(),
            'isTrial' => $plans->isTrial(),
            'leadsUsed' => $plans->leadsUsedThisMonth(),
            'aiSpent' => $budget->spentThisMonth(),
            'aiLimit' => $budget->limit(),
        ]);
    }
}
