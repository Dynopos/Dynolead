<?php

use App\Enums\SearchStatus;
use App\Exceptions\AccountLimitReached;
use App\Exceptions\BudgetExceeded;
use App\Jobs\SearchPlacesJob;
use App\Livewire\CostsPage;
use App\Livewire\LeadsPage;
use App\Livewire\OnboardingPage;
use App\Livewire\ProductsPage;
use App\Livewire\SearchPage;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\PlacesUsage;
use App\Models\Product;
use App\Models\Search;
use App\Models\WalletTransaction;
use App\Services\Ai\AiGateway;
use App\Services\Billing\WalletService;
use App\Services\Leads\FollowupService;
use App\Services\Leads\LeadService;
use App\Services\Search\SearchPipeline;
use App\Services\Search\SearchService;
use App\Support\Billing\UsageMeter;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    withAiPrices();
    config([
        'ai_prices.places' => ['text_search' => 0.032, 'details' => 0.025, 'details_display' => 0.02],
        'billing.markup_percent' => 20,
    ]);
    $this->workspace->forceFill(['plan' => 'pelanggan', 'trial_ends_at' => now()->addDays(14), 'activated_at' => null, 'balance_sen' => 0])->save();
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    $this->wallet = app(WalletService::class);
    $this->product = Product::factory()->create(['filters' => ['min_rating' => 0, 'min_reviews' => 0]]);
    actingAsOwner();
});

function activate(int $balanceSen = 0): void
{
    test()->workspace->forceFill(['activated_at' => now()])->save();
    if ($balanceSen > 0) {
        app(WalletService::class)->credit(test()->workspace, $balanceSen, 'admin');
    }
    app(CurrentWorkspace::class)->set(test()->workspace->refresh());
}

function places(int $n): array
{
    return collect(range(1, $n))->map(fn ($i) => apiPlace("kedai{$i}"))->all();
}

// --- Trial ------------------------------------------------------------------

it('starts every new account on a free trial of 20 leads or 14 days', function () {
    expect($this->wallet->inTrial())->toBeTrue()
        ->and($this->wallet->trialLeadsRemaining())->toBe(20)
        ->and($this->wallet->isActivated())->toBeFalse();
});

it('runs trial searches for free', function () {
    fakePlaces(places(3));
    fakeClaude();

    $search = app(SearchService::class)->start($this->product, 'kedai runcit', ['Pasir Mas'], 20);

    expect($search->is_trial)->toBeTrue()
        ->and($search->charged_sen)->toBe(0)
        ->and(Lead::count())->toBe(3)
        ->and(WalletTransaction::count())->toBe(0)
        ->and($this->wallet->trialLeadsRemaining())->toBe(17);
});

it('ends the trial at 20 leads, stopping before paying for more details or AI', function () {
    config(['billing.trial_leads' => 2]);
    fakePlaces(places(4));
    fakeClaude();

    $search = app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 20);

    expect(Lead::count())->toBe(2)
        ->and(collect($search->rejections)->filter(fn ($r) => $r === 'Had lead percubaan dicapai'))->toHaveCount(2)
        ->and($this->wallet->inTrial())->toBeFalse()
        ->and($this->wallet->trialEnded())->toBeTrue();
    Http::assertSentCount(1 + 2 + 2 * 2); // text search, 2 details, 2 × (score + write)

    Livewire::test(SearchPage::class)->assertSee('Percubaan percuma dah tamat')->assertSee('RM23.90');
});

it('ends the trial after 14 days even with leads left', function () {
    $this->travel(15)->days();
    app(CurrentWorkspace::class)->set($this->workspace->refresh());

    expect($this->wallet->trialEnded())->toBeTrue();
    expect(fn () => app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 10))
        ->toThrow(AccountLimitReached::class, 'Percubaan percuma dah tamat');
});

it('keeps leads visible after the trial, but no AI extras until activated', function () {
    cachePlace('p1');
    $lead = Lead::factory()->for($this->product)->create(['place_id' => 'p1']);
    $this->travel(15)->days();
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    cachePlace('p1');

    Livewire::test(LeadsPage::class)->assertSee('Kedai p1')->assertSee('Buka WhatsApp');
    expect(fn () => app(LeadService::class)->requestRegenerate($lead))->toThrow(AccountLimitReached::class, 'Aktifkan akaun');
});

// --- Paid usage ---------------------------------------------------------------

