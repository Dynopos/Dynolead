<?php

namespace App\Jobs;

use App\Services\Places\PlaceRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Daily: delete Google Places content older than PLACES_CACHE_HOURS. */
class PurgePlaceCacheJob implements ShouldQueue
{
    use Queueable;

    public function handle(PlaceRepository $repository): void
    {
        $repository->purgeExpired();
    }
}
