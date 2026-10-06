<?php

namespace App\Services\Costs;

use App\Models\AiUsage;
use App\Models\PlacesUsage;
use App\Services\Ai\AiBudget;
use Illuminate\Support\Collection;

/** Numbers for the Kos page (spec §3.5). */
class CostReport
{
    public function __construct(private AiBudget $budget) {}

    public function thisMonth(): array
    {
        $start = now()->startOfMonth();

        $ai = AiUsage::query()->where('created_at', '>=', $start)
            ->selectRaw('COUNT(*) as calls, COALESCE(SUM(input_tokens),0) as input, COALESCE(SUM(output_tokens),0) as output, COALESCE(SUM(cache_read_tokens),0) as cache_read, COALESCE(SUM(cache_write_tokens),0) as cache_write, COALESCE(SUM(cost_estimate),0) as cost')
            ->first();

        $places = PlacesUsage::query()->where('created_at', '>=', $start)
            ->selectRaw('COUNT(*) as calls, COALESCE(SUM(cost_estimate),0) as cost')
            ->first();

        $limit = $this->budget->limit();
        $spent = (float) $ai->cost;

        return [
            'ai_calls' => (int) $ai->calls,
            'input_tokens' => (int) $ai->input,
            'output_tokens' => (int) $ai->output,
            'cache_read_tokens' => (int) $ai->cache_read,
            'cache_write_tokens' => (int) $ai->cache_write,
            'ai_cost' => round($spent, 4),
            'places_calls' => (int) $places->calls,
            'places_cost' => round((float) $places->cost, 4),
            'limit' => $limit,
            'percent' => $limit > 0 ? min(100, round($spent / $limit * 100, 1)) : 100,
            'exhausted' => $this->budget->isExhausted(),
        ];
    }

    /** @return Collection<int, AiUsage> */
    public function lastCalls(int $limit = 50): Collection
    {
        return AiUsage::query()->latest('id')->limit($limit)->get();
    }
}
