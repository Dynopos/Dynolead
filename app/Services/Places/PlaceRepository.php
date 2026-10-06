<?php

namespace App\Services\Places;

use App\Models\PlaceCache;

/**
 * Short-lived cache in front of PlacesClient (spec §6).
 *
 * Only place_id is kept long term. Everything else lives in place_cache for
 * PLACES_CACHE_HOURS and is fetched again from Google when it expires.
 */
class PlaceRepository
{
    public function __construct(private PlacesClient $client) {}

    /** Store a Text Search result without overwriting fresher details. */
    public function storeSummary(array $place): void
    {
        $cache = PlaceCache::query()->fresh()->where('place_id', $place['id'])->first();

        if ($cache && $cache->has_details) {
            return;
        }

        PlaceCache::query()->updateOrCreate(
            ['place_id' => $place['id']],
            ['payload' => $place, 'has_details' => false, 'fetched_at' => now()],
        );
    }

    /** Fresh cached payload, or null. Never calls Google. */
    public function cached(string $placeId): ?array
    {
        return PlaceCache::query()->fresh()->where('place_id', $placeId)->first()?->payload;
    }

    /** Details with reviews, for the rule filter and AI scoring. */
    public function details(string $placeId, ?int $searchId = null): array
    {
        $cache = PlaceCache::query()->fresh()->where('place_id', $placeId)->first();

        if ($cache && $cache->has_details && array_key_exists('reviews', $cache->payload)) {
            return $cache->payload;
        }

        return $this->put($this->client->details($placeId, withReviews: true, searchId: $searchId));
    }

    /** Details for the lead card. Uses the cheaper field mask when the cache has expired. */
    public function forDisplay(string $placeId): array
    {
        $cache = PlaceCache::query()->fresh()->where('place_id', $placeId)->first();

        if ($cache && $cache->has_details) {
            return $cache->payload;
        }

        return $this->put($this->client->details($placeId, withReviews: false));
    }

    private function put(array $place): array
    {
        PlaceCache::query()->updateOrCreate(
            ['place_id' => $place['id']],
            ['payload' => $place, 'has_details' => true, 'fetched_at' => now()],
        );

        return $place;
    }

    /** Delete cache rows older than PLACES_CACHE_HOURS. */
    public function purgeExpired(): int
    {
        return PlaceCache::query()->expired()->delete();
    }
}
