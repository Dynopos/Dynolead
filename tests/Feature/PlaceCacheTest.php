<?php

use App\Jobs\PurgePlaceCacheJob;
use App\Models\PlaceCache;
use App\Services\Places\PlaceRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

it('purges cached place data after PLACES_CACHE_HOURS', function () {
    config(['dynoleads.places.cache_hours' => 24]);

    PlaceCache::create(['place_id' => 'old', 'payload' => ['id' => 'old'], 'fetched_at' => now()->subHours(25)]);
    PlaceCache::create(['place_id' => 'new', 'payload' => ['id' => 'new'], 'fetched_at' => now()->subHours(23)]);

    dispatch_sync(new PurgePlaceCacheJob);

    expect(PlaceCache::pluck('place_id')->all())->toBe(['new']);

    $this->travel(2)->hours();
    dispatch_sync(new PurgePlaceCacheJob);

    expect(PlaceCache::count())->toBe(0);
});

it('respects a custom PLACES_CACHE_HOURS', function () {
    config(['dynoleads.places.cache_hours' => 2]);

    PlaceCache::create(['place_id' => 'p', 'payload' => ['id' => 'p'], 'fetched_at' => now()->subHours(3)]);

    dispatch_sync(new PurgePlaceCacheJob);

    expect(PlaceCache::count())->toBe(0);
});

it('serves the lead card from cache, and fetches again once the cache expires', function () {
    fakePlaces([]);
    $repo = app(PlaceRepository::class);

    $repo->forDisplay('p1');
    $repo->forDisplay('p1');
    Http::assertSentCount(1);

    $this->travel(25)->hours();
    expect($repo->cached('p1'))->toBeNull();

    $repo->forDisplay('p1');
    Http::assertSentCount(2);
});

it('schedules the cache purge job', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->contains(fn ($e) => str_contains((string) $e->description, 'purge-place-cache')))->toBeTrue();
});
