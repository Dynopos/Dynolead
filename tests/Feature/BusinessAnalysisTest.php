<?php

use App\Enums\LeadStatus;
use App\Livewire\OnboardingPage;
use App\Livewire\ProductsPage;
use App\Livewire\SearchPage;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\PlaceCache;
use App\Models\Product;
use App\Services\Ai\AiBudget;
use App\Services\Ai\BusinessAnalyst;
use App\Services\Ai\LeadScorer;
use App\Services\Places\PlaceData;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    withAiPrices();
    $this->product = Product::factory()->create(['name' => 'Website Premium', 'pitch_core' => 'Website premium RM200 termasuk domain.', 'fit_signals' => null, 'default_place_types' => []]);
});

it('analyses the business with the write model and saves targets, signals and red flags', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(analysisReply())]);

    app(BusinessAnalyst::class)->analyse($this->product);
    $p = $this->product->refresh();

    expect($p->analysis['summary'])->toContain('Website premium')
        ->and(array_column($p->analysis['targets'], 'type'))->toBe(['kontraktor', 'klinik gigi', 'bengkel kereta'])
        ->and($p->default_place_types)->toBe(['kontraktor', 'klinik gigi', 'bengkel kereta'])
        ->and($p->fit_signals)->toBe('Review banyak tapi tiada website; Gambar kerja dalam review')
        ->and($p->analysis['version'])->toBe('analyse-v1')
        ->and($p->analysis_count)->toBe(1)
        ->and(AiUsage::where('purpose', 'analyse')->count())->toBe(1);

    Http::assertSent(fn (Request $r) => $r['model'] === config('dynoleads.ai.model_write')
        && str_contains(json_encode($r['messages'], JSON_UNESCAPED_UNICODE), 'Website premium RM200'));
});

it('keeps signals the owner wrote', function () {
    $this->product->update(['fit_signals' => 'Kedai baru buka']);
    Http::fake(['api.anthropic.com/*' => Http::response(analysisReply())]);

    app(BusinessAnalyst::class)->analyse($this->product);

    expect($this->product->refresh()->fit_signals)->toBe('Kedai baru buka');
});

it('limits free analyses per product for customers, and checks the AI budget first', function () {
    $this->workspace->forceFill(['plan' => 'pelanggan'])->save();
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    config(['billing.max_analyses_per_product' => 1]);
    Http::fake(['api.anthropic.com/*' => Http::response(analysisReply())]);

    Livewire::test(ProductsPage::class)->call('analyse', $this->product->id)->assertSee('kontraktor');
    Livewire::test(ProductsPage::class)->call('analyse', $this->product->id)->assertSee('Had analisis untuk produk ini');
    Http::assertSentCount(1);

    $this->product->forceFill(['analysis_count' => 0])->save();
    app(AiBudget::class)->setLimit(1.00);
    AiUsage::create(['model' => 'x', 'purpose' => 'score', 'cost_estimate' => 1.00]);
    Livewire::test(ProductsPage::class)->call('analyse', $this->product->id)->assertSee('Had kos AI bulan ini dah dicapai');
    Http::assertSentCount(1);
});

it('shows the analysis on the product card with a Cari button for each target', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(analysisReply())]);
    app(BusinessAnalyst::class)->analyse($this->product);

    Livewire::test(ProductsPage::class)
        ->assertSee('Pelanggan terbaik untuk anda')
        ->assertSee('Pelanggan cari portfolio sebelum upah kontraktor.')
        ->assertSee('Tak berbaloi dihubungi')
        ->assertSeeHtml(e(route('search', ['produk' => $this->product->id, 'jenis' => 'klinik gigi'])));
});

it('opens Cari with the chosen target and explains why it is a good target', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(analysisReply())]);
    app(BusinessAnalyst::class)->analyse($this->product);
    actingAsOwner();

    $this->get(route('search', ['produk' => $this->product->id, 'jenis' => 'klinik gigi']))
        ->assertOk()
        ->assertSee('Kenapa sasaran ini:')
        ->assertSee('Pesakit baru cari waktu buka dan lokasi.');
});

it('asks for an analysis on Cari when the product has none', function () {
    Livewire::test(SearchPage::class)->set('product_id', $this->product->id)->assertSee('Belum pasti nak cari siapa?');
});

it('analyses the first product right after onboarding, and never blocks onboarding', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'down']], 500)]);
    $this->workspace->forceFill(['onboarded_at' => null])->save();

    Livewire::test(OnboardingPage::class)
        ->call('pick', array_key_first(config('product_templates')))
        ->set('name', 'Produk Baru')
        ->set('pitch_core', 'Sistem POS untuk kedai runcit, RM99 sebulan.')
        ->call('finish')
        ->assertRedirect(route('search'));

    expect(Product::where('name', 'Produk Baru')->first()->analysis)->toBeNull();
    Http::assertSentCount(1);
});

it('cannot analyse another customer’s product', function () {
    [$other] = otherWorkspace();
    $theirs = app(CurrentWorkspace::class)->runAs($other, fn () => Product::factory()->create());

    expect(fn () => Livewire::test(ProductsPage::class)->call('analyse', $theirs->id))->toThrow(Exception::class);
    Http::assertNothingSent();
});

it('gives the scorer the target customers and red flags from the analysis', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(analysisReply())
        ->push(claudeReply(['fit' => 75, 'reason' => 'Kontraktor aktif.', 'hook' => '', 'gap' => '', 'flag' => null]))]);
    app(BusinessAnalyst::class)->analyse($this->product);

    $lead = Lead::factory()->for($this->product->refresh())->create(['fit' => null, 'status' => LeadStatus::Baru, 'business_type' => 'kontraktor']);
    PlaceCache::create(['place_id' => $lead->place_id, 'payload' => PlaceData::fromApi(apiDetails($lead->place_id), true, true), 'has_details' => true, 'fetched_at' => now()]);
    app(LeadScorer::class)->score($lead);

    Http::assertSent(function (Request $r) {
        $system = json_encode($r['system'] ?? '', JSON_UNESCAPED_UNICODE);

        return str_contains($system, 'kontraktor (Pelanggan cari portfolio sebelum upah kontraktor.)')
            && str_contains($system, 'Francais besar; Kedai tutup');
    });
    expect($lead->refresh()->fit)->toBe(75);
});
