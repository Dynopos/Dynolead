<?php

use App\Enums\SearchStatus;
use App\Jobs\FilterCandidatesJob;
use App\Jobs\SearchPlacesJob;
use App\Livewire\SearchPage;
use App\Models\Product;
use App\Models\Search;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use App\Services\Search\SearchPipeline;
use App\Services\Search\SearchService;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    withAiPrices();
    $this->product = Product::factory()->create(['filters' => ['min_rating' => 0, 'min_reviews' => 0]]);
    actingAsOwner();
});

it('lets the user cancel an unfinished search, and the queued job then skips it', function () {
    Http::fake();
    $search = Search::factory()->for($this->product)->create();

    Livewire::test(SearchPage::class)
        ->assertSee('Dalam giliran')
        ->assertSeeHtml('wire:click="cancel('.$search->id.')"')
        ->call('cancel', $search->id)
        ->assertSee('Dibatalkan oleh pengguna')
        ->assertDontSeeHtml('wire:click="cancel('.$search->id.')"');

    $search->refresh();
    expect($search->status)->toBe(SearchStatus::Cancelled)
        ->and($search->finished_at)->not->toBeNull();

    // The job still sitting in the queue does nothing: no Places call.
    (new SearchPlacesJob($search->id))->handle(app(SearchPipeline::class));
    expect($search->refresh()->status)->toBe(SearchStatus::Cancelled);
    Http::assertNothingSent();
});

it('shows no cancel button on finished searches and leaves them unchanged', function () {
    $search = Search::factory()->for($this->product)->create(['status' => SearchStatus::Done]);

    Livewire::test(SearchPage::class)
        ->assertDontSeeHtml('wire:click="cancel('.$search->id.')"')
        ->call('cancel', $search->id);

    expect($search->refresh()->status)->toBe(SearchStatus::Done)
        ->and(app(SearchPipeline::class)->cancel($search))->toBeFalse();
});

it('cannot cancel another customer’s search', function () {
    [$other] = otherWorkspace();
    $theirs = app(CurrentWorkspace::class)->runAs($other, fn () => Search::factory()->for(Product::factory()->create())->create());

    Livewire::test(SearchPage::class)->call('cancel', $theirs->id);

    expect(Search::withoutGlobalScope('workspace')->find($theirs->id)->status)->toBe(SearchStatus::Pending);
});

it('stops a running search before its next AI call once cancelled', function () {
    fakePlaces([apiPlace('a'), apiPlace('b'), apiPlace('c')]);
    $calls = 0;
    $scored = claudeReply(['fit' => 80, 'reason' => 'Kedai sibuk.', 'hook' => 'Layanan mesra.', 'gap' => 'Kaunter lambat.', 'flag' => null]);
    Http::fake([
        'api.anthropic.com/v1/messages' => function () use (&$calls, $scored) {
            $calls++;
            // The user presses "Batalkan" while the first lead is being scored.
            Search::query()->update(['status' => SearchStatus::Cancelled->value]);

            return Http::response($scored);
        },
    ]);

    $search = app(SearchPipeline::class)->start($this->product, 'kedai runcit', ['Pasir Mas'], 20)->refresh();

    expect($calls)->toBe(1)
        ->and($search->status)->toBe(SearchStatus::Cancelled)
        ->and($search->scored_count)->toBe(1)
        ->and($search->written_count)->toBe(0);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'anthropic') && $r['model'] === config('dynoleads.ai.model_write'));
});

it('keeps a search cancelled when its running job fails afterwards', function () {
    $search = Search::factory()->for($this->product)->create(['status' => SearchStatus::Searching]);
    app(SearchPipeline::class)->cancel($search);

    (new SearchPlacesJob($search->id))->failed(new RuntimeException('Google Places Text Search gagal (403)'));

    expect($search->refresh()->status)->toBe(SearchStatus::Cancelled)
        ->and($search->error)->toBe('Dibatalkan oleh pengguna');
});

it('charges a paid search only for what it used before it was cancelled', function () {
    config(['ai_prices.places' => ['text_search' => 0.032, 'details' => 0.025, 'details_display' => 0.02]]);
    $this->workspace->forceFill(['plan' => 'pelanggan', 'trial_ends_at' => now()->subDay(), 'activated_at' => now(), 'balance_sen' => 0])->save();
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    $wallet = app(WalletService::class);
    $wallet->credit($this->workspace, 1000, 'admin');

    fakePlaces([apiPlace('a')]);
    Queue::fake([FilterCandidatesJob::class]);
    $search = app(SearchService::class)->start($this->product, 'kedai runcit', ['Pasir Mas'], 20);

    expect(app(SearchPipeline::class)->cancel($search))->toBeTrue();

    $expected = $wallet->priceSen(0.032 * (float) config('ai_prices.usd_to_myr'));
    expect($search->refresh()->status)->toBe(SearchStatus::Cancelled)
        ->and($search->charged_sen)->toBe($expected)
        ->and($wallet->balanceSen())->toBe(1000 - $expected)
        ->and(WalletTransaction::where('reason', 'usage')->count())->toBe(1);
});