it('charges a paid search its real AI + Places cost plus 20%', function () {
    activate(2000);
    fakePlaces(places(2));
    fakeClaude();

    $search = app(SearchService::class)->start($this->product, 'kedai runcit', ['Pasir Mas'], 20);

    $ai = (float) AiUsage::where('billable_search_id', $search->id)->sum('cost_estimate');
    $places = (float) PlacesUsage::where('billable_search_id', $search->id)->sum('cost_estimate');
    $expected = (int) ceil(round(($ai + $places) * 1.2 * 100, 4));

    expect($search->is_trial)->toBeFalse()
        ->and($ai)->toBeGreaterThan(0)
        ->and($places)->toBeGreaterThan(0)
        ->and($search->charged_sen)->toBe($expected)
        ->and($this->wallet->balanceSen())->toBe(2000 - $expected)
        ->and((int) WalletTransaction::where('reason', 'usage')->sum('amount_sen'))->toBe(-$expected)
        ->and((int) WalletTransaction::where('reason', 'usage')->sum('cost_sen'))->toBe((int) round(($ai + $places) * 100));
});

it('prices with the configured markup: RM50 cost becomes RM60', function () {
    expect($this->wallet->priceSen(50.0))->toBe(6000)
        ->and($this->wallet->priceSen(0.0105))->toBe(2); // rounds up to the sen

    config(['billing.markup_percent' => 10]);
    expect($this->wallet->priceSen(50.0))->toBe(5500);
});

it('can bill AI cost only, if Bob turns Places billing off', function () {
    config(['billing.include_places_cost' => false]);
    activate(2000);
    fakePlaces(places(1));
    fakeClaude();

    $search = app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 20);
    $ai = (float) AiUsage::where('billable_search_id', $search->id)->sum('cost_estimate');

    expect($search->charged_sen)->toBe((int) ceil(round($ai * 1.2 * 100, 4)));
});

it('refuses a paid search while Places prices are missing, so Places is never billed at RM0', function () {
    config(['ai_prices.places' => ['text_search' => null, 'details' => null, 'details_display' => null]]);
    activate(2000);
    Http::fake();

    expect(fn () => app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 20))
        ->toThrow(AccountLimitReached::class, 'Perkhidmatan carian belum sedia');
    expect(Search::count())->toBe(0);
    Http::assertNothingSent();

    // A free trial search is not billed, so it is not blocked.
    $this->workspace->forceFill(['activated_at' => null])->save();
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    expect(app(SearchService::class)->blocker(20))->toBeNull();

    // With Places billing turned off, AI prices alone are enough.
    activate();
    config(['billing.include_places_cost' => false]);
    expect(app(SearchService::class)->blocker(20))->toBeNull();
});

it('refuses a paid search when the balance is below the estimate, and calls no API', function () {
    activate(1);
    Http::fake();

    expect(fn () => app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 40))
        ->toThrow(AccountLimitReached::class, 'Baki tak cukup');

    expect(Search::count())->toBe(0);
    Http::assertNothingSent();
});

it('stops a paid search when the balance runs out, charging only what was used', function () {
    // Enough for the estimate (default 50% pass, 60% fit), not for 5 real leads.
    activate();
    $estimate = app(SearchService::class)->chargeEstimateSen(5);
    activate($estimate);
    fakePlaces(places(5));
    fakeClaude();

    $search = app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 5);

    expect($search->status)->toBe(SearchStatus::BudgetExceeded)
        ->and($search->error)->toContain('Baki habis')
        ->and($search->lead_count)->toBeLessThan(5)
        ->and($this->wallet->balanceSen())->toBeLessThanOrEqual(0)
        ->and($search->charged_sen)->toBe($this->wallet->priceSen($this->wallet->billableCostMyr($search)));
});

it('never bills regenerations, follow-ups or lead-card refreshes', function () {
    activate(500);
    cachePlace('p1');
    $lead = Lead::factory()->for($this->product)->create(['place_id' => 'p1', 'status' => 'dihantar', 'contacted_at' => now()->subDays(5), 'next_followup_at' => now()->subDay()]);
    fakeClaude([], [], ['places.googleapis.com/*' => Http::response(apiDetails('p1'))]);

    app(LeadService::class)->requestRegenerate($lead);
    app(FollowupService::class)->requestMessage($lead->refresh());

    expect(AiUsage::count())->toBeGreaterThan(0)
        ->and(AiUsage::whereNotNull('billable_search_id')->count())->toBe(0)
        ->and($this->wallet->balanceSen())->toBe(500);
});

it('limits free regenerations and follow-ups per lead', function () {
    activate(500);
    config(['billing.max_regenerations_per_lead' => 2, 'billing.max_followups_per_lead' => 1]);
    Queue::fake();
    $lead = Lead::factory()->for($this->product)->create(['status' => 'dihantar']);

    app(LeadService::class)->requestRegenerate($lead);
    app(LeadService::class)->requestRegenerate($lead->refresh());
    expect(fn () => app(LeadService::class)->requestRegenerate($lead->refresh()))->toThrow(AccountLimitReached::class, 'Had jana semula');

    app(FollowupService::class)->requestMessage($lead->refresh());
    expect(fn () => app(FollowupService::class)->requestMessage($lead->refresh()))->toThrow(AccountLimitReached::class, 'Had mesej follow-up');
});

