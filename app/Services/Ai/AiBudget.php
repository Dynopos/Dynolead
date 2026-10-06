<?php

namespace App\Services\Ai;

use App\Exceptions\BudgetExceeded;
use App\Models\AiUsage;
use App\Models\Setting;

/** Monthly AI cost limit (spec §9.4). Checked before every Claude call. */
class AiBudget
{
    public const SETTING_KEY = 'ai_monthly_budget_myr';

    public function limit(): float
    {
        $value = Setting::get(self::SETTING_KEY);

        return is_numeric($value) ? (float) $value : (float) config('dynoleads.ai.monthly_budget_myr');
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

    public function remaining(): float
    {
        return max(0, $this->limit() - $this->spentThisMonth());
    }

    public function isExhausted(): bool
    {
        return $this->spentThisMonth() >= $this->limit();
    }

    /** @throws BudgetExceeded */
    public function assertCanSpend(float $estimateMyr): void
    {
        if ($this->spentThisMonth() + max(0, $estimateMyr) > $this->limit()) {
            throw new BudgetExceeded;
        }
    }
}
