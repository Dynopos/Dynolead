<?php

namespace App\Services\Ai;

use App\Exceptions\BudgetExceeded;
use App\Models\AiUsage;
use App\Models\Setting;

/**
 * Monthly AI cost limits (spec §9.4). Checked before every Claude call.
 *
 *  - Platform limit (AI_MONTHLY_BUDGET_MYR): all customers together, protecting the
 *    central Anthropic key. Always applies.
 *  - Workspace limit: only when one is set (the admin's own workspace on the Kos page).
 *    Customers pay for their searches from a prepaid balance, and free extras are capped per lead, so they
 *    have no separate RM limit.
 */
class AiBudget
{
    public const SETTING_KEY = 'ai_monthly_budget_myr';

    public const PLATFORM_MESSAGE = 'Perkhidmatan AI berehat sekejap sebab had penggunaan platform bulan ini dah dicapai. Kami sedang uruskan, cuba lagi nanti.';

    /** Workspace limit, or the platform limit when the workspace has none. */
    public function limit(): float
    {
        $value = Setting::get(self::SETTING_KEY);

        return is_numeric($value) ? (float) $value : $this->platformLimit();
    }

    public function setLimit(float $myr): void
    {
        Setting::put(self::SETTING_KEY, (string) max(0, round($myr, 2)));
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