it('settles a failed search for what it used, once', function () {
    activate(500);
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429)]);
    Queue::fake([SearchPlacesJob::class]);
    $search = app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 20);

    $job = new SearchPlacesJob($search->id);
    try {
        app(UsageMeter::class)->runFor($search, fn () => $job->handle(app(SearchPipeline::class)));
    } catch (Throwable $e) {
        $job->failed($e);
        $job->failed($e);
    }

    // One Text Search call was made (and failed); it is billed once.
    expect($search->refresh()->status)->toBe(SearchStatus::Failed)
        ->and(WalletTransaction::where('reason', 'usage')->count())->toBe(1)
        ->and($this->wallet->balanceSen())->toBe(500 - $this->wallet->priceSen(0.032 * 4.5));
});

it('keeps the ledger equal to the balance', function () {
    activate(3000);
    fakePlaces(places(2));
    fakeClaude();
    app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 20);
    app(SearchService::class)->start($this->product, 'kedai', ['Tumpat'], 20);

    expect((int) WalletTransaction::sum('amount_sen'))->toBe($this->wallet->balanceSen());
});

it('never charges or limits the internal workspace', function () {
    $this->workspace->update(['plan' => 'dalaman', 'trial_ends_at' => null]);
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    fakePlaces(places(2));
    fakeClaude();

    $search = app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 60);

    expect($search->charged_sen)->toBe(0)->and(WalletTransaction::count())->toBe(0);
});

// --- Screens ------------------------------------------------------------------

it('shows the trial as free on the search screen', function () {
    Livewire::test(SearchPage::class)
        ->assertSee('Percuma semasa percubaan')
        ->assertSee('20 lead')
        ->set('business_type', 'kedai')->set('areas', 'Pasir Mas')
        ->call('calculate')
        ->assertSee('Percuma')
        ->assertDontSee('Kos AI');
});

it('shows paid customers an RM estimate and their balance before they confirm', function () {
    activate(2000);

    Livewire::test(SearchPage::class)
        ->assertSee('Anggaran caj')
        ->assertSee('baki RM20.00')
        ->set('business_type', 'kedai')->set('areas', 'Pasir Mas')
        ->call('calculate')
        ->assertSee('Sahkan carian')
        ->assertSee('≈ RM')
        ->assertSee('Caj sebenar ikut penggunaan')
        ->assertDontSee('Anggaran kos carian ni');
});

it('shows the balance and history on the Baki page, without internal costs', function () {
    activate(1234);

    Livewire::test(CostsPage::class)
        ->assertSee('RM12.34')
        ->assertSee('Pelarasan')
        ->assertSee('Tambah baki')
        ->assertDontSee('cost_sen')
        ->assertDontSee('config/ai_prices.php');
});

it('caps products per account', function () {
    config(['billing.max_products' => 1]);

    Livewire::test(ProductsPage::class)
        ->call('create')
        ->set('name', 'Kedua')->set('company', 'X')->set('pitch_core', 'P')->set('cta', 'C')
        ->call('save')
        ->assertHasErrors('name')
        ->assertSee('Had 1 produk');
});

it('still stops all AI calls at the platform-wide budget', function () {
    config(['dynoleads.ai.monthly_budget_myr' => 10]);
    [$other] = otherWorkspace();
    app(CurrentWorkspace::class)->runAs($other, fn () => AiUsage::create(['model' => 'x', 'purpose' => 'score', 'cost_estimate' => 10]));
    fakeClaude();

    expect(fn () => app(AiGateway::class)->call('score', 'claude-haiku-4-5-20251001', 's', 'u', 100))
        ->toThrow(BudgetExceeded::class, 'had penggunaan platform');
    Http::assertNothingSent();
});

it('walks a new customer through creating the first product', function () {
    $this->workspace->update(['onboarded_at' => null]);
    Product::query()->delete();
    actingAsOwner()->get('/lead')->assertRedirect(route('onboarding'));

    Livewire::test(OnboardingPage::class)
        ->call('pick', 'pos')
        ->set('name', 'KedaiPOS')
        ->call('finish')
        ->assertHasErrors(['pitch_core' => 'not_regex'])
        ->set('pitch_core', 'KedaiPOS ni sistem POS untuk kedai kecil. Rekod jualan dan stok.')
        ->call('finish')
        ->assertHasNoErrors()
        ->assertRedirect(route('search'));

    expect(Product::firstOrFail()->name)->toBe('KedaiPOS');
});
