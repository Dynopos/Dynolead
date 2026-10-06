<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Billing\BillingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Hourly safety net for missed CHIP callbacks. */
class SyncPendingPaymentsJob implements ShouldQueue
{
    use Queueable;

    public function handle(BillingService $billing): void
    {
        Payment::query()->withoutGlobalScope('workspace')
            ->where('status', 'created')
            ->whereNotNull('chip_purchase_id')
            ->where('created_at', '>=', now()->subDays(2))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (Payment $payment) use ($billing) {
                try {
                    $billing->sync($payment);
                } catch (Throwable $e) {
                    report($e);
                }
            });
    }
}
