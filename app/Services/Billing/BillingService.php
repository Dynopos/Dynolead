<?php

namespace App\Services\Billing;

use App\Exceptions\AccountLimitReached;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Payments through CHIP (one-off purchases, no recurring charge):
 *  - 'activation': the one-time fee that turns a trial into a paying account;
 *  - 'topup': adds RM to the prepaid balance that paid searches draw from.
 *
 * A payment only counts after CHIP confirms it: the signed callback is verified,
 * then the purchase is fetched again from CHIP and its amount, currency and
 * reference are checked. Handling is idempotent (CHIP may deliver more than once).
 */
class BillingService
{
    public function __construct(
        private ChipClient $chip,
        private WalletService $wallet,
    ) {}

    public function startActivation(Workspace $workspace, User $user): string
    {
        if ($this->wallet->isActivated($workspace) || $this->wallet->isUnlimited($workspace)) {
            throw new AccountLimitReached('Akaun ini dah aktif.');
        }

        return $this->checkout($workspace, $user, 'activation', $this->wallet->activationFeeSen(), 'Dyno Leads: aktifkan akaun (sekali bayar)');
    }

    public function startTopup(Workspace $workspace, User $user, int $amountMyr): string
    {
        if (! in_array($amountMyr, array_map('intval', (array) config('billing.topup_options', [])), true)) {
            throw new InvalidArgumentException('Jumlah tambah baki tidak sah.');
        }

        if (! $this->wallet->isActivated($workspace)) {
            throw new AccountLimitReached('Aktifkan akaun dahulu sebelum tambah baki.');
        }

        return $this->checkout($workspace, $user, 'topup', $amountMyr * 100, 'Dyno Leads: tambah baki RM'.$amountMyr);
    }

    private function checkout(Workspace $workspace, User $user, string $kind, int $amountSen, string $label): string
    {
        $payment = Payment::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'kind' => $kind,
            'amount_sen' => $amountSen,
            'currency' => 'MYR',
            'status' => 'created',
        ]);

        $purchase = $this->chip->createPurchase([
            'client' => ['email' => $user->email, 'full_name' => $user->name],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [['name' => $label, 'price' => $amountSen, 'quantity' => '1']],
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
            $this->apply($payment);
        });
    }

    /** Bob records a payment made outside CHIP (bank transfer, cash). */
    public function recordManual(Workspace $workspace, string $kind, int $amountSen, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($workspace, $kind, $amountSen, $note) {
            $payment = Payment::query()->create([
                'workspace_id' => $workspace->id,
                'kind' => $kind,
                'amount_sen' => $amountSen,
                'status' => 'manual',
                'paid_at' => now(),
                'note' => $note,
            ]);

            $this->apply($payment);

            return $payment;
        });
    }

    private function apply(Payment $payment): void
    {
        $workspace = Workspace::query()->lockForUpdate()->findOrFail($payment->workspace_id);

        match ($payment->kind) {
            'activation' => $workspace->activated_at ?? $workspace->forceFill(['activated_at' => now()])->save(),
            'topup' => $this->wallet->credit($workspace, $payment->amount_sen, 'topup', $payment->note ?: $payment->reference(), $payment->id, $payment->user_id),
            default => throw new InvalidArgumentException("Jenis bayaran {$payment->kind} tidak dikenali."),
        };
    }
}
