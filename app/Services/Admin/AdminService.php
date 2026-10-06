<?php

namespace App\Services\Admin;

use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PlacesUsage;
use App\Models\Workspace;
use App\Services\Ai\AiBudget;
use App\Services\Billing\BillingService;
use App\Services\Billing\PlanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Platform view for the admin (Bob). Reads across all workspaces on purpose. */
class AdminService
{
    public function __construct(
        private PlanService $plans,
        private BillingService $billing,
        private AiBudget $budget,
    ) {}

    public function totals(): array
    {
        $start = now()->startOfMonth();

        $workspaces = Workspace::query()->where('plan', '!=', 'dalaman')->get();

        return [
            'customers' => $workspaces->count(),
            'active_paid' => $workspaces->filter(fn ($w) => ! $this->plans->isTrial($w) && $this->plans->isActive($w))->count(),
            'trials' => $workspaces->filter(fn ($w) => $this->plans->isTrial($w) && $this->plans->isActive($w))->count(),
            'revenue_month' => Payment::query()->withoutGlobalScope('workspace')->whereIn('status', ['paid', 'manual'])->where('paid_at', '>=', $start)->sum('amount_sen') / 100,
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
        $leads = Lead::query()->withoutGlobalScope('workspace')->whereIn('workspace_id', $ids)->where('created_at', '>=', $start)
            ->selectRaw('workspace_id, COUNT(*) as n')->groupBy('workspace_id')->pluck('n', 'workspace_id');
        $ai = AiUsage::query()->withoutGlobalScope('workspace')->whereIn('workspace_id', $ids)->where('created_at', '>=', $start)
            ->selectRaw('workspace_id, SUM(cost_estimate) as c')->groupBy('workspace_id')->pluck('c', 'workspace_id');

        return $workspaces->map(fn (Workspace $w) => [
            'workspace' => $w,
            'owner' => $w->users->first(),
            'plan' => $this->plans->planOf($w),
            'active' => $this->plans->isActive($w),
            'ends_at' => $this->plans->accessEndsAt($w),
            'leads_month' => (int) ($leads[$w->id] ?? 0),
            'ai_month' => round((float) ($ai[$w->id] ?? 0), 2),
        ]);
    }

    public function addPaidPeriod(Workspace $workspace, string $plan, ?string $note): void
    {
        $this->billing->recordManual($workspace, $plan, $note ?: 'Bayaran manual (admin)');
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
