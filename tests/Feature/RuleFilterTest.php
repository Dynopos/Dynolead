<?php

use App\Models\ContactLog;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Suppression;
use App\Services\Leads\RuleFilter;
use App\Services\Places\PlaceData;

function placeSummary(string $id, array $overrides = []): array
{
    return PlaceData::fromApi(apiPlace($id, $overrides));
}

function placeDetails(string $id, array $overrides = []): array
{
    return PlaceData::fromApi(apiDetails($id, $overrides), withDetails: true, withReviews: true);
}

beforeEach(function () {
    $this->product = Product::factory()->create([
        'filters' => ['min_rating' => 4.0, 'min_reviews' => 20, 'require_no_website' => false],
    ]);
    $this->filter = app(RuleFilter::class);
});

it('drops suppressed shops, for every product', function () {
    Suppression::create(['place_id' => 'stop1', 'reason' => 'STOP']);

    $result = $this->filter->beforeDetails($this->product, [placeSummary('stop1'), placeSummary('ok1')]);

    expect(array_column($result->passed, 'id'))->toBe(['ok1'])
        ->and($result->rejected)->toBe(['stop1' => RuleFilter::SUPPRESSED]);
});

it('drops shops whose phone number is suppressed', function () {
    Suppression::create(['phone' => '601112345678', 'reason' => 'STOP']);

    expect($this->filter->afterDetails($this->product, placeDetails('other-branch')))->toBe(RuleFilter::SUPPRESSED);
});

it('drops shops contacted in the last 30 days for any product', function () {
    $other = Product::factory()->create();
    ContactLog::create(['place_id' => 'recent', 'product_id' => $other->id, 'contacted_at' => now()->subDays(29)]);
    ContactLog::create(['place_id' => 'old', 'product_id' => $other->id, 'contacted_at' => now()->subDays(31)]);

    $result = $this->filter->beforeDetails($this->product, [placeSummary('recent'), placeSummary('old')]);

    expect(array_column($result->passed, 'id'))->toBe(['old'])
        ->and($result->rejected['recent'])->toBe(RuleFilter::CONTACTED);
});

it('drops shops below the minimum rating or review count', function () {
    $result = $this->filter->beforeDetails($this->product, [
        placeSummary('low', ['rating' => 3.9]),
        placeSummary('few', ['userRatingCount' => 19]),
        placeSummary('none', ['rating' => null, 'userRatingCount' => null]),
        placeSummary('ok', ['rating' => 4.0, 'userRatingCount' => 20]),
    ]);

    expect(array_column($result->passed, 'id'))->toBe(['ok'])
        ->and($result->rejected)->toMatchArray([
            'low' => RuleFilter::LOW_RATING,
            'few' => RuleFilter::FEW_REVIEWS,
            'none' => RuleFilter::LOW_RATING,
        ]);
});

it('drops closed shops and shops that already have a lead for this product', function () {
    Lead::factory()->for($this->product)->create(['place_id' => 'has-lead']);

    $result = $this->filter->beforeDetails($this->product, [
        placeSummary('closed', ['businessStatus' => 'CLOSED_PERMANENTLY']),
        placeSummary('has-lead'),
    ]);

    expect($result->passed)->toBe([])
        ->and($result->rejected)->toBe(['closed' => RuleFilter::CLOSED, 'has-lead' => RuleFilter::EXISTING_LEAD]);
});

it('drops shops with a website when require_no_website is true', function () {
    $this->product->update(['filters' => ['min_rating' => 0, 'min_reviews' => 0, 'require_no_website' => true]]);

    expect($this->filter->afterDetails($this->product, placeDetails('web', ['websiteUri' => 'https://kedai.my'])))->toBe(RuleFilter::HAS_WEBSITE)
        ->and($this->filter->afterDetails($this->product, placeDetails('noweb')))->toBeNull();
});

it('keeps shops with a website when require_no_website is false', function () {
    expect($this->filter->afterDetails($this->product, placeDetails('web', ['websiteUri' => 'https://kedai.my'])))->toBeNull();
});

it('drops shops with no usable phone number', function () {
    expect($this->filter->afterDetails($this->product, placeDetails('nophone', ['nationalPhoneNumber' => null, 'internationalPhoneNumber' => null])))
        ->toBe(RuleFilter::NO_PHONE);
});

it('keeps landline shops (no WhatsApp, but Bob can call or visit)', function () {
    expect($this->filter->afterDetails($this->product, placeDetails('landline', ['nationalPhoneNumber' => '09-790 1234'])))->toBeNull();
});
