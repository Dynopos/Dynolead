<?php

/*
| Spec §10 — Kriteria siap Fasa 0.
| One test (or more) per checkbox, named after the box. Other test files cover
| each rule in more detail; this file is the checklist.
*/

use App\Enums\SearchStatus;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\ContactRuleViolation;
use App\Jobs\PurgePlaceCacheJob;
use App\Livewire\LeadsPage;
use App\Livewire\SearchPage;
use App\Models\AiUsage;
use App\Models\ContactLog;
use App\Models\Lead;
use App\Models\PlaceCache;
use App\Models\Product;
use App\Models\Search;
use App\Services\Ai\AiBudget;
use App\Services\Ai\MessageWriter;
use App\Services\Leads\LeadService;
use App\Services\Places\PlacesClient;
use App\Services\Search\SearchPipeline;
use Database\Seeders\ProductSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    withAiPrices();
    $this->seed(ProductSeeder::class);
    $this->dynopos = Product::where('slug', 'dynopos')->first();
    $this->murah = Product::where('slug', 'murahwebsite')->first();
});

function runSearch(Product $product, string $type = 'kedai runcit', string $area = 'Pasir Mas, Kelantan'): Search
{
    return app(SearchPipeline::class)->start($product, $type, [$area], 20)->refresh();
}

it('§10.1 seeds DynoPOS and murahwebsite.my', function () {
    expect($this->dynopos)->not->toBeNull()
        ->and($this->murah)->not->toBeNull()
        ->and($this->dynopos->bannedWords())->toContain('demo')
        ->and($this->murah->requiresNoWebsite())->toBeTrue();
});

it('§10.2 a "kedai runcit, Pasir Mas" search produces leads with score, reason and message', function () {
    fakePlaces([apiPlace('runcit1'), apiPlace('runcit2'), apiPlace('runcit3', ['rating' => 2.0])]);
    fakeClaude();

    Livewire::test(SearchPage::class)
        ->set('product_id', $this->dynopos->id)
        ->set('business_type', 'kedai runcit')
        ->set('areas', 'Pasir Mas, Kelantan')
        ->call('calculate')
        ->call('confirm');

    expect(Search::first()->status)->toBe(SearchStatus::Done);
    Http::assertSent(fn (Request $r) => ($r['textQuery'] ?? null) === 'kedai runcit Pasir Mas, Kelantan');

    $leads = Lead::all();
    expect($leads)->toHaveCount(2);
    $leads->each(fn (Lead $lead) => expect($lead->fit)->toBeInt()
        ->and($lead->reason)->not->toBeEmpty()
        ->and($lead->message)->toContain('STOP'));

    Livewire::test(LeadsPage::class)
        ->assertSee('Kedai runcit1')->assertSee('Skor 80')->assertSee('Kenapa sesuai')->assertSee('Buka WhatsApp');
});

it('§10.3 shops with a websiteUri never appear for murahwebsite.my', function () {
    fakePlaces(
        [apiPlace('ada-web'), apiPlace('tiada-web')],
        ['ada-web' => apiDetails('ada-web', ['websiteUri' => 'https://adaweb.my'])],
    );
    fakeClaude();

    runSearch($this->murah, 'restoran');

    expect(Lead::pluck('place_id')->all())->toBe(['tiada-web']);
    Livewire::test(LeadsPage::class)->assertSee('Kedai tiada-web')->assertDontSee('Kedai ada-web');
    expect(AiUsage::where('lead_id', null)->count())->toBe(0);
});

it('§10.4 a DynoPOS message never contains "demo"', function () {
    fakePlaces([apiPlace('a'), apiPlace('b')]);
    $demo = str_replace('POS boleh', 'Boleh buat demo percuma, POS boleh', goodMessage());
    // Shop a: AI says "demo" once then fixes it. Shop b: AI keeps saying "Demo".
    fakeClaude([], [
        claudeReply(['message' => $demo]),
        claudeReply(['message' => goodMessage()]),
        claudeReply(['message' => ucfirst($demo)]),
    ]);

    runSearch($this->dynopos);

    $sendable = Lead::where('needs_review', false)->whereNotNull('message')->get();
    expect($sendable)->toHaveCount(1);
    $sendable->each(fn (Lead $l) => expect(mb_stripos($l->message, 'demo'))->toBeFalse());

    // The flagged one can never be sent or copied.
    $html = Livewire::test(LeadsPage::class)->html();
    expect(substr_count($html, 'wa.me/'))->toBe(1)
        ->and(collect(explode('wa.me/', $html))->skip(1)->every(fn ($chunk) => ! str_contains(strtolower(rawurldecode(strtok($chunk, '"'))), 'demo')))->toBeTrue();
});

