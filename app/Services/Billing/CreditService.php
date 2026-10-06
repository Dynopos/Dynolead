<?php

namespace App\Services\Billing;

use App\Enums\SearchStatus;
use App\Exceptions\AccountLimitReached;
use App\Models\CreditTransaction;
use App\Models\Search;
use App\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pay per search with prepaid credits.
 *
 * Balance lives on workspaces.credits; every change is written to credit_transactions
 * in the same database transaction, with the workspace row locked.
 * The platform owner's workspace (plan 'dalaman') is never charged.
 */
class CreditService
{
    public function __construct(private CurrentWorkspace $current) {}

    public function workspace(?Workspace $workspace = null): Workspace
    {
        return $workspace ?? $this->current->get() ?? throw new InvalidArgumentException('Tiada workspace semasa.');
    }

    public function isUnlimited(?Workspace $workspace = null): bool
    {
        return $this->workspace($workspace)->plan === 'dalaman';
    }

    public function balance(?Workspace $workspace = null): int
    {
        return (int) Workspace::query()->whereKey($this->workspace($workspace)->id)->value('credits');
    }

    /** Credits needed for a search of $candidates. */
    public function costFor(int $candidates): int
    {
        return max(1, (int) ceil($candidates / max(1, (int) config('credits.candidates_per_credit', 20))));
    }

    /** @return array<string, CreditPack> */
    public function packs(): array
    {
        $packs = [];
        foreach ((array) config('credits.packs', []) as $key => $c) {
            $packs[$key] = CreditPack::fromConfig($key, $c);
        }

        return $packs;
    }

    public function pack(string $key): CreditPack
    {
        return $this->packs()[$key] ?? throw new InvalidArgumentException("Pek {$key} tidak wujud.");
    }

    /** Why this workspace cannot start a search of $candidates, or null. */
    public function searchBlocker(int $candidates, ?Workspace $workspace = null): ?string
    {
        $workspace = $this->workspace($workspace);

        if ($workspace->suspended_at !== null) {
            return 'Akaun ini digantung. Hubungi kami.';
        }

        if ($this->isUnlimited($workspace)) {
            return null;
        }

        $need = $this->costFor($candidates);
        $have = $this->balance($workspace);

        return $have >= $need ? null : "Kredit tak cukup: carian ini perlu {$need} kredit, baki anda {$have}. Tambah kredit untuk teruskan.";
    }

    /** Why this workspace cannot use free AI extras (regenerate, follow-up), or null. */
    public function accessBlocker(?Workspace $workspace = null): ?string
    {
        return $this->workspace($workspace)->suspended_at !== null ? 'Akaun ini digantung. Hubungi kami.' : null;
    }

    public function grant(Workspace $workspace, int $amount, string $reason, ?string $note = null, ?int $paymentId = null, ?int $userId = null, ?int $searchId = null): CreditTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah kredit mesti positif.');
        }

        return $this->apply($workspace, $amount, $reason, $note, $paymentId, $userId, $searchId);
    }

    /**
     * Take credits for a search before it runs.
     *
     * @throws AccountLimitReached
     */
    public function chargeForSearch(Workspace $workspace, int $candidates, ?int $userId = null): int
    {
        if ($this->isUnlimited($workspace)) {
            return 0;
        }

        $cost = $this->costFor($candidates);
        $this->apply($workspace, -$cost, 'search', null, null, $userId, null);

        return $cost;
    }

    /** Link the charge row to the search once it exists. */
    public function attachSearch(Workspace $workspace, Search $search): void
    {
        CreditTransaction::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspace->id)
            ->where('reason', 'search')
            ->whereNull('search_id')
            ->latest('id')
            ->limit(1)
            ->update(['search_id' => $search->id]);
    }

    /**
     * Called when a search reaches a final state. Returns the credits when the
     * customer got nothing: no shop passed the filters, or the search stopped
     * (error or platform limit) before any message was written.
     */
    public function settleSearch(Search $search): void
    {
        if ($search->credits_charged <= 0 || $search->credits_refunded_at !== null || ! config('credits.refund_empty_searches', true)) {
            return;
        }

        $search->refresh();
        $stopped = in_array($search->status, [SearchStatus::Failed, SearchStatus::BudgetExceeded], true);

        if ($search->lead_count > 0 && ! ($stopped && $search->written_count === 0)) {
            return;
        }

        DB::transaction(function () use ($search) {
            $locked = Search::query()->withoutGlobalScope('workspace')->lockForUpdate()->find($search->id);
            if ($locked === null || $locked->credits_refunded_at !== null) {
                return;
            }

            $locked->forceFill(['credits_refunded_at' => now()])->save();
            $this->apply(Workspace::query()->findOrFail($locked->workspace_id), $locked->credits_charged, 'refund', 'Carian tiada lead', null, null, $locked->id);
        });
    }

    private function apply(Workspace $workspace, int $amount, string $reason, ?string $note, ?int $paymentId, ?int $userId, ?int $searchId): CreditTransaction
    {
        return DB::transaction(function () use ($workspace, $amount, $reason, $note, $paymentId, $userId, $searchId) {
            $locked = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $balance = (int) $locked->credits + $amount;

            if ($balance < 0) {
                throw new AccountLimitReached('Kredit tak cukup. Tambah kredit untuk teruskan.');
            }

            $locked->forceFill(['credits' => $balance])->save();
            $workspace->credits = $balance;

            return CreditTransaction::query()->create([
                'workspace_id' => $locked->id,
                'amount' => $amount,
                'balance_after' => $balance,
                'reason' => $reason,
                'search_id' => $searchId,
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'note' => $note,
            ]);
        });
    }
}
