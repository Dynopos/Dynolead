<?php

namespace App\Services\Admin;

use App\Models\AiUsage;
use App\Models\Payment;
use App\Models\PlacesUsage;
use App\Models\Search;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Services\Ai\AiBudget;
use App\Services\Billing\BillingService;
use App\Services\Billing\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Platform view for the admin (Bob). Reads across all workspaces on purpose. */
class AdminService
{
    public function __construct(
        private WalletService $wallet,
        private BillingService $billing,
        private AiBudget $budget,
    ) {}

    public function totals(): array
    {
        $start = now()->startOfMonth();
        $payments = Payment::query()->withoutGlobalScope('workspace')->whereIn('status', ['paid', 'manual'])->where('paid_at', '>=', $start);
        $usage = WalletTransaction::query()->withoutGlobalScope('workspace')->where('reason', 'usage')->where('created_at', '>=', $start);
        $customers = Workspace::query()->where('plan', '!=', 'dalaman')->get();

        return [
            'customers' => $customers->count(),
            'activated' => $customers->whereNotNull('activated_at')->count(),
            'in_trial' => $customers->filter(fn ($w) => $this->wallet->inTrial($w))->count(),
            'activation_revenue' => (clone $payments)->where('kind', 'activation')->sum('amount_sen') / 100,
            'topup_revenue' => (clone $payments)->where('kind', 'topup')->sum('amount_sen') / 100,
            'usage_charged' => -(int) (clone $usage)->sum('amount_sen') / 100,
            'usage_cost' => (int) (clone $usage)->sum('cost_sen') / 100,
            'searches_month' => Search::query()->withoutGlobalScope('workspace')->where('created_at', '>=', $start)->count(),
            'ai_cost_month' => $this->budget->platformSpentThisMonth(),
            'ai_limit' => $this->budget->platformLimit(),
            'places_calls_month' => PlacesUsage::query()->withoutGlobalScope('workspace')->where('created_at', '>=', $start)->count(),
            'places_cost_month' => (float) PlacesUsage::query()->withoutGlobalScope('workspace')->where('created_at', '>=', $start)->sum('cost_estimate'),
        ];
    }

    /** @return Collection<int, array> */
    public function workspaces(string $search = ''): Collection
    {
        $start = now()->startOfMonth();

        $workspaces = Workspace::query()
            ->with(['users' => fn ($q) => $q->orderBy('id')])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('users', fn (Builder $u) => $u->where('email', 'like', "%{$search}%"))))
            ->latest('id')
            ->limit(100)
            ->get();

        $ids = $workspaces->pluck('id');
        $searches = Search::query()->withoutGlobalScope('workspace')->whereIn('workspace_id', $ids)->where('created_at', '>=', $start)
            ->selectRaw('workspace_id, COUNT(*) as n')->groupBy('workspace_id')->pluck('n', 'workspace_id');
        $ai = AiUsage::query()->withoutGlobalScope('workspace')->whereIn('workspace_id', $ids)->where('created_at', '>=', $start)
            ->selectRaw('workspace_id, SUM(cost_estimate) as c')->groupBy('workspace_id')->pluck('c', 'workspace_id');

        return $workspaces->map(fn (Workspace $w) => [
            'workspace' => $w,
            'owner' => $w->users->first(),
            'status' => $this->status($w),
            'trial_leads_used' => $this->wallet->trialLeadsUsed($w),
            'searches_month' => (int) ($searches[$w->id] ?? 0),
            'ai_month' => round((float) ($ai[$w->id] ?? 0), 2),
        ]);
    }

    public function status(Workspace $w): string
    {
        return match (true) {
            $this->wallet->isUnlimited($w) => 'Dalaman',
            $w->suspended_at !== null => 'Digantung',
            $this->wallet->isActivated($w) => 'Aktif',
            $this->wallet->inTrial($w) => 'Percubaan',
            default => 'Percubaan tamat',
        };
    }

    public function addBalance(Workspace $workspace, float $myr, ?string $note, ?int $adminId): void
    {
        $this->wallet->credit($workspace, (int) round($myr * 100), 'admin', $note ?: 'Baki dari admin', userId: $adminId);
    }

    public function recordPayment(Workspace $workspace, string $kind, float $myr, ?string $note): void
    {
        $amount = $kind === 'activation' ? $this->wallet->activationFeeSen() : (int) round($myr * 100);
        $this->billing->recordManual($workspace, $kind, $amount, $note ?: 'Bayaran manual (admin)');
    }

    public function extendTrial(Workspace $workspace, int $days = 7): void
    {
        $base = $workspace->trial_ends_at !== null && $workspace->trial_ends_at->isFuture() ? $workspace->trial_ends_at : now();
        $workspace->forceFill(['trial_ends_at' => $base->copy()->addDays($days)])->save();
    }

    public function setSuspended(Workspace $workspace, bool $suspended): void
    {
        $workspace->forceFill(['suspended_at' => $suspended ? now() : null])->save();
    }
}
