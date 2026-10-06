<?php

use App\Enums\SearchStatus;
use App\Exceptions\BudgetExceeded;
use App\Livewire\SearchPage;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Search;
use App\Services\Ai\AiBudget;
use App\Services\Costs\CostEstimator;
use App\Services\Search\SearchService;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    withAiPrices();
    $this->seed(ProductSeeder::class);
    $this->dynopos = Product::where('slug', 'dynopos')->first();
});

it('shows the admin a cost estimate before running and only runs after confirming', function () {
    actingAsAdmin();
    fakePlaces([apiPlace('a'), apiPlace('b')]);
    fakeClaude();

    $component = Livewire::test(SearchPage::class)
        ->set('product_id', $this->dynopos->id)
        ->set('business_type', 'kedai runcit')
        ->set('areas', 'Pasir Mas, Kelantan')
        ->set('max_candidates', 20)
        ->call('calculate')
        ->assertSee('Anggaran kos carian ni')
        ->assertSee('Sahkan &amp; cari', false)
        ->assertSee('nilai default');

    expect($component->get('estimate')['candidates'])->toBe(20)
        ->and($component->get('estimate')['passed'])->toEqual(10.0)
        ->and($component->get('estimate')['fit'])->toEqual(6.0)
        ->and($component->get('estimate')['total'])->toBeGreaterThan(0);

    // Nothing has run yet.
    expect(Search::count())->toBe(0);
    Http::assertNothingSent();

    $component->call('confirm');

    $search = Search::firstOrFail();
    expect($search->status)->toBe(SearchStatus::Done)
        ->and($search->estimate_myr)->toBeGreaterThan(0)
        ->and(Lead::count())->toBe(2);

    $component->assertSee('Siap')->assertSee('Tengok lead');
});

it('does not run a search without an estimate', function () {
    Livewire::test(SearchPage::class)
        ->set('business_type', 'kedai runcit')
        ->set('areas', 'Pasir Mas')
        ->call('confirm')
        ->assertHasErrors('estimate');

    expect(Search::count())->toBe(0);
});

it('caps candidates at 60', function () {
    Livewire::test(SearchPage::class)
        ->set('business_type', 'kedai runcit')
        ->set('areas', 'Pasir Mas')
        ->set('max_candidates', 61)
        ->call('calculate')
        ->assertHasErrors(['max_candidates' => 'max']);
});

it('uses product place types as quick picks', function () {
    Livewire::test(SearchPage::class)
        ->set('product_id', $this->dynopos->id)
        ->assertSee('butik pakaian')
        ->call('pickType', 'restoran')
        ->assertSet('business_type', 'restoran');
});

it('blocks searching when the monthly AI limit is reached', function () {
    app(AiBudget::class)->setLimit(1);
    AiUsage::create(['model' => 'x', 'purpose' => 'score', 'cost_estimate' => 1]);

    Livewire::test(SearchPage::class)
        ->assertSee('Had kos AI bulan ini dah dicapai')
        ->set('business_type', 'kedai runcit')
        ->set('areas', 'Pasir Mas')
        ->call('calculate')
        ->call('confirm')
        ->assertHasErrors('estimate');

    expect(Search::count())->toBe(0);
});

it('warns the admin when AI prices are not filled in, and shows customers a plain message', function () {
    config(['ai_prices.models' => []]);

    actingAsOwner();
    Livewire::test(SearchPage::class)->assertSee('Perkhidmatan AI belum sedia')->assertDontSee('ai_prices.php');

    actingAsAdmin();

    Livewire::test(SearchPage::class)->assertSee('belum diisi dalam config/ai_prices.php');
});

it('shows the budget message on a search that hit the limit', function () {
    Search::factory()->for($this->dynopos)->create([
        'status' => SearchStatus::BudgetExceeded,
        'error' => BudgetExceeded::MESSAGE,
    ]);

    Livewire::test(SearchPage::class)->assertSee('Had kos AI dicapai')->assertSee('Naikkan had di halaman Kos');
});

it('uses 30-day rates in the estimate once there is enough data', function () {
    Search::factory()->for($this->dynopos)->create(['found_count' => 40, 'lead_count' => 10, 'status' => SearchStatus::Done]);
    Lead::factory()->for($this->dynopos)->count(8)->create(['fit' => 80]);
    Lead::factory()->for($this->dynopos)->count(2)->create(['fit' => 20]);

    $e = app(CostEstimator::class)->forSearch(20);

    expect($e->passRate)->toBe(0.25)
        ->and($e->fitRate)->toBe(0.8);
});

it('falls back to default rates with too little data', function () {
    Lead::factory()->for($this->dynopos)->count(2)->create(['fit' => 90]);

    $e = app(CostEstimator::class)->forSearch(20);

    expect($e->passRate)->toBe(0.5)->and($e->fitRate)->toBe(0.6)->and($e->usedDefaults)->toBeTrue();
});

it('keeps "Pasir Mas, Kelantan" as one area and splits areas by line', function () {
    expect(SearchService::parseAreas("Pasir Mas, Kelantan\nTumpat, Kelantan; Kota Bharu"))
        ->toBe(['Pasir Mas, Kelantan', 'Tumpat, Kelantan', 'Kota Bharu']);
});
