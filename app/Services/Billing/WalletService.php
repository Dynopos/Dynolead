<?php

namespace App\Services\Billing;

use App\Exceptions\WalletEmpty;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\PlacesUsage;
use App\Models\Search;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Account status and RM balance (config/billing.php):
 *  - trial: free, until trial_leads leads or trial_days days, whichever comes first;
 *  - activation: one-time fee, then searches are paid from a prepaid balance;
 *  - usage: a paid search costs its actual AI (+ Places) cost plus markup_percent.
 * The platform owner's workspace (plan 'dalaman') is never limited or charged.
 */
class WalletService
{
    public function __construct(private CurrentWorkspace $current) {}

    public function workspace(?Workspace $workspace = null): Workspace
    {
        return $workspace ?? $this->current->get() ?? throw new InvalidArgumentException('Tiada workspace semasa.');
    }

    // --- Status -------------------------------------------------------------

    public function isUnlimited(?Workspace $workspace = null): bool
    {
        return $this->workspace($workspace)->plan === 'dalaman';
    }

    public function isActivated(?Workspace $workspace = null): bool
    {
        return $this->workspace($workspace)->activated_at !== null;
    }

    public function trialEndsAt(?Workspace $workspace = null): ?Carbon
    {
        return $this->workspace($workspace)->trial_ends_at;
    }

    public function trialLeadsUsed(?Workspace $workspace = null): int
    {
        $workspace = $this->workspace($workspace);

        return Lead::query()->withoutGlobalScope('workspace')->where('workspace_id', $workspace->id)->count();
    }

    public function trialLeadsRemaining(?Workspace $workspace = null): int
    {
        return max(0, (int) config('billing.trial_leads', 20) - $this->trialLeadsUsed($workspace));
    }

    /** Free trial still running (not activated, days and leads left). */
    public function inTrial(?Workspace $workspace = null): bool
    {
        $workspace = $this->workspace($workspace);

        return ! $this->isUnlimited($workspace)
            && ! $this->isActivated($workspace)
            && $workspace->trial_ends_at !== null
            && $workspace->trial_ends_at->isFuture()
            && $this->trialLeadsRemaining($workspace) > 0;
    }

    public function trialEnded(?Workspace $workspace = null): bool
    {
        $workspace = $this->workspace($workspace);

        return ! $this->isUnlimited($workspace) && ! $this->isActivated($workspace) && ! $this->inTrial($workspace);
    }

    public function activationFeeSen(): int
    {
        return (int) round((float) config('billing.activation_fee_myr') * 100);
    }

    /** Why this workspace cannot start a search with this estimated raw cost, or null. */
    public function searchBlocker(float $estimateMyr = 0, ?Workspace $workspace = null): ?string
    {
        $workspace = $this->workspace($workspace);

        if ($reason = $this->accessBlocker($workspace)) {
            return $reason;
        }

        if ($this->isUnlimited($workspace) || $this->inTrial($workspace)) {
            return null;
        }

        $need = $this->priceSen($estimateMyr);
        $have = $this->balanceSen($workspace);

        return $have >= $need && $have > 0
            ? null
            : 'Baki tak cukup: anggaran caj '.self::rm($need).', baki anda '.self::rm($have).'. Tambah baki untuk teruskan.';
    }

    /** Why this workspace cannot use the app's AI at all right now, or null. */
    public function accessBlocker(?Workspace $workspace = null): ?string
    {
        $workspace = $this->workspace($workspace);

        if ($workspace->suspended_at !== null) {
            return 'Akaun ini digantung. Hubungi kami.';
        }

        if ($this->trialEnded($workspace)) {
            return 'Percubaan percuma dah tamat. Aktifkan akaun ('.self::rm($this->activationFeeSen()).' sekali bayar) untuk teruskan.';
        }

        return null;
    }

    // --- Money --------------------------------------------------------------

