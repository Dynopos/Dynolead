<?php

namespace App\Services\Billing;

use App\Models\Lead;
use App\Models\Product;
use App\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/** Plans, access and quotas for a workspace (Fasa 2). */
class PlanService
{
    public function __construct(private CurrentWorkspace $current) {}

    /** @return array<string, Plan> */
    public function all(): array
    {
        $plans = [];
        foreach ((array) config('plans.plans', []) as $key => $config) {
            $plans[$key] = Plan::fromConfig($key, $config);
        }

        return $plans;
    }

    /** @return array<string, Plan> */
    public function forSale(): array
    {
        return array_filter($this->all(), fn (Plan $p) => $p->purchasable);
    }

    public function find(string $key): Plan
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Pelan {$key} tidak wujud.");
    }

    public function workspace(?Workspace $workspace = null): Workspace
    {
        return $workspace ?? $this->current->get() ?? throw new InvalidArgumentException('Tiada workspace semasa.');
    }

    public function planOf(?Workspace $workspace = null): Plan
    {
        $workspace = $this->workspace($workspace);

        return $this->all()[$workspace->plan] ?? $this->find((string) config('plans.trial_plan'));
    }

    /** Until when the workspace may search and use AI; null = no end (internal plan). */
    public function accessEndsAt(?Workspace $workspace = null): ?Carbon
    {
        $workspace = $this->workspace($workspace);

        if ($workspace->plan === 'dalaman') {
            return null;
        }

        return $workspace->paid_until !== null && ($workspace->trial_ends_at === null || $workspace->paid_until->gt($workspace->trial_ends_at))
            ? $workspace->paid_until
            : $workspace->trial_ends_at;
    }

    public function isActive(?Workspace $workspace = null): bool
    {
        $workspace = $this->workspace($workspace);

        if ($workspace->suspended_at !== null) {
            return false;
        }

        $ends = $this->accessEndsAt($workspace);

        return $ends === null || $ends->isFuture();
    }

    public function isTrial(?Workspace $workspace = null): bool
    {
        return $this->workspace($workspace)->plan === config('plans.trial_plan');
    }

    /** Why this workspace cannot start new paid work, or null. */
    public function accessBlocker(?Workspace $workspace = null): ?string
    {
        $workspace = $this->workspace($workspace);

        if ($workspace->suspended_at !== null) {
            return 'Akaun ini digantung. Hubungi kami.';
        }

        if (! $this->isActive($workspace)) {
            return $this->isTrial($workspace)
                ? 'Tempoh percubaan dah tamat. Langgan untuk teruskan cari lead.'
                : 'Langganan dah tamat. Bayar untuk teruskan cari lead.';
        }

        return null;
    }

    public function leadsUsedThisMonth(?Workspace $workspace = null): int
    {
        $workspace = $this->workspace($workspace);

        return Lead::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspace->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    /** Leads left this month; null = no limit. */
    public function leadsRemaining(?Workspace $workspace = null): ?int
    {
        $quota = $this->planOf($workspace)->monthlyLeads;

        return $quota === null ? null : max(0, $quota - $this->leadsUsedThisMonth($workspace));
    }

    public function canAddProduct(?Workspace $workspace = null): bool
    {
        $workspace = $this->workspace($workspace);
        $max = $this->planOf($workspace)->maxProducts;

        return $max === null || Product::query()->withoutGlobalScope('workspace')->where('workspace_id', $workspace->id)->count() < $max;
    }

    public function maxCandidates(?Workspace $workspace = null): int
    {
        return $this->planOf($workspace)->maxCandidates;
    }
}
