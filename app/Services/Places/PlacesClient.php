<?php

namespace App\Services\Places;

use App\Exceptions\PlacesException;
use App\Models\PlacesUsage;
use App\Services\Costs\PriceTable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The only class that talks to Google Places API (New).
 *
 * Field masks keep cost down: Text Search asks for cheap fields only. Phone,
 * website and reviews come from Place Details, for candidates that passed the
 * rule filter.
 */
class PlacesClient
{
    public const TEXT_SEARCH_FIELDS = [
        'places.id',
        'places.displayName',
        'places.types',
        'places.primaryType',
        'places.rating',
        'places.userRatingCount',
        'places.shortFormattedAddress',
        'places.businessStatus',
        'nextPageToken',
    ];

    public const DETAILS_FIELDS = [
        'id',
        'displayName',
        'types',
        'primaryType',
        'rating',
        'userRatingCount',
        'shortFormattedAddress',
        'businessStatus',
        'nationalPhoneNumber',
        'internationalPhoneNumber',
        'websiteUri',
        'googleMapsUri',
        'reviews',
    ];

    /** Details for the lead card when the cache has expired: no reviews (cheaper SKU). */
    public const DISPLAY_FIELDS = [
        'id',
        'displayName',
        'types',
        'primaryType',
        'rating',
        'userRatingCount',
        'shortFormattedAddress',
        'businessStatus',
        'nationalPhoneNumber',
        'internationalPhoneNumber',
        'websiteUri',
        'googleMapsUri',
    ];

    public function __construct(private PriceTable $prices) {}

    /**
     * @return array{places: array<int, array>, next_page_token: ?string}
     */
    public function textSearch(string $query, int $pageSize = 20, ?string $pageToken = null, ?int $searchId = null): array
    {
        $body = array_filter([
            'textQuery' => $query,
            'pageSize' => max(1, min(20, $pageSize)),
            'pageToken' => $pageToken,
            'languageCode' => config('dynoleads.places.language'),
            'regionCode' => config('dynoleads.places.region'),
        ], fn ($v) => $v !== null);

        $response = $this->request(self::TEXT_SEARCH_FIELDS)
            ->post('/places:searchText', $body);

        $this->record('text_search', null, $searchId);
        $json = $this->ensureOk($response, 'Text Search');

        return [
            'places' => array_map([PlaceData::class, 'fromApi'], $json['places'] ?? []),
            'next_page_token' => $json['nextPageToken'] ?? null,
        ];
    }

    /** Place Details. With reviews for scoring, without for display refresh. */
    public function details(string $placeId, bool $withReviews = true, ?int $searchId = null): array
    {
        $fields = $withReviews ? self::DETAILS_FIELDS : self::DISPLAY_FIELDS;

        $response = $this->request($fields)
            ->get('/places/'.rawurlencode($placeId), [
                'languageCode' => config('dynoleads.places.language'),
                'regionCode' => config('dynoleads.places.region'),
            ]);

        $this->record($withReviews ? 'details' : 'details_display', $placeId, $searchId);
        $json = $this->ensureOk($response, 'Place Details');

        return PlaceData::fromApi($json, withDetails: true, withReviews: $withReviews);
    }

    private function request(array $fields): PendingRequest
    {
        $key = (string) config('services.google_places.key');

        if ($key === '') {
            throw new PlacesException('GOOGLE_PLACES_API_KEY belum diset dalam .env.');
        }

        return Http::baseUrl(config('services.google_places.base_url'))
            ->timeout((int) config('services.google_places.timeout', 30))
            ->acceptJson()
            ->withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => implode(',', $fields),
            ]);
    }

    private function ensureOk(Response $response, string $what): array
    {
        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new PlacesException("Google Places {$what} gagal ({$response->status()}): ".mb_substr((string) $message, 0, 300));
        }

        return (array) $response->json();
    }

    private function record(string $sku, ?string $placeId, ?int $searchId): void
    {
        PlacesUsage::query()->create([
            'sku' => $sku,
            'place_id' => $placeId,
            'search_id' => $searchId,
            'cost_estimate' => $this->prices->placesCostMyr($sku),
        ]);
    }
}
