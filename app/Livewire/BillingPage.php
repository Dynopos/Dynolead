<?php

namespace App\Livewire;

use App\Exceptions\AccountLimitReached;
use App\Exceptions\ChipException;
use App\Models\Payment;
use App\Services\Billing\BillingService;
use App\Services\Billing\WalletService;
use App\Support\Tenancy\CurrentWorkspace;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bayaran')]
class BillingPage extends Component
{
    public function activate(BillingService $billing, CurrentWorkspace $current)
    {
        return $this->go(fn () => $billing->startActivation($current->get(), auth()->user()));
    }

    public function topup(int $amount, BillingService $billing, CurrentWorkspace $current)
    {
        return $this->go(fn () => $billing->startTopup($current->get(), auth()->user(), $amount));
    }

    private function go(callable $start)
    {
        try {
            $url = $start();
        } catch (AccountLimitReached|ChipException|InvalidArgumentException $e) {
            report($e);
            session()->flash('warning', $e instanceof ChipException ? 'Sistem bayaran tak dapat dihubungi. Cuba lagi sebentar.' : $e->getMessage());

            return null;
        }

        return redirect()->away($url);
    }

    public function render(WalletService $wallet)
    {
        return view('livewire.billing-page', [
            'unlimited' => $wallet->isUnlimited(),
            'activated' => $wallet->isActivated(),
            'inTrial' => $wallet->inTrial(),
            'trialEndsAt' => $wallet->trialEndsAt(),
            'trialLeadsUsed' => $wallet->trialLeadsUsed(),
            'trialLeads' => (int) config('billing.trial_leads'),
            'fee' => WalletService::rm($wallet->activationFeeSen()),
            'balance' => $wallet->balanceSen(),
            'markup' => (float) config('billing.markup_percent'),
            'includesPlaces' => (bool) config('billing.include_places_cost'),
            'topups' => (array) config('billing.topup_options'),
            'payments' => Payment::query()->whereIn('status', ['paid', 'manual'])->latest('id')->limit(12)->get(),
        ]);
    }
}
