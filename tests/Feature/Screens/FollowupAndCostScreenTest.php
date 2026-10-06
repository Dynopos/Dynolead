<?php

use App\Enums\LeadStatus;
use App\Livewire\CostsPage;
use App\Livewire\FollowupsPage;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\PlacesUsage;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Ai\AiBudget;
use Database\Seeders\ProductSeeder;
use Livewire\Livewire;

beforeEach(function () {
    withAiPrices();
    $this->seed(ProductSeeder::class);
    $this->dynopos = Product::where('slug', 'dynopos')->first();
});

function sentLead(Product $product, int $daysAgo): Lead
{
    cachePlace('sent'.$daysAgo, ['nationalPhoneNumber' => '012-345 6789']);

    return Lead::factory()->for($product)->create([
        'place_id' => 'sent'.$daysAgo,
        'status' => LeadStatus::Dihantar,
        'contacted_at' => now()->subDays($daysAgo),
        'next_followup_at' => now()->subDays($daysAgo)->addDays(3),
    ]);
}

it('lists leads sent more than 3 days ago with no change', function () {
    sentLead($this->dynopos, 4);
    sentLead($this->dynopos, 2);

    Livewire::test(FollowupsPage::class)->assertSee('Kedai sent4')->assertDontSee('Kedai sent2');
});

it('writes a short follow-up with the cheap model and builds a wa.me link', function () {
    $lead = sentLead($this->dynopos, 5);
    $followup = "Salam lagi Kedai sent5 👋 Nak tanya kalau sempat baca mesej saya hari tu.\nKalau nak info lanjut, balas je mesej ni atau tengok dynopos.my\nKalau tak berminat, balas STOP, saya tak ganggu lagi 🙏";
    fakeClaude([claudeReply(['message' => $followup])]);

    $html = Livewire::test(FollowupsPage::class)
        ->call('generate', $lead->id)
        ->assertSee('Mesej follow-up')
        ->assertSee('Buka WhatsApp')
        ->html();

    expect($html)->toContain('https://wa.me/60123456789?text=')
        ->and(AiUsage::where('purpose', 'followup')->first()->model)->toBe('claude-haiku-4-5-20251001');

    Livewire::test(FollowupsPage::class)->call('markDone', $lead->id)->assertDontSee('Kedai sent5');
    expect($lead->refresh()->next_followup_at->isFuture())->toBeTrue();
});

it('does not offer WhatsApp for a follow-up containing a banned word', function () {
    $lead = sentLead($this->dynopos, 5);
    fakeClaude([claudeReply(['message' => 'Jom tengok demo. Balas STOP kalau tak berminat.'])]);

    $html = Livewire::test(FollowupsPage::class)
        ->call('generate', $lead->id)
        ->assertSee('perlu semak manual')
        ->html();

    expect($html)->not->toContain('wa.me');
});

it('shows this month cost, Places calls and the last AI calls to the admin', function () {
    actingAsAdmin();
    AiUsage::create(['model' => 'claude-haiku-4-5-20251001', 'purpose' => 'score', 'input_tokens' => 1200, 'output_tokens' => 300, 'cost_estimate' => 1.25]);
    AiUsage::create(['model' => 'claude-sonnet-5-5', 'purpose' => 'write', 'input_tokens' => 1500, 'output_tokens' => 700, 'cost_estimate' => 2.50]);
    PlacesUsage::create(['sku' => 'text_search', 'cost_estimate' => 0.15]);
    PlacesUsage::create(['sku' => 'details', 'cost_estimate' => 0.10]);

    Livewire::test(CostsPage::class)
        ->assertSee('RM3.75')
        ->assertSee('RM100.00')
        ->assertSee('2,700')
        ->assertSee('Panggilan Places')
        ->assertSee('50 panggilan AI terakhir')
        ->assertSee('claude-sonnet-5-5');
});

it('lets the admin change the monthly limit', function () {
    actingAsAdmin();
    Livewire::test(CostsPage::class)
        ->set('limit', '250')
        ->call('saveLimit')
        ->assertSee('Had bulanan dikemas kini');

    expect(app(AiBudget::class)->limit())->toBe(250.0);
});

it('warns the admin when prices are missing', function () {
    actingAsAdmin();
    config(['ai_prices.models' => []]);

    Livewire::test(CostsPage::class)->assertSee('Harga belum diisi');
});

it('shows customers their quota, not internal RM costs', function () {
    $this->workspace->update(['plan' => 'asas']);
    actingAsOwner();
    AiUsage::create(['model' => 'claude-sonnet-5-5', 'purpose' => 'write', 'cost_estimate' => 2.50]);
    Lead::factory()->count(3)->create();

    Livewire::test(CostsPage::class)
        ->assertSee('Kuota')
        ->assertSee('3')
        ->assertSee('/ 150')
        ->assertDontSee('RM2.50')
        ->assertDontSee('claude-sonnet-5-5')
        ->assertDontSee('config/ai_prices.php');

    Livewire::test(CostsPage::class)->set('limit', '999')->call('saveLimit')->assertForbidden();
    expect(Setting::get(AiBudget::SETTING_KEY))->toBeNull();
});
