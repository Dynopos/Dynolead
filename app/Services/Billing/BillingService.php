<?php

namespace App\Services\Billing;

use App\Exceptions\AccountLimitReached;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Credit packs paid through CHIP (one-off purchases, no recurring charge).
 *
 * A payment only counts after CHIP confirms it: the signed callback is verified,
 * then the purchase is fetched again from CHIP and its amount, currency and
 * reference are checked. Handling is idempotent (CHIP may deliver more than once).
 */
class BillingService
{
    public function __construct(
        private ChipClient $chip,
        private CreditService $credits,
    ) {}

    /** Create a CHIP purchase for a credit pack and return the checkout URL. */
    public function startCheckout(Workspace $workspace, User $user, string $packKey): string
    {
        $pack = $this->credits->pack($packKey);

        if (! $pack->isForSale()) {
            throw new AccountLimitReached('Pek ini belum dibuka untuk dibeli.');
        }

        $payment = Payment::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'pack' => $pack->key,
            'credits' => $pack->credits,
            'amount_sen' => $pack->priceSen(),
            'currency' => 'MYR',
            'status' => 'created',
        ]);

        $purchase = $this->chip->createPurchase([
            'client' => ['email' => $user->email, 'full_name' => $user->name],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [[
                    'name' => 'Dyno Leads '.$pack->name.' ('.$pack->credits.' kredit)',
                    'price' => $pack->priceSen(),
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

            $payment->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

            $this->credits->grant(
                Workspace::query()->findOrFail($payment->workspace_id),
                $payment->credits,
                'purchase',
                $payment->reference(),
                paymentId: $payment->id,
                userId: $payment->user_id,
            );
        });
    }

    /** Bob records a payment made outside CHIP (bank transfer, cash). */
    public function recordManual(Workspace $workspace, string $packKey, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($workspace, $packKey, $note) {
            $pack = $this->credits->pack($packKey);

            $payment = Payment::query()->create([
                'workspace_id' => $workspace->id,
                'pack' => $pack->key,
                'credits' => $pack->credits,
                'amount_sen' => $pack->priceSen(),
                'status' => 'manual',
                'paid_at' => now(),
                'note' => $note,
            ]);

            $this->credits->grant($workspace, $pack->credits, 'purchase', $note ?: $payment->reference(), paymentId: $payment->id);

            return $payment;
        });
    }
}
