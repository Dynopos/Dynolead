<?php

namespace App\Livewire;

use App\Exceptions\ChipException;
use App\Exceptions\PlanLimitReached;
use App\Models\Payment;
use App\Services\Ai\AiBudget;
use App\Services\Billing\BillingService;
use App\Services\Billing\PlanService;
use App\Support\Tenancy\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Langganan')]
class BillingPage extends Component
{
    public function subscribe(string $plan, BillingService $billing, CurrentWorkspace $current)
    {
        try {
            $url = $billing->startCheckout($current->get(), auth()->user(), $plan);
        } catch (PlanLimitReached|ChipException|\InvalidArgumentException $e) {
            report($e);
            session()->flash('warning', $e instanceof ChipException ? 'Sistem bayaran tak dapat dihubungi. Cuba lagi sebentar.' : $e->getMessage());

            return null;
        }

        return redirect()->away($url);
    }

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
            'payments' => Payment::query()->whereIn('status', ['paid', 'manual'])->latest('id')->limit(12)->get(),
        ]);
    }
}
