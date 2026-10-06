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
use App\Models\CreditTransaction;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Search;
use App\Services\Ai\AiGateway;
use App\Services\Billing\CreditService;
use App\Services\Leads\FollowupService;
use App\Services\Leads\LeadService;
use App\Services\Search\SearchPipeline;
use App\Services\Search\SearchService;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    withAiPrices();
    $this->workspace->update(['plan' => 'kredit']);
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    $this->credits = app(CreditService::class);
    $this->product = Product::factory()->create(['filters' => ['min_rating' => 0, 'min_reviews' => 0]]);
    actingAsOwner();
});

function topUp(int $n): void
{
    app(CreditService::class)->grant(test()->workspace, $n, 'admin');
}

it('prices a search by size: 1 credit per 20 candidates', function () {
    expect($this->credits->costFor(5))->toBe(1)
        ->and($this->credits->costFor(20))->toBe(1)
        ->and($this->credits->costFor(21))->toBe(2)
        ->and($this->credits->costFor(40))->toBe(2)
        ->and($this->credits->costFor(60))->toBe(3);
});

it('charges credits when a search starts and logs it in the ledger', function () {
    topUp(5);
    fakePlaces([apiPlace('a'), apiPlace('b')]);
    fakeClaude();

    $search = app(SearchService::class)->start($this->product, 'kedai runcit', ['Pasir Mas'], 40);

    expect($search->status)->toBe(SearchStatus::Done)
        ->and($search->credits_charged)->toBe(2)
        ->and($this->credits->balance())->toBe(3);

    $charge = CreditTransaction::where('reason', 'search')->firstOrFail();
    expect($charge->amount)->toBe(-2)->and($charge->balance_after)->toBe(3)->and($charge->search_id)->toBe($search->id);
});

it('refuses a search without enough credits and calls no API', function () {
    topUp(1);
    Http::fake();

    expect(fn () => app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 40))
        ->toThrow(AccountLimitReached::class, 'perlu 2 kredit, baki anda 1');

    expect(Search::count())->toBe(0)->and($this->credits->balance())->toBe(1);
    Http::assertNothingSent();
});

it('shows the credit cost on the search screen and blocks when short', function () {
    topUp(1);

    Livewire::test(SearchPage::class)
        ->assertSee('Carian ini guna')
        ->assertSee('1 kredit')
        ->set('max_candidates', 60)
        ->assertSee('perlu 3 kredit, baki anda 1')
        ->assertSee('Tambah kredit');
});

it('shows customers credits, not RM, before they confirm', function () {
    topUp(3);

    Livewire::test(SearchPage::class)
        ->set('business_type', 'kedai runcit')->set('areas', 'Pasir Mas')->set('max_candidates', 20)
        ->call('calculate')
        ->assertSee('Sahkan carian')
        ->assertSee('1 kredit')
        ->assertSee('Baki selepas carian')
        ->assertDontSee('Anggaran kos carian ni')
        ->assertDontSee('Kos AI');
});

it('refunds credits when no shop passes the filters', function () {
    topUp(1);
    fakePlaces([apiPlace('low', ['rating' => 1.0])]);
    $this->product->update(['filters' => ['min_rating' => 4, 'min_reviews' => 0]]);

    $search = app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 20);

    expect($search->lead_count)->toBe(0)
        ->and($search->credits_refunded_at)->not->toBeNull()
        ->and($this->credits->balance())->toBe(1)
        ->and(CreditTransaction::where('reason', 'refund')->value('amount'))->toBe(1);
});

it('refunds credits when the search fails, once only', function () {
    topUp(2);
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429)]);
    Queue::fake([SearchPlacesJob::class]);

    $search = app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 20);
    expect($this->credits->balance())->toBe(1);

    $job = new SearchPlacesJob($search->id);
    try {
        $job->handle(app(SearchPipeline::class));
    } catch (Throwable $e) {
        $job->failed($e);
        $job->failed($e);
    }

    expect($search->refresh()->status)->toBe(SearchStatus::Failed)
        ->and($this->credits->balance())->toBe(2)
        ->and(CreditTransaction::where('reason', 'refund')->count())->toBe(1);
});

it('keeps the credits when leads were found, even if none fit', function () {
    topUp(1);
    fakePlaces([apiPlace('a')]);
    fakeClaude([claudeReply(['fit' => 20, 'reason' => 'Tak sesuai.', 'hook' => '', 'gap' => '', 'flag' => null])]);

    app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 20);

    expect(Lead::count())->toBe(1)->and($this->credits->balance())->toBe(0);
});

it('never charges the internal workspace', function () {
    $this->workspace->update(['plan' => 'dalaman']);
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    fakePlaces([apiPlace('a')]);
    fakeClaude();

    $search = app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 60);

    expect($search->credits_charged)->toBe(0)->and(CreditTransaction::count())->toBe(0);
});

it('limits free regenerations and follow-ups per lead', function () {
    config(['credits.max_regenerations_per_lead' => 2, 'credits.max_followups_per_lead' => 1]);
    Queue::fake();
    $lead = Lead::factory()->for($this->product)->create(['status' => 'dihantar']);

    app(LeadService::class)->requestRegenerate($lead);
    app(LeadService::class)->requestRegenerate($lead->refresh());
    expect(fn () => app(LeadService::class)->requestRegenerate($lead->refresh()))->toThrow(AccountLimitReached::class, 'Had jana semula');

    app(FollowupService::class)->requestMessage($lead->refresh());
    expect(fn () => app(FollowupService::class)->requestMessage($lead->refresh()))->toThrow(AccountLimitReached::class, 'Had mesej follow-up');

    expect($this->credits->balance())->toBe(0); // free: no credits used
});

it('tells the customer how many free regenerations are left', function () {
    cachePlace('p1');
    Lead::factory()->for($this->product)->create(['place_id' => 'p1', 'regenerate_count' => 1]);

    Livewire::test(LeadsPage::class)->assertSee('Percuma, baki 2 kali untuk lead ini.')->assertDontSee('Anggaran kos RM');
});

it('blocks a suspended account from searching and AI extras', function () {
    topUp(5);
    $this->workspace->update(['suspended_at' => now()]);
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    $lead = Lead::factory()->for($this->product)->create();

    expect(fn () => app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 20))->toThrow(AccountLimitReached::class, 'digantung');
    expect(fn () => app(LeadService::class)->requestRegenerate($lead))->toThrow(AccountLimitReached::class, 'digantung');
});

it('keeps the ledger equal to the balance', function () {
    topUp(10);
    fakePlaces([apiPlace('a')]);
    fakeClaude();
    app(SearchService::class)->start($this->product, 'kedai', ['Pasir Mas'], 40);
    app(SearchService::class)->start($this->product, 'kedai', ['Tumpat'], 20);

    expect((int) CreditTransaction::sum('amount'))->toBe($this->credits->balance());
});

it('caps products per account', function () {
    config(['credits.max_products' => 1]);

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

    expect(Product::firstOrFail()->name)->toBe('KedaiPOS')
        ->and($this->workspace->refresh()->onboarded_at)->not->toBeNull();
});

it('shows the balance and ledger on the Kredit page', function () {
    topUp(7);

    Livewire::test(CostsPage::class)
        ->assertSee('Baki kredit')
        ->assertSee('7')
        ->assertSee('Pelarasan')
        ->assertSee('Tambah kredit')
        ->assertDontSee('config/ai_prices.php');
});
