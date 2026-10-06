<?php

use App\Exceptions\BudgetExceeded;
use App\Exceptions\PlanLimitReached;
use App\Livewire\Auth\Register;
use App\Livewire\BillingPage;
use App\Livewire\LeadsPage;
use App\Livewire\OnboardingPage;
use App\Livewire\ProductsPage;
use App\Livewire\SearchPage;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Search;
use App\Services\Ai\AiBudget;
use App\Services\Ai\AiGateway;
use App\Services\Billing\PlanService;
use App\Services\Leads\LeadService;
use App\Services\Search\SearchPipeline;
use App\Services\Search\SearchService;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    withAiPrices();
    $this->workspace->update(['plan' => 'percubaan', 'trial_ends_at' => now()->addDays(10)]);
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    $this->plans = app(PlanService::class);
});

it('gives a trial workspace 20 leads, 1 product and 20 candidates per search', function () {
    expect($this->plans->planOf()->key)->toBe('percubaan')
        ->and($this->plans->isActive())->toBeTrue()
        ->and($this->plans->leadsRemaining())->toBe(20)
        ->and($this->plans->maxCandidates())->toBe(20)
        ->and($this->plans->canAddProduct())->toBeTrue();
});

it('blocks searching and AI once the trial has ended, but keeps leads visible', function () {
    $product = Product::factory()->create();
    cachePlace('p1');
    $lead = Lead::factory()->for($product)->create(['place_id' => 'p1']);

    $this->travel(11)->days();
    app(CurrentWorkspace::class)->set($this->workspace->refresh());

    expect($this->plans->isActive())->toBeFalse();

    Livewire::test(SearchPage::class)->assertSee('Tempoh percubaan dah tamat')->assertSee('Lihat pelan');
    expect(fn () => app(LeadService::class)->requestRegenerate($lead))->toThrow(PlanLimitReached::class);
    expect(fn () => app(SearchService::class)->start($product, 'kedai', ['Pasir Mas'], 10))->toThrow(PlanLimitReached::class);

    cachePlace('p1'); // the 24h Google cache expired while we travelled
    Livewire::test(LeadsPage::class)->assertSee('Kedai p1')->assertSee('Buka WhatsApp');
});

it('stops creating leads when the monthly quota runs out, before paying for details or AI', function () {
    config(['plans.plans.percubaan.monthly_leads' => 2]);
    $product = Product::factory()->create(['filters' => ['min_rating' => 0, 'min_reviews' => 0]]);
    fakePlaces([apiPlace('a'), apiPlace('b'), apiPlace('c'), apiPlace('d')]);
    fakeClaude();

    app(SearchPipeline::class)->start($product, 'kedai runcit', ['Pasir Mas'], 20);

    $search = Search::first();
    expect(Lead::count())->toBe(2)
        ->and(collect($search->rejections)->filter(fn ($r) => $r === 'Kuota lead bulan ini habis'))->toHaveCount(2)
        ->and($this->plans->leadsRemaining())->toBe(0);
    Http::assertSentCount(1 + 2 + 2 * 2); // 1 text search, 2 details, 2 × (score + write)

    Livewire::test(SearchPage::class)->assertSee('Kuota lead bulan ini dah habis');
});

it('resets the lead quota each month', function () {
    config(['plans.plans.percubaan.monthly_leads' => 1]);
    $this->workspace->update(['trial_ends_at' => now()->addMonths(2)]);
    Lead::factory()->create();
    expect($this->plans->leadsRemaining())->toBe(0);

    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addDay());
    expect($this->plans->leadsRemaining())->toBe(1);
});

it('limits the number of products to the plan', function () {
    Product::factory()->create();

    Livewire::test(ProductsPage::class)
        ->call('create')
        ->set('name', 'Kedua')->set('company', 'X')->set('pitch_core', 'P')->set('cta', 'C')
        ->call('save')
        ->assertHasErrors('name')
        ->assertSee('1 produk sahaja');

    expect(Product::count())->toBe(1);
});

it('caps candidates per search to the plan', function () {
    Livewire::test(SearchPage::class)
        ->set('business_type', 'kedai')->set('areas', 'Pasir Mas')
        ->set('max_candidates', 21)
        ->call('calculate')
        ->assertHasErrors(['max_candidates' => 'max']);
});

it('caps each customer’s AI spend at the plan budget', function () {
    expect(app(AiBudget::class)->limit())->toBe(5.0);

    app(AiBudget::class)->setLimit(500);
    expect(app(AiBudget::class)->limit())->toBe(5.0);

    AiUsage::create(['model' => 'x', 'purpose' => 'score', 'cost_estimate' => 5.0]);
    fakeClaude();
    expect(fn () => app(AiGateway::class)->call('score', 'claude-haiku-4-5-20251001', 's', 'u', 100))->toThrow(BudgetExceeded::class);
    Http::assertNothingSent();
});

it('stops all AI calls when the platform-wide budget is reached', function () {
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
    actingAsOwner()->get('/lead')->assertRedirect(route('onboarding'));

    Livewire::test(OnboardingPage::class)
        ->call('pick', 'pos')
        ->assertSet('step', 2)
        ->set('name', 'KedaiPOS')
        ->call('finish')
        ->assertHasErrors(['pitch_core' => 'not_regex'])
        ->set('pitch_core', 'KedaiPOS ni sistem POS untuk kedai kecil. Rekod jualan dan stok.')
        ->call('finish')
        ->assertHasNoErrors()
        ->assertRedirect(route('search'));

    $product = Product::firstOrFail();
    expect($product->name)->toBe('KedaiPOS')
        ->and($product->sender_name)->toBe($this->workspace->sender_name)
        ->and($product->company)->toBe($this->workspace->name)
        ->and($product->default_place_types)->toContain('kedai runcit')
        ->and($this->workspace->refresh()->onboarded_at)->not->toBeNull();

    actingAsOwner()->get('/lead')->assertOk();
});

it('sends new sign-ups to onboarding', function () {
    app(CurrentWorkspace::class)->clear();

    Livewire::test(Register::class)
        ->set('name', 'Siti')->set('business', 'Siti Web')->set('email', 'siti@contoh.my')
        ->set('password', 'rahsia123')->set('agree', true)
        ->call('register')
        ->assertRedirect(route('onboarding'));
});

it('shows plans that are for sale with their status', function () {
    config(['plans.plans.asas.price_myr' => 99]);
    actingAsOwner();

    Livewire::test(BillingPage::class)
        ->assertSee('Percubaan')
        ->assertSee('Asas')->assertSee('RM99')
        ->assertSee('Harga belum ditetapkan') // Pro has no price yet
        ->assertSee('Langgan Asas')
        ->assertSee('Belum dibuka');
});
