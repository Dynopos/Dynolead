<?php

namespace App\Livewire;

use App\Exceptions\AccountLimitReached;
use App\Exceptions\ChipException;
use App\Models\Payment;
use App\Services\Billing\BillingService;
use App\Services\Billing\CreditService;
use App\Support\Tenancy\CurrentWorkspace;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tambah kredit')]
class BillingPage extends Component
{
    public function buy(string $pack, BillingService $billing, CurrentWorkspace $current)
    {
        try {
            $url = $billing->startCheckout($current->get(), auth()->user(), $pack);
        } catch (AccountLimitReached|ChipException|InvalidArgumentException $e) {
            report($e);
            session()->flash('warning', $e instanceof ChipException ? 'Sistem bayaran tak dapat dihubungi. Cuba lagi sebentar.' : $e->getMessage());

            return null;
        }

        return redirect()->away($url);
    }

    public function render(CreditService $credits)
    {
        return view('livewire.billing-page', [
            'balance' => $credits->balance(),
            'unlimited' => $credits->isUnlimited(),
            'packs' => $credits->packs(),
            'perCredit' => (int) config('credits.candidates_per_credit'),
            'payments' => Payment::query()->whereIn('status', ['paid', 'manual'])->latest('id')->limit(12)->get(),
        ]);
    }
}
