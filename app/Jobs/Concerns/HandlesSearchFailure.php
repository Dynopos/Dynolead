<?php

namespace App\Jobs\Concerns;

use App\Enums\SearchStatus;
use App\Models\Search;
use App\Services\Billing\WalletService;
use Throwable;

trait HandlesSearchFailure
{
    public function failed(?Throwable $e): void
    {
        $search = Search::query()->withoutGlobalScope('workspace')->find($this->searchId);

        if ($search === null) {
            return;
        }

        // Charge what the search really used before it failed (nothing in a trial).
        app(WalletService::class)->settle($search);
        $search->markStatus(SearchStatus::Failed, mb_substr($e?->getMessage() ?? 'Ralat tidak diketahui', 0, 500));
    }
}