it('§10.5 wa.me button is correct for 01x numbers and absent for landlines', function () {
    cachePlace('mobile', ['nationalPhoneNumber' => '018-792 2844']);
    cachePlace('landline', ['nationalPhoneNumber' => '09-790 1234']);
    $mobile = Lead::factory()->for($this->dynopos)->create(['place_id' => 'mobile']);
    Lead::factory()->for($this->dynopos)->create(['place_id' => 'landline']);

    $html = Livewire::test(LeadsPage::class)->assertSee('Telefon atau singgah')->html();

    expect($html)->toContain('https://wa.me/60187922844?text='.rawurlencode($mobile->message))
        ->and(substr_count($html, 'https://wa.me/'))->toBe(1)
        ->and($html)->not->toContain('wa.me/6097901234');
});

it('§10.6 Tolak/STOP removes the shop for every product and it is never generated again', function () {
    fakePlaces([apiPlace('kedai-x')]);
    fakeClaude();
    runSearch($this->dynopos);
    $lead = Lead::firstOrFail();

    Livewire::test(LeadsPage::class)->call('setStatus', $lead->id, 'tolak');

    Livewire::test(LeadsPage::class)->assertDontSee('Kedai kedai-x');
    $callsBefore = AiUsage::count();

    // A new search for the other product finds the shop again: no lead, no AI.
    runSearch($this->murah, 'kedai runcit');
    expect(Lead::where('product_id', $this->murah->id)->count())->toBe(0)
        ->and(AiUsage::count())->toBe($callsBefore)
        ->and(Search::latest('id')->first()->rejections['kedai-x'])->toBe('Dalam senarai STOP');

    // Regenerating is refused too.
    expect(fn () => app(LeadService::class)->requestRegenerate($lead->refresh()))
        ->toThrow(ContactRuleViolation::class);
    expect(fn () => app(MessageWriter::class)->write($lead, force: true))
        ->toThrow(RuntimeException::class, 'STOP');
});

it('§10.7 a shop contacted for product A does not appear for product B within 30 days', function () {
    fakePlaces([apiPlace('kedai-y')]);
    fakeClaude();
    runSearch($this->dynopos);
    $lead = Lead::firstOrFail();

    Livewire::test(LeadsPage::class)->call('setStatus', $lead->id, 'dihantar');
    expect(ContactLog::count())->toBe(1);

    $this->travel(29)->days();
    runSearch($this->murah, 'kedai runcit');
    expect(Lead::where('product_id', $this->murah->id)->count())->toBe(0);

    $this->travel(2)->days(); // day 31
    runSearch($this->murah, 'kedai runcit');
    expect(Lead::where('product_id', $this->murah->id)->count())->toBe(1);
});

it('§10.8 every AI call is logged in ai_usage and the monthly limit stops AI work', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::sequence()
            ->push(['places' => [apiPlace('a'), apiPlace('b')]])
            ->push(['places' => [apiPlace('c')]]),
        'places.googleapis.com/v1/places/*' => fn (Request $r) => Http::response(apiDetails(basename(parse_url($r->url(), PHP_URL_PATH)))),
    ]);
    fakeClaude();

    runSearch($this->dynopos);
    $calls = Http::recorded(fn ($r) => str_contains($r->url(), 'api.anthropic.com'))->count();
    expect($calls)->toBe(4)->and(AiUsage::count())->toBe($calls);

    app(AiBudget::class)->setLimit(app(AiBudget::class)->spentThisMonth());
    $search = runSearch($this->dynopos, 'kedai runcit', 'Tumpat, Kelantan');

    expect($search->status)->toBe(SearchStatus::BudgetExceeded)
        ->and($search->error)->toBe(BudgetExceeded::MESSAGE)
        ->and(AiUsage::count())->toBe($calls);
});

it('§10.9 Places cache is deleted after PLACES_CACHE_HOURS', function () {
    config(['dynoleads.places.cache_hours' => 24]);
    fakePlaces([apiPlace('a')]);
    fakeClaude();
    runSearch($this->dynopos);
    expect(PlaceCache::count())->toBe(1);

    $this->travel(23)->hours();
    dispatch_sync(new PurgePlaceCacheJob);
    expect(PlaceCache::count())->toBe(1);

    $this->travel(2)->hours();
    dispatch_sync(new PurgePlaceCacheJob);
    expect(PlaceCache::count())->toBe(0)
        ->and(Lead::first()->place_id)->toBe('a'); // place_id stays
});

it('§10.10 tests can never reach a real API', function () {
    expect(fn () => app(PlacesClient::class)->textSearch('kedai runcit Pasir Mas'))
        ->toThrow(RuntimeException::class, 'without a matching fake');
});

it('§10.11 the scheduler has the daily cache purge (Forge cron runs schedule:run)', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('purge-place-cache')->assertSuccessful();
});
