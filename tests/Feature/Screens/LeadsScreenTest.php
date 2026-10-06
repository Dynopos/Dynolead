<?php

use App\Enums\LeadStatus;
use App\Exceptions\ContactRuleViolation;
use App\Jobs\RegenerateLeadJob;
use App\Livewire\LeadsPage;
use App\Models\ContactLog;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Suppression;
use App\Services\Leads\LeadService;
use Database\Seeders\ProductSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    withAiPrices();
    $this->seed(ProductSeeder::class);
    $this->dynopos = Product::where('slug', 'dynopos')->first();
    $this->murah = Product::where('slug', 'murahwebsite')->first();
});

function shownLead(Product $product, string $placeId, array $place = [], array $lead = []): Lead
{
    cachePlace($placeId, $place);

    return Lead::factory()->for($product)->create(array_merge(['place_id' => $placeId], $lead));
}

it('shows a lead card with Google data, score, reason, message and attribution', function () {
    shownLead($this->dynopos, 'p1', [], ['fit' => 82, 'reason' => 'Kaunter selalu panjang.', 'flag' => 'Mungkin dah ada sistem, semak dulu']);

    Livewire::test(LeadsPage::class)
        ->assertSee('Kedai p1')
        ->assertSee('4.4')
        ->assertSee('120 review')
        ->assertSee('Skor 82')
        ->assertSee('Kenapa sesuai')
        ->assertSee('Kaunter selalu panjang.')
        ->assertSee('Mungkin dah ada sistem, semak dulu')
        ->assertSee('Salin mesej')
        ->assertSee('Jana semula')
        ->assertSee('Data kedai:')
        ->assertSee('Google Maps');
});

it('builds a correct wa.me button for 01x numbers', function () {
    $lead = shownLead($this->dynopos, 'mobile', ['nationalPhoneNumber' => '011-1149 6842']);

    $html = Livewire::test(LeadsPage::class)->assertSee('Buka WhatsApp')->html();

    expect($html)->toContain('https://wa.me/601111496842?text='.rawurlencode($lead->message));
});

it('shows "Telefon atau singgah" and no WhatsApp button for landlines', function () {
    shownLead($this->dynopos, 'landline', ['nationalPhoneNumber' => '09-790 1234']);

    $html = Livewire::test(LeadsPage::class)
        ->assertSee('Telefon atau singgah')
        ->assertDontSee('Buka WhatsApp')
        ->html();

    expect($html)->not->toContain('wa.me');
});

it('hides WhatsApp and copy for a message flagged Semak manual', function () {
    shownLead($this->dynopos, 'p1', [], [
        'message' => "Salam, jom tengok demo.\nBalas STOP",
        'needs_review' => true,
        'review_note' => 'Semak manual: Ada perkataan dilarang "demo"',
    ]);

    $html = Livewire::test(LeadsPage::class)
        ->assertSee('Semak manual')
        ->assertDontSee('Buka WhatsApp')
        ->assertDontSee('Salin mesej')
        ->html();

    expect($html)->not->toContain('wa.me');
});

it('lets Bob fix a flagged message by hand, with the same checks', function () {
    $lead = shownLead($this->dynopos, 'p1', [], ['message' => 'Jom demo. STOP', 'needs_review' => true]);

    Livewire::test(LeadsPage::class)
        ->call('editMessage', $lead->id)
        ->set("messages.{$lead->id}", 'Masih ada demo. STOP')
        ->call('saveMessage', $lead->id)
        ->assertSee('Belum boleh simpan');

    expect($lead->refresh()->needs_review)->toBeTrue();

    Livewire::test(LeadsPage::class)
        ->call('editMessage', $lead->id)
        ->set("messages.{$lead->id}", goodMessage())
        ->call('saveMessage', $lead->id)
        ->assertSee('Buka WhatsApp');

    expect($lead->refresh()->needs_review)->toBeFalse()->and($lead->message)->toBe(goodMessage());
});

it('Tolak adds the shop to suppressions and hides it for every product', function () {
    $a = shownLead($this->dynopos, 'shop', ['nationalPhoneNumber' => '011-1149 6842']);
    $b = Lead::factory()->for($this->murah)->create(['place_id' => 'shop']);

    Livewire::test(LeadsPage::class)
        ->call('setStatus', $a->id, 'tolak')
        ->assertSee('Ditanda STOP');

    expect(Suppression::where('place_id', 'shop')->where('phone', '601111496842')->exists())->toBeTrue()
        ->and($a->refresh()->status)->toBe(LeadStatus::Tolak);

    Livewire::test(LeadsPage::class)->assertDontSee('Kedai shop');
    Livewire::test(LeadsPage::class)->set('productId', $this->murah->id)->assertDontSee('Kedai shop');

    // ...and cannot be acted on or regenerated any more.
    Queue::fake();
    expect(fn () => Livewire::test(LeadsPage::class)->call('regenerate', $b->id))
        ->toThrow(ModelNotFoundException::class);
    Queue::assertNothingPushed();
});

