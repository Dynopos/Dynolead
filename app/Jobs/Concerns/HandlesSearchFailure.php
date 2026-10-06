<?php

namespace App\Jobs\Concerns;

use App\Enums\SearchStatus;
use App\Models\Search;
use Throwable;

trait HandlesSearchFailure
{
    public function failed(?Throwable $e): void
    {
        Search::query()->withoutGlobalScope('workspace')->find($this->searchId)?->markStatus(
            SearchStatus::Failed,
            mb_substr($e?->getMessage() ?? 'Ralat tidak diketahui', 0, 500),
        );
    }
}
