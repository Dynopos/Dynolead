<?php

namespace App\Services\Billing;

use App\Exceptions\PlanLimitReached;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Prepaid 30-day periods paid through CHIP.
 *
 * A payment only counts after CHIP confirms it: the signed callback is verified,
 * then the purchase is fetched again from CHIP and its amount, currency and
 * reference are checked. Handling is idempotent (CHIP may deliver more than once).
 */
class BillingService
{
    public const PERIOD_DAYS = 30;

    public function __construct(
        private ChipClient $chip,
        private PlanService $plans,
    ) {}

    /** Create a CHIP purchase and return the checkout URL. */
    public function startCheckout(Workspace $workspace, User $user, string $planKey): string
    {
        $plan = $this->plans->find($planKey);

        if (! $plan->isForSale()) {
            throw new PlanLimitReached('Pelan ini belum dibuka untuk dibeli.');
        }

        $payment = Payment::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'plan' => $plan->key,
            'amount_sen' => $plan->priceSen(),
            'currency' => 'MYR',
            'status' => 'created',
        ]);

        $purchase = $this->chip->createPurchase([
            'client' => ['email' => $user->email, 'full_name' => $user->name],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [[
                    'name' => 'Dyno Leads '.$plan->name.' (30 hari)',
                    'price' => $plan->priceSen(),
                    'quantity' => '1',
                ]],
            ],
            'reference' => $payment->reference(),
            'send_receipt' => true,
            'success_callback' => route('chip.callback'),
            'success_redirect' => route('billing.return', $payment),
            'failure_redirect' => route('billing.return', $payment),
            'cancel_redirect' => route('billing'),
            'creator_agent' => 'DynoLeads/1.0',
            'platform' => 'web',
        ]);

        $payment->forceFill([
            'chip_purchase_id' => $purchase['id'] ?? null,
            'checkout_url' => $purchase['checkout_url'] ?? null,
            'is_test' => (bool) ($purchase['is_test'] ?? false),
        ])->save();

        return (string) $payment->checkout_url;
    }

    /** Signed callback from CHIP. Returns false when the signature is invalid. */
    public function handleCallback(string $rawBody, ?string $signature): bool
    {
        if (! $this->chip->verifySignature($rawBody, $signature)) {
            Log::warning('CHIP callback: tandatangan tidak sah.');

            return false;
        }

        $id = (string) (json_decode($rawBody, true)['id'] ?? '');
        $payment = $id === '' ? null : Payment::query()->withoutGlobalScope('workspace')->where('chip_purchase_id', $id)->first();

        if ($payment !== null) {
            // Never trust the payload alone: confirm with CHIP.
            $this->sync($payment);
        }

        return true;
    }

    /** Fetch the purchase from CHIP and apply its status. Safe to call many times. */
    public function sync(Payment $payment): Payment
    {
        if ($payment->isPaid() || $payment->chip_purchase_id === null) {
            return $payment;
        }

        $purchase = $this->chip->getPurchase($payment->chip_purchase_id);
        $status = (string) ($purchase['status'] ?? '');

        if (in_array($status, ['paid', 'cleared', 'settled'], true)) {
            $this->confirmPaid($payment, $purchase);
        } elseif (in_array($status, ['cancelled', 'expired', 'blocked'], true)) {
            $payment->forceFill(['status' => $status === 'blocked' ? 'failed' : $status])->save();
        } elseif ($status === 'error') {
            $payment->forceFill(['status' => 'failed'])->save();
        }

        return $payment->refresh();
    }

    private function confirmPaid(Payment $payment, array $purchase): void
    {
        $total = (int) ($purchase['purchase']['total'] ?? $purchase['payment']['amount'] ?? -1);
        $currency = (string) ($purchase['purchase']['currency'] ?? $purchase['payment']['currency'] ?? '');
        $reference = (string) ($purchase['reference'] ?? '');

        if ($total !== $payment->amount_sen || $currency !== $payment->currency || $reference !== $payment->reference()) {
            Log::error('CHIP: pembelian tidak sepadan dengan rekod.', ['payment' => $payment->id, 'total' => $total, 'currency' => $currency, 'reference' => $reference]);
            $payment->forceFill(['status' => 'failed', 'note' => 'Jumlah/rujukan tidak sepadan'])->save();

            return;
        }

        DB::transaction(function () use ($payment) {
            $payment = Payment::query()->withoutGlobalScope('workspace')->lockForUpdate()->findOrFail($payment->id);
            if ($payment->isPaid()) {
                return;
            }

            $workspace = Workspace::query()->lockForUpdate()->findOrFail($payment->workspace_id);
            [$start, $end] = $this->nextPeriod($workspace);

            $payment->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'period_start' => $start,
                'period_end' => $end,
            ])->save();

            $workspace->forceFill(['plan' => $payment->plan, 'paid_until' => $end])->save();
        });
    }

    /** Bob records a payment made outside CHIP (bank transfer, cash). */
    public function recordManual(Workspace $workspace, string $planKey, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($workspace, $planKey, $note) {
            $plan = $this->plans->find($planKey);
            [$start, $end] = $this->nextPeriod($workspace);

            $payment = Payment::query()->create([
                'workspace_id' => $workspace->id,
                'plan' => $plan->key,
                'amount_sen' => $plan->priceSen(),
                'status' => 'manual',
                'paid_at' => now(),
                'period_start' => $start,
                'period_end' => $end,
                'note' => $note,
            ]);

            $workspace->forceFill(['plan' => $plan->key, 'paid_until' => $end])->save();

            return $payment;
        });
    }

    /** A new period starts now, or when the current paid period ends (no lost days). */
    private function nextPeriod(Workspace $workspace): array
    {
        $start = $workspace->paid_until !== null && $workspace->paid_until->isFuture()
            ? Carbon::parse($workspace->paid_until)
            : now();

        return [$start, $start->copy()->addDays(self::PERIOD_DAYS)];
    }
}
