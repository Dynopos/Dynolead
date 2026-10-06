<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesSearchFailure;
use App\Models\Search;
use App\Services\Search\SearchPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Pipeline step: Rule filter on Text Search results. */
class FilterCandidatesJob implements ShouldQueue
{
    use HandlesSearchFailure, Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 600;

    public function __construct(public int $searchId) {}

    public function handle(SearchPipeline $pipeline): void
    {
        $search = Search::query()->find($this->searchId);

        if ($search === null || $search->status->isFinished()) {
            return;
        }

        $pipeline->filterCandidates($search);
    }
}
