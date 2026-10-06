<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Billing\BillingService;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Throwable;

/** Customer comes back from the CHIP page. Status comes from CHIP, never from the URL. */
class BillingReturnController
{
    public function __invoke(Payment $payment, BillingService $billing, CurrentWorkspace $current): RedirectResponse
    {
        // Route binding may run before the workspace scope is set: check ownership explicitly.
        abort_unless($payment->workspace_id === $current->id(), 404);

        try {
            $payment = $billing->sync($payment);
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('billing')->with(
            $payment->isPaid() ? 'status' : 'warning',
            $payment->isPaid()
                ? ($payment->kind === 'activation' ? 'Terima kasih! Akaun anda dah aktif.' : 'Terima kasih! Baki RM'.number_format($payment->amountMyr(), 2).' dah ditambah.')
                : 'Bayaran belum disahkan. Kalau anda dah bayar, status akan dikemas kini dalam beberapa minit.',
        );
    }
}
