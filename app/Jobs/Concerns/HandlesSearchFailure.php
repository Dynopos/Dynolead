<?php

namespace App\Jobs\Concerns;

use App\Enums\SearchStatus;
use App\Models\Search;
use App\Services\Billing\CreditService;
use Throwable;

trait HandlesSearchFailure
{
    public function failed(?Throwable $e): void
    {
        $search = Search::query()->withoutGlobalScope('workspace')->find($this->searchId);

        if ($search === null) {
            return;
        }

        $search->markStatus(SearchStatus::Failed, mb_substr($e?->getMessage() ?? 'Ralat tidak diketahui', 0, 500));
        app(CreditService::class)->settleSearch($search);
    }
}