    /** Customer price for a raw cost: cost × (1 + markup), rounded up to the sen. */
    public function priceSen(float $costMyr): int
    {
        return (int) ceil(round(max(0, $costMyr) * (1 + (float) config('billing.markup_percent', 20) / 100) * 100, 4));
    }

    public function balanceSen(?Workspace $workspace = null): int
    {
        return (int) Workspace::query()->whereKey($this->workspace($workspace)->id)->value('balance_sen');
    }

    public function credit(Workspace $workspace, int $amountSen, string $reason, ?string $note = null, ?int $paymentId = null, ?int $userId = null): WalletTransaction
    {
        if ($amountSen <= 0) {
            throw new InvalidArgumentException('Jumlah mesti positif.');
        }

        return $this->apply($workspace, $amountSen, $reason, $note, $paymentId, $userId);
    }

    /**
     * Bring a paid search's charge up to date with its metered usage.
     * Safe to call many times; only the difference is charged.
     */
    public function settle(Search $search): void
    {
        if ($search->is_trial) {
            return;
        }

        DB::transaction(function () use ($search) {
            $locked = Search::query()->withoutGlobalScope('workspace')->lockForUpdate()->find($search->id);
            $workspace = $locked ? Workspace::query()->find($locked->workspace_id) : null;

            if ($locked === null || $workspace === null || $this->isUnlimited($workspace)) {
                return;
            }

            $cost = $this->billableCostMyr($locked);
            $delta = $this->priceSen($cost) - (int) $locked->charged_sen;

            if ($delta <= 0) {
                return;
            }

            $locked->forceFill(['charged_sen' => (int) $locked->charged_sen + $delta])->save();
            $search->charged_sen = $locked->charged_sen;

            // Raw cost behind this charge only (earlier settlements already recorded theirs).
            $costSoFar = (int) WalletTransaction::query()->withoutGlobalScope('workspace')
                ->where('search_id', $locked->id)->where('reason', 'usage')->sum('cost_sen');

            $this->apply($workspace, -$delta, 'usage', null, null, null, $locked->id, (int) round($cost * 100) - $costSoFar);
        });
    }

    /**
     * Before each paid step: charge what was used so far, and stop when the balance is gone.
     *
     * @throws WalletEmpty
     */
    public function ensureFunds(Search $search): void
    {
        if ($search->is_trial) {
            return;
        }

        $this->settle($search);
        $workspace = Workspace::query()->find($search->workspace_id);

        if ($workspace !== null && ! $this->isUnlimited($workspace) && $this->balanceSen($workspace) <= 0) {
            throw new WalletEmpty;
        }
    }

    public function billableCostMyr(Search $search): float
    {
        $ai = (float) AiUsage::query()->withoutGlobalScope('workspace')->where('billable_search_id', $search->id)->sum('cost_estimate');
        $places = config('billing.include_places_cost', true)
            ? (float) PlacesUsage::query()->withoutGlobalScope('workspace')->where('billable_search_id', $search->id)->sum('cost_estimate')
            : 0.0;

        return $ai + $places;
    }

    public static function rm(int $sen): string
    {
        return ($sen < 0 ? '-' : '').'RM'.number_format(abs($sen) / 100, 2);
    }

    private function apply(Workspace $workspace, int $amountSen, string $reason, ?string $note, ?int $paymentId, ?int $userId, ?int $searchId = null, ?int $costSen = null): WalletTransaction
    {
        return DB::transaction(function () use ($workspace, $amountSen, $reason, $note, $paymentId, $userId, $searchId, $costSen) {
            $locked = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $balance = (int) $locked->balance_sen + $amountSen;

            $locked->forceFill(['balance_sen' => $balance])->save();
            $workspace->balance_sen = $balance;

            return WalletTransaction::query()->create([
                'workspace_id' => $locked->id,
                'amount_sen' => $amountSen,
                'balance_after_sen' => $balance,
                'reason' => $reason,
                'cost_sen' => $costSen,
                'search_id' => $searchId,
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'note' => $note,
            ]);
        });
    }
}
