<?php

namespace App\Services\Ai;

use App\Exceptions\BudgetExceeded;
use App\Models\AiUsage;
use App\Models\Setting;
use App\Services\Billing\PlanService;
use App\Support\Tenancy\CurrentWorkspace;

/**
 * Monthly AI cost limits (spec §9.4). Checked before every Claude call.
 *
 * Two limits apply:
 *  - the workspace limit: the customer's own setting, never above their plan's ai_budget_myr;
 *  - the platform limit (AI_MONTHLY_BUDGET_MYR): all customers together, protecting the
 *    central Anthropic key.
 */
class AiBudget
{
    public const SETTING_KEY = 'ai_monthly_budget_myr';

    public const PLATFORM_MESSAGE = 'Perkhidmatan AI berehat sekejap sebab had penggunaan platform bulan ini dah dicapai. Kami sedang uruskan, cuba lagi nanti.';

    public function __construct(
        private PlanService $plans,
        private CurrentWorkspace $current,
    ) {}

    /** The plan's cap for this workspace, or null when the plan has none. */
    public function planCap(): ?float
    {
        return $this->current->id() === null ? null : $this->plans->planOf()->aiBudgetMyr;
    }

    public function limit(): float
    {
        $value = Setting::get(self::SETTING_KEY);
        $cap = $this->planCap();
        $own = is_numeric($value) ? (float) $value : ($cap ?? (float) config('dynoleads.ai.monthly_budget_myr'));

        return $cap === null ? $own : min($own, $cap);
    }

    public function setLimit(float $myr): void
    {
        $cap = $this->planCap();
        $myr = max(0, round($myr, 2));

        Setting::put(self::SETTING_KEY, (string) ($cap === null ? $myr : min($myr, $cap)));
    }

    public function spentThisMonth(): float
    {
        return (float) AiUsage::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost_estimate');
    }

    public function platformLimit(): float
    {
        return (float) config('dynoleads.ai.monthly_budget_myr');
    }

    public function platformSpentThisMonth(): float
    {
        return (float) AiUsage::query()->withoutGlobalScope('workspace')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost_estimate');
    }

    public function remaining(): float
    {
        return max(0, $this->limit() - $this->spentThisMonth());
    }

    public function isExhausted(): bool
    {
        return $this->spentThisMonth() >= $this->limit()
            || $this->platformSpentThisMonth() >= $this->platformLimit();
    }

    /** @throws BudgetExceeded */
    public function assertCanSpend(float $estimateMyr): void
    {
        $estimateMyr = max(0, $estimateMyr);

        if ($this->platformSpentThisMonth() + $estimateMyr > $this->platformLimit()) {
            throw new BudgetExceeded(self::PLATFORM_MESSAGE);
        }

        if ($this->spentThisMonth() + $estimateMyr > $this->limit()) {
            throw new BudgetExceeded;
        }
    }
}
