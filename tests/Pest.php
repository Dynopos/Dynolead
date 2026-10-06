<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Feature tests run against an in-memory SQLite database. No test may call a
| real external API: every HTTP call must be faked with Http::fake().
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit');

/** Log in as the single Fasa 0 owner. */
function actingAsOwner(): Tests\TestCase
{
    return test()->withSession(['owner' => true]);
}

/** A Places API (New) place object, as Google returns it. */
function apiPlace(string $id, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'displayName' => ['text' => 'Kedai '.$id, 'languageCode' => 'ms'],
        'types' => ['grocery_store', 'food', 'store'],
        'primaryType' => 'grocery_store',
        'rating' => 4.4,
        'userRatingCount' => 120,
        'shortFormattedAddress' => 'Jalan Pasar, Pasir Mas',
        'businessStatus' => 'OPERATIONAL',
    ], $overrides);
}

/** Place Details payload: summary fields plus phone, website and reviews. */
function apiDetails(string $id, array $overrides = []): array
{
    return array_replace(apiPlace($id), [
        'nationalPhoneNumber' => '011-1234 5678',
        'internationalPhoneNumber' => '+60 11-1234 5678',
        'googleMapsUri' => 'https://maps.google.com/?cid='.$id,
        'reviews' => [
            ['rating' => 5, 'text' => ['text' => 'Layanan mesra, barang lengkap.'], 'originalText' => ['text' => 'Layanan mesra, barang lengkap.']],
            ['rating' => 2, 'text' => ['text' => 'Kaunter lambat waktu petang.'], 'originalText' => ['text' => 'Kaunter lambat waktu petang.']],
        ],
    ], $overrides);
}

/**
 * Fake Google Places: Text Search returns $places (one page) and Place Details
 * returns $details[place_id] (or a default details payload).
 */
function fakePlaces(array $places, array $details = []): void
{
    Illuminate\Support\Facades\Http::fake([
        'places.googleapis.com/v1/places:searchText' => Illuminate\Support\Facades\Http::response(['places' => $places]),
        'places.googleapis.com/v1/places/*' => function (Illuminate\Http\Client\Request $request) use ($details) {
            $id = rawurldecode(Illuminate\Support\Str::of(parse_url($request->url(), PHP_URL_PATH))->afterLast('/')->toString());

            return Illuminate\Support\Facades\Http::response($details[$id] ?? apiDetails($id));
        },
    ]);
}
