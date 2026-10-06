<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesSearchFailure;
use App\Models\Search;
use App\Services\Search\SearchPipeline;
use App\Support\Billing\UsageMeter;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Pipeline step: AI scoring (cheap model). */
class ScoreLeadsJob implements ShouldQueue
{
    use HandlesSearchFailure, Queueable;

    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 600;

    public function __construct(public int $searchId) {}

    public function handle(SearchPipeline $pipeline): void
    {
        $search = Search::query()->withoutGlobalScope('workspace')->find($this->searchId);

        if ($search === null || $search->status->isFinished()) {
            return;
        }

        app(CurrentWorkspace::class)->runAs(
            $search->workspace_id,
            fn () => app(UsageMeter::class)->runFor($search, fn () => $pipeline->scoreLeads($search)),
        );
    }
}