it('Dah hantar records contacts_log and hides the shop from other products for 30 days', function () {
    $a = shownLead($this->dynopos, 'shop');
    Lead::factory()->for($this->murah)->create(['place_id' => 'shop']);

    Livewire::test(LeadsPage::class)->call('setStatus', $a->id, 'dihantar');

    $log = ContactLog::firstOrFail();
    expect($log->place_id)->toBe('shop')
        ->and($log->product_id)->toBe($this->dynopos->id)
        ->and($a->refresh()->status)->toBe(LeadStatus::Dihantar)
        ->and($a->contacted_at)->not->toBeNull()
        ->and($a->next_followup_at->isSameDay(now()->addDays(3)))->toBeTrue();

    Livewire::test(LeadsPage::class)->set('productId', $this->murah->id)->assertDontSee('Kedai shop');
    Livewire::test(LeadsPage::class)->set('productId', $this->dynopos->id)->assertSee('Kedai shop');

    $this->travel(31)->days();
    cachePlace('shop');
    Livewire::test(LeadsPage::class)->set('productId', $this->murah->id)->assertSee('Kedai shop');
});

it('refuses Dah hantar when the shop was contacted for another product in the last 30 days', function () {
    $lead = Lead::factory()->for($this->murah)->create(['place_id' => 'shop']);
    ContactLog::create(['place_id' => 'shop', 'product_id' => $this->dynopos->id, 'contacted_at' => now()->subDays(5)]);

    expect(fn () => app(LeadService::class)->changeStatus($lead, LeadStatus::Dihantar))
        ->toThrow(ContactRuleViolation::class, '30 hari');

    expect($lead->refresh()->status)->toBe(LeadStatus::Baru)
        ->and(ContactLog::where('place_id', 'shop')->count())->toBe(1);
});

it('saves notes', function () {
    $lead = shownLead($this->dynopos, 'p1');

    Livewire::test(LeadsPage::class)
        ->set("notes.{$lead->id}", 'Owner balik pukul 5')
        ->call('saveNotes', $lead->id)
        ->assertSee('Nota disimpan');

    expect($lead->refresh()->notes)->toBe('Owner balik pukul 5');
});

it('regenerates a message only when asked, through the queue', function () {
    Queue::fake();
    $lead = shownLead($this->dynopos, 'p1');

    Livewire::test(LeadsPage::class)
        ->call('regenerate', $lead->id)
        ->assertSee('Sedang jana semula');

    Queue::assertPushed(RegenerateLeadJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('filters by product, status, type and area and shows the summary', function () {
    shownLead($this->dynopos, 'r1', [], ['business_type' => 'kedai runcit', 'area' => 'Pasir Mas']);
    shownLead($this->dynopos, 'k1', [], ['business_type' => 'kafe', 'area' => 'Kota Bharu']);
    shownLead($this->murah, 'w1', [], ['business_type' => 'kafe', 'area' => 'Pasir Mas', 'status' => LeadStatus::Reply]);
    shownLead($this->dynopos, 'x1', [], ['fit' => 20, 'status' => LeadStatus::TakSesuai]);

    Livewire::test(LeadsPage::class)
        ->assertSee('Kedai r1')->assertSee('Kedai k1')->assertSee('Kedai w1')->assertDontSee('Kedai x1')
        ->set('businessType', 'kafe')->assertDontSee('Kedai r1')->assertSee('Kedai k1')
        ->set('area', 'Pasir Mas')->assertSee('Kedai w1')->assertDontSee('Kedai k1')
        ->call('clearFilters')
        ->set('status', 'reply')->assertSee('Kedai w1')->assertDontSee('Kedai r1')
        ->set('status', 'tak_sesuai')->assertSee('Kedai x1')
        ->call('clearFilters')
        ->set('productId', $this->murah->id)->assertSee('Kedai w1')->assertDontSee('Kedai r1');
});

it('refreshes Google data with the cheap field mask when the cache has expired', function () {
    Http::fake(['places.googleapis.com/v1/places/*' => Http::response(apiDetails('p1'))]);
    Lead::factory()->for($this->dynopos)->create(['place_id' => 'p1']);

    Livewire::test(LeadsPage::class)->assertSee('Kedai p1');

    Http::assertSent(fn ($r) => ! str_contains($r->header('X-Goog-FieldMask')[0] ?? '', 'reviews'));
});

it('works without Google data when Places fails', function () {
    Http::fake(['places.googleapis.com/*' => Http::response([], 500)]);
    Lead::factory()->for($this->dynopos)->create(['place_id' => 'p1']);

    Livewire::test(LeadsPage::class)->assertOk()->assertSee('Nama tak dapat dimuat');
});
