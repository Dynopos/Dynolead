<?php

use App\Enums\LeadStatus;
use App\Enums\SearchStatus;
use App\Jobs\FetchDetailsJob;
use App\Jobs\FilterCandidatesJob;
use App\Jobs\ScoreLeadsJob;
use App\Jobs\SearchPlacesJob;
use App\Models\ContactLog;
use App\Models\Lead;
use App\Models\PlaceCache;
use App\Models\Product;
use App\Models\Search;
use App\Models\Suppression;
use App\Services\Search\SearchPipeline;
use Database\Seeders\ProductSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(ProductSeeder::class);
    $this->dynopos = Product::where('slug', 'dynopos')->first();
    $this->murah = Product::where('slug', 'murahwebsite')->first();
});

/** Run the pipeline without the AI steps (those have their own tests). */
function runPlacesSteps(Product $product, int $max = 20): Search
{
    Queue::fake([ScoreLeadsJob::class]);

    return app(SearchPipeline::class)->start($product, 'kedai runcit', ['Pasir Mas, Kelantan'], $max)->refresh();
}

it('runs every pipeline step as its own queued job', function () {
    Queue::fake();

    $search = app(SearchPipeline::class)->start($this->dynopos, 'kedai runcit', ['Pasir Mas, Kelantan'], 20);

    Queue::assertPushed(SearchPlacesJob::class, fn ($job) => $job->searchId === $search->id);
    Queue::assertNotPushed(FilterCandidatesJob::class);

    fakePlaces([apiPlace('p1')]);
    (new SearchPlacesJob($search->id))->handle(app(SearchPipeline::class));
    Queue::assertPushed(FilterCandidatesJob::class);
    Queue::assertNotPushed(FetchDetailsJob::class);

    (new FilterCandidatesJob($search->id))->handle(app(SearchPipeline::class));
    Queue::assertPushed(FetchDetailsJob::class);
});

it('turns a search into leads and only fetches details for candidates that passed', function () {
    fakePlaces([
        apiPlace('good1'),
        apiPlace('good2'),
        apiPlace('low', ['rating' => 2.5]),
        apiPlace('few', ['userRatingCount' => 3]),
    ]);

    $search = runPlacesSteps($this->dynopos);

    Queue::assertPushed(ScoreLeadsJob::class, fn ($job) => $job->searchId === $search->id);

    expect($search->status)->toBe(SearchStatus::Details)
        ->and($search->found_count)->toBe(4)
        ->and($search->passed_count)->toBe(2)
        ->and($search->lead_count)->toBe(2)
        ->and(Lead::pluck('place_id')->sort()->values()->all())->toBe(['good1', 'good2'])
        ->and(Lead::first()->status)->toBe(LeadStatus::Baru)
        ->and(Lead::first()->area)->toBe('Pasir Mas, Kelantan');

    // 1 Text Search + 2 Details. No details for the filtered-out shops.
    Http::assertSentCount(3);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/places/low') || str_contains($r->url(), '/places/few'));
});

it('never stores Google content in leads, only place_id', function () {
    fakePlaces([apiPlace('good1')]);

    runPlacesSteps($this->dynopos);

    $lead = Lead::first()->getAttributes();
    expect(json_encode($lead))->not->toContain('Kedai good1')
        ->and(json_encode($lead))->not->toContain('011-1234');
});

it('hides shops with a websiteUri from murahwebsite.my', function () {
    fakePlaces(
        [apiPlace('with-site'), apiPlace('no-site')],
        ['with-site' => apiDetails('with-site', ['websiteUri' => 'https://kedai.my'])],
    );

    $search = runPlacesSteps($this->murah);

    expect(Lead::pluck('place_id')->all())->toBe(['no-site'])
        ->and($search->rejections['with-site'])->toBe('Dah ada website');
});

it('skips suppressed shops and shops contacted for another product in the last 30 days', function () {
    Suppression::create(['place_id' => 'stopped', 'reason' => 'STOP']);
    ContactLog::create(['place_id' => 'contacted', 'product_id' => $this->dynopos->id, 'contacted_at' => now()->subDays(10)]);

    fakePlaces([apiPlace('stopped'), apiPlace('contacted'), apiPlace('fresh')]);

    runPlacesSteps($this->murah);

    expect(Lead::pluck('place_id')->all())->toBe(['fresh']);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/places/stopped') || str_contains($r->url(), '/places/contacted'));
});

it('allows a shop again after the 30 day window', function () {
    ContactLog::create(['place_id' => 'old', 'product_id' => $this->dynopos->id, 'contacted_at' => now()->subDays(31)]);
    fakePlaces([apiPlace('old')]);

    runPlacesSteps($this->murah);

    expect(Lead::pluck('place_id')->all())->toBe(['old']);
});

it('caps a search at 60 candidates', function () {
    expect(SearchPipeline::clampMax(500))->toBe(60)
        ->and(SearchPipeline::clampMax(0))->toBe(1);
});

it('stops paging once it has enough candidates', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::sequence()
            ->push(['places' => collect(range(1, 20))->map(fn ($i) => apiPlace("a{$i}"))->all(), 'nextPageToken' => 'next'])
            ->push(['places' => collect(range(21, 40))->map(fn ($i) => apiPlace("a{$i}"))->all(), 'nextPageToken' => 'more']),
        'places.googleapis.com/v1/places/*' => Http::response(apiDetails('x')),
    ]);
    Queue::fake();

    $search = Search::factory()->for($this->dynopos)->create(['max_candidates' => 25]);
    app(SearchPipeline::class)->searchPlaces($search);

    expect($search->refresh()->found_count)->toBe(25);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r) => ($r['pageSize'] ?? null) === 5 && ($r['pageToken'] ?? null) === 'next');
});

it('marks the search as failed when Google returns an error', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429)]);

    $search = Search::factory()->for($this->dynopos)->create();
    $job = new SearchPlacesJob($search->id);

    try {
        $job->handle(app(SearchPipeline::class));
    } catch (Throwable $e) {
        $job->failed($e);
    }

    expect($search->refresh()->status)->toBe(SearchStatus::Failed)
        ->and($search->error)->toContain('quota');
});

it('keeps place content only in the temporary cache', function () {
    fakePlaces([apiPlace('good1')]);

    runPlacesSteps($this->dynopos);

    expect(PlaceCache::where('place_id', 'good1')->first()->payload['name'])->toBe('Kedai good1');
});
