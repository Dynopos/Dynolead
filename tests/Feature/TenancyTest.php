<?php

use App\Enums\LeadStatus;
use App\Jobs\ScoreLeadsJob;
use App\Jobs\SearchPlacesJob;
use App\Livewire\CostsPage;
use App\Livewire\LeadsPage;
use App\Livewire\ProductsPage;
use App\Models\AiUsage;
use App\Models\ContactLog;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Search;
use App\Models\Suppression;
use App\Services\Search\SearchPipeline;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/** Run $callback as another customer. */
function asWorkspace($workspace, Closure $callback): mixed
{
    return app(CurrentWorkspace::class)->runAs($workspace, $callback);
}

it('stamps new rows with the current workspace', function () {
    $product = Product::factory()->create();
    $lead = Lead::factory()->for($product)->create();

    expect($product->workspace_id)->toBe($this->workspace->id)
        ->and($lead->workspace_id)->toBe($this->workspace->id);
});

it('never shows another customer’s products, leads or costs', function () {
    [$other] = otherWorkspace();
    asWorkspace($other, function () {
        $p = Product::factory()->create(['name' => 'Produk Rahsia Orang Lain']);
        cachePlace('kedai-lain');
        Lead::factory()->for($p)->create(['place_id' => 'kedai-lain']);
        AiUsage::create(['model' => 'x', 'purpose' => 'score', 'cost_estimate' => 9.99]);
    });

    expect(Product::count())->toBe(0)->and(Lead::count())->toBe(0);

    Livewire::test(ProductsPage::class)->assertDontSee('Produk Rahsia Orang Lain');
    Livewire::test(LeadsPage::class)->assertDontSee('Kedai kedai-lain');
    actingAsAdmin();
    Livewire::test(CostsPage::class)->assertDontSee('9.99')->assertSee('RM0.00');
});

it('cannot act on another customer’s lead by id', function () {
    [$other] = otherWorkspace();
    $theirs = asWorkspace($other, fn () => Lead::factory()->create());

    expect(fn () => Livewire::test(LeadsPage::class)->call('setStatus', $theirs->id, 'tolak'))
        ->toThrow(ModelNotFoundException::class);
    expect($theirs->refresh()->status)->toBe(LeadStatus::Baru);
});

it('keeps STOP lists and the 30 day rule per customer', function () {
    [$other] = otherWorkspace();
    asWorkspace($other, function () {
        Suppression::create(['place_id' => 'shared-shop', 'reason' => 'STOP']);
        ContactLog::create(['place_id' => 'shared-shop', 'product_id' => Product::factory()->create()->id, 'contacted_at' => now()]);
    });

    // Our customer never heard STOP from this shop and never contacted it.
    $mine = Product::factory()->create(['filters' => ['min_rating' => 0, 'min_reviews' => 0]]);
    fakePlaces([apiPlace('shared-shop')]);
    Queue::fake([ScoreLeadsJob::class]);

    app(SearchPipeline::class)->start($mine, 'kedai runcit', ['Pasir Mas'], 20);

    expect(Lead::pluck('place_id')->all())->toBe(['shared-shop']);
});

it('runs queued pipeline jobs inside the search’s workspace', function () {
    [$other] = otherWorkspace();
    $search = asWorkspace($other, fn () => Search::factory()->for(Product::factory()->create(['filters' => ['min_rating' => 0, 'min_reviews' => 0]]))->create());
    fakePlaces([apiPlace('their-shop')]);
    Queue::fake([ScoreLeadsJob::class]);

    // Simulate a queue worker: no current workspace.
    app(CurrentWorkspace::class)->clear();
    (new SearchPlacesJob($search->id))->handle(app(SearchPipeline::class));

    $lead = Lead::withoutGlobalScope('workspace')->where('place_id', 'their-shop')->firstOrFail();
    expect($lead->workspace_id)->toBe($other->id)
        ->and(app(CurrentWorkspace::class)->id())->toBeNull();
});

it('uses the workspace of the signed-in user for every request', function () {
    [$other, $otherUser] = otherWorkspace();
    asWorkspace($other, fn () => Product::factory()->create(['name' => 'Produk Orang Lain']));
    app(CurrentWorkspace::class)->clear();

    $this->actingAs($this->user)->get('/produk')->assertOk()->assertDontSee('Produk Orang Lain');
    $this->actingAs($otherUser)->get('/produk')->assertOk()->assertSee('Produk Orang Lain');
});
