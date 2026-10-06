<?php

namespace App\Services\Admin;

use App\Models\AiUsage;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Models\PlacesUsage;
use App\Models\Search;
use App\Models\Workspace;
use App\Services\Ai\AiBudget;
use App\Services\Billing\BillingService;
use App\Services\Billing\CreditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Platform view for the admin (Bob). Reads across all workspaces on purpose. */
class AdminService
{
    public function __construct(
        private CreditService $credits,
        private BillingService $billing,
        private AiBudget $budget,
    ) {}

    public function totals(): array
    {
        $start = now()->startOfMonth();
        $txns = CreditTransaction::query()->withoutGlobalScope('workspace')->where('created_at', '>=', $start);

        return [
            'customers' => Workspace::query()->where('plan', '!=', 'dalaman')->count(),
            'paying' => Payment::query()->withoutGlobalScope('workspace')->whereIn('status', ['paid', 'manual'])->distinct()->count('workspace_id'),
            'revenue_month' => Payment::query()->withoutGlobalScope('workspace')->whereIn('status', ['paid', 'manual'])->where('paid_at', '>=', $start)->sum('amount_sen') / 100,
            'credits_sold_month' => (int) (clone $txns)->where('reason', 'purchase')->sum('amount'),
            'credits_used_month' => (int) -(clone $txns)->whereIn('reason', ['search', 'refund'])->sum('amount'),
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
            'unlimited' => $w->plan === 'dalaman',
            'searches_month' => (int) ($searches[$w->id] ?? 0),
            'ai_month' => round((float) ($ai[$w->id] ?? 0), 2),
        ]);
    }

    public function grantCredits(Workspace $workspace, int $amount, ?string $note, ?int $adminId): void
    {
        $this->credits->grant($workspace, $amount, 'admin', $note ?: 'Kredit dari admin', userId: $adminId);
    }

    public function recordPackPayment(Workspace $workspace, string $pack, ?string $note): void
    {
        $this->billing->recordManual($workspace, $pack, $note ?: 'Bayaran manual (admin)');
    }

    public function setSuspended(Workspace $workspace, bool $suspended): void
    {
        $workspace->forceFill(['suspended_at' => $suspended ? now() : null])->save();
    }
}
