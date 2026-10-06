<?php

use App\Models\PlacesUsage;
use App\Services\Places\PlacesClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('asks Text Search for cheap fields only', function () {
    fakePlaces([apiPlace('p1')]);

    $result = app(PlacesClient::class)->textSearch('kedai runcit Pasir Mas', 20);

    expect($result['places'])->toHaveCount(1)
        ->and($result['places'][0])->toMatchArray([
            'id' => 'p1', 'name' => 'Kedai p1', 'rating' => 4.4, 'review_count' => 120,
        ]);

    Http::assertSent(function (Request $request) {
        $mask = $request->header('X-Goog-FieldMask')[0] ?? '';

        return str_ends_with($request->url(), '/v1/places:searchText')
            && $request->header('X-Goog-Api-Key')[0] === 'test-places-key'
            && $request['textQuery'] === 'kedai runcit Pasir Mas'
            && str_contains($mask, 'places.id')
            && str_contains($mask, 'places.rating')
            && str_contains($mask, 'places.userRatingCount')
            && ! str_contains($mask, 'Phone')
            && ! str_contains($mask, 'websiteUri')
            && ! str_contains($mask, 'reviews')
            && ! str_contains($mask, '*');
    });

    expect(PlacesUsage::where('sku', 'text_search')->count())->toBe(1);
});

it('asks Place Details for phone, website and reviews, trimmed to 5 x 300 characters', function () {
    $reviews = collect(range(1, 7))->map(fn ($i) => [
        'rating' => 4,
        'originalText' => ['text' => str_repeat("Review {$i} ", 60)],
    ])->all();

    fakePlaces([], ['p1' => apiDetails('p1', ['websiteUri' => 'https://kedai.my', 'reviews' => $reviews])]);

    $place = app(PlacesClient::class)->details('p1');

    expect($place['phone'])->toBe('011-1234 5678')
        ->and($place['website'])->toBe('https://kedai.my')
        ->and($place['reviews'])->toHaveCount(5);

    foreach ($place['reviews'] as $review) {
        expect(mb_strlen($review['text']))->toBeLessThanOrEqual(300);
    }

    Http::assertSent(function (Request $request) {
        $mask = $request->header('X-Goog-FieldMask')[0] ?? '';

        return str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/v1/places/p1')
            && str_contains($mask, 'nationalPhoneNumber')
            && str_contains($mask, 'websiteUri')
            && str_contains($mask, 'reviews');
    });
});

it('leaves reviews out of the display refresh', function () {
    fakePlaces([]);

    app(PlacesClient::class)->details('p1', withReviews: false);

    Http::assertSent(fn (Request $request) => ! str_contains($request->header('X-Goog-FieldMask')[0] ?? '', 'reviews'));
    expect(PlacesUsage::where('sku', 'details_display')->count())->toBe(1);
});

it('throws a clear error when Google fails', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

    app(PlacesClient::class)->textSearch('kedai');
})->throws(App\Exceptions\PlacesException::class, 'API key not valid');
